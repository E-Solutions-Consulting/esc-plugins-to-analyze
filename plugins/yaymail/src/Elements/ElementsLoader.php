<?php

namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Shortcodes\ShortcodesExecutor;
use YayMail\Utils\SingletonTrait;
use YayMail\Utils\TemplateHelpers;

/**
 * Email Loader Class
 *
 * @method static ElementsLoader get_instance()
 */
class ElementsLoader {

    use SingletonTrait;

    private $elements = [];

    private function __construct() {
        $dir = new \DirectoryIterator( YAYMAIL_PLUGIN_PATH . '/src/Elements' );
        foreach ( $dir as $fileinfo ) {
            if ( ! $fileinfo->isDot() ) {
                $file_name  = $fileinfo->getFilename();
                $class_name = basename( $file_name, '.php' );
                $class      = 'YayMail\\Elements\\' . $class_name;
                if ( __CLASS__ === $class || 'ElementsHelper' === $class_name ) {
                    continue;
                }
                if ( class_exists( $class ) ) {
                    $instance = $class::get_instance();
                    $this->register_element( $instance );
                }
            }
        }

        do_action( 'yaymail_register_elements', $this );

        $emails = yaymail_get_emails();

        foreach ( $this->elements as $element ) {
            foreach ( $emails as $email ) {
                if ( $element->is_available_in_email( $email ) ) {
                    $email->register_element( $element );
                }
            }
        }
    }

    public function register_element( $element ) {
        if ( ! ( $element instanceof BaseElement ) ) {
            return;
        }
        $this->elements[] = $element;
    }

    public function get_all() {
        return $this->elements;
    }

    public function get_element_instance_by_type( $type ) {
        foreach ( $this->elements as $element ) {
            if ( $element->get_type() === $type ) {
                return $element;
            }
        }
        return null;
    }

    public static function load_elements( $elements ) {
        $content = [];
        if ( ! is_array( $elements ) ) {
            return [];
        }
        foreach ( $elements as $element ) {
            if ( isset( $element['integration'] ) && '3rd' === $element['integration'] ) {
                $class = 'YayMail\Integrations\\' . $element['type'];
            } elseif ( ! empty( $element['addon_namespace'] ) ) {
                $class = $element['addon_namespace'] . '\\Elements\\' . $element['type'];
            } elseif ( ! empty( $element['caller_class'] ) ) {
                $class = $element['caller_class'];
            } else {
                $class = 'YayMail\Elements\\' . $element['type'];
            }
            if ( ! class_exists( $class ) ) {
                continue;
            }

            $attributes   = isset( $element['attributes'] ) ? $element['attributes'] : [];
            $element_info = $class::get_data( $attributes );

            // Map data to get the 'default_value' only
            $mapped_data          = array_map(
                function( $attribute ) {
                    // If the attribute has default value, get the default value
                    // Else leave the whole value as is
                    return $attribute['default_value'] ?? $attribute;
                },
                $element_info['data']
            );
            $element_info['data'] = $mapped_data;

            // Remove unneeded attributes
            $unneeded_attributes = [ 'icon', 'group', 'position' ];
            foreach ( $unneeded_attributes as $attribute ) {
                unset( $element_info[ $attribute ] );
            }

            $content[] = $element_info;
        }//end foreach
        return $content;
    }

    /**
     * Render list elements
     *
     * @param $args includes
     * $render_data
     * $template
     * $settings
     * $is_nested
     * ...
     */
    public static function render_elements( $elements, $args ) {
        $is_nested     = isset( $args['is_nested'] ) ? $args['is_nested'] : false;
        $is_horizontal = isset( $args['is_horizontal'] ) ? $args['is_horizontal'] : false;
        $template_name = $args['template']->get_name();

        $shortcodes = yaymail_get_email_shortcodes( $template_name );

        // if ( ! empty( $args['render_data']['order'] ) ) {
        // $order = $args['render_data']['order'];
        // if ( $order instanceof \WC_Order ) {
        // $order_id = $order->get_id();
        // } elseif ( is_numeric( $order ) ) {
        // $order_id = $order;
        // } else {
        // $order_id = null;
        // }
        // $shortcodes = apply_filters( 'yaymail_extra_shortcodes', $shortcodes, $template_name, $order_id );
        // }

        $args = apply_filters( 'yaymail_template_rendering_args', $args, $template_name, $elements );

        foreach ( $elements as $element ) {
            if ( empty( $element['available'] ) ) {
                continue;
            }

            if ( ! apply_filters( 'yaymail_validate_element_before_sending', true, $element, $args ) ) {
                continue;
            }

            $args['element'] = $element;

            new ShortcodesExecutor( $shortcodes, $args );

            $element_instance = yaymail_get_element( $element['type'] );

            $layout = '';
            if ( $element_instance ) {
                $layout = $element_instance->get_layout( $element, $args );
            }

            $layout = TemplateHelpers::remove_empty_shortcodes( $layout );

            /**
             * Render Column content
             */
            if ( 'column' === $element['type'] || $is_nested ) {
                yaymail_kses_post_e( $layout );
                continue;
            }

            /**
             * Render Container content
             */
            if ( $is_horizontal ) {
                $width = count( $elements ) > 1 ? round( 100 / count( $elements ), 2 ) : 100;
                ?>
                <td style="padding: 0; width: <?php echo esc_attr( $width ); ?>%; max-width: <?php echo esc_attr( $width ); ?>%;padding-left: 0;padding-right: 0;"><?php yaymail_kses_post_e( $layout ); ?></td>
                <?php
                continue;
            }
            ?>
            <tr>
                <td style="padding: 0;"><?php yaymail_kses_post_e( $layout ); ?></td>
            </tr>
            <?php
        }//end foreach
    }
}
