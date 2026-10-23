<?php
/**
 * Adds a product-specific condition to Elementor Pro single product templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'elementor/theme/register_conditions', function( $conditions_manager ) {
    if ( ! class_exists( 'ElementorPro\\Modules\\ThemeBuilder\\Conditions\\Condition_Base' ) ) {
        return;
    }

    $product_condition = $conditions_manager->get_condition( 'product' );
    if ( ! $product_condition || $conditions_manager->get_condition( 'specific_product' ) ) {
        return;
    }

    $product_condition->register_sub_condition( new class extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {
        public static function get_type() {
            return 'singular';
        }

        /**
         * Lower wins. Tag/category sub-conditions resolve to 40 - 20 = 20, so a
         * specific product must sit below that or a tag template can shadow it.
         */
        public static function get_priority() {
            return 30;
        }

        public function get_name() {
            return 'specific_product';
        }

        public function get_label() {
            return esc_html__( 'Specific Product', 'bh-features' );
        }

        public function get_all_label() {
            return esc_html__( 'Specific Products', 'bh-features' );
        }

        public function check( $args ) {
            return isset( $args['id'] )
                && is_singular( 'product' )
                && get_queried_object_id() === (int) $args['id'];
        }

        protected function register_controls() {
            $this->add_control(
                'product_id',
                [
                    'section' => 'settings',
                    'type' => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
                    'select2options' => [
                        'dropdownCssClass' => 'elementor-conditions-select2-dropdown',
                    ],
                    'autocomplete' => [
                        'object' => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_POST,
                        'query' => [
                            'post_type' => 'product',
                        ],
                    ],
                ]
            );
        }
    } );
}, 20 );
