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

        register_rest_route( $this->namespace, '/products/(?P<id>[^/]+)', [ // id can be numeric ID or SKU string (more permissive regex)
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

        $modified_since = $request->get_param( 'modified_since' );
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Retrieve all matching products
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];

        if ( ! empty( $modified_since ) ) {
            // Validate ISO8601 date format (basic check)
            if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?)$/', $modified_since ) ) {
                return new WP_Error(
                    'rest_invalid_param',
                    __( 'Invalid date format for modified_since. Please use ISO8601 format (YYYY-MM-DDTHH:MM:SS).' ),
                    [ 'status' => 400, 'param' => 'modified_since' ]
                );
            }
            $args['date_query'] = [
                [
                    'column' => 'post_modified_gmt',
                    'after'  => $modified_since,
                    'inclusive' => false, // Typically, modified *since* is exclusive of the provided date
                ],
            ];
        }

        $query = new WP_Query( $args );
        $products_data = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $product = wc_get_product( $post_id );

                if ( ! $product ) {
                    continue;
                }

                // Prepare data according to README Section 9.1
                $data = [
                    'id'             => $product->get_id(),
                    'sku'            => $product->get_sku(),
                    'title'          => ['rendered' => get_the_title() ],
                    'content'        => ['rendered' => get_the_content() ],
                    'excerpt'        => ['rendered' => get_the_excerpt() ],
                    'status'         => $product->get_status(),
                    'modified_gmt'   => get_post( $product->get_id() )->post_modified_gmt,
                    'regular_price'  => $product->get_regular_price(),
                    'sale_price'     => $product->get_sale_price(),
                    'stock_status'   => $product->get_stock_status(),
                    'manage_stock'   => $product->managing_stock() ? 'yes' : 'no',
                    'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
                    'weight'         => $product->get_weight(),
                    'dimensions'     => [
                        'length' => $product->get_length(),
                        'width'  => $product->get_width(),
                        'height' => $product->get_height(),
                    ],
                    'images'         => [], // To be populated
                    'categories'     => [], // To be populated
                    'tags'           => [], // To be populated
                    'attributes'     => [], // To be populated
                    'variations'     => [], // To be populated for variable products
                    'meta_data'      => [], // To be populated (_swatch_type, _swatch_type_options)
                ];

                // Featured Image
                $featured_image_id = $product->get_image_id();
                if ( $featured_image_id ) {
                    $image_url = wp_get_attachment_url( $featured_image_id );
                    $data['images'][] = [
                        'id'  => (int) $featured_image_id,
                        'src' => $image_url,
                        // 'name' => basename(get_attached_file($featured_image_id)), // Optional
                        // 'alt' => get_post_meta($featured_image_id, '_wp_attachment_image_alt', true) // Optional
                    ];
                }

                // Gallery Images
                $gallery_image_ids = $product->get_gallery_image_ids();
                foreach ( $gallery_image_ids as $gallery_image_id ) {
                    $image_url = wp_get_attachment_url( $gallery_image_id );
                    $data['images'][] = [
                        'id'  => (int) $gallery_image_id,
                        'src' => $image_url,
                    ];
                }

                // Categories
                $term_ids = $product->get_category_ids();
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, 'product_cat' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $data['categories'][] = [
                            'id'   => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        ];
                    }
                }

                // Tags
                $term_ids = $product->get_tag_ids();
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, 'product_tag' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $data['tags'][] = [
                            'id'   => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        ];
                    }
                }

                // Attributes
                $wc_attributes = $product->get_attributes();
                foreach ( $wc_attributes as $attribute_slug => $attribute_obj ) {
                    if ( $attribute_obj->is_taxonomy() ) {
                        $term_options = [];
                        foreach ( $attribute_obj->get_terms() as $term ) {
                            $term_options[] = $term->name; // Or $term->slug, or full term object
                        }
                        $data['attributes'][] = [
                            'id'        => $attribute_obj->get_id(), // This is the global attribute ID (from wp_woocommerce_attribute_taxonomies)
                            'name'      => wc_attribute_label( $attribute_obj->get_name() ), // e.g. Color
                            'slug'      => $attribute_obj->get_name(), // e.g. pa_color
                            'options'   => $term_options, // Array of term names/slugs selected for this product
                            'variation' => $attribute_obj->get_variation(),
                            'visible'   => $attribute_obj->get_visible(),
                        ];
                    } else {
                        // Custom product attribute
                        $data['attributes'][] = [
                            'id'        => 0, // Custom attributes don't have a global ID
                            'name'      => $attribute_obj->get_name(),
                            'slug'      => sanitize_title($attribute_obj->get_name()), // approximate slug
                            'options'   => $attribute_obj->get_options(), // Array of pipe-separated values
                            'variation' => $attribute_obj->get_variation(),
                            'visible'   => $attribute_obj->get_visible(),
                        ];
                    }
                }

                // Meta Data (_swatch_type, _swatch_type_options)
                $swatch_type = $product->get_meta('_swatch_type', true);
                if ($swatch_type) {
                    $data['meta_data'][] = ['key' => '_swatch_type', 'value' => $swatch_type];
                }
                $swatch_type_options = $product->get_meta('_swatch_type_options', true);
                if ($swatch_type_options) {
                    // This might be a serialized array/JSON, ensure it's passed as such or decoded if needed by client.
                    $data['meta_data'][] = ['key' => '_swatch_type_options', 'value' => $swatch_type_options];
                }

                // Variations (Basic handling for now - IDs only, or could be more detailed)
                if ( $product->is_type('variable') ) {
                    $variation_ids = $product->get_children();
                    // For now, just list IDs. Full variation data can be fetched via /products/<id>/variations if needed.
                    // Or, we can embed full variation data here if preferred (makes response larger).
                    // README Table 9.1 suggests: "variations (array of variation IDs, or full variation objects)"
                    // Let's go with IDs for now for the /products endpoint.
                    $data['variations'] = $variation_ids;
                }


                $products_data[] = $data;
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response( $products_data, 200 );
    }

    public function get_product( WP_REST_Request $request ) {
        $id_or_sku = $request['id'];
        $this->logger->info( "API Endpoint /products/<id> hit. ID: {$id_or_sku}" );

        FDSH_Logger::get_instance()->log( "Attempting to get product by ID or SKU: {$id_or_sku}", 'DEBUG' );

        $product_id = 0;
        $product = null;

        if ( is_numeric( $id_or_sku ) ) {
            $product_id = intval( $id_or_sku );
            FDSH_Logger::get_instance()->log( "Input '{$id_or_sku}' is numeric. Treated as product ID: {$product_id}", 'DEBUG' );
            $product = wc_get_product( $product_id );

            if ( ! $product ) {
                // If numeric ID doesn't find a product, it might be an SKU that happens to be all numbers.
                FDSH_Logger::get_instance()->log( "Product ID {$product_id} not found with wc_get_product(). Attempting as SKU: {$id_or_sku}", 'DEBUG' );
                $product_id_by_sku = wc_get_product_id_by_sku( $id_or_sku );
                FDSH_Logger::get_instance()->log( "SKU lookup for numeric '{$id_or_sku}' (after wc_get_product() failed) returned product ID: " . ($product_id_by_sku ?: 'not found'), 'DEBUG' );
                if ( $product_id_by_sku ) {
                    $product = wc_get_product( $product_id_by_sku );
                    // Update $product_id to the one found by SKU if successful
                    if ($product) $product_id = $product_id_by_sku; else $product_id = 0; // Reset product_id if lookup failed
                } else {
                    $product = null; // Ensure product is null if SKU lookup also fails
                    $product_id = 0; // Reset product_id
                }
            }
        } else {
            FDSH_Logger::get_instance()->log( "Input '{$id_or_sku}' is not numeric. Attempting as SKU.", 'DEBUG' );
            $product_id_by_sku = wc_get_product_id_by_sku( $id_or_sku );
            FDSH_Logger::get_instance()->log( "SKU lookup for '{$id_or_sku}' returned product ID: " . ($product_id_by_sku ?: 'not found'), 'DEBUG' );
            if ( $product_id_by_sku ) {
                $product = wc_get_product( $product_id_by_sku );
                 // Update $product_id to the one found by SKU if successful
                if ($product) $product_id = $product_id_by_sku; else $product_id = 0; // Reset product_id if lookup failed
            } else {
                $product = null;
                $product_id = 0; // Reset product_id
            }
        }

        if ( ! $product ) {
            return new WP_Error(
                'fdsh_product_not_found_by_param',
                __( 'Product not found with the provided ID or SKU.', 'forbes-data-sync-hub' ),
                [ 'status' => 404, 'param' => $id_or_sku ]
            );
        }

        // Prepare data according to README Section 9.1 (similar to get_products loop)
        $data = [
            'id'             => $product->get_id(),
            'sku'            => $product->get_sku(),
            'title'          => ['rendered' => $product->get_name() ], // Use get_name() for consistency with WC REST API for single product
            'content'        => ['rendered' => $product->get_description() ],
            'excerpt'        => ['rendered' => $product->get_short_description() ],
            'status'         => $product->get_status(),
            'modified_gmt'   => get_post( $product->get_id() )->post_modified_gmt,
            'regular_price'  => $product->get_regular_price(),
            'sale_price'     => $product->get_sale_price(),
            'stock_status'   => $product->get_stock_status(),
            'manage_stock'   => $product->managing_stock() ? 'yes' : 'no',
            'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
            'weight'         => $product->get_weight(),
            'dimensions'     => [
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ],
            'images'         => [],
            'categories'     => [],
            'tags'           => [],
            'attributes'     => [],
            'variations'     => [],
            'meta_data'      => [],
        ];

        // Featured Image
        $featured_image_id = $product->get_image_id();
        if ( $featured_image_id ) {
            $image_url = wp_get_attachment_url( $featured_image_id );
            $data['images'][] = [
                'id'  => (int) $featured_image_id,
                'src' => $image_url,
            ];
        }

        // Gallery Images
        $gallery_image_ids = $product->get_gallery_image_ids();
        foreach ( $gallery_image_ids as $gallery_image_id ) {
            $image_url = wp_get_attachment_url( $gallery_image_id );
            $data['images'][] = [
                'id'  => (int) $gallery_image_id,
                'src' => $image_url,
            ];
        }

        // Categories
        $term_ids = $product->get_category_ids();
        foreach ( $term_ids as $term_id ) {
            $term = get_term( $term_id, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $data['categories'][] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        // Tags
        $term_ids = $product->get_tag_ids();
        foreach ( $term_ids as $term_id ) {
            $term = get_term( $term_id, 'product_tag' );
            if ( $term && ! is_wp_error( $term ) ) {
                $data['tags'][] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        // Attributes
        $wc_attributes = $product->get_attributes();
        foreach ( $wc_attributes as $attribute_slug => $attribute_obj ) {
            if ( $attribute_obj->is_taxonomy() ) {
                $term_options = [];
                foreach ( $attribute_obj->get_terms() as $term ) {
                    $term_options[] = $term->name; 
                }
                $data['attributes'][] = [
                    'id'        => $attribute_obj->get_id(),
                    'name'      => wc_attribute_label( $attribute_obj->get_name() ),
                    'slug'      => $attribute_obj->get_name(),
                    'options'   => $term_options,
                    'variation' => $attribute_obj->get_variation(),
                    'visible'   => $attribute_obj->get_visible(),
                ];
            } else {
                $data['attributes'][] = [
                    'id'        => 0,
                    'name'      => $attribute_obj->get_name(),
                    'slug'      => sanitize_title($attribute_obj->get_name()),
                    'options'   => $attribute_obj->get_options(),
                    'variation' => $attribute_obj->get_variation(),
                    'visible'   => $attribute_obj->get_visible(),
                ];
            }
        }

        // Meta Data (_swatch_type, _swatch_type_options)
        $swatch_type = $product->get_meta('_swatch_type', true);
        if ($swatch_type) {
            $data['meta_data'][] = ['key' => '_swatch_type', 'value' => $swatch_type];
        }
        $swatch_type_options = $product->get_meta('_swatch_type_options', true);
        if ($swatch_type_options) {
            $data['meta_data'][] = ['key' => '_swatch_type_options', 'value' => $swatch_type_options];
        }

        // Variations (IDs only for now)
        if ( $product->is_type('variable') ) {
            $data['variations'] = $product->get_children();
        }

        return new WP_REST_Response( $data, 200 );
    }

    public function get_attributes( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /attributes hit.' );

        $modified_since = $request->get_param( 'modified_since' );
        $valid_modified_since = null;

        if ( ! empty( $modified_since ) ) {
            if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?)$/', $modified_since ) ) {
                return new WP_Error(
                    'rest_invalid_param',
                    __( 'Invalid date format for modified_since. Please use ISO8601 format (YYYY-MM-DDTHH:MM:SS).' ),
                    [ 'status' => 400, 'param' => 'modified_since' ]
                );
            }
            // Convert to timestamp for easier comparison, assuming stored timestamps are also comparable (e.g. Y-m-d H:i:s or Unix timestamp)
            // WordPress current_time('mysql', true) gives Y-m-d H:i:s GMT.
            $valid_modified_since = $modified_since; // Keep as string for direct comparison with stored GMT string
        }

        $attributes = wc_get_attribute_taxonomies();
        $attribute_timestamps = get_option( 'fdsh_attribute_modified_gmt_tracking', [] );
        $result = [];

        if ( ! empty( $attributes ) ) {
            foreach ( $attributes as $attribute_obj ) {
                $attribute_id = $attribute_obj->attribute_id;
                $timestamp_gmt = isset( $attribute_timestamps[ $attribute_id ] ) ? $attribute_timestamps[ $attribute_id ] : null;

                if ( $valid_modified_since && $timestamp_gmt ) {
                    // Both timestamps should be directly comparable strings in GMT: 'Y-m-d H:i:s'
                    if ( $timestamp_gmt <= $valid_modified_since ) {
                        continue; // Skip if not modified since the given date
                    }
                } elseif ( $valid_modified_since && ! $timestamp_gmt ) {
                    // If modified_since is set, but this attribute has no timestamp, skip it
                    // (or decide to include it if it implies it's "new" and un-timestamped)
                    // For now, skipping seems safer to only get timestamped items.
                    continue;
                }

                $result[] = [
                    'id'           => (int) $attribute_id, // This is the attribute_id from wp_woocommerce_attribute_taxonomies
                    'name'         => $attribute_obj->attribute_label, // e.g., "Color"
                    'slug'         => 'pa_' . $attribute_obj->attribute_name, // e.g., "pa_color"
                    'type'         => $attribute_obj->attribute_type, // e.g., "select"
                    'order_by'     => $attribute_obj->attribute_orderby, // e.g., "menu_order"
                    'has_archives' => (bool) $attribute_obj->attribute_public,
                    'modified_gmt' => $timestamp_gmt, // The _fdsh_attribute_modified_gmt
                ];
            }
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function get_attribute( WP_REST_Request $request ) {
        $this->logger->info( 'API Endpoint /attributes/<slug> hit. Slug: ' . $request->get_param('slug') );
        $requested_slug = $request->get_param('slug');

        // The slug in wc_get_attribute_taxonomies is without 'pa_', e.g., 'color'
        // The route captures the full taxonomy name e.g. 'pa_color'
        $attribute_name_only = preg_replace( '/^pa_/', '', $requested_slug );

        $attributes = wc_get_attribute_taxonomies();
        $found_attribute_obj = null;

        if ( ! empty( $attributes ) ) {
            foreach ( $attributes as $attribute_obj ) {
                if ( $attribute_obj->attribute_name === $attribute_name_only ) {
                    $found_attribute_obj = $attribute_obj;
                    break;
                }
            }
        }

        if ( ! $found_attribute_obj ) {
            return new WP_Error(
                'fdsh_attribute_not_found',
                __( 'Attribute definition not found with the provided slug.', 'forbes-data-sync-hub' ),
                [ 'status' => 404, 'slug' => $requested_slug ]
            );
        }

        $attribute_id = $found_attribute_obj->attribute_id;
        $attribute_timestamps = get_option( 'fdsh_attribute_modified_gmt_tracking', [] );
        $timestamp_gmt = isset( $attribute_timestamps[ $attribute_id ] ) ? $attribute_timestamps[ $attribute_id ] : null;

        $result = [
            'id'           => (int) $attribute_id,
            'name'         => $found_attribute_obj->attribute_label,
            'slug'         => 'pa_' . $found_attribute_obj->attribute_name, // Ensure pa_ prefix for consistency
            'type'         => $found_attribute_obj->attribute_type,
            'order_by'     => $found_attribute_obj->attribute_orderby,
            'has_archives' => (bool) $found_attribute_obj->attribute_public,
            'modified_gmt' => $timestamp_gmt,
        ];

        return new WP_REST_Response( $result, 200 );
    }

    public function get_attribute_terms( WP_REST_Request $request ) {
        $attribute_slug = $request->get_param('attribute_slug'); // e.g., pa_color
        $this->logger->info( "API Endpoint /attributes/{$attribute_slug}/terms hit." );

        $modified_since = $request->get_param( 'modified_since' );
        $valid_modified_since = null;

        if ( ! taxonomy_exists( $attribute_slug ) || strpos( $attribute_slug, 'pa_' ) !== 0 ) {
            return new WP_Error(
                'fdsh_invalid_attribute_taxonomy',
                __( 'Invalid attribute taxonomy slug provided.', 'forbes-data-sync-hub' ),
                [ 'status' => 400, 'slug' => $attribute_slug ]
            );
        }

        if ( ! empty( $modified_since ) ) {
            if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?)$/', $modified_since ) ) {
                return new WP_Error(
                    'rest_invalid_param',
                    __( 'Invalid date format for modified_since. Please use ISO8601 format (YYYY-MM-DDTHH:MM:SS).' ),
                    [ 'status' => 400, 'param' => 'modified_since' ]
                );
            }
            $valid_modified_since = $modified_since;
        }

        $terms = get_terms( [
            'taxonomy'   => $attribute_slug,
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            $this->logger->error("Error fetching terms for {$attribute_slug}: " . $terms->get_error_message());
            return new WP_Error(
                'fdsh_terms_fetch_error',
                __( 'Error fetching terms for the attribute.', 'forbes-data-sync-hub' ),
                [ 'status' => 500, 'slug' => $attribute_slug ]
            );
        }

        $result = [];
        foreach ( $terms as $term ) {
            $term_id = $term->term_id;
            $timestamp_gmt = get_term_meta( $term_id, '_fdsh_term_modified_gmt', true );

            if ( $valid_modified_since && $timestamp_gmt ) {
                if ( $timestamp_gmt <= $valid_modified_since ) {
                    continue; 
                }
            } elseif ( $valid_modified_since && ! $timestamp_gmt ) {
                continue;
            }
            
            $swatch_image_url = '';
            $thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
            if ( $thumbnail_id ) {
                $swatch_image_url = wp_get_attachment_url( (int) $thumbnail_id );
            }

            $result[] = [
                'id'             => $term_id,
                'name'           => $term->name,
                'slug'           => $term->slug,
                'description'    => $term->description,
                'meta'           => [
                    'term_price'   => get_term_meta( $term_id, 'term_price', true ),
                    '_term_suffix' => get_term_meta( $term_id, '_term_suffix', true ),
                    'thumbnail_id' => $thumbnail_id ? (int) $thumbnail_id : null, // Source ID for the swatch image
                ],
                'swatch_image_url' => $swatch_image_url ? $swatch_image_url : null,
                'modified_gmt'   => $timestamp_gmt ? $timestamp_gmt : null, 
            ];
        }

        return new WP_REST_Response( $result, 200 );
    }
}
?>
