<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Settings_Manager Class
 *
 * Handles the registration and retrieval of plugin settings.
 */
class FDSH_Settings_Manager {

    private static $instance = null;
    private $settings_group = 'fdsh_settings';
    private $general_settings_key = 'fdsh_general_settings';
    private $options = [];

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // Load stored options
        $this->options = get_option( $this->general_settings_key, [] );

        // Register settings
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Gets the single instance of this class.
     *
     * @return FDSH_Settings_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registers the plugin's settings with WordPress.
     */
    public function register_settings() {
        register_setting(
            $this->settings_group,          // Option group
            $this->general_settings_key,    // Option name
            [ $this, 'sanitize_settings' ]  // Sanitize callback
        );

        // Add a general settings section (more sections can be added later)
        add_settings_section(
            'fdsh_general_section',         // ID
            __( 'General Settings', 'forbes-data-sync-hub' ), // Title
            null,                           // Callback to render the section description (optional)
            $this->settings_group           // Page on which to show this section
        );

        // Add a settings field for 'Plugin Role'
        add_settings_field(
            'fdsh_plugin_role',                 // ID
            __( 'Plugin Role', 'forbes-data-sync-hub' ), // Title
            [ $this, 'render_plugin_role_field' ], // Callback to render the field
            $this->settings_group,              // Page
            'fdsh_general_section'              // Section
        );

        // --- Provider Mode Settings ---
        add_settings_field(
            'fdsh_provider_info',
            __( 'Provider Mode Information', 'forbes-data-sync-hub' ),
            [ $this, 'render_provider_info_field' ],
            $this->settings_group,
            'fdsh_general_section' // Or a new section specific to Provider
        );

        // --- Client Mode Settings ---
        add_settings_field(
            'fdsh_client_source_api_url',
            __( 'Source API URL', 'forbes-data-sync-hub' ),
            [ $this, 'render_client_source_api_url_field' ],
            $this->settings_group,
            'fdsh_general_section' // Or a new section specific to Client
        );

        add_settings_field(
            'fdsh_client_app_password',
            __( 'Application Password', 'forbes-data-sync-hub' ),
            [ $this, 'render_client_app_password_field' ],
            $this->settings_group,
            'fdsh_general_section' // Or a new section specific to Client
        );

        add_settings_field(
            'fdsh_client_test_connection',
            __( 'Test Connection', 'forbes-data-sync-hub' ),
            [ $this, 'render_client_test_connection_button' ],
            $this->settings_group,
            'fdsh_general_section' // Or a new section specific to Client
        );
    }

    /**
     * Renders the Plugin Role selection field.
     */
    public function render_plugin_role_field() {
        $role = $this->get_setting( 'plugin_role', 'api_provider' ); // Default to 'api_provider'
        ?>
        <select name="<?php echo esc_attr( $this->general_settings_key ); ?>[plugin_role]" id="fdsh_plugin_role">
            <option value="api_provider" <?php selected( $role, 'api_provider' ); ?>>
                <?php esc_html_e( 'API Provider (Source Site)', 'forbes-data-sync-hub' ); ?>
            </option>
            <option value="api_client" <?php selected( $role, 'api_client' ); ?>>
                <?php esc_html_e( 'API Client (Destination Site)', 'forbes-data-sync-hub' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Define the primary role of this plugin instance.', 'forbes-data-sync-hub' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitizes the settings input.
     *
     * @param array $input Contains all settings fields as array keys.
     * @return array Sanitized input.
     */
    public function sanitize_settings( $input ) {
        // Ensure $input is an array, default to empty if not.
        $input = is_array( $input ) ? $input : [];

        $new_input = [];

        // Sanitize the 'plugin_role'
        if ( isset( $input['plugin_role'] ) && in_array( $input['plugin_role'], [ 'api_provider', 'api_client' ], true ) ) {
            $new_input['plugin_role'] = $input['plugin_role'];
        } else {
            // If invalid value is passed, fall back to existing or default.
            $new_input['plugin_role'] = $this->get_setting('plugin_role', 'api_provider');
        }

        // Determine current/newly submitted role for conditional sanitization
        $current_role = $this->get_plugin_role(); // Get currently saved role
        if(isset($new_input['plugin_role'])) {
            $current_role = $new_input['plugin_role']; // Or use newly submitted role if it's being changed
        }

        if ( 'api_client' === $current_role ) {
            if ( isset( $input['client_source_api_url'] ) ) {
                $new_input['client_source_api_url'] = esc_url_raw( trim( $input['client_source_api_url'] ) );
            }
            if ( isset( $input['client_app_password'] ) ) {
                // Passwords are typically not re-sanitized beyond trimming if they are non-empty.
                // WordPress itself doesn't re-sanitize passwords from options.php.
                // It's crucial not to display it back in the field directly for security.
                // The `value` attribute in the render callback should handle existing passwords carefully.
                $new_input['client_app_password'] = trim( $input['client_app_password'] );
            }
        } else {
            // If switching to provider mode, or already in provider mode,
            // decide if client-specific settings should be cleared or preserved.
            // For now, we'll clear them if the role is explicitly not client.
            // This prevents orphaned data if the role changes.
            // However, if $input doesn't contain these keys (e.g. form submitted in provider mode),
            // they won't be added to $new_input, and array_merge below will keep old $this->options values.
            // To explicitly clear:
            // $new_input['client_source_api_url'] = '';
            // $new_input['client_app_password'] = '';
            // For now, let's rely on them not being in $input if not in client view,
            // and if role changes, they might be submitted as empty or not at all.
            // The array_merge will preserve existing if not in $new_input.
            // If they ARE in $input (e.g. user had client form, switched role, submitted),
            // they will be sanitized above if $current_role was client, then this else is skipped.
            // If $current_role became provider, they won't be processed by the client block.
            // This logic can be tricky. A simpler approach for clearing is to do it explicitly
            // if $current_role is 'api_provider'.
            if ('api_provider' === $current_role) {
                 // If we are definitely in provider mode (or switching to it),
                 // and these keys are submitted (e.g. from a form that had them visible before role change),
                 // we might want to explicitly ignore/clear them from $new_input.
                 // However, if they are not in $input, they won't be in $new_input.
                 // If they are in $input (e.g. user was in client mode, fields populated, changed role to provider, submitted)
                 // we should probably clear them.
                if (array_key_exists('client_source_api_url', $input)) {
                    $new_input['client_source_api_url'] = ''; // Clear it
                }
                if (array_key_exists('client_app_password', $input)) {
                    $new_input['client_app_password'] = ''; // Clear it
                }
            }
        }

        // Merge sanitized input with existing options.
        return array_merge( $this->options, $new_input );
    }

    /**
     * Renders information for Provider Mode.
     */
    public function render_provider_info_field() {
        if ( ! $this->is_provider_mode() ) {
            echo '<p class="description">' . esc_html__( 'Not applicable in Client Mode.', 'forbes-data-sync-hub' ) . '</p>';
            return;
        }
        ?>
        <p class="description">
            <?php esc_html_e( 'When in Provider Mode, this site exposes data via its REST API.', 'forbes-data-sync-hub' ); ?><br>
            <?php esc_html_e( 'Manage API access using WordPress Application Passwords under Users > Your Profile.', 'forbes-data-sync-hub' ); ?>
        </p>
        <!-- Placeholder for future API access log summary -->
        <?php
    }

    /**
     * Renders the Source API URL field for Client Mode.
     */
    public function render_client_source_api_url_field() {
        if ( ! $this->is_client_mode() ) {
            echo '<p class="description">' . esc_html__( 'Not applicable in Provider Mode.', 'forbes-data-sync-hub' ) . '</p>';
            return;
        }
        $value = $this->get_setting( 'client_source_api_url', '' );
        ?>
        <input type="url" name="<?php echo esc_attr( $this->general_settings_key ); ?>[client_source_api_url]" id="fdsh_client_source_api_url" value="<?php echo esc_url( $value ); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e( 'Enter the full REST API URL of the Source Site (e.g., https://source.com/wp-json/).', 'forbes-data-sync-hub' ); ?>
        </p>
        <?php
    }

    /**
     * Renders the Application Password field for Client Mode.
     */
    public function render_client_app_password_field() {
        if ( ! $this->is_client_mode() ) {
            return; // No message needed as it's usually grouped with other client fields
        }
        $value = $this->get_setting( 'client_app_password', '' );
        // For security, don't output the password directly if it's already set.
        // Instead, show a placeholder or a message indicating it's set.
        // However, the requirement was to populate it, so we will for now.
        // Consider changing this to: $display_value = $value ? '********' : '';
        // Or not setting `value` at all if $value is not empty, requiring re-entry to change.
        ?>
        <input type="password" name="<?php echo esc_attr( $this->general_settings_key ); ?>[client_app_password]" id="fdsh_client_app_password" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e( 'Enter the Application Password generated on the Source Site for this client.', 'forbes-data-sync-hub' ); ?>
        </p>
        <?php
    }

    /**
     * Renders the Test Connection button for Client Mode.
     */
    public function render_client_test_connection_button() {
        if ( ! $this->is_client_mode() ) {
            return;
        }
        ?>
        <button type="button" class="button" id="fdsh_test_connection_button">
            <?php esc_html_e( 'Test Connection', 'forbes-data-sync-hub' ); ?>
        </button>
        <span id="fdsh_test_connection_status"></span>
        <p class="description">
            <?php esc_html_e( 'Click to test the connection to the Source API using the provided URL and Application Password.', 'forbes-data-sync-hub' ); ?>
        </p>
        <?php
        // TODO: Implement AJAX handler for 'Test Connection' button.
        // This will involve:
        // 1. Enqueueing a JS file for this admin page.
        // 2. JS to make an AJAX call (using FDSH_AJAX_Handler conventions) to a new action (e.g., 'fdsh_test_connection').
        // 3. A new method in an appropriate AJAX handler class (e.g., a new FDSH_Admin_AJAX_Handler or similar)
        //    to handle 'fdsh_test_connection', verify nonce, check permissions.
        // 4. The handler method will attempt a remote request to the provided API URL with basic auth (app password).
        // 5. Return JSON success/error based on the remote request's outcome (e.g., HTTP 200, specific body content).
        // 6. JS to update #fdsh_test_connection_status with the result.
    }

    /**
     * Retrieves a specific setting value.
     *
     * @param string $key The key of the setting to retrieve.
     * @param mixed $default The default value if the setting is not found.
     * @return mixed The value of the setting or the default.
     */
    public function get_setting( $key, $default = null ) {
        return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
    }

    /**
     * Retrieves all general settings.
     * @return array
     */
    public function get_general_settings() {
        return $this->options;
    }

    /**
     * Returns the key for general settings.
     * @return string
     */
    public function get_general_settings_key() {
        return $this->general_settings_key;
    }

    /**
     * Returns the settings group name.
     * @return string
     */
    public function get_settings_group() {
        return $this->settings_group;
    }

    /**
     * Gets the current configured plugin role.
     *
     * @return string 'api_provider' or 'api_client'.
     */
    public function get_plugin_role() {
        return $this->get_setting( 'plugin_role', 'api_provider' ); // Default to 'api_provider'
    }

    /**
     * Checks if the current role is API Provider.
     *
     * @return bool
     */
    public function is_provider_mode() {
        return 'api_provider' === $this->get_plugin_role();
    }

    /**
     * Checks if the current role is API Client.
     *
     * @return bool
     */
    public function is_client_mode() {
        return 'api_client' === $this->get_plugin_role();
    }
}
?>
