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
     * Placeholder for rendering the Sync Logs page.
     */
    // public function render_logs_page() {
    //     ?>
    //     <div class="wrap">
    //         <h1><?php // esc_html_e( 'Sync Logs', 'forbes-data-sync-hub' ); ?></h1>
    //         <p><?php // esc_html_e( 'Log display will be implemented here.', 'forbes-data-sync-hub' ); ?></p>
    //     </div>
    //     <?php
    // }

    /**
     * Gets the main menu slug.
     * @return string
     */
    public function get_main_menu_slug(){
        return $this->main_menu_slug;
    }
}
?>
