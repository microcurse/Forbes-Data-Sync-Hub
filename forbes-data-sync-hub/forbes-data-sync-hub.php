<?php
/**
 * Plugin Name: Forbes Data Sync Hub
 * Plugin URI: https://forbesindustries.com/
 * Description: Centralized WordPress plugin for managing, syncing, and exposing data from the Forbes Industries ecosystem.
 * Version: 0.1.0
 * Author: Forbes Industries
 * Author URI: https://forbesindustries.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: forbes-data-sync-hub
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define constants.
define( 'FDSH_VERSION', '0.1.0' );
// Ensure FDSH_PLUGIN_DIR is defined correctly for both web and CLI contexts.
if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . plugin_basename( __FILE__ ) ) ) {
    // Standard WordPress environment
    define( 'FDSH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'FDSH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
} else {
    // Fallback for CLI execution or if WordPress environment is not fully loaded
    // Assumes the script is in the plugin's root directory.
    define( 'FDSH_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
    define( 'FDSH_PLUGIN_URL', '' ); // URL is not relevant in this context
}
define( 'FDSH_TEXT_DOMAIN', 'forbes-data-sync-hub' );

// Basic autoloader.
spl_autoload_register( function ( $class_name ) {
    // Only autoload classes from this plugin.
    if ( strpos( $class_name, 'FDSH_' ) === 0 || strpos( $class_name, 'ForbesDataSyncHub\\' ) === 0 ) {
        // Replace namespace separators with directory separators.
        $file_path = str_replace( '\\', '/', $class_name );

        // Adjust for main plugin namespace vs generic FDSH_ prefix.
        if ( strpos( $file_path, 'ForbesDataSyncHub/' ) === 0 ) {
            // PSR-4 like structure: ForbesDataSyncHub\Foo\Bar -> includes/foo/bar.php
            $file_path = str_replace( 'ForbesDataSyncHub/', 'includes/', $file_path ) . '.php';
        } else {
             // Handle FDSH_ prefixed classes (older convention)
             // FDSH_Foo_Bar -> includes/class-fdsh-foo-bar.php
            $file_path = 'includes/class-' . strtolower( str_replace( '_', '-', $file_path ) ) . '.php';
        }

        $full_path = FDSH_PLUGIN_DIR . $file_path;

        if ( file_exists( $full_path ) ) {
            require_once $full_path;
            return;
        }

        // Fallback for FDSH_ prefixed classes that might be in core/
        // FDSH_Baz -> core/class-fdsh-baz.php
        if (strpos($class_name, 'FDSH_') === 0 && strpos($file_path, 'includes/') === 0) {
            $core_file_path = str_replace('includes/', 'core/', $file_path);
            if (file_exists(FDSH_PLUGIN_DIR . $core_file_path)) {
                require_once FDSH_PLUGIN_DIR . $core_file_path;
                return;
            }
        }

        // Fallback for module classes (simplified)
        // FDSH_Module_Name_Class_Something -> modules/module-name/class-fdsh-module-name-class-something.php
        // FDSH_Module_Name_Admin_Page -> modules/module-name/admin/class-fdsh-module-name-admin-page.php
        $parts = explode( '_', $class_name );
        if ( count( $parts ) > 2 && $parts[0] === 'FDSH' ) {
            $module_slug = strtolower( $parts[1] );
            $class_file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

            $potential_module_paths = [
                'modules/' . $module_slug . '/' . $class_file_name,
                'modules/' . $module_slug . '/includes/' . $class_file_name,
                'modules/' . $module_slug . '/admin/' . $class_file_name,
                'modules/' . $module_slug . '/api/' . $class_file_name,
                'modules/' . $module_slug . '/core/' . $class_file_name,
            ];

            foreach ( $potential_module_paths as $module_path_segment ) {
                if ( file_exists( FDSH_PLUGIN_DIR . $module_path_segment ) ) {
                    require_once FDSH_PLUGIN_DIR . $module_path_segment;
                    return;
                }
            }
        }
    }
});

// Note: Directory structure (includes/, admin/, modules/, etc.)
// is expected to be created via bash `mkdir -p` command prior to plugin activation,
// or handled by a deployment script. This PHP file does not create them.

// Placeholder for plugin activation/deactivation hooks if needed later.
// function fdsh_activate() {}
// register_activation_hook( __FILE__, 'fdsh_activate' );

// function fdsh_deactivate() {}
// register_deactivation_hook( __FILE__, 'fdsh_deactivate' );

// Main plugin class instance could be loaded here.
// require_once FDSH_PLUGIN_DIR . 'includes/class-fdsh-core.php';
// FDSH_Core::instance();

// Initialize Logger
FDSH_Logger::get_instance();

// Initialize Settings Manager and Admin UI (Admin UI depends on Settings Manager)
if ( is_admin() ) { // Only load admin-specific components on admin pages
    FDSH_Settings_Manager::get_instance();
    FDSH_Admin_UI::get_instance();
}

// Initialize Product Module
FDSH_Product_Module::get_instance();

?>
