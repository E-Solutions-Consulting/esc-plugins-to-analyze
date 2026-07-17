<?php
namespace YayMail;

use YayMail\Elements\ElementsLoader;
use YayMail\Emails\EmailsLoader;
use YayMail\Engine\ActDeact;
use YayMail\Engine\Backend\SettingsPage;
use YayMail\Engine\RestAPI;
use YayMail\Integrations\IntegrationsLoader;
use YayMail\PostTypes\TemplatePostType;
use YayMail\Shortcodes\ShortcodesLoader;
use YayMail\Utils\SingletonTrait;
use YayMail\TemplatePatterns\PatternsLoader;
use YayMail\TemplatePatterns\SectionTemplatesLoader;
use YayMail\PreviewEmail\PreviewEmailsLoader;
use YayMail\Notices\NoticeMain;
use YayMail\TemplateLibrary\LibraryTemplateSchema;
use YayMail\TemplateLibrary\TemplateLibraryLoader;
/**
 * YayMail Plugin Initializer
 *
 * @method static Initialize get_instance()
 */
class Initialize {

    use SingletonTrait;

    /**
     * The Constructor that load the engine classes
     */
    protected function __construct() {
        I18n::get_instance();

        /**
         * Handle init core
         * Emails, Templates, Elements, Shortcodes, Integrations
         * $hook_name => $priority
         */
        $initialization_core_hooks = [
            apply_filters( 'yaymail_temp_init_hook_name', 'init' ) => 10,
            // Integrate for Mastercard Gateway plugin
            'woocommerce_api_mastercard_gateway' => 10,
        ];
        foreach ( $initialization_core_hooks as $hook => $priority ) {
            add_action( $hook, [ $this, 'init_core' ], $priority ?? 10 );
        }

        add_action( 'init', [ $this, 'init_modules' ] );
    }

    public function init_core() {
        require_once YAYMAIL_PLUGIN_PATH . 'src/Functions.php';
        do_action( 'yaymail_init_start' );

        /**
         * Core Integrations
         */
        IntegrationsLoader::get_instance();

        EmailsLoader::get_instance();
        ElementsLoader::get_instance();
        ShortcodesLoader::get_instance();
    }

    public function init_modules() {

        $version_current        = yaymail_version();
        $version_old            = get_option( 'yaymail_version' );
        $version_current_backup = get_option( 'yaymail_version_backup' );

        if ( $version_current !== $version_old && yaymail_is_wc_installed() ) {
            if ( $version_current_backup !== $version_current ) {
                \YayMail\Migrations\MainMigration::get_instance()->migrate();

                update_option( 'yaymail_version', $version_current );
                update_option( 'yaymail_version_backup', $version_current );
            }
        }

        ActDeact::get_instance();

        if ( yaymail_is_wc_installed() ) {
            WooHandler::get_instance();
        }

        /**
         * Preview Email loader
         */

        PreviewEmailsLoader::get_instance();

        /**
         * Supported templates
         */
        SupportedPlugins::get_instance();

        /**
         * Core core filters
         */

        SectionTemplatesLoader::get_instance();
        PatternsLoader::get_instance();
        TemplateLibraryLoader::get_instance();
        LibraryTemplateSchema::maybe_create_table();

        /**
         * Initialize rest api
         */
        RestAPI::get_instance();

        /**
         * Initialize pages
         */
        SettingsPage::get_instance();

        TemplatePostType::get_instance();
        Ajax::get_instance();

        /**
         * Notices
         */
        NoticeMain::get_instance();

        do_action( 'yaymail_loaded' );
    }
}