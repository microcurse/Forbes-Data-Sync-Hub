<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Admin_UI Class
 *
 * Base class for creating admin pages and menus.
 */
class FDSH_Admin_UI {

    private static $instance = null;
    private $main_menu_slug = 'forbes-data-sync-hub';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Gets the single instance of this class.
     *
     * @return FDSH_Admin_UI
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers the main admin menu and submenus.
     */
    public function register_admin_menus() {
        // Main Menu Page
        add_menu_page(
            __( 'Forbes Data Sync Hub', 'forbes-data-sync-hub' ), // Page title
            __( 'Forbes Data Sync', 'forbes-data-sync-hub' ),    // Menu title
            'manage_options',                                   // Capability
            $this->main_menu_slug,                              // Menu slug
            [ $this, 'render_general_settings_page' ],          // Function to display the page
            'dashicons-database-sync',                          // Icon URL (using a Dashicon)
            75                                                  // Position
        );

        // General Settings Submenu Page (will be the same as the main page for now)
        add_submenu_page(
            $this->main_menu_slug,                              // Parent slug
            __( 'General Settings', 'forbes-data-sync-hub' ),   // Page title
            __( 'General Settings', 'forbes-data-sync-hub' ),   // Menu title
            'manage_options',                                   // Capability
            $this->main_menu_slug,                              // Menu slug (same as parent to make it the default)
            [ $this, 'render_general_settings_page' ]           // Function
        );

        // Product Sync Submenu
        add_submenu_page(
            $this->main_menu_slug,
            __( 'Product Sync', 'forbes-data-sync-hub' ),
            __( 'Product Sync', 'forbes-data-sync-hub' ),
            'manage_options',
            $this->main_menu_slug . '-product-sync',
            [ $this, 'render_product_sync_page' ]
        );

        // Placeholder for future "Sync Logs" submenu
        // add_submenu_page(
        //     $this->main_menu_slug,
        //     __( 'Sync Logs', 'forbes-data-sync-hub' ),
        //     __( 'Sync Logs', 'forbes-data-sync-hub' ),
        //     'manage_options',
        //     $this->main_menu_slug . '-logs',
        //     [ $this, 'render_logs_page' ] // This function would need to be created
        // );
    }

    /**
     * Renders the General Settings page.
     */
    public function render_general_settings_page() {
        $settings_manager = FDSH_Settings_Manager::get_instance();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Forbes Data Sync Hub - General Settings', 'forbes-data-sync-hub' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( $settings_manager->get_settings_group() );
                // This prints out all settings sections added to a particular settings page
                do_settings_sections( $settings_manager->get_settings_group() );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the Product Sync page.
     */
    public function render_product_sync_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Product & Attribute Synchronization', 'forbes-data-sync-hub' ); ?></h1>
            <p><?php esc_html_e( 'Use the controls on this page to sync data from the provider site.', 'forbes-data-sync-hub' ); ?></p>
            
            <div id="fdsh-attribute-sync-wrapper" class="card">
                <h2><?php esc_html_e( 'Manual Attribute Sync', 'forbes-data-sync-hub' ); ?></h2>
                <p><?php esc_html_e( 'Select a specific attribute from the provider to sync its definition and all of its terms to this site.', 'forbes-data-sync-hub' ); ?></p>
                
                <div style="margin-bottom: 15px;">
                    <button type="button" id="fdsh_fetch_attributes_button" class="button">
                        <?php esc_html_e( 'Fetch Attributes from Provider', 'forbes-data-sync-hub' ); ?>
                    </button>
                     <?php wp_nonce_field( 'fdsh_get_attributes_nonce', 'fdsh_get_attributes_nonce_field', false ); ?>
                </div>

                <div id="fdsh-attribute-selector-container" style="display: none;">
                    <label for="fdsh_attribute_to_sync" style="margin-right: 10px;"><?php esc_html_e( 'Attribute to Sync:', 'forbes-data-sync-hub' ); ?></label>
                    <select id="fdsh_attribute_to_sync" name="fdsh_attribute_to_sync"></select>
                    
                    <button type="button" id="fdsh_sync_selected_attribute_button" class="button button-primary" style="margin-left: 10px;">
                        <?php esc_html_e( 'Sync Selected Attribute', 'forbes-data-sync-hub' ); ?>
                    </button>
                    <?php wp_nonce_field( 'fdsh_sync_attributes_nonce', 'fdsh_sync_attributes_nonce_field', false ); ?>
                </div>
                
                <div id="fdsh_sync_status_container" style="margin-top: 15px;">
                    <span id="fdsh_sync_attributes_status"></span>
                </div>
            </div>

            <!-- Placeholder for the "Sync All" functionality, which could be re-added later -->
            <!-- 
            <div class="card">
                <h2><?php esc_html_e( 'Full Synchronization', 'forbes-data-sync-hub' ); ?></h2>
                <p><?php esc_html_e( 'This will sync ALL attributes and terms. This can be a long process. It is recommended to use the manual sync above for specific updates.', 'forbes-data-sync-hub' ); ?></p>
                <button type="button" id="fdsh_sync_all_attributes_button" class="button">
                    <?php esc_html_e( 'Sync All Attributes & Terms', 'forbes-data-sync-hub' ); ?>
                </button>
            </div>
            -->
        </div>
        <?php
    }

    /**
     * Enqueues scripts and styles for the admin pages.
     *
     * @param string $hook_suffix The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        // Check if we are on our plugin's main settings page or product sync page.
        $allowed_hooks = [
            'toplevel_page_' . $this->main_menu_slug,
            'forbes-data-sync_page_' . $this->main_menu_slug . '-product-sync',
        ];

        if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
            return;
        }

        // Enqueue the admin settings JavaScript file
        wp_enqueue_script(
            'fdsh-admin-settings',
            FDSH_PLUGIN_URL . 'assets/js/fdsh-admin-settings.js',
            [ 'jquery' ], // Dependencies
            FDSH_VERSION, // Version
            true // Load in footer
        );

        // Localize script with data for AJAX requests
        wp_localize_script(
            'fdsh-admin-settings',
            'fdsh_admin_vars',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'test_connection_nonce_name' => 'fdsh_test_connection_nonce_field',
                'get_attributes_nonce_name' => 'fdsh_get_attributes_nonce_field',
                'sync_attributes_nonce_name' => 'fdsh_sync_attributes_nonce_field'
            ]
        );
    }

    /**
     * Gets the main menu slug.
     * @return string
     */
    public function get_main_menu_slug(){
        return $this->main_menu_slug;
    }
}
?>
