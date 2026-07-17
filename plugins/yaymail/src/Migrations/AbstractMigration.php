<?php
namespace YayMail\Migrations;

use YayMail\SupportedPlugins;
use YayMail\Utils\Logger;
use YayMail\YayMailTemplate;

/**
 * AbstractMigration
 */
abstract class AbstractMigration {

    const BACKUP_PREFIX         = '_yaymail_backup_';
    const SUCCESSFUL_MIGRATIONS = '_yaymail_successful_migrations';
    /**
     * @var string $old_version
     * @Example '3.9.9'
     */
    protected $old_version;

    /**
     * @var string $new_version
     * @Example '4.0.0'
     */
    protected $new_version;

    /** @var Logger */
    protected $logger;

    /**
     * @var string $backup_option_name
     */
    protected $backup_option_name;

    /**
     * Addon Namespace, empty means core
     *
     * @var string $addon_namespace
     */
    protected $addon_namespace = '';



    abstract protected function up();

    protected function __construct( $old_version, $new_version, $addon_namespace = '' ) {
        $this->old_version = $old_version;
        $this->new_version = $new_version;

        $this->addon_namespace = $addon_namespace;

        $this->logger = new Logger();
    }

    public function perform_migration( $skip_check_migration = false ) {
        $this->logger->log( "Attempt to migrate data from [$this->old_version] to [$this->new_version]." );

        if ( ! $skip_check_migration && $this->has_migration_been_performed() ) {
            $this->logger->log( 'Migration aborted. Data had been successfully migrated before.' );
            return;
        }

        $this->backup();

        $this->up();

        if ( ! $skip_check_migration ) {
            $this->log_succeeded_migration();
        }
    }


    protected function backup() {
        global $wpdb;

        $all_backups = $this->get_all_backups();

        $prefix                   = self::BACKUP_PREFIX . ( ! empty( $this->addon_namespace ) ? $this->addon_namespace . '_' : '' );
        $this->backup_option_name = $prefix . str_replace( '.', '_', $this->old_version );

        foreach ( $all_backups as $backup_name => $backup ) {
            if ( $backup_name === $this->backup_option_name
            || MigrationHelper::is_within_time_window( $backup['created_date'], 60 ) ) {

                // Set this backup name as the current backup option name
                // In order to let the log_succeeded_migration() know which backup to use
                $this->backup_option_name = $backup_name;

                // No need for new backup
                $this->logger->log( "Back up [$this->backup_option_name] already existed or there is new backup added recently. No more backup needed" );
                return;
            }
        }

        /**
         * Backup posts and postmeta
         */
        $query_posts            = "
            SELECT *
            FROM {$wpdb->posts}
            WHERE post_type = 'yaymail_template'
        ";
        $yaymail_template_posts = $wpdb->get_results( $query_posts );// phpcs:ignore

        $query_postmeta            = "
            SELECT *
            FROM {$wpdb->postmeta}
            WHERE meta_key LIKE '%yaymail%'
        ";
        $yaymail_template_postmeta = $wpdb->get_results( $query_postmeta );// phpcs:ignore

        $backup_data = [
            'posts'    => $yaymail_template_posts,
            'postmeta' => $yaymail_template_postmeta,
        ];
        /** ****************************** */

        /**
         * Backup options
         */
        $query_options          = "
            SELECT *
            FROM {$wpdb->options}
            WHERE option_name LIKE '%yaymail%'
        ";
        $yaymail_options        = $wpdb->get_results( $query_options ); // phpcs:ignore
        $backup_data['options'] = $yaymail_options;

        $backup_data['created_date'] = current_datetime()->format( 'Y-m-d H:i:s' );

        $backup_data = apply_filters( 'yaymail_migration_backup_data', $backup_data );
        /** ****************************** */

        $backup_response = update_option( $this->backup_option_name, $backup_data );

        if ( ! $backup_response ) {
            // When update_option failes, throw exception
            throw new \Exception( 'YayMail failed to save backup option' );
        }
        $this->logger->log( $this->backup_option_name . ' saved!' );
    }

    /**
     * Log succeeded migration to the db
     */
    protected function log_succeeded_migration() {
        $successful_migrations = get_option( self::SUCCESSFUL_MIGRATIONS, [] );

        $current_migration = [
            'created_date' => current_datetime()->format( 'Y-m-d H:i:s' ),
            'from_version' => $this->old_version,
            'to_version'   => $this->new_version,
            'backup_name'  => $this->backup_option_name,
        ];

        if ( ! empty( $this->addon_namespace ) ) {
            $current_migration['addon_namespace'] = $this->addon_namespace;
        }

        array_unshift( $successful_migrations, $current_migration );

        update_option( self::SUCCESSFUL_MIGRATIONS, $successful_migrations );
    }

    protected function has_migration_been_performed() {
        $successful_migrations = get_option( self::SUCCESSFUL_MIGRATIONS, [] );

        foreach ( $successful_migrations as $migration ) {

            if ( version_compare( $migration['to_version'], $this->new_version, '<' ) ) {
                continue;
            }

            if ( empty( $this->addon_namespace ) ) {
                if ( empty( $migration['addon_namespace'] ) ) {
                    return true;
                }
            } elseif ( $migration['addon_namespace'] === $this->addon_namespace ) {
                return true;
            }
        }

        return false;
    }

    protected function get_all_backups() {
        global $wpdb;

        $options           = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( self::BACKUP_PREFIX ) . '%'
            ),
            ARRAY_A
        );
        $formatted_options = [];
        foreach ( $options as $option ) {
            $formatted_options[ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
        }
        return $formatted_options;
    }

    protected function may_mark_template_as_v4_supported( string $template_post_id, $migration_manager = null ): void {
        if ( ! isset( $migration_manager ) ) {
            $supported_template_ids = SupportedPlugins::get_instance()->get_template_ids_from_core();
        } else {
            $supported_template_ids = $migration_manager->get_supported_template_ids();
        }

        if ( empty( $template_post_id ) || empty( $supported_template_ids ) ) {
            return;
        }

        $template_name = get_post_meta( $template_post_id, YayMailTemplate::META_KEYS['name'], true );

        if ( in_array( $template_name, $supported_template_ids, true ) ) {
            update_post_meta( $template_post_id, YayMailTemplate::META_KEYS['is_v4_supported'], true );
        }
    }
}
