<?php

namespace YayMail\Utils;

/**
 * Logger
 */
class Logger {
    private $log_directory;
    private $wp_filesystem;
    private $max_log_files;

    /**
     * Constructor for the Logger class.
     *
     * Initializes the logger with a specified log directory, prefix for log entries,
     * and the maximum number of log files to keep. Ensures the log directory exists and
     * performs cleanup of old log files.
     */
    public function __construct( $log_directory = null, $max_log_files = 30 ) {
        if ( ! $log_directory ) {
            $log_directory = WP_CONTENT_DIR . '/yaymail-logs';
        }
        $this->log_directory = $log_directory;
        $this->max_log_files = $max_log_files;
        $this->initialize_filesystem();

        // Ensure the log directory exists (only if filesystem is available)
        if ( $this->wp_filesystem && ! $this->wp_filesystem->is_dir( $this->log_directory ) ) {
            $this->wp_filesystem->mkdir( $this->log_directory, 0755 );
        }

        // Clean up old log files (only if filesystem is available)
        if ( $this->wp_filesystem ) {
            $this->cleanup_old_logs();
        }
    }

    private function initialize_filesystem() {
        global $wp_filesystem;

        // Initialize the WordPress filesystem
        require_once ABSPATH . 'wp-admin/includes/file.php';

        // Try to initialize WP_Filesystem, fallback to direct method if failed
        if ( ! WP_Filesystem() || ! $wp_filesystem ) {
            // Fallback to direct filesystem access
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            $this->wp_filesystem = new \WP_Filesystem_Direct( null );
        } else {
            $this->wp_filesystem = $wp_filesystem;
        }
    }

    private function cleanup_old_logs() {
        // Return early if filesystem is not available or log directory is not a directory
        if ( ! $this->wp_filesystem || ! $this->wp_filesystem->is_dir( $this->log_directory ) ) {
            return;
        }

        // Get all log files in the directory
        $files = $this->wp_filesystem->dirlist( $this->log_directory );

        if ( ! is_array( $files ) ) {
            return;
        }

        // Filter only log files
        $log_files = array_filter(
            $files,
            function( $file ) {
                return strpos( $file['name'], '.log' ) !== false;
            }
        );

        // Sort log files by name (assuming the name is in the format 'Y-m-d.log')
        usort(
            $log_files,
            function( $a, $b ) {
                return strcmp( $b['name'], $a['name'] );
            }
        );

        // If there are more log files than the allowed maximum, delete the oldest
        if ( count( $log_files ) > $this->max_log_files ) {
            $files_to_delete = array_slice( $log_files, $this->max_log_files );

            foreach ( $files_to_delete as $file ) {
                $this->wp_filesystem->delete( $this->log_directory . '/' . $file['name'] );
            }
        }
    }

    public function log( $message ) {
        // Return early if filesystem is not available
        if ( ! $this->wp_filesystem ) {
            return;
        }

        // Get the current date to create a log file for each day, based on local time
        $date     = current_time( 'Y-m-d' );
        $log_file = $this->log_directory . '/' . $date . '.log';

        // Construct the log message with a timestamp based on local time
        $timestamp   = current_time( 'Y-m-d H:i:s' );
        $log_message = "[$timestamp] $message" . PHP_EOL;

        // Append the log message to the log file
        if ( $this->wp_filesystem->exists( $log_file ) ) {
            $existing_content = $this->wp_filesystem->get_contents( $log_file );
            $log_message      = $existing_content . $log_message;
        }

        $this->wp_filesystem->put_contents( $log_file, $log_message, FS_CHMOD_FILE );
    }

    /**
     * Logs an exception message and sends a JSON error response.
     * Message will display in folder wp-content/yaymail-logs
     */
    public function log_exception_message( $ex, $log_type = 'error', $additional_data = null ) {
        $prefix = ( $log_type === 'warning' ) ? __( 'WARNING:', 'yaymail' ) : __( 'SYSTEM ERROR:', 'yaymail' );

        $message  = $prefix . ' ' . $ex->getCode() . ' : ' . $ex->getMessage();
        $message .= PHP_EOL . $ex->getFile() . '(' . $ex->getLine() . ')';
        $message .= PHP_EOL . $ex->getTraceAsString();

        // Add additional data if provided
        if ( $additional_data !== null ) {
            $message .= PHP_EOL . 'Additional data: ';
            if ( is_array( $additional_data ) || is_object( $additional_data ) ) {
                $message .= PHP_EOL . print_r( $additional_data, true );
            } else {
                $message .= $additional_data;
            }
        }

        $this->log( $message );

        // Only send JSON response for errors, not for warnings
        if ( $log_type === 'error' ) {
            wp_send_json_error( [ 'mess' => $message ] );
        }
    }
}
