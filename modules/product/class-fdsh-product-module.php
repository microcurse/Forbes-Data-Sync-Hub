<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Product_Module Class
 *
 * Orchestrates product synchronization functionalities.
 * Loads appropriate components based on the plugin's role (Provider/Client).
 */
class FDSH_Product_Module {

    private static $instance = null;
    private $settings_manager;
    private $logger;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->settings_manager = FDSH_Settings_Manager::get_instance();
        $this->logger = FDSH_Logger::get_instance();

        $this->load_dependencies();
        $this->init_hooks();

        $this->logger->info( 'Product Module initialized. Mode: ' . ( $this->settings_manager->is_provider_mode() ? 'Provider' : 'Client' ) );
    }

    /**
     * Gets the single instance of this class.
     *
     * @return FDSH_Product_Module
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load module-specific dependencies.
     */
    private function load_dependencies() {
        // Example: Load shared product functions
        // require_once FDSH_PLUGIN_DIR . 'modules/product/includes/product-functions.php';

        if ( $this->settings_manager->is_provider_mode() ) {
            $api_provider_file = FDSH_PLUGIN_DIR . 'modules/product/api/class-fdsh-product-api-provider.php';
            if ( file_exists( $api_provider_file ) ) {
                require_once $api_provider_file;
                FDSH_Product_API_Provider::get_instance();
                $this->logger->debug( 'FDSH_Product_API_Provider class loaded and instance created.' );
            } else {
                $this->logger->error( 'FDSH_Product_API_Provider class file not found at: ' . $api_provider_file );
            }
        } elseif ( $this->settings_manager->is_client_mode() ) {
            // Example: Load Client sync logic
            // require_once FDSH_PLUGIN_DIR . 'modules/product/client/class-fdsh-product-client.php';
            // FDSH_Product_Client::get_instance();
            $this->logger->debug( 'Loading Product Module - Client specific dependencies (placeholder).' );
        }

        // Load admin components if in admin area
        if ( is_admin() ) {
            // Example: Load Product Admin UI
            // require_once FDSH_PLUGIN_DIR . 'modules/product/admin/class-fdsh-product-admin.php';
            // FDSH_Product_Admin::get_instance();
             $this->logger->debug( 'Loading Product Module - Admin specific components (placeholder).' );
        }
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        if ( $this->settings_manager->is_provider_mode() ) {
            // For Products (CPT product): WordPress already updates post_modified_gmt.
            // We just need to ensure our API exposes it. This hook is more of a placeholder
            // if we needed to do something *extra* when a product is saved in provider mode.
            add_action( 'save_post_product', [ $this, 'handle_product_save_provider' ], 10, 3 );

            // For Product Attribute Definitions (e.g., "Color", "Size")
            add_action( 'woocommerce_attribute_added', [ $this, 'handle_wc_attribute_definition_save_provider' ], 10, 2 );
            add_action( 'woocommerce_attribute_updated', [ $this, 'handle_wc_attribute_definition_save_provider' ], 10, 2 );
            // Consider: add_action( 'woocommerce_attribute_deleted', [ $this, 'handle_wc_attribute_definition_delete_provider' ], 10, 1 );


            // For Attribute Terms (terms of pa_* taxonomies like "Red", "Large")
            // 'saved_term' covers both creation and update of any term.
            // We will filter by taxonomy inside the handler.
            add_action( 'saved_term', [ $this, 'handle_attribute_term_save_provider' ], 10, 4 ); // term_id, tt_id, taxonomy, update
        }
    }

    /**
     * Handles actions when a product is saved in Provider Mode.
     * WordPress automatically updates post_modified_gmt for posts.
     * This is a placeholder if specific actions for FDSH are needed.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function handle_product_save_provider( $post_id, $post, $update ) {
        // post_modified_gmt is already handled by WordPress.
        // Log for now to confirm it's firing.
        $this->logger->debug( "Product saved (Provider Mode): ID {$post_id}. WP will update post_modified_gmt." );
        // Potentially clear any related caches if we implement caching for the API.
    }

    /**
     * Handles saving/updating of a WooCommerce product attribute definition in Provider Mode.
     * Stores a last_modified timestamp for the attribute definition.
     *
     * @param int   $attribute_id   Attribute ID (from wp_woocommerce_attribute_taxonomies table).
     * @param array $attribute_data Attribute data (passed by WooCommerce hooks).
     */
    public function handle_wc_attribute_definition_save_provider( $attribute_id, $attribute_data ) {
        // WooCommerce stores attribute definitions in its own table: wp_woocommerce_attribute_taxonomies
        // We need a way to store meta against this attribute definition.
        // A common approach is to use a dedicated option or a custom table.
        // For simplicity, let's use an option where keys are attribute IDs.

        $option_name = 'fdsh_attribute_modified_gmt_tracking'; // Option stores an array [id => timestamp]
        $timestamps = get_option( $option_name, [] );
        if (!is_array($timestamps)) { // Ensure it's an array
            $timestamps = [];
        }
        $current_gmt_time = current_time( 'mysql', true ); // Get current GMT time
        $timestamps[ $attribute_id ] = $current_gmt_time;

        update_option( $option_name, $timestamps, false ); // 'false' for not autoloading if it might become large

        $this->logger->info( "Attribute definition saved/updated (Provider Mode): ID {$attribute_id}. Updated modified_gmt to {$current_gmt_time} (via option: {$option_name})." );
    }

    /**
     * Handles saving/updating of an attribute term in Provider Mode.
     * Stores a last_modified timestamp for the term.
     *
     * @param int    $term_id  Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @param bool   $update   Whether this is an existing term being updated or not.
     */
    public function handle_attribute_term_save_provider( $term_id, $tt_id, $taxonomy, $update ) {
        // Check if the taxonomy is a product attribute taxonomy (starts with 'pa_')
        if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
            $current_gmt_time = current_time( 'mysql', true ); // Get current GMT time
            update_term_meta( $term_id, '_fdsh_term_modified_gmt', $current_gmt_time );
            $this->logger->info( "Attribute term '{$term_id}' in taxonomy '{$taxonomy}' saved/updated (Provider Mode). Updated _fdsh_term_modified_gmt to {$current_gmt_time}." );

            // Also, when a term is saved, we might consider its parent attribute definition as "modified"
            // in the sense that its collection of terms has changed. This could be done by calling
            // $this->handle_wc_attribute_definition_save_provider() if we can get the attribute_id from the $taxonomy slug.
            // For example, if $taxonomy is 'pa_color', the attribute_id for 'color' would be needed.
            // This requires a lookup from taxonomy slug to attribute ID.
            // global $wpdb;
            // $attribute_name = str_replace( 'pa_', '', $taxonomy );
            // $attribute_id = $wpdb->get_var( $wpdb->prepare( "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s", $attribute_name ) );
            // if ( $attribute_id ) {
            //    $this->handle_wc_attribute_definition_save_provider( $attribute_id, [] ); // Pass empty data as it's just for timestamp
            // }
        }
    }

    // --- Public methods for the module ---
    // Example:
    // public function sync_product( $product_id ) {
    //     if ( $this->settings_manager->is_client_mode() ) {
    //         // Call client sync logic
    //     }
    // }

}
?>
