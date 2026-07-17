<?php
namespace YayMail\Migrations;

use YayMail\Utils\Logger;
use YayMail\SupportedPlugins;
use YayMail\YayMailTemplate;


/**
 * AbstractAddonMigrationManager
 */
abstract class AbstractAddonMigrationManager {

    /**
     * @var string $old_version
     * @Example '3.9.9'
     */
    protected $old_version;

    /**
     * @var string $default_legacy_version
     * @Example '3.9.9'
     */
    protected $default_legacy_version;

    /**
     * @var string $new_version
     * @Example '4.0.0'
     */
    protected $new_version;


    /** @var Logger */
    protected $logger;

    /**
     * Addon Namespace, empty means core
     *
     * @var string $addon_namespace
     */
    protected $addon_namespace = '';

    /**
     * Addon name, empty means core
     *
     * @var string $addon_name
     */
    protected $addon_name = '';

    /**
     * Available migrations
     *
     * @var array $addon_migrations
     */
    protected $addon_migrations = [];

    /**
     * @var SupportedPlugins $supported_plugins_instance
     */
    protected $supported_plugins_instance;

    /**
     * @var array $supported_template_ids
     */
    protected $supported_template_ids;

    /**
     * Migration manager's constructor
     *
     * @param string $old_version
     * @param string $new_version
     * @param string $addon_namespace
     * @param string $addon_name
     */
    protected function __construct( $old_version, $new_version, $addon_namespace, $addon_name ) {
        $this->old_version = $old_version;
        $this->new_version = $new_version;

        $this->addon_namespace = $addon_namespace;
        $this->addon_name      = $addon_name;

        $this->logger = new Logger();

        $this->supported_plugins_instance = SupportedPlugins::get_instance();

        $this->supported_template_ids = $this->supported_plugins_instance->get_addon_supported_template_ids( $addon_namespace );

        $this->init_migrations();
        $this->init_hooks();
    }

    protected function init_hooks() {
        add_action( 'yaymail_run_addon_migrations', [ $this, 'execute_migrations' ] );
        add_filter( 'yaymail_required_migration_names', [ $this, 'check_if_addon_migration_needed' ] );
        add_filter( 'yaymail_migration_backup_data', [ $this, 'add_additional_backup_data' ] );
    }

    abstract protected function declare_migrations();

    public function execute_migrations() {
        $this->logger->log( "*Starting migrations for {$this->addon_namespace}*" );

        if ( empty( $this->addon_migrations ) ) {
            $this->logger->log( "No migrations available for {$this->addon_namespace}" );
        } else {
            MigrationHelper::perform_migrations( $this->addon_migrations );

            // After migrations succeeded, update addon version
            $addon_versions                           = get_option( 'yaymail_addon_versions', [] );
            $addon_versions[ $this->addon_namespace ] = $this->new_version;
            update_option( 'yaymail_addon_versions', $addon_versions );
        }

        $this->logger->log( "*Finished migrations for {$this->addon_namespace}*" );
    }

    public function check_if_addon_migration_needed( $needed_migrations ) {

        if ( empty( $this->addon_migrations ) ) {
            return $needed_migrations;
        }

        $successful_migrations = get_option( '_yaymail_successful_migrations', [] );

        foreach ( $successful_migrations as $successful_migration ) {

            if ( isset( $successful_migration['addon_namespace'] )
            && $successful_migration['addon_namespace'] === $this->addon_namespace
            && version_compare( $successful_migration['to_version'], $this->new_version, '>=' ) ) {

                return $needed_migrations;
            }
        }

        $needed_migrations[] = $this->addon_name;

        return $needed_migrations;
    }

    // Add addon version to backup data if addon version does not exist (before 4.0.0)
    public function add_additional_backup_data( $backup_data ) {
        $options = $backup_data['options'];

        $yaymail_addon_versions_option = null;

        foreach ( $options as &$option ) {
            if ( $option->option_name === 'yaymail_addon_versions' ) {
                $yaymail_addon_versions_option = &$option;
                break;
            }
        }

        // If the option is found
        if ( $yaymail_addon_versions_option ) {
            $versions = maybe_unserialize( $yaymail_addon_versions_option->option_value );

            // If the unserialized value is an array
            if ( is_array( $versions ) ) {
                // Add or update the addon version
                $versions[ $this->addon_namespace ] = $versions[ $this->addon_namespace ] ?? $this->default_legacy_version;

                // Serialize and update the option value
                $yaymail_addon_versions_option->option_value = maybe_serialize( $versions );
            } else {
                throw new \Error( esc_html( "Error unserializing {$this->addon_namespace} version data" ), 500 );
            }
        } else {
            // If 'yaymail_addon_versions' option does not exist, create it
            $options[] = (object) [
                'option_name'  => 'yaymail_addon_versions',
                'option_value' => maybe_serialize( [ $this->addon_namespace => $this->default_legacy_version ] ),
            ];
        }

        // Update the backup_data with the modified options
        $backup_data['options'] = $options;

        return $backup_data;
    }


    private function init_migrations() {
        $this->declare_migrations();
        $this->filter_migrations();
    }

    protected function filter_migrations() {
        $this->addon_migrations = MigrationHelper::filter_migrations( $this->addon_migrations, $this->old_version, $this->new_version );
        $this->filter_migrations_by_v4_supported_status();
    }

    /**
     * Filter migrations by the supported status and a specific version threshold.
     *
     * @param string $version_threshold The first addon version on newcore V4.
     */
    protected function filter_migrations_by_version_threshold( string $version_threshold ): void {
        $is_v4_supported = $this->is_v4_supported();

        $this->addon_migrations = array_filter(
            $this->addon_migrations,
            function ( $ver ) use ( $is_v4_supported, $version_threshold ) {
                return ! ( version_compare( $ver, $version_threshold, '<=' ) && $is_v4_supported );
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Filter migrations pre-4.0.0 if the addon is V4-supported.
     */
    protected function filter_migrations_by_v4_supported_status(): void {
        $this->filter_migrations_by_version_threshold( '2.0.0' );
        // Version should be replaced in implementation classes
    }

    /**
     * Check if the current add-on is v4-supported
     *
     * @return bool
     */
    public function is_v4_supported() {
        foreach ( $this->supported_template_ids as $template_id ) {
            $args = [
                'post_type'   => 'yaymail_template',
                'meta_key'    => YayMailTemplate::META_KEYS['name'],
                'meta_value'  => $template_id,
                'fields'      => 'ids',
                'numberposts' => -1,
                'post_status' => [ 'publish', 'pending', 'future' ],
            ];

            $post_ids = get_posts( $args );
            foreach ( $post_ids as $post_id ) {
                $is_v4_supported = get_post_meta( $post_id, YayMailTemplate::META_KEYS['is_v4_supported'], true );
                if ( ! $is_v4_supported ) {
                    return false;
                }
            }
        }//end foreach
        return true;
    }

    public function get_supported_template_ids() {
        return $this->supported_template_ids;
    }
}
