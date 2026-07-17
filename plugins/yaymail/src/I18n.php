<?php
namespace YayMail;

use YayMail\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;
/**
 * I18n Logic
 *
 * @method static I18n get_instance()
 */
class I18n {

    use SingletonTrait;

    private function __construct() {
        add_action( 'init', [ $this, 'load_plugin_text_domain' ] );
        add_filter( 'yaymail_translations', [ $this, 'get_translations' ] );
    }

    public static function load_plugin_text_domain() {
        if ( function_exists( 'determine_locale' ) ) {
            $locale = determine_locale();
        } else {
            $locale = is_admin() ? get_user_locale() : get_locale();
        }

        unload_textdomain( 'yaymail' );
        load_textdomain( 'yaymail', YAYMAIL_PLUGIN_PATH . 'i18n/languages/yaymail-' . $locale . '.mo' );

        load_plugin_textdomain( 'yaymail', false, YAYMAIL_PLUGIN_PATH . 'i18n/languages/' );
    }

    public function get_translations() {
        $translations = get_translations_for_domain( 'yaymail' );
        $messages     = [];

        $entries = $translations->entries;
        foreach ( $entries as $key => $entry ) {
            $messages[ $entry->singular ] = $entry->translations;
        }

        return [
            'locale_data' => [
                'messages' => $messages,
            ],
        ];
    }
}
