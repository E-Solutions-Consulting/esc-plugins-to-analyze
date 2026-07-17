<?php
namespace YayMail;

use YayMail\Elements\ElementsHelper;
use YayMail\Models\TemplateModel;
use YayMail\Utils\Helpers;
use YayMail\Utils\TemplateHelpers;
use YayMail\Utils\TemplateRenderer;
use YayMail\Utils\StyleInline;

/**
 * YayMail Template
 */
class YayMailTemplate {

    /**
     * TemplateModel
     *
     * @var TemplateModel
     */
    private $model = null;

    /**
     * Contains template id
     *
     * @var number
     */
    private $id = 0;

    public $renderer = null;

    public const META_KEYS = [
        'name'                     => '_yaymail_template',
        'elements'                 => '_yaymail_elements',
        'status'                   => '_yaymail_status',
        'background_color'         => '_yaymail_email_backgroundColor_settings',
        'text_link_color'          => '_yaymail_email_textLinkColor_settings',
        'content_background_color' => '_yaymail_email_content_background_color',
        'content_text_color'       => '_yaymail_email_content_text_color',
        'title_color'              => '_yaymail_email_title_color',
        'language'                 => '_yaymail_template_language',
        'modified_by'              => '_yaymail_modified_by',
        'is_v4_supported'          => '_yaymail_is_v4_supported',
        'global_header_settings'   => '_yaymail_global_header_settings',
        'global_footer_settings'   => '_yaymail_global_footer_settings',
        'preheader'                => '_yaymail_preheader',
    ];

    public const DEFAULT_DATA = [
        'name'                     => '',
        'elements'                 => [],
        'status'                   => 0,
        'background_color'         => '',
        'text_link_color'          => '',
        'content_background_color' => '',
        'content_text_color'       => '',
        'title_color'              => '',
        'language'                 => '',
        'modified_by'              => '',
        'is_v4_supported'          => false,
        'attachments'              => [],
        'preheader'                => '',
        'global_header_settings'   => [
            'content_override' => false,
            'heading_content'  => '<h1 style="font-size: 30px; font-weight: 300; line-height: normal; margin: 0px; color: inherit;">Hello YayMail</h1>',
            'hidden'           => false,
        ],
        'global_footer_settings'   => [
            'content_override' => false,
            'footer_content'   => '<p style="font-size: 14px; margin: 0px 0px 16px; text-align: center;">[yaymail_site_name] - Built with <a style="color: #873eff; font-weight: normal; text-decoration: underline;" href="https://woocommerce.com" target="_blank" rel="noopener">WooCommerce</a></p>',
            'hidden'           => false,
        ],
    ];

    /**
     * Contains template data
     */
    private $data = [
        'name'                   => self::DEFAULT_DATA['name'],
        'elements'               => self::DEFAULT_DATA['elements'],
        'status'                 => self::DEFAULT_DATA['status'],
        'background_color'       => self::DEFAULT_DATA['background_color'],
        'text_link_color'        => self::DEFAULT_DATA['text_link_color'],
        'language'               => self::DEFAULT_DATA['language'],
        'title_color'            => self::DEFAULT_DATA['title_color'],
        'attachments'            => self::DEFAULT_DATA['attachments'],
        'preheader'              => self::DEFAULT_DATA['preheader'],
        'global_header_settings' => self::DEFAULT_DATA['global_header_settings'],
        'global_footer_settings' => self::DEFAULT_DATA['global_footer_settings'],
    ];

    public function __construct( $template_name = '', $language = '' ) {

        $this->model = TemplateModel::get_instance();

        if ( is_string( $template_name ) && ! empty( $template_name ) ) {
            $template_data = $this->model::find_by_name( $template_name, $language );
            if ( empty( $template_data['id'] ) && SupportedPlugins::get_instance()->get_support_info( $template_name )['status'] === 'already_supported' ) {
                /** Insert new template when not exists */
                $template_data = $this->model::insert(
                    [
                        'name'                     => $template_name,
                        'elements'                 => yaymail_get_default_elements( $template_name ),
                        'language'                 => $language,
                        'background_color'         => self::DEFAULT_DATA['background_color'],
                        'text_link_color'          => self::DEFAULT_DATA['text_link_color'],
                        'content_background_color' => self::DEFAULT_DATA['content_background_color'],
                        'content_text_color'       => self::DEFAULT_DATA['content_text_color'],
                        'title_color'              => self::DEFAULT_DATA['title_color'],
                        'global_header_settings'   => self::DEFAULT_DATA['global_header_settings'],
                        'global_footer_settings'   => self::DEFAULT_DATA['global_footer_settings'],
                    ]
                );
            }
            $this->set_id( $template_data['id'] );
            $this->set_props( $template_data );
            // TODO: Consider filter available elements before pass to props
            $this->renderer = new TemplateRenderer( $this );
        }//end if
    }

    public function is_exists() {
        return is_numeric( $this->id ) && $this->id > 0;
    }

    public function is_enabled() {
        // Check if YayMail core is migrated
        // If not, consider template is not activated
        $old_version = get_option( 'yaymail_version' );
        if ( $old_version && version_compare( $old_version, '4.0.0', '<' ) ) {
            return false;
        }

        return $this->get_status() === 'active';
    }

    // GETTER METHOD

    private function get_prop( $prop, $context = 'view' ) {
        $value = null;

        if ( array_key_exists( $prop, $this->data ) ) {
            $value = $this->data[ $prop ];

            if ( 'view' === $context ) {
                $value = apply_filters( 'yaymail_template_get_' . $prop, $value, $this );
            }
        }

        return $value;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_data() {
        return array_merge(
            [
                'id' => $this->get_id(),
            ],
            $this->data
        );
    }

    public function get_name( $context = 'view' ) {
        return $this->get_prop( 'name', $context );
    }

    public function get_elements( $context = 'view' ) {
        $elements = $this->get_prop( 'elements', $context );
        return ElementsHelper::filter_available_elements( $elements, $this->get_name() );
    }

    public function get_status( $context = 'view' ) {
        $value = $this->get_prop( 'status', $context );
        if ( is_numeric( $value ) || is_bool( $value ) ) {
            $value = empty( $value ) ? 'inactive' : 'active';
            // Process old value
        }
        if ( 'inactve' !== $value && 'active' !== $value ) {
            $value = 'inactive';
        }
        return $value;
    }

    public function get_background_color( $context = 'view' ) {
        $color = $this->get_prop( 'background_color', $context );
        return TemplateHelpers::convert_rgb_to_hex( $color );
    }

    public function get_text_link_color( $context = 'view' ) {
        return $this->get_prop( 'text_link_color', $context );
    }

    public function get_language( $context = 'view' ) {
        return $this->get_prop( 'language', $context );
    }

    public function get_title_color( $context = 'view' ) {
        return $this->get_prop( 'title_color', $context );
    }

    public function get_attachments( $context = 'view' ) {
        return $this->get_prop( 'attachments', $context );
    }

    public function get_preheader( $context = 'view' ) {
        return $this->get_prop( 'preheader', $context );
    }

    /**
     * Get global header
     *
     * @since 4.1.0
     *
     * @param string $context
     * @return array
     */
    public function get_global_header_settings( $context = 'view' ) {
        return $this->get_prop( 'global_header_settings', $context );
    }

    /**
     * Get global header
     *
     * @since 4.1.0
     *
     * @param string $context
     * @return array
     */
    public function get_global_footer_settings( $context = 'view' ) {
        return $this->get_prop( 'global_footer_settings', $context );
    }

    // SETTER METHOD

    public function set_props( $props ) {
        foreach ( $props as $prop_key => $prop_value ) {
            if ( is_null( $prop_value ) ) {
                continue;
            }
            $set_method = "set_$prop_key";
            if ( is_callable( [ $this, $set_method ] ) ) {
                $this->{$set_method}( $prop_value );
            }
        }
    }

    private function set_prop( $prop, $value ) {
        if ( array_key_exists( $prop, $this->data ) ) {
            $this->data[ $prop ] = $value;
        }
    }

    public function set_id( $id ) {
        $this->id = absint( $id );
    }

    public function set_name( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'name', $value );
        }
    }

    public function set_elements( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'elements', $value );
        }
    }

    public function set_status( $value ) {
        if ( ! is_null( $value ) ) {
            if ( is_numeric( $value ) || is_bool( $value ) ) {
                $value = empty( $value ) ? 'inactive' : 'active';
                // Process old value
            }
            if ( 'inactive' === $value || 'active' === $value ) {
                $this->set_prop( 'status', $value );
            }
        }
    }

    public function set_background_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'background_color', $value );
        }
    }

    public function set_text_link_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'text_link_color', $value );
        }
    }

    public function set_language( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'language', $value );
        }
    }

    public function set_preheader( $value ) {
        if ( is_string( $value ) ) {
            $this->set_prop( 'preheader', $value );
        }
    }

    public function set_title_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'title_color', $value );
        }
    }
    public function set_content_background_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'content_background_color', $value );
        }
    }
    public function set_content_text_color( $value ) {
        if ( ! is_null( $value ) && is_string( $value ) ) {
            $this->set_prop( 'content_text_color', $value );
        }
    }


    /**
     * Set global header
     *
     * @since 4.1.0
     *
     * @param array $value
     */
    public function set_global_header_settings( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'global_header_settings', $value );
        }
    }

    /**
     * Set global footer
     *
     * @since 4.1.0
     *
     * @param array $value
     */
    public function set_global_footer_settings( $value ) {
        if ( ! is_null( $value ) && is_array( $value ) ) {
            $this->set_prop( 'global_footer_settings', $value );
        }
    }

    // UPDATE - DELETE METHOD

    public function save() {
        if ( $this->get_id() ) {
            $this->model::update( $this->get_id(), $this->data );
        }
        return $this->get_id();
    }

    public function delete() {
        if ( $this->get_id() ) {
            $this->model::delete( $this->get_id() );
            return true;
        }
        return false;
    }

    public function get_content( $data ) {
        try {
            if ( strpos( $this->get_name(), 'wp-core-' ) === 0 && ! empty( $this->renderer ) ) {
                return $this->renderer->generate_content( $data );
            }

            if ( ! empty( $this->renderer ) ) {
                return StyleInline::get_instance()->convert_style_inline( $this->renderer->generate_content( $data ) );
            }
        } catch ( \Exception $e ) {
            yaymail_get_logger( $e->getMessage() );
        } catch ( \Error $e ) {
            yaymail_get_logger( $e->getMessage() );
        }//end try
        return '';
    }
}