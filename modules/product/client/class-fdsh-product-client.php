<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Product_Client Class
 *
 * Handles the client-side logic for fetching product and attribute data from the provider API
 * and syncing it to the local WordPress site.
 */
class FDSH_Product_Client {

    private $settings_manager;
    private $logger;
    private $api_url;
    private $api_namespace = 'forbes-data/v1';
    private $auth_header;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings_manager = FDSH_Settings_Manager::get_instance();
        $this->logger = FDSH_Logger::get_instance();

        // Prepare API credentials from settings
        $raw_api_url = $this->settings_manager->get_setting('client_source_api_url');
        // Ensure the API URL has the /wp-json/ part.
        if ( $raw_api_url && strpos( $raw_api_url, 'wp-json' ) === false ) {
            $this->api_url = rtrim( $raw_api_url, '/' ) . '/wp-json/';
        } else {
            $this->api_url = $raw_api_url;
        }

        $username = $this->settings_manager->get_setting('client_app_username');
        $password = $this->settings_manager->get_setting('client_app_password');
        
        if ( ! empty( $username ) && ! empty( $password ) ) {
            $this->auth_header = 'Basic ' . base64_encode( $username . ':' . $password );
        }
    }
    
    /**
     * Helper to make authenticated API requests.
     *
     * @param string $endpoint The API endpoint to call (e.g., '/attributes').
     * @return array|WP_Error The decoded JSON response body or a WP_Error on failure.
     */
    private function do_api_request( $endpoint ) {
        $request_url = $this->api_url . $this->api_namespace . $endpoint;
        $this->logger->debug( "Doing API request to: {$request_url}" );

        $args = [
            'headers' => [ 'Authorization' => $this->auth_header ],
            'timeout' => 30, // seconds
        ];

        $response = wp_remote_get( $request_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( "API request to {$endpoint} failed. WP_Error: " . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_message = "API request to {$endpoint} failed with status code {$status_code}.";
            $data = json_decode( $body, true );
            if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
                $error_message .= ' Details: ' . esc_html($data['message']);
            }
            $this->logger->error($error_message);
            return new WP_Error( 'api_error', $error_message, [ 'status' => $status_code ] );
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->error( "Failed to decode JSON response from {$endpoint}." );
            return new WP_Error( 'json_decode_error', 'Failed to decode JSON response.', [ 'body' => $body ] );
        }

        return $data;
    }

    /**
     * Main method to orchestrate the synchronization of attributes and their terms.
     *
     * @param string|null $single_attribute_slug If provided, only this attribute (e.g. 'pa_color') will be synced.
     * @return array A status array with 'success' (bool) and 'message' (string).
     */
    public function sync_attributes_and_terms( $single_attribute_slug = null ) {
        if ( $single_attribute_slug ) {
            $this->logger->info( "Starting single attribute and term synchronization for: {$single_attribute_slug}..." );
        } else {
            $this->logger->info( 'Starting all attribute and term synchronization...' );
        }

        if ( empty( $this->api_url ) || empty( $this->auth_header ) ) {
            $message = 'API credentials are not configured. Cannot start sync.';
            $this->logger->error( $message );
            return [ 'success' => false, 'message' => $message ];
        }
        
        $summary = [
            'attributes' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
            'terms' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'images_sideloaded' => 0, 'images_failed' => 0],
        ];

        // 1. Fetch and process attribute definitions
        $this->logger->info( 'Fetching attribute definitions from provider...' );
        
        $api_attributes = [];
        if ( $single_attribute_slug ) {
            // Fetch a single attribute definition
            $attribute = $this->do_api_request( "/attributes/{$single_attribute_slug}" );
            if ( ! is_wp_error( $attribute ) ) {
                $api_attributes[] = $attribute;
            } else {
                 $message = 'Failed to fetch single attribute from provider: ' . $attribute->get_error_message();
                 $this->logger->error( $message );
                 return [ 'success' => false, 'message' => $message ];
            }
        } else {
            // Fetch all attributes
            $api_attributes = $this->do_api_request( '/attributes' );
        }

        if ( is_wp_error( $api_attributes ) ) {
            $message = 'Failed to fetch attributes from provider: ' . $api_attributes->get_error_message();
            $this->logger->error( $message );
            return [ 'success' => false, 'message' => $message ];
        }
        
        $this->logger->info( 'Found ' . count($api_attributes) . ' attributes on provider. Processing...' );

        // Get a map of existing local attributes for efficient lookup
        $local_attributes = wc_get_attribute_taxonomies();
        $local_attribute_map = [];
        foreach($local_attributes as $local_attr) {
            $local_attribute_map[$local_attr->attribute_name] = $local_attr->attribute_id;
        }

        foreach ( $api_attributes as $attr ) {
            $this->logger->debug( 'Processing attribute: ' . print_r($attr, true) );
            
            // Prepare data for wc_create_attribute / wc_update_attribute
            $attribute_slug_no_prefix = preg_replace( '/^pa_/', '', $attr['slug'] );
            $args = [
                'slug'         => $attribute_slug_no_prefix,
                'name'         => $attr['name'],
                'type'         => $attr['type'],
                'order_by'     => $attr['order_by'],
                'has_archives' => $attr['has_archives'],
            ];

            try {
                if ( isset( $local_attribute_map[$attribute_slug_no_prefix] ) ) {
                    // Update existing attribute
                    $attribute_id = $local_attribute_map[$attribute_slug_no_prefix];
                    $this->logger->info( "Updating local attribute '{$args['name']}' (ID: {$attribute_id})." );
                    wc_update_attribute( $attribute_id, $args );
                    $summary['attributes']['updated']++;
                } else {
                    // Create new attribute
                    $this->logger->info( "Creating new local attribute '{$args['name']}'." );
                    $attribute_id = wc_create_attribute( $args );
                    if ( is_wp_error( $attribute_id ) ) {
                        throw new Exception( $attribute_id->get_error_message() );
                    }
                    $summary['attributes']['created']++;
                    
                    // Manually register the taxonomy for the new attribute so we can add terms in the same process.
                    $taxonomy_name = wc_attribute_taxonomy_name($args['slug']);
                    if ( ! taxonomy_exists( $taxonomy_name ) ) {
                        $this->logger->debug( "Manually registering new taxonomy '{$taxonomy_name}' for immediate use." );
                        register_taxonomy(
                            $taxonomy_name,
                            apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array('product') ),
                            apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy_name, array(
                                'labels'       => array(
                                    'name' => $args['name'],
                                ),
                                'hierarchical' => true,
                                'show_ui'      => false,
                                'query_var'    => true,
                                'rewrite'      => false,
                            ) )
                        );
                    }
                }

                // 2. Fetch and process terms for this attribute
                $this->sync_terms_for_attribute($attr['slug'], $summary);

            } catch ( Exception $e ) {
                $this->logger->error( "Failed to process attribute '{$attr['name']}': " . $e->getMessage() );
                $summary['attributes']['failed']++;
                continue;
            }
        }
        
        $final_message = sprintf(
            'Sync complete. Attributes: %d created, %d updated, %d failed. Terms: %d created, %d updated, %d failed. Images: %d sideloaded, %d failed.',
            $summary['attributes']['created'],
            $summary['attributes']['updated'],
            $summary['attributes']['failed'],
            $summary['terms']['created'],
            $summary['terms']['updated'],
            $summary['terms']['failed'],
            $summary['terms']['images_sideloaded'],
            $summary['terms']['images_failed']
        );
        $this->logger->info( $final_message );

        return [ 'success' => true, 'message' => $final_message ];
    }
    
    /**
     * Syncs all terms for a given attribute slug.
     *
     * @param string $attribute_slug The full attribute slug (e.g., 'pa_color').
     * @param array &$summary The summary array, passed by reference.
     */
    private function sync_terms_for_attribute($attribute_slug, &$summary) {
        $this->logger->info( "Fetching terms for attribute '{$attribute_slug}'..." );
        $api_terms = $this->do_api_request( "/attributes/{$attribute_slug}/terms" );

        if ( is_wp_error( $api_terms ) ) {
            $this->logger->error( "Could not fetch terms for '{$attribute_slug}': " . $api_terms->get_error_message() );
            $summary['attributes']['failed']++; // Count as a failure for the parent attribute
            return;
        }

        $this->logger->info( 'Found ' . count($api_terms) . " terms for '{$attribute_slug}'. Processing..." );

        foreach($api_terms as $term_data) {
            try {
                $local_term = term_exists($term_data['slug'], $attribute_slug);

                $term_args = [
                    'slug' => $term_data['slug'],
                    'description' => $term_data['description']
                ];

                $term_id = null;

                if ( $local_term && is_array($local_term) ) {
                    // Term exists, update it
                    $term_id = $local_term['term_id'];
                    $this->logger->debug( "Updating term '{$term_data['name']}' (ID: {$term_id}) in taxonomy '{$attribute_slug}'." );
                    wp_update_term($term_id, $attribute_slug, $term_args);
                    $summary['terms']['updated']++;
                } else {
                    // Term does not exist, create it
                    $this->logger->debug( "Creating term '{$term_data['name']}' in taxonomy '{$attribute_slug}'." );
                    $new_term = wp_insert_term($term_data['name'], $attribute_slug, $term_args);
                    if ( is_wp_error($new_term) ) {
                        throw new Exception("Failed to insert term '{$term_data['name']}': " . $new_term->get_error_message());
                    }
                    $term_id = $new_term['term_id'];
                    $summary['terms']['created']++;
                }

                // Update term meta
                if ($term_id) {
                    // Standard meta
                    update_term_meta($term_id, 'term_price', $term_data['meta']['term_price']);
                    update_term_meta($term_id, '_term_suffix', $term_data['meta']['_term_suffix']);
                    
                    // Sync tracking meta
                    update_term_meta($term_id, '_fdsh_source_term_id', $term_data['id']);
                    update_term_meta($term_id, '_fdsh_last_synced_gmt', $term_data['modified_gmt']);

                    // Handle image sideloading
                    $this->handle_term_image_sideload($term_id, $term_data, $summary);
                }

            } catch(Exception $e) {
                $this->logger->error("Failed to process term '{$term_data['name']}' for attribute '{$attribute_slug}': " . $e->getMessage());
                $summary['terms']['failed']++;
                continue;
            }
        }
    }

    /**
     * Handles sideloading and attaching an image for a term.
     *
     * @param int $term_id The local term ID.
     * @param array $term_data The term data from the API.
     * @param array &$summary The summary array, passed by reference.
     */
    private function handle_term_image_sideload($term_id, $term_data, &$summary) {
        $source_image_url = $term_data['swatch_image_url'];
        
        // If there's no source image, ensure local thumbnail is removed
        if ( empty($source_image_url) ) {
            delete_term_meta($term_id, 'thumbnail_id');
            return;
        }

        // Check if we already have this image by looking for its source URL in meta
        $existing_attachment = get_posts([
            'post_type' => 'attachment',
            'meta_key' => '_source_image_url',
            'meta_value' => $source_image_url,
            'posts_per_page' => 1,
            'post_status' => 'inherit',
        ]);

        if ($existing_attachment) {
            $attachment_id = $existing_attachment[0]->ID;
            $this->logger->debug("Found existing attachment (ID: {$attachment_id}) for source URL {$source_image_url}. Skipping sideload.");
        } else {
            // Need to sideload the image
            // We need these files for media_sideload_image()
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $this->logger->debug("Sideloading image for term {$term_id} from {$source_image_url}");
            $attachment_id = media_sideload_image($source_image_url, 0, $term_data['name'], 'id');
            
            if (is_wp_error($attachment_id)) {
                $this->logger->error("Failed to sideload image from {$source_image_url}: " . $attachment_id->get_error_message());
                $summary['terms']['images_failed']++;
                return;
            }
            
            // Store the original source URL in meta to prevent future duplicates
            update_post_meta($attachment_id, '_source_image_url', $source_image_url);
            $summary['terms']['images_sideloaded']++;
        }

        // Now link the attachment ID to the term
        update_term_meta($term_id, 'thumbnail_id', $attachment_id);
    }

    /**
     * Fetches only the list of attributes from the provider API.
     * This is used to populate UI elements without performing a full sync.
     *
     * @return array|WP_Error An array of attribute data or a WP_Error on failure.
     */
    public function get_provider_attributes() {
        $this->logger->info( 'Fetching attribute definitions from provider for UI display...' );

        if ( empty( $this->api_url ) || empty( $this->auth_header ) ) {
            $message = 'API credentials are not configured. Cannot fetch attributes.';
            $this->logger->error( $message );
            return new WP_Error('config_error', $message);
        }

        $api_attributes = $this->do_api_request( '/attributes' );

        if ( is_wp_error( $api_attributes ) ) {
            $message = 'Failed to fetch attributes from provider: ' . $api_attributes->get_error_message();
            $this->logger->error( $message );
            return $api_attributes;
        }

        return $api_attributes;
    }
}
?> 