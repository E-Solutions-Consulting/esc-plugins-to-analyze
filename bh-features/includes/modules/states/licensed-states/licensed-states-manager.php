<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Licensed_States_Manager {

    const OPTION_KEY          = 'ah_states_config';
    const BACKUP_DIR_NAME     = 'ah-states';
    const BACKUP_LATEST_FILE  = 'config-latest.json';
    const ADMIN_NOTICE_TRANSIENT = 'ah_states_loaded_from_backup';

    /**
     * Cached configuration for this request.
     *
     * @var array|null
     */
    protected static $config = null;

    /**
     * Default config used when there is no DB / backup.
     *
     * @return array
     */
    protected static function get_default_config() {

        $all_states = AH_States::get_all();

        // Default: mark all states as "unavailable" and manually
        // enable only the ones you want from the admin UI.
        $default_statuses = [];
        foreach ( $all_states as $code => $label ) {
            $default_statuses[ $code ] = 'unavailable';
        }

        return [
            'states_statuses' => $default_statuses,

            // Internal status slugs are short; labels/descriptions are editable via admin.
            'statuses' => [
                'available'   => [
                    'label'       => 'Available',
                    'description' => 'Licensed and available',
                ],
                'in_progress' => [
                    'label'       => 'Available (4-6 Weeks Delay)',
                    'description' => 'Will be available within 4-6 Weeks Delay',
                ],
                'in_progress_a' => [
                    'label'       => 'Available (6-8 Weeks Delay)',
                    'description' => 'Will be available within 6-8 Weeks Delay',
                ],
                'unavailable' => [
                    'label'       => 'Unavailable',
                    'description' => 'Not licensed',
                ],
            ],

            // Visual defaults for the map (can be overridden in admin).
            'visual' => [
                'available' => [
                    'state_color'        => '#1F0159',
                    'state_hover_color'  => '#FAF8A2',
                    'label_color'        => '#FFFFFF',
                    'label_hover_color'  => '#1F0159',
                ],
                'unavailable' => [
                    'state_color'        => '#CCCCCC',
                    'state_hover_color'  => '#999999',
                    'label_color'        => '#666666',
                    'label_hover_color'  => '#333333',
                ],
                'in_progress' => [
                    'state_color'        => '#DED9FC',
                    'state_hover_color'  => '#DED9FC',
                    'label_color'        => '#1F0159',
                    'label_hover_color'  => '#1F0159',
                ],
                'in_progress_a' => [
                    'state_color'        => '#DED9FC',
                    'state_hover_color'  => '#DED9FC',
                    'label_color'        => '#1F0159',
                    'label_hover_color'  => '#1F0159',
                ],
            ],
        ];
    }

    /**
     * Public accessor for the full config.
     *
     * @return array
     */
    public static function get_config() {
        if ( null !== self::$config ) {
            return self::$config;
        }

        self::$config = self::load_config_with_fallback();

        return self::$config;
    }

    /**
     * Return the status slug for a given state code.
     *
     * @param string $state_code
     * @return string One of 'available', 'unavailable', 'in_progress', etc.
     */
    public static function get_state_status( $state_code ) {
        $state_code = strtoupper( trim( $state_code ) );
        $config     = self::get_config();

        if ( isset( $config['states_statuses'][ $state_code ] ) ) {
            return $config['states_statuses'][ $state_code ];
        }

        // Fallback: any undefined state is treated as unavailable.
        return 'unavailable';
    }

    /**
     * Return plain text description for a given state's current status.
     *
     * @param string $state_code
     * @return string
     */
    public static function get_state_description( $state_code ) {

        $state_code = strtoupper( trim( $state_code ) );
        $status_slug = self::get_state_status( $state_code );
        $definitions = self::get_status_definitions();
        if ( isset( $definitions[ $status_slug ]['description'] ) ) {
            // Ensure no HTML is ever returned.
            return wp_strip_all_tags( $definitions[ $status_slug ]['description'] );
        }

        return '';
    }


    /**
     * Whether a given state is currently available (licensed) for business.
     *
     * @param string $state_code
     * @return bool
     */
    public static function is_state_available( $state_code ) {
        return 'available' === self::get_state_status( $state_code );
    }

    /**
     * Return an array of [ 'CA' => 'California', ... ] for states
     * matching the given status.
     *
     * @param string $status_slug
     * @return array
     */
    public static function get_states_by_status( $status_slug ) {
        $status_slug = trim( $status_slug );
        $config      = self::get_config();
        $all_states  = AH_States::get_all();
        $result      = [];

        if ( empty( $config['states_statuses'] ) ) {
            return $result;
        }

        foreach ( $config['states_statuses'] as $code => $state_status ) {
            if ( $state_status === $status_slug && isset( $all_states[ $code ] ) ) {
                $result[ $code ] = $all_states[ $code ];
            }
        }

        return $result;
    }

    /**
     * Get state codes by status slug.
     *
     * @param string $status_slug
     * @return string[] List of state codes (e.g. ['CA', 'TX'])
     */
    public static function get_state_codes_by_status( $status_slug ) {

        $status_slug = trim( (string) $status_slug );
        $config      = self::get_config();
        $result      = [];

        if ( empty( $config['states_statuses'] ) ) {
            return $result;
        }

        foreach ( $config['states_statuses'] as $code => $state_status ) {
            if ( $state_status === $status_slug ) {
                $result[] = $code;
            }
        }

        return array_values( array_unique( $result ) );
    }

    /**
     * Return all known status definitions (label + description).
     *
     * @return array
     */
    public static function get_status_definitions() {
        $config = self::get_config();

        return isset( $config['statuses'] ) ? $config['statuses'] : [];
    }

    /**
     * Return visual configuration for each status.
     *
     * @return array
     */
    public static function get_visual_config() {
        $config = self::get_config();

        return isset( $config['visual'] ) ? $config['visual'] : [];
    }

    /**
     * Should be called when admin saves the configuration from the UI.
     *
     * @param array $new_config
     * @return bool True on success, false on failure.
     */
    public static function save_config__( array $new_config ) {

        $validated = self::validate_config( $new_config );

        if ( empty( $validated['states_statuses'] ) ) {
            // Do NOT overwrite with empty config - keep previous and let the UI show an error.
            return false;
        }

        // Save to DB.
        $updated = update_option( self::OPTION_KEY, $validated, false );

        if ( $updated ) {
            self::$config = $validated;

            // Also write backup file.
            self::write_backup_file( $validated );
        }

        if ( $updated && class_exists( 'AH_MapData_Generator' ) ) {
            AH_MapData_Generator::generate();
        }


        return $updated;
    }
    public static function save_config( array $new_config ) {

        $old_config = self::get_config();
        $validated = self::validate_config( $new_config );

        if ( empty( $validated['states_statuses'] ) ) {
            return false;
        }

        $updated = update_option( self::OPTION_KEY, $validated, false );

        if ( $updated ) {
            self::$config = $validated;
            self::write_backup_file( $validated );

            self::maybe_fire_state_change_events( $old_config, $validated );
        }

        if ( $updated && class_exists( 'AH_MapData_Generator' ) ) {
            AH_MapData_Generator::generate();
        }

        return $updated;
    }

    protected static function maybe_fire_state_change_events( array $old, array $new ) {

        $old_states = $old['states_statuses'] ?? [];
        $new_states = $new['states_statuses'] ?? [];

        foreach ( $new_states as $state => $new_status ) {

            $old_status = $old_states[ $state ] ?? null;

            if ( $old_status === $new_status ) {
                continue;
            }

            /**
             * Canonical state transition hook
             */
            do_action(
                'ah_state_status_changed',
                $state,
                $old_status,
                $new_status
            );
        }
    }

    /**
     * Validate shape of config before saving.
     *
     * @param array $config
     * @return array
     */
    protected static function validate_config__( array $config ) {
        $defaults    = self::get_default_config();
        $all_states  = AH_States::get_all();
        $clean       = $defaults;

        // States statuses
        if ( isset( $config['states_statuses'] ) && is_array( $config['states_statuses'] ) ) {
            $clean_states = [];

            foreach ( $config['states_statuses'] as $code => $status ) {
                $code   = strtoupper( trim( $code ) );
                $status = trim( $status );

                if ( ! isset( $all_states[ $code ] ) ) {
                    continue;
                }

                // Allow only known statuses or keep raw if you want to support future ones.
                if ( ! isset( $defaults['statuses'][ $status ] ) ) {
                    // Unknown status slug - skip or map to unavailable.
                    $status = 'unavailable';
                }

                $clean_states[ $code ] = $status;
            }

            if ( ! empty( $clean_states ) ) {
                $clean['states_statuses'] = $clean_states;
            }
        }

        // Status definitions (labels, descriptions) can be editable in the UI.
        if ( isset( $config['statuses'] ) && is_array( $config['statuses'] ) ) {
            foreach ( $config['statuses'] as $slug => $data ) {
                if ( ! isset( $defaults['statuses'][ $slug ] ) ) {
                    // Ignore unknown slugs for now.
                    continue;
                }

                $clean['statuses'][ $slug ] = [
                    'label'       => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : $defaults['statuses'][ $slug ]['label'],
                    'description' => isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : $defaults['statuses'][ $slug ]['description'],
                ];
            }
        }

        // Visual config editable in UI.
        if ( isset( $config['visual'] ) && is_array( $config['visual'] ) ) {
            foreach ( $config['visual'] as $slug => $data ) {
                if ( ! isset( $defaults['visual'][ $slug ] ) ) {
                    continue;
                }

                $clean['visual'][ $slug ] = [
                    'state_color'       => isset( $data['state_color'] ) ? sanitize_hex_color( $data['state_color'] ) : $defaults['visual'][ $slug ]['state_color'],
                    'state_hover_color' => isset( $data['state_hover_color'] ) ? sanitize_hex_color( $data['state_hover_color'] ) : $defaults['visual'][ $slug ]['state_hover_color'],
                    'label_color'       => isset( $data['label_color'] ) ? sanitize_hex_color( $data['label_color'] ) : $defaults['visual'][ $slug ]['label_color'],
                    'label_hover_color' => isset( $data['label_hover_color'] ) ? sanitize_hex_color( $data['label_hover_color'] ) : $defaults['visual'][ $slug ]['label_hover_color'],
                ];
            }
        }

        return $clean;
    }

    /**
     * Load config from DB, then fall back to backup file, then defaults.
     *
     * If backup/defaults are used, a transient is set to display an admin notice.
     *
     * @return array
     */
    protected static function load_config_with_fallback() {
        $config = get_option( self::OPTION_KEY );

        if ( is_array( $config ) && ! empty( $config['states_statuses'] ) ) {
            return $config;
        }

        // Try backup file.
        $backup = self::read_backup_file();

        if ( is_array( $backup ) && ! empty( $backup['states_statuses'] ) ) {
            // Flag so we can alert admin.
            set_transient( self::ADMIN_NOTICE_TRANSIENT, 'backup', 60 * 10 );
            return $backup;
        }

        // Last resort: hardcoded defaults.
        set_transient( self::ADMIN_NOTICE_TRANSIENT, 'defaults', 60 * 10 );

        return self::get_default_config();
    }

    /**
     * Compute backup directory path.
     *
     * @return string
     */
    protected static function get_backup_dir() {
        $upload_dir = wp_upload_dir();

        return trailingslashit( $upload_dir['basedir'] ) . self::BACKUP_DIR_NAME . '/';
    }

    /**
     * Write backup JSON file to uploads dir.
     *
     * @param array $config
     * @return void
     */
    protected static function write_backup_file( array $config ) {
        $dir = self::get_backup_dir();

        if ( ! wp_mkdir_p( $dir ) ) {
            return;
        }

        $json = wp_json_encode( $config, JSON_PRETTY_PRINT );

        if ( ! $json ) {
            return;
        }

        // Timestamped file.
        $timestamp_file = $dir . 'config-' . gmdate( 'Ymd-His' ) . '.json';
        file_put_contents( $timestamp_file, $json );

        // Latest alias.
        $latest_file = $dir . self::BACKUP_LATEST_FILE;
        file_put_contents( $latest_file, $json );
    }

    /**
     * Read the "latest" backup JSON file if it exists.
     *
     * @return array|null
     */
    protected static function read_backup_file() {
        $dir        = self::get_backup_dir();
        $latestFile = $dir . self::BACKUP_LATEST_FILE;

        if ( ! file_exists( $latestFile ) ) {
            return null;
        }

        $contents = file_get_contents( $latestFile );

        if ( ! $contents ) {
            return null;
        }

        $decoded = json_decode( $contents, true );

        return is_array( $decoded ) ? $decoded : null;
    }

    /**
     * Hook this into admin_notices to inform when backup/defaults were used.
     */
    public static function maybe_show_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $flag = get_transient( self::ADMIN_NOTICE_TRANSIENT );

        if ( ! $flag ) {
            return;
        }

        delete_transient( self::ADMIN_NOTICE_TRANSIENT );

        if ( 'backup' === $flag ) : ?>
            <div class="notice notice-warning">
                <p><strong>AH States:</strong> Licensed states configuration was loaded from a backup file. Please review and re-save the configuration as soon as possible.</p>
            </div>
        <?php elseif ( 'defaults' === $flag ) : ?>
            <div class="notice notice-error">
                <p><strong>AH States:</strong> Licensed states configuration could not be loaded from the database or backup. Defaults are in use. Please configure and save the states configuration.</p>
            </div>
        <?php endif;
    }

    /**
     * Check if a subscription can renew based on the shipping (or billing) state.
     *
     * @param WC_Subscription $subscription
     * @return bool
     */
    public static function subscription_can_renew( $subscription ) {
        if ( ! $subscription instanceof WC_Subscription ) {
            return false;
        }

        // Business rule: primary source is shipping state, fallback to billing.
        $state = $subscription->get_shipping_state();

        if ( ! $state ) {
            $state = $subscription->get_billing_state();
        }

        if ( ! $state ) {
            // No state = not allowed to renew for safety.
            return false;
        }

        return self::is_state_available( $state );
    }

    protected static function validate_config( array $config ) {
        $defaults     = self::get_default_config();
        $all_states   = AH_States::get_all();
        $clean        = $defaults;

        // Validate state statuses (existing logic)
        if ( isset( $config['states_statuses'] ) && is_array( $config['states_statuses'] ) ) {
            foreach ( $config['states_statuses'] as $code => $status_slug ) {
                $code = strtoupper( trim( $code ) );

                if ( ! isset( $all_states[ $code ] ) ) {
                    continue;
                }
                if ( ! isset( $defaults['statuses'][ $status_slug ] ) ) {
                    continue;
                }
                $clean['states_statuses'][ $code ] = $status_slug;
            }
        }

        // Validate status labels/descriptions
        if ( isset( $config['statuses'] ) && is_array( $config['statuses'] ) ) {
            foreach ( $defaults['statuses'] as $slug => $original ) {
                $clean['statuses'][ $slug ]['label'] =
                    sanitize_text_field( $config['statuses'][ $slug ]['label'] ?? $original['label'] );

                $clean['statuses'][ $slug ]['description'] =
                    sanitize_text_field( $config['statuses'][ $slug ]['description'] ?? $original['description'] );
            }
        }

        // Validate visual settings for each status
        if ( isset( $config['visual'] ) && is_array( $config['visual'] ) ) {
            foreach ( $defaults['visual'] as $slug => $orig_visual ) {

                $clean_v = [];

                foreach ( $orig_visual as $key => $val ) {

                    $incoming = $config['visual'][ $slug ][ $key ] ?? $val;

                    // Validate hex color fields
                    if ( stripos( $key, 'color' ) !== false ) {
                        $incoming = sanitize_hex_color( $incoming );
                    }

                    $clean_v[ $key ] = $incoming;
                }

                $clean['visual'][ $slug ] = $clean_v;
            }
        }

        // ⚡ NEW: per-state visual overrides
        if ( isset( $config['per_state_visual'] ) && is_array( $config['per_state_visual'] ) ) {

            $clean_overrides = [];

            foreach ( $config['per_state_visual'] as $code => $row ) {

                $code = strtoupper( trim( $code ) );

                if ( ! isset( $all_states[ $code ] ) ) {
                    continue;
                }

                // Must have override flag
                $override_enabled = isset( $row['override'] ) && (int) $row['override'] === 1;
                if ( ! $override_enabled ) {
                    continue;
                }

                $entry = [ 'override' => 1 ];

                // Accept colors if provided
                foreach ( ['state_color','state_hover_color','label_color','label_hover_color'] as $field ) {
                    if ( ! empty( $row[ $field ] ) ) {
                        $entry[ $field ] = sanitize_hex_color( $row[ $field ] );
                    }
                }

                if ( count( $entry ) > 1 ) {
                    $clean_overrides[ $code ] = $entry;
                }
            }

            $clean['per_state_visual'] = $clean_overrides;
        }

        return $clean;
    }


}

// Register the admin notice.
add_action( 'admin_notices', [ 'AH_Licensed_States_Manager', 'maybe_show_admin_notice' ] );
