<?php
// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * FDSH_Logger Class
 *
 * Handles logging for the plugin.
 */
class FDSH_Logger {

    private static $instance = null;
    private $log_file_path;
    private $log_dir_url; // Not strictly needed for file logging but good for consistency
    private $log_dir_base = 'fdsh-logs';
    private $max_log_files = 30; // Max number of log files to keep (for rotation)

    const LEVEL_INFO    = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';
    const LEVEL_DEBUG   = 'DEBUG';

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file_path = trailingslashit( $upload_dir['basedir'] ) . $this->log_dir_base;
        $this->log_dir_url = trailingslashit( $upload_dir['baseurl'] ) . $this->log_dir_base;

        if ( ! file_exists( $this->log_file_path ) ) {
            wp_mkdir_p( $this->log_file_path );
        }

        // TODO: Implement database logging for admin-viewable logs (INFO, WARNING, ERROR)
        // as per README section 10. This will require a custom table and UI for viewing/pruning.

        // Secure the log directory by adding an index.php and .htaccess file if not present.
        $this->secure_log_directory();
        $this->rotate_logs();
    }

    /**
     * Gets the single instance of this class.
     *
     * @return FDSH_Logger
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Secures the log directory by adding index.php and .htaccess.
     */
    private function secure_log_directory() {
        if ( ! file_exists( trailingslashit( $this->log_file_path ) . 'index.php' ) ) {
            @file_put_contents( trailingslashit( $this->log_file_path ) . 'index.php', '<?php // Silence is golden.' );
        }
        if ( ! file_exists( trailingslashit( $this->log_file_path ) . '.htaccess' ) ) {
            @file_put_contents( trailingslashit( $this->log_file_path ) . '.htaccess', 'Options -Indexes' . PHP_EOL . 'deny from all' );
        }
    }

    /**
     * Log a message.
     *
     * @param string $level The log level (e.g., INFO, ERROR).
     * @param string $message The message to log.
     */
    public function log( $level, $message ) {
        if ( self::LEVEL_DEBUG === $level && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) && ( ! defined( 'FDSH_DEBUG' ) || ! FDSH_DEBUG ) ) {
            return; // Do not log debug messages if WP_DEBUG and FDSH_DEBUG are off
        }

        $log_entry = sprintf(
            "[%s] [%s]: %s
",
            gmdate( 'Y-m-d H:i:s' ), // UTC timestamp
            strtoupper( $level ),
            is_string( $message ) ? $message : print_r( $message, true ) // Handle arrays/objects in message
        );

        $file_name = trailingslashit( $this->log_file_path ) . 'fdsh-' . gmdate( 'Y-m-d' ) . '.log';

        // Attempt to write to the file
        if ( @file_put_contents( $file_name, $log_entry, FILE_APPEND | LOCK_EX ) === false ) {
            // Fallback or error handling if needed, e.g., write to error_log()
            error_log("FDSH_Logger: Failed to write to log file: " . $file_name . " | Message: " . $log_entry);
        }
    }

    /**
     * Log an informational message.
     * @param string $message
     */
    public function info( $message ) {
        $this->log( self::LEVEL_INFO, $message );
    }

    /**
     * Log a warning message.
     * @param string $message
     */
    public function warning( $message ) {
        $this->log( self::LEVEL_WARNING, $message );
    }

    /**
     * Log an error message.
     * @param string $message
     */
    public function error( $message ) {
        $this->log( self::LEVEL_ERROR, $message );
    }

    /**
     * Log a debug message.
     * @param string $message
     */
    public function debug( $message ) {
        $this->log( self::LEVEL_DEBUG, $message );
    }

    /**
     * Rotate log files, keeping only the most recent ones.
     */
    private function rotate_logs() {
        $files = glob( trailingslashit( $this->log_file_path ) . 'fdsh-*.log' );
        if ( $files && count( $files ) > $this->max_log_files ) {
            usort( $files, function ( $a, $b ) {
                return filemtime( $a ) - filemtime( $b );
            } );
            $files_to_delete = array_slice( $files, 0, count( $files ) - $this->max_log_files );
            foreach ( $files_to_delete as $file ) {
                @unlink( $file );
            }
        }
    }

    /**
     * Get the path to the log directory.
     * @return string
     */
    public function get_log_directory_path() {
        return $this->log_file_path;
    }
}
?>
