<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Global settings page (Brello > Settings).
 *
 * Holds simple site-wide values (texts, later toggles) that modules read with
 * BH_Settings::get(). A module adds its own fields with register_section() /
 * register_field() while it loads; modules with complex admin UIs keep their
 * own pages.
 *
 * All values live in a single option (bh_settings), keyed by field key.
 */
class BH_Settings {

    const OPTION = 'bh_settings';
    const GROUP  = 'bh_settings';

    private static $sections = [];
    private static $fields   = [];

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_ui' ] );
    }

    /**
     * Shared admin UI styles (e.g. the on/off switch) used by every module's
     * settings screen, so each page doesn't carry its own inline copy.
     */
    public static function enqueue_admin_ui() {
        wp_enqueue_style(
            'bh-admin-ui',
            plugins_url( 'assets/css/admin-ui.css', __FILE__ ),
            [],
            defined( 'BH_FEATURES_VERSION' ) ? BH_FEATURES_VERSION : '1.0.0'
        );
    }

    public static function register_section( $id, $title, $description = '' ) {
        self::$sections[ $id ] = [
            'title'       => $title,
            'description' => $description,
        ];
    }

    /**
     * @param string $key  Unique key, stored as bh_settings[$key].
     * @param array  $args section, label, default, description, allowed_html
     *                     (tag => attributes, as accepted by wp_kses; empty = plain text).
     */
    public static function register_field( $key, array $args ) {
        $field = wp_parse_args( $args, [
            'section'      => 'general',
            'label'        => $key,
            'default'      => '',
            'description'  => '',
            'allowed_html' => [],
        ] );

        if ( ! isset( self::$sections[ $field['section'] ] ) ) {
            self::register_section( $field['section'], ucfirst( $field['section'] ) );
        }

        self::$fields[ $key ] = $field;
    }

    /**
     * Saved value, or the field default when nothing (or only whitespace) is saved.
     */
    public static function get( $key ) {
        $default = isset( self::$fields[ $key ] ) ? self::$fields[ $key ]['default'] : '';
        $all     = get_option( self::OPTION, [] );
        $value   = ( is_array( $all ) && isset( $all[ $key ] ) ) ? trim( (string) $all[ $key ] ) : '';

        return '' === $value ? $default : $value;
    }

    /**
     * Same as get(), filtered through the field's allowed HTML. Use this for
     * output, so a dirty value already in the database can never reach a page.
     */
    public static function get_html( $key ) {
        $allowed = isset( self::$fields[ $key ] ) ? self::$fields[ $key ]['allowed_html'] : [];

        return wp_kses( self::get( $key ), $allowed );
    }

    /**
     * Only fields registered by loaded modules are updated; values saved for
     * fields whose module isn't loaded are kept as they are.
     */
    public static function sanitize( $input ) {
        $old   = get_option( self::OPTION, [] );
        $out   = is_array( $old ) ? $old : [];
        $input = is_array( $input ) ? $input : [];

        foreach ( self::$fields as $key => $field ) {
            if ( array_key_exists( $key, $input ) ) {
                $out[ $key ] = trim( wp_kses( (string) $input[ $key ], $field['allowed_html'] ) );
            }
        }

        return $out;
    }

    public static function register_setting() {
        register_setting( self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default'           => [],
        ] );
    }

    public static function register_menu() {
        $parent = defined( 'PARENT_MENU_SLUG' ) ? PARENT_MENU_SLUG : 'bh-features';

        add_submenu_page(
            $parent,
            'Settings',
            'Settings',
            'manage_options',
            $parent . '--settings',
            [ __CLASS__, 'render_page' ],
            0 // Position 0: first item in the Brello submenu.
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <?php settings_errors(); ?>

            <form action="options.php" method="POST">
                <?php settings_fields( self::GROUP ); ?>

                <?php foreach ( self::$sections as $section_id => $section ) : ?>
                    <?php
                    $section_fields = array_filter( self::$fields, function ( $field ) use ( $section_id ) {
                        return $field['section'] === $section_id;
                    } );
                    if ( ! $section_fields ) {
                        continue;
                    }
                    ?>
                    <h2><?php echo esc_html( $section['title'] ); ?></h2>
                    <?php if ( $section['description'] ) : ?>
                        <p><?php echo esc_html( $section['description'] ); ?></p>
                    <?php endif; ?>

                    <table class="form-table" role="presentation">
                        <?php foreach ( $section_fields as $key => $field ) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="bh-setting-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                </th>
                                <td>
                                    <input type="text" class="large-text"
                                           id="bh-setting-<?php echo esc_attr( $key ); ?>"
                                           name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
                                           value="<?php echo esc_attr( self::get( $key ) ); ?>">
                                    <?php if ( $field['description'] ) : ?>
                                        <p class="description"><?php echo wp_kses( $field['description'], [ 'code' => [] ] ); ?></p>
                                    <?php endif; ?>
                                    <p class="description">
                                        Default: <code><?php echo esc_html( $field['default'] ); ?></code>. Leave empty to use the default.
                                        <?php if ( $field['allowed_html'] ) : ?>
                                            Allowed HTML: <code><?php echo esc_html( implode( ', ', array_keys( $field['allowed_html'] ) ) ); ?></code>.
                                        <?php else : ?>
                                            Plain text only.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

BH_Settings::init();
