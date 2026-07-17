<?php
namespace YayMail\Models;

use YayMail\Migrations\AbstractMigration;
use YayMail\Utils\SingletonTrait;
use YayMail\Utils\Logger;
use YayMail\Migrations\MainMigration;
use YayMail\Migrations\MigrationHelper;

/**
 * Migration Model
 *
 * @method static MigrationModel get_instance()
 */
class MigrationModel {
    use SingletonTrait;

    /** @var Logger */
    private $logger;

    private function __construct() {
        $this->logger = new Logger();
    }

    /**
     * Safely unserialize values coming from backup/import data.
     *
     * @param mixed $value The value to unserialize.
     * @return mixed
     */
    private function safe_maybe_unserialize( $value ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( ! function_exists( 'is_serialized' ) || ! is_serialized( $value ) ) {
            return $value;
        }

        $unserialized = @unserialize( $value, [ 'allowed_classes' => false ] );

        if ( false === $unserialized && 'b:0;' !== $value ) {
            return $value;
        }

        if ( is_object( $unserialized ) ) {
            return $value;
        }

        return $unserialized;
    }

    public function get_onload_data() {
        return [
            'required_migrations' => $this->get_required_migration_names(),
            'backups'             => $this->get_backups(),
        ];
    }

    public function migrate() {
        $is_migration_succeeded = MainMigration::get_instance()->migrate();
        if ( ! $is_migration_succeeded ) {
            throw new \Exception( esc_html__( 'Migration failed, please contact our Customer Support for help!', 'yaymail' ), 500 );
        }
        return [ 'is_critical_migration_required' => $this->check_if_critical_migration_required() ];
    }

    /**
     * Restore backup to a specific version
     *
     * @param array $backup Backup data containing posts, postmeta, options, metadata, and version.
     *
     * @return array Returns array with 'is_critical_migration_required' boolean flag.
     * @throws \Exception When backup doesn't exist or restoration fails.
     */
    public function reset( $backup ) {
        global $wpdb;

        if ( ! $backup || ! $backup['version'] || ! $backup['name'] ) {
            throw new \Exception( 'Back up does not exist', 500 );
        }

        try {
            $backup_name = $backup['name'];
            $version     = $backup['version'];

            $wpdb->query( 'START TRANSACTION' );
            $this->logger->log( '***** Start reset transaction ****' );
            $this->logger->log( "Backup name: $backup_name" );

            // Get IDs of posts to be deleted
            $post_ids_to_delete = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
                    'yaymail_template'
                )
            );
            if ( $post_ids_to_delete ) {
                // Delete current post meta data
                $delete_postmeta_query = sprintf(
                    "DELETE FROM {$wpdb->postmeta} WHERE post_id IN (%s)",
                    implode( ',', array_map( 'intval', $post_ids_to_delete ) )
                );
                $this->logger->log( $delete_postmeta_query );
                $wpdb->query( $delete_postmeta_query );// phpcs:ignore

                // Delete the posts
                $delete_template_posts_query = sprintf(
                    "DELETE FROM {$wpdb->posts} WHERE ID IN (%s)",
                    implode( ',', array_map( 'intval', $post_ids_to_delete ) )
                );
                $this->logger->log( $delete_template_posts_query );
                $wpdb->query( $delete_template_posts_query );// phpcs:ignore
            }

            $delete_template_posts_query = $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} WHERE post_type = %s",
                'yaymail_template'
            );
            $this->logger->log( $delete_template_posts_query );
            $wpdb->query( $delete_template_posts_query );// phpcs:ignore

            // Restore the backed-up posts
            $backed_up_posts    = $backup['posts'];
            $backed_up_postmeta = $backup['postmeta'];
            foreach ( $backed_up_posts as $post ) {
                if ( ! isset( $post->post_type ) || 'yaymail_template' !== $post->post_type ) {
                    continue;
                }
                $wpdb->insert(
                    $wpdb->posts,
                    [
                        'post_author'           => $post->post_author,
                        'post_date'             => $post->post_date,
                        'post_date_gmt'         => $post->post_date_gmt,
                        'post_content'          => $post->post_content,
                        'post_title'            => $post->post_title,
                        'post_excerpt'          => $post->post_excerpt,
                        'post_status'           => $post->post_status,
                        'comment_status'        => $post->comment_status,
                        'ping_status'           => $post->ping_status,
                        'post_password'         => $post->post_password,
                        'post_name'             => $post->post_name,
                        'to_ping'               => $post->to_ping,
                        'pinged'                => $post->pinged,
                        'post_modified'         => $post->post_modified,
                        'post_modified_gmt'     => $post->post_modified_gmt,
                        'post_content_filtered' => $post->post_content_filtered,
                        'post_parent'           => $post->post_parent,
                        'guid'                  => $post->guid,
                        'menu_order'            => $post->menu_order,
                        'post_type'             => $post->post_type,
                        'post_mime_type'        => $post->post_mime_type,
                        'comment_count'         => $post->comment_count,
                    ]
                );

                // Insert post meta data
                $inserted_post_id           = $wpdb->insert_id;
                $postmetas_for_current_post = array_filter(
                    $backed_up_postmeta,
                    function( $postmeta ) use ( $post ) {
                        return $postmeta->post_id === $post->ID;
                    }
                );
                if ( ! empty( $postmetas_for_current_post ) ) {
                    foreach ( $postmetas_for_current_post as $postmeta ) {
                        if ( ! isset( $postmeta->meta_key ) || strpos( (string) $postmeta->meta_key, 'yaymail' ) === false ) {
                            continue;
                        }
                        add_post_meta(
                            $inserted_post_id,
                            $postmeta->meta_key,
                            $this->safe_maybe_unserialize( $postmeta->meta_value )
                        );
                    }
                }
            }//end foreach

            // Restore backed-up options
            foreach ( $backup['options'] as $option ) {
                // Only restore options that belong to YayMail (same pattern as export)
                if ( strpos( $option->option_name, 'yaymail' ) !== false ) {
                    update_option( $option->option_name, $this->safe_maybe_unserialize( $option->option_value ) );
                }
            }

            // Remove the succeeded migration log from db
            $successful_migrations = get_option( AbstractMigration::SUCCESSFUL_MIGRATIONS, null );
            $removed_migrations    = [];
            if ( ! empty( $successful_migrations ) ) {
                $filtered_successful_migrations = array_filter(
                    $successful_migrations,
                    function( $migration ) use ( $version, $backup ) {
                        $backup_date    = new \DateTime( $backup['created_date'], wp_timezone() );
                        $migration_date = new \DateTime( $migration['created_date'], wp_timezone() );
                        $should_remove  = $migration_date >= $backup_date;

                        return ! $should_remove;
                    }
                );

                update_option( AbstractMigration::SUCCESSFUL_MIGRATIONS, $filtered_successful_migrations );
                $removed_migrations = array_udiff(
                    $successful_migrations,
                    $filtered_successful_migrations,
                    function( $a, $b ) {
                        return strcmp( serialize( $a ), serialize( $b ) );
                    }
                );
            }//end if

            // Restore core version
            update_option( 'yaymail_version', $version );
            wp_cache_delete( 'yaymail_version', 'options' );

            // Restore addon versions
            $addon_versions          = get_option( 'yaymail_addon_versions', [] );
            $restored_addon_versions = array_filter(
                $addon_versions,
                function( $addon_version, $addon_namespace ) use ( $removed_migrations ) {
                    foreach ( $removed_migrations as $migration ) {
                        if ( ! empty( $migration['addon_namespace'] ) && $migration['addon_namespace'] === $addon_namespace && version_compare( $addon_version, $migration['to_version'], '<=' ) ) {
                            return false;
                        }
                    }
                    return true;
                },
                ARRAY_FILTER_USE_BOTH
            );
            update_option( 'yaymail_addon_versions', $restored_addon_versions );

            do_action( 'yaymail_before_reset_migration_commit', $backup, $removed_migrations );

            $wpdb->query( 'COMMIT' );
            $this->logger->log( '***** Finish reset transaction ****' );
            $this->logger->log( "$backup_name restored successfully!" );

            return [ 'is_critical_migration_required' => $this->check_if_critical_migration_required() ];

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->logger->log( '***** Transaction rolled back. ' . $e->getMessage() );
            throw new \Exception( esc_html__( 'Restoration failed', 'yaymail' ), 500 );
        }//end try
    }

    public function check_if_critical_migration_required() {
        $old_version = get_option( 'yaymail_version' );
        if ( $old_version ) {
            return version_compare( $old_version, '4.0.0', '<' );
        }
        return false;
    }

    private function get_required_migration_names() {
        $old_version           = MigrationHelper::format_version_number( get_option( 'yaymail_version' ) );
        $new_version           = MigrationHelper::format_version_number( yaymail_version() );
        $successful_migrations = get_option( AbstractMigration::SUCCESSFUL_MIGRATIONS, [] );

        // If freshly installed or already on latest version with no migrations needed
        if ( ( version_compare( $old_version, $new_version, '>=' ) && empty( $successful_migrations ) ) ) {
            return apply_filters( 'yaymail_required_migration_names', [] );
        }

        $args = [
            'post_type'      => 'yaymail_template',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query( $args );

        $has_yaymail_template = $query->have_posts();
        if ( ( $old_version == null && ! $has_yaymail_template ) && empty( $successful_migrations ) ) {
            return [];
        }

        // Check if any migrations have already been run up to current version
        foreach ( $successful_migrations as $migration ) {
            if ( empty( $migration['addon_namespace'] ) && version_compare( $migration['to_version'], yaymail_version(), '>=' ) ) {
                return apply_filters( 'yaymail_required_migration_names', [] );
            }
        }

        // Get available migrations that need to be run
        $core_migrations     = MainMigration::CORE_MIGRATIONS;
        $filtered_migrations = MigrationHelper::filter_migrations( $core_migrations, $old_version, $new_version );

        $needed_migrations = empty( $filtered_migrations ) ? [] : [ 'YayMail' ];

        return apply_filters( 'yaymail_required_migration_names', $needed_migrations );
    }

    private function get_backups() {
        global $wpdb;

        $backup_prefix = AbstractMigration::BACKUP_PREFIX;

        $backup_options_query = $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            $backup_prefix . '%'
        );
        $backups_result       = $wpdb->get_results( $backup_options_query );// phpcs:ignore

        $backups = [];
        foreach ( $backups_result as $backup ) {
            $data      = $this->safe_maybe_unserialize( $backup->option_value );
            $backups[] = [
                'created_date' => $data['created_date'] ?? 'created_date not found',
                'name'         => $backup->option_name ?? 'backup name not found',
            ];
        }

        return $backups;
    }
}
