<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_AJAX_Handler Class
 *
 * Base class for handling AJAX actions within the Forbes Data Sync Hub plugin.
 * Provides a structured way to register and manage AJAX hooks.
 */
class FDSH_AJAX_Handler {

    private static $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // Common AJAX hooks can be added here or in specific module handlers
        // that might extend this class or use it.
    }

    /**
     * Gets the single instance of this class.
     *
     * @return FDSH_AJAX_Handler
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Helper function to register an AJAX action.
     *
     * @param string   $action_name The name of the AJAX action (without wp_ajax_ or wp_ajax_nopriv_).
     * @param callable $callback    The callback function to handle the AJAX request.
     * @param bool     $nopriv      Whether to also register for non-logged-in users (wp_ajax_nopriv_).
     * @param bool     $logged_in   Whether to register for logged-in users (wp_ajax_). Defaults to true.
     */
    protected function register_ajax_action( $action_name, $callback, $nopriv = false, $logged_in = true ) {
        if ( ! is_callable( $callback ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // Optionally log or trigger an error if callback is not callable
                // error_log( 'FDSH_AJAX_Handler: Invalid callback provided for action ' . $action_name );
            }
            return;
        }

        if ( $logged_in ) {
            add_action( 'wp_ajax_' . $action_name, $callback );
        }
        if ( $nopriv ) {
            add_action( 'wp_ajax_nopriv_' . $action_name, $callback );
        }
    }

    /**
     * Helper function to send a JSON success response.
     * Ensures consistent success responses.
     *
     * @param mixed $data Optional. Data to be sent.
     * @param int $status_code Optional. HTTP status code for success (e.g., 200, 201).
     */
    protected function send_json_success( $data = null, $status_code = null ) {
        $response = ['success' => true];
        if ( $data !== null ) {
            // If $data is a string and not JSON, treat it as a message.
            // Otherwise, assume it's structured data.
            if ( is_string( $data ) && ! ( is_object( json_decode( $data ) ) || is_array( json_decode( $data ) ) ) ) {
                 $response['message'] = $data;
            } else {
                 $response['data'] = $data;
            }
        }
        if ( $status_code && is_int( $status_code ) ) {
            wp_status_header( $status_code );
        }
        wp_send_json_success( $response ); // Exits
    }

    /**
     * Helper function to send a JSON error response.
     * Ensures consistent error responses.
     *
     * @param string $message Error message.
     * @param int $status_code Optional. HTTP status code (e.g., 400, 403, 500).
     * @param mixed $data Optional. Additional data to send with the error.
     */
    protected function send_json_error( $message, $status_code = null, $data = null ) {
        $response_data = ['message' => $message];
        if( $data !== null ) {
            $response_data['data'] = $data;
        }
        if ( $status_code && is_int( $status_code ) ) {
            // No direct wp_status_header() here as wp_send_json_error sets it to 500 by default
            // if no other status code is passed in its $status_code param.
            // However, wp_send_json_error itself doesn't accept a status code argument in its signature.
            // We need to call wp_status_header before wp_send_json_error.
            wp_status_header( $status_code );
        }
        wp_send_json_error( $response_data ); // Exits
    }

    /**
     * Verifies the AJAX nonce. Dies with JSON error if invalid.
     *
     * @param string $action The nonce action name.
     * @param string $nonce_field The name of the nonce field in the request (default: '_ajax_nonce').
     * @param string $request_method 'REQUEST' (default), 'POST', or 'GET'.
     * @return bool True if nonce is valid and execution continues.
     */
    protected function verify_nonce( $action, $nonce_field = '_ajax_nonce', $request_method = 'REQUEST' ) {
        $nonce = '';
        switch ( strtoupper( $request_method ) ) {
            case 'POST':
                $nonce = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';
                break;
            case 'GET':
                $nonce = isset( $_GET[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_GET[ $nonce_field ] ) ) : '';
                break;
            default: // REQUEST
                $nonce = isset( $_REQUEST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $nonce_field ] ) ) : '';
                break;
        }

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
            $this->send_json_error( __( 'Nonce verification failed. Please refresh and try again.', 'forbes-data-sync-hub' ), 403 );
            // send_json_error will die, so execution stops here.
            return false; 
        }
        return true;
    }

    /**
     * Checks current user capabilities. Dies with JSON error if capabilities are not met.
     *
     * @param string|array $capability The capability or capabilities to check.
     * @return bool True if the user has the capability and execution continues.
     */
    protected function check_permissions( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            $this->send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'forbes-data-sync-hub' ), 403 );
            // send_json_error will die, so execution stops here.
            return false;
        }
        return true;
    }
}
// Example usage:
// class FDSH_My_Module_AJAX_Handler extends FDSH_AJAX_Handler {
//
//    public function __construct() {
//        // Note: No parent::__construct() needed if base constructor is empty or not essential for children.
//        $this->register_ajax_action('fdsh_my_action', [$this, 'handle_my_action']);
//    }
//
//    public function handle_my_action() {
//        $this->verify_nonce('fdsh_my_action_nonce');
//        $this->check_permissions('manage_options'); // Example capability
//
//        // Process the request
//        // $param = isset($_POST['param']) ? sanitize_text_field($_POST['param']) : null;
//
//        $this->send_json_success(['message' => 'Action completed successfully!']);
//    }
// }
// If this base class itself needs to be instantiated globally (e.g. for some core AJAX hooks),
// you would do it in the main plugin file. Otherwise, modules will extend or instantiate it.
// For now, no global instance is created here.
?> 