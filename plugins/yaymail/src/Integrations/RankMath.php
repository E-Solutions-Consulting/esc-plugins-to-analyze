<?php

namespace YayMail\Integrations;

use YayMail\PostTypes\TemplatePostType;
use YayMail\Utils\SingletonTrait;

/**
 * RankMath
 * * @method static RankMath get_instance()
 */
class RankMath {
    use SingletonTrait;

    protected function __construct() {

        if ( ! class_exists( 'RankMath' ) ) {
            return;
        }

        add_filter(
            'rank_math/sitemap/exclude_post_type',
            function( $check, $post_type ) {
                if ( $post_type === TemplatePostType::POST_TYPE ) {
                    return true;
                }
                return $check;
            },
            10,
            2
        );

        // Trick to delete all the duplicate value in the previous version of the plugin
        add_action( 'init', [ $this, 'init' ], PHP_INT_MAX );
    }

    public function init() {
        $titles_settings = get_option( 'rank-math-options-titles', [] );
        if ( empty( $titles_settings ) ) {
            return;
        }

        $title_meta_key = 'pt_' . TemplatePostType::POST_TYPE . '_robots';

        if ( empty( $titles_settings[ $title_meta_key ] ) ) {
            $titles_settings[ $title_meta_key ] = [];
        }

        $titles_settings[ $title_meta_key ] = array_unique( $titles_settings[ $title_meta_key ] );

        update_option( 'rank-math-options-titles', $titles_settings );
    }
}
