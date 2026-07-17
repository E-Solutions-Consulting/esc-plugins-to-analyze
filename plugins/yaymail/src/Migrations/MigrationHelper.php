<?php
namespace YayMail\Migrations;

/**
 * Migation Helper
 */
class MigrationHelper {

    /**
     * Append third number to version if it does not exist yet
     *
     * @param string $version The version string to format.
     * @return string|null
     * @Example: 4.0 => 4.0.0
     */
    public static function format_version_number( $version ) {
        if ( empty( $version ) ) {
            return null;
        }
        $parts               = explode( '.', $version );
        $version_parts_count = count( $parts );
        while ( $version_parts_count < 3 ) {
            $parts[] = '0';
            ++$version_parts_count;
        }
        return implode( '.', $parts );
    }

    public static function filter_migrations( array $migrations, $old_version, $new_version ): array {
        $filtered_migrations = array_filter(
            $migrations,
            function( $version ) use ( $old_version, $new_version ) {
                return version_compare( empty( $old_version ) ? '0.0.0' : $old_version, $version, '<' ) && version_compare( $new_version, $version, '>=' );
            },
            ARRAY_FILTER_USE_KEY
        );

        // Sort migrations by version
        uksort( $filtered_migrations, 'version_compare' );

        return $filtered_migrations;
    }

    /**
     * Check if the created date is within the specified time window from the current time.
     *
     * @param string      $created_date The date to check, formatted as 'Y-m-d H:i:s'.
     * @param int         $time_window  The time window in seconds to check against.
     * @param string|null $reference_time Optional reference time to check against instead of current time.
     * @return bool True if the created date is within the time window, false otherwise.
     */
    public static function is_within_time_window( $created_date, $time_window, $reference_time = null ) {
        try {
            $compared_time = $reference_time ? new \DateTime( $reference_time, wp_timezone() ) : current_datetime();
            $created       = new \DateTime( $created_date, wp_timezone() );
            return abs( $compared_time->getTimestamp() - $created->getTimestamp() ) <= $time_window;
        } catch ( \Exception $e ) {
            return false;
        }
    }


    /**
     * Perform migrations for specified versions.
     *
     * @param array $migrations An associative array where keys are version numbers (e.g., '4.0.0') and values are fully qualified class names of the migration classes.
     * @param bool  $skip_check_migration Whether to skip migration checks.
     *
     * @throws \Exception If the class for a version does not exist, or if the class or its instance does not have a callable `get_instance()` or `perform_migration()` method.
     *
     * @return void
     */
    public static function perform_migrations( $migrations, $skip_check_migration = false ) {
        foreach ( $migrations as $version => $class ) {
            if ( ! class_exists( $class ) ) {
                throw new \Exception( esc_html( "Class $class for version $version does not exist." ), 500 );
            }

            if ( ! is_callable( [ $class, 'get_instance' ] ) ) {
                throw new \Exception( esc_html( "Class $class for version $version does not have a callable get_instance method." ), 500 );
            }

            $migration_instance = $class::get_instance();

            if ( ! is_callable( [ $migration_instance, 'perform_migration' ] ) ) {
                throw new \Exception( esc_html( "Instance of class $class for version $version does not have a callable perform_migration method." ), 500 );
            }

            $migration_instance->perform_migration( $skip_check_migration );
        }
    }
}
