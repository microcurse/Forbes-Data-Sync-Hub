<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Product_API_Provider Class
 *
 * Handles the registration and callbacks for product/attribute API endpoints in Provider Mode.
 */
class FDSH_Product_API_Provider {

    private static $instance = null;
    private $namespace = 'forbes-data/v1';
    private $logger;
    private $settings_manager;


    /**
     * Private constructor.
     */
    private function __construct() {
        $this->logger = FDSH_Logger::get_instance();
        $this->settings_manager = FDSH_Settings_Manager::get_instance(); // To check mode if necessary

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        $this->logger->info('FDSH_Product_API_Provider initialized and rest_api_init hook added.');
    }

    /**
     * Gets the single instance of this class.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers the REST API routes for products and attributes.
     */
    public function register_routes() {
        // Ensure this only runs in provider mode (double check, although module loading should handle this)
        if ( ! $this->settings_manager->is_provider_mode() ) {
            return;
        }

        $this->logger->info('Registering Product API Provider routes.');

        // Product Endpoints
        register_rest_route( $this->namespace, '/products', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_products' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'modified_since' => [
                        'description' => __( 'Limit results to products modified since a given ISO8601 compliant date.', 'forbes-data-sync-hub' ),
                        'type'        => 'string',
                        'format'      => 'date-time',
                    ],
                    'context' => [ 'default' => 'view' ],
                ],
            ],
        ]);

        register_rest_route( $this->namespace, '/products/(?P<id>[\w-]+)', [ // id can be numeric ID or SKU string
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_product' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'id' => [
                        'description' => __( 'Product ID or SKU.', 'forbes-data-sync-hub' ),
                        'type'        => 'string', // SKU can be string
                        'required'    => true,
                    ],
                    'context' => [ 'default' => 'view' ],
                ],
            ],
        ]);

        // Attribute Definition Endpoints
        register_rest_route( $this->namespace, '/attributes', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_attributes' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                 'args'                => [
                    'modified_since' => [
                        'description' => __( 'Limit results to attributes modified since a given ISO8601 compliant date (based on _fdsh_attribute_modified_gmt).', 'forbes-data-sync-hub' ),
                        'type'        => 'string',
                        'format'      => 'date-time',
                    ],
                ],
            ],
        ]);

        register_rest_route( $this->namespace, '/attributes/(?P<slug>[\w-]+)', [ // slug is attribute slug e.g. pa_color
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_attribute' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'slug' => [
                        'description' => __( 'Attribute slug (e.g., pa_color).', 'forbes-data-sync-hub' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // Attribute Terms Endpoints
        register_rest_route( $this->namespace, '/attributes/(?P<attribute_slug>[\w-]+)/terms', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_attribute_terms' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'attribute_slug' => [
                        'description' => __( 'Parent attribute slug (e.g., pa_color).', 'forbes-data-sync-hub' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'modified_since' => [
                        'description' => __( 'Limit results to terms modified since a given ISO8601 compliant date (based on _fdsh_term_modified_gmt).', 'forbes-data-sync-hub' ),
                        'type'        => 'string',
                        'format'      => 'date-time',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Basic permission check for API endpoints.
     * Using Application Passwords, so the user associated with the app password needs appropriate caps.
     * For read-only, 'read' capability or similar might be enough.
     * WooCommerce uses 'manage_woocommerce' for many things, or specific product/order caps.
     * Let's use a generic capability that can be filtered if needed.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function permissions_check( $request ) {
        // Application passwords authenticate a user. That user needs capabilities.
        // For now, let's check for a general capability like 'manage_options' or 'edit_products'.
        // This should be refined based on security requirements.
        if ( ! current_user_can( 'manage_woocommerce' ) ) { // Example capability
            $this->logger->warning( 'API Permission Check Failed for user: ' . get_current_user_id() );
            return new WP_Error( 'rest_forbidden', esc_html__( 'You do not have permissions to access this data.', 'forbes-data-sync-hub' ), [ 'status' => 403 ] );
        }
        $this->logger->debug( 'API Permission Check Passed for user: ' . get_current_user_id() );
        return true;
    }

    // --- Callback Implementations (Placeholders) ---

    public function get_products( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /products hit.' );
        // Full implementation later
        return new WP_REST_Response( [ 'message' => 'Products endpoint hit. Implementation pending.', 'params' => $request->get_params() ], 200 );
    }

    public function get_product( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /products/<id> hit. ID: ' . $request->get_param('id') );
        // Full implementation later
        return new WP_REST_Response( [ 'message' => 'Single product endpoint hit. Implementation pending.', 'id' => $request->get_param('id') ], 200 );
    }

    public function get_attributes( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /attributes hit.' );
        // Full implementation later
        return new WP_REST_Response( [ 'message' => 'Attributes endpoint hit. Implementation pending.', 'params' => $request->get_params() ], 200 );
    }

    public function get_attribute( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /attributes/<slug> hit. Slug: ' . $request->get_param('slug') );
        // Full implementation later
        return new WP_REST_Response( [ 'message' => 'Single attribute endpoint hit. Implementation pending.', 'slug' => $request->get_param('slug') ], 200 );
    }

    public function get_attribute_terms( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /attributes/<attribute_slug>/terms hit. Attribute Slug: ' . $request->get_param('attribute_slug') );
        // Full implementation later
        return new WP_REST_Response( [ 'message' => 'Attribute terms endpoint hit. Implementation pending.', 'attribute_slug' => $request->get_param('attribute_slug'), 'params' => $request->get_params() ], 200 );
    }
}
?>
