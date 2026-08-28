<?php
/**
 * Migration admin.
 *
 * WooCommerce submenu, tabbed layout:
 *   - "Module enabled" toggle always visible on top
 *   - Patient Platform tab: PP API credentials + app URL
 *   - Labels tab: consent box copy + blocked login message
 *   - Bulk Action tab: CSV eligibility tool with preview-then-commit
 *
 * A single form wraps the enabled toggle and both settings tabs (inputs
 * are hidden, never removed from the DOM), so saving from any tab never
 * wipes the fields of another. The Bulk tab uses its own forms/actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Admin {

    const PAGE_SLUG         = PARENT_MENU_SLUG . '--ah-migration';
    const CAPABILITY        = 'manage_woocommerce';
    const PENDING_TRANSIENT = 'ah_migration_bulk_pending_';
    const REPORT_TRANSIENT  = 'ah_migration_bulk_report_';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
        add_action( 'admin_post_ah_migration_save_settings', array( __CLASS__, 'handle_save_settings' ) );
        add_action( 'admin_post_ah_migration_bulk_preview', array( __CLASS__, 'handle_bulk_preview' ) );
        add_action( 'admin_post_ah_migration_bulk_apply', array( __CLASS__, 'handle_bulk_apply' ) );
        add_action( 'admin_post_ah_migration_bulk_cancel', array( __CLASS__, 'handle_bulk_cancel' ) );
        add_action( 'admin_post_ah_migration_csv_template', array( __CLASS__, 'handle_csv_template_download' ) );
        add_action( 'show_user_profile', array( __CLASS__, 'render_profile_field' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_field' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'handle_profile_update' ) );
    }

    /**
     * Register the WooCommerce submenu page.
     *
     * @return void
     */
    public static function register_menu() {
        add_submenu_page(
            PARENT_MENU_SLUG,
            'Patient Migration',
            'Patient Migration',
            self::CAPABILITY,
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $settings = AH_Migration_Config::get_settings();
        $pending  = get_transient( self::PENDING_TRANSIENT . get_current_user_id() );
        $report   = get_transient( self::REPORT_TRANSIENT . get_current_user_id() );

        delete_transient( self::REPORT_TRANSIENT . get_current_user_id() );

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'platform';

        if ( is_array( $pending ) || is_array( $report ) ) {
            $active_tab = 'bulk';
        }

        if ( ! in_array( $active_tab, array( 'platform', 'labels', 'bulk' ), true ) ) {
            $active_tab = 'platform';
        }

        $base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $tabs     = array(
            'platform' => array( 'label' => 'Patient Platform', 'icon' => 'dashicons-cloud' ),
            'labels'   => array( 'label' => 'Labels',           'icon' => 'dashicons-editor-quote' ),
            'bulk'     => array( 'label' => 'Bulk Action',      'icon' => 'dashicons-upload' ),
        );
        ?>
        <div class="wrap ah-migration-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-database-import" style="font-size:28px;width:28px;height:28px;color:#7c3aed;"></span>
                Patient Platform Migration
            </h1>

            <?php if ( isset( $_GET['settings_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <?php if ( is_array( $report ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong>Bulk eligibility applied:</strong>
                        <?php echo absint( $report['enabled'] ); ?> enabled,
                        <?php echo absint( $report['skipped'] ); ?> skipped,
                        <?php echo absint( $report['not_found'] ); ?> not found,
                        <?php echo isset( $report['invalid'] ) ? absint( $report['invalid'] ) : 0; ?> invalid.
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ah-migration-settings-form">
                <input type="hidden" name="action" value="ah_migration_save_settings" />
                <?php wp_nonce_field( 'ah_migration_save_settings' ); ?>

                <div style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin-bottom:16px;">
                    <label style="font-weight:700;margin-right:10px;">Enable Migration</label>    
                    <label class="switch">
                        <input type="checkbox" name="settings[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?>>
                        <span class="slider round"></span>
                    </label>
                    
                    
                    <p class="description">
                        Activates the migration features: consent box, login gate, renewal blocker, and Patient
                        Platform lookup. When disabled, none of the tabs below have any effect.
                    </p>
                </div>

                <?php /* ── Tab nav ── */ ?>
                <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:0;">
                    <?php foreach ( $tabs as $slug => $tab ) : ?>
                        <a href="<?php echo esc_url( $base_url . '&tab=' . $slug ); ?>"
                           class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>"
                           data-ah-tab="<?php echo esc_attr( $slug ); ?>">
                            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"
                                  style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;margin-top:-2px;"></span>
                            <?php echo esc_html( $tab['label'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div id="ah-settings-panel" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:24px 24px 8px;margin-bottom:20px;<?php echo 'bulk' === $active_tab ? 'display:none;' : ''; ?>">

                <div class="ah-tab-content" data-ah-tab-content="platform" style="<?php echo 'platform' === $active_tab ? '' : 'display:none;'; ?>">

                    <p class="description" style="margin-bottom:18px;">
                        API credentials for the Patient Platform's <code>patient-api</code> function.
                        Required for status lookups, consent submission, and login redirection.
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="pp_api_base">Patient Platform API base</label></th>
                            <td>
                                <input type="url" id="pp_api_base" name="settings[pp_api_base]" class="large-text" value="<?php echo esc_attr( $settings['pp_api_base'] ); ?>" placeholder="https://xxxx.supabase.co/functions/v1/patient-api" />
                                <p class="description">Base URL of the patient-api function for this environment.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pp_publishable_key">PP bearer hash / key</label></th>
                            <td>
                                <input type="text" id="pp_publishable_key" name="settings[pp_publishable_key]" class="regular-text" value="<?php echo esc_attr( $settings['pp_publishable_key'] ); ?>" />
                                <p class="description">Paste the MD5 hash provided by the PP team (used verbatim) or a raw key (hashed at request time).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pp_tenant_slug">PP tenant slug</label></th>
                            <td><input type="text" id="pp_tenant_slug" name="settings[pp_tenant_slug]" class="regular-text" value="<?php echo esc_attr( $settings['pp_tenant_slug'] ); ?>" placeholder="brello" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="app_login_url">Patient App login URL</label></th>
                            <td>
                                <input type="url" id="app_login_url" name="settings[app_login_url]" class="large-text" value="<?php echo esc_attr( $settings['app_login_url'] ); ?>" placeholder="https://app.joinbrello.com" />
                                <p class="description">Appended to the blocked message for migrated users, and shown to Patient Platform users who try to log in on WooCommerce.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ah-tab-content" data-ah-tab-content="labels" style="<?php echo 'labels' === $active_tab ? '' : 'display:none;'; ?>">

                    <p class="description" style="margin-bottom:18px;">
                        Copy shown to customers throughout the migration flow: the consent box on their
                        account page, and the message shown when a migrating/migrated user tries to log
                        in to WooCommerce.
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="consent_title">Consent box title</label></th>
                            <td><input type="text" id="consent_title" name="settings[consent_title]" class="large-text" value="<?php echo esc_attr( $settings['consent_title'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="consent_text">Consent box text</label></th>
                            <td><input type="text" id="consent_text" name="settings[consent_text]" class="large-text" value="<?php echo esc_attr( $settings['consent_text'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="consent_button">Consent button label</label></th>
                            <td><input type="text" id="consent_button" name="settings[consent_button]" class="regular-text" value="<?php echo esc_attr( $settings['consent_button'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="consent_accepted_message">Consent accepted message</label></th>
                            <td><input type="text" id="consent_accepted_message" name="settings[consent_accepted_message]" class="large-text" value="<?php echo esc_attr( $settings['consent_accepted_message'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="blocked_message">Blocked login message</label></th>
                            <td>
                                <input type="text" id="blocked_message" name="settings[blocked_message]" class="large-text" value="<?php echo esc_attr( $settings['blocked_message'] ); ?>" />
                                <p class="description">Shown to users in migrating/migrated status. For migrated users the app link is appended automatically.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ah-settings-submit" style="<?php echo 'bulk' === $active_tab ? 'display:none;' : 'margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;'; ?>">
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                    <span style="margin-left:10px;color:#6b7280;font-size:13px;">
                        All settings are saved together — switching tabs will not lose your changes.
                    </span>
                </div>

                </div><?php /* panel — closed before the bulk tab so its own <form> tags below are never nested inside this one */ ?>
            </form>

            <div id="ah-bulk-panel" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:24px 24px 8px;margin-bottom:20px;<?php echo 'bulk' === $active_tab ? '' : 'display:none;'; ?>">
                <?php
                if ( is_array( $pending ) ) {
                    self::render_bulk_preview( $pending );
                } else {
                    self::render_bulk_form();
                }
                ?>
            </div>

        </div>

        <script>
        ( function () {
            var tabs         = document.querySelectorAll( '[data-ah-tab]' );
            var panels       = document.querySelectorAll( '[data-ah-tab-content]' );
            var submit       = document.querySelector( '.ah-settings-submit' );
            var settingsPane = document.getElementById( 'ah-settings-panel' );
            var bulkPane     = document.getElementById( 'ah-bulk-panel' );

            tabs.forEach( function ( tab ) {
                tab.addEventListener( 'click', function ( e ) {
                    e.preventDefault();

                    var target = tab.getAttribute( 'data-ah-tab' );

                    tabs.forEach( function ( t ) {
                        t.classList.toggle( 'nav-tab-active', t === tab );
                    } );

                    panels.forEach( function ( panel ) {
                        panel.style.display = panel.getAttribute( 'data-ah-tab-content' ) === target ? '' : 'none';
                    } );

                    if ( settingsPane ) {
                        settingsPane.style.display = 'bulk' === target ? 'none' : '';
                    }

                    if ( bulkPane ) {
                        bulkPane.style.display = 'bulk' === target ? '' : 'none';
                    }

                    if ( submit ) {
                        submit.style.display = 'bulk' === target ? 'none' : '';
                    }

                    if ( window.history && window.history.replaceState ) {
                        var url = new URL( window.location.href );
                        url.searchParams.set( 'tab', target );
                        window.history.replaceState( null, '', url.toString() );
                    }
                } );
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Render the bulk upload form (step 1).
     *
     * @return void
     */
    private static function render_bulk_form() {
        ?>
        <h2 style="display:flex;align-items:center;gap:6px;">
            <span class="dashicons dashicons-upload" style="color:#7c3aed;"></span>
            Bulk Eligibility (not_available → available)
        </h2>
        <p class="description" style="margin-bottom:18px;">
            Upload a CSV of user emails (first column) or paste one email / user ID per line. Users already at
            <code>available</code>, <code>migrating</code>, <code>migrated</code> or <code>migration_error</code>
            are skipped — status is never downgraded. You will see a preview before anything is applied.
        </p>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ah_migration_csv_template' ), 'ah_migration_csv_template' ) ); ?>" class="button">
                <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:3px;"></span>
                Download CSV template
            </a>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="ah_migration_bulk_preview" />
            <?php wp_nonce_field( 'ah_migration_bulk_preview' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" style="width:220px;"><label for="csv_file">CSV file</label></th>
                    <td><input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="customer_list">Or paste list</label></th>
                    <td><textarea id="customer_list" name="customer_list" rows="8" class="large-text code" placeholder="patient1@example.com&#10;patient2@example.com&#10;123"></textarea></td>
                </tr>
            </table>
            <?php submit_button( 'Preview Changes', 'primary' ); ?>
        </form>
        <?php
    }

    /**
     * Render the bulk preview (step 2) with commit/cancel actions.
     *
     * @param array $pending
     * @return void
     */
    private static function render_bulk_preview( $pending ) {
        $report = self::bulk_enable( $pending['lines'], true );
        ?>
        <h2 style="display:flex;align-items:center;gap:6px;">
            <span class="dashicons dashicons-visibility" style="color:#7c3aed;"></span>
            Bulk Eligibility — Preview
        </h2>
        <div class="notice notice-warning inline">
            <p>
                <strong>Nothing has been applied yet.</strong>
                Out of <?php echo count( $pending['lines'] ); ?> entries:
                <strong><?php echo absint( $report['enabled'] ); ?></strong> will be set to <code>available</code>,
                <strong><?php echo absint( $report['skipped'] ); ?></strong> will be skipped,
                <strong><?php echo absint( $report['not_found'] ); ?></strong> were not found,
                <strong><?php echo absint( $report['invalid'] ); ?></strong> have invalid format.
            </p>
        </div>
        <?php if ( ! empty( $report['details'] ) ) : ?>
            <table class="widefat striped" style="max-width:700px;">
                <thead><tr><th>Entry</th><th>Result</th></tr></thead>
                <tbody>
                    <?php foreach ( array_slice( $report['details'], 0, 100 ) as $detail ) : ?>
                        <tr><td><?php echo esc_html( $detail['entry'] ); ?></td><td><?php echo esc_html( $detail['result'] ); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( count( $report['details'] ) > 100 ) : ?>
                <p class="description">Showing first 100 of <?php echo count( $report['details'] ); ?> entries.</p>
            <?php endif; ?>
        <?php endif; ?>
        <div style="margin-top:16px;display:flex;gap:8px;">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ah_migration_bulk_apply" />
                <?php wp_nonce_field( 'ah_migration_bulk_apply' ); ?>
                <button type="submit" class="button button-primary">Apply — enable <?php echo absint( $report['enabled'] ); ?> users</button>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ah_migration_bulk_cancel" />
                <?php wp_nonce_field( 'ah_migration_bulk_cancel' ); ?>
                <button type="submit" class="button">Cancel</button>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings handler.
     *
     * @return void
     */
    public static function handle_save_settings() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        check_admin_referer( 'ah_migration_save_settings' );

        $settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

        AH_Migration_Config::save_settings( $settings );

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'settings_saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Bulk preview handler: parse input, store pending list, show preview.
     *
     * @return void
     */
    public static function handle_bulk_preview() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        check_admin_referer( 'ah_migration_bulk_preview' );

        $lines = array();

        if ( ! empty( $_FILES['csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
            $handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
            if ( $handle ) {
                $first_row = true;
                while ( false !== ( $row = fgetcsv( $handle ) ) ) {
                    $value = isset( $row[0] ) ? trim( $row[0] ) : '';

                    if ( $first_row ) {
                        $first_row = false;
                        if ( in_array( strtolower( $value ), array( 'email', 'user_id', 'id', 'customer_id' ), true ) ) {
                            continue;
                        }
                    }

                    if ( '' !== $value ) {
                        $lines[] = sanitize_text_field( $value );
                    }
                }
                fclose( $handle );
            }
        }

        if ( empty( $lines ) && ! empty( $_POST['customer_list'] ) ) {
            $raw   = sanitize_textarea_field( wp_unslash( $_POST['customer_list'] ) );
            $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        }

        $lines = array_values( array_unique( array_map( function ( $line ) {
            return is_email( $line ) ? strtolower( $line ) : $line;
        }, $lines ) ) );

        if ( ! empty( $lines ) ) {
            set_transient( self::PENDING_TRANSIENT . get_current_user_id(), array( 'lines' => $lines ), 900 );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'bulk' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Bulk apply handler: commit the pending list.
     *
     * @return void
     */
    public static function handle_bulk_apply() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        check_admin_referer( 'ah_migration_bulk_apply' );

        $pending = get_transient( self::PENDING_TRANSIENT . get_current_user_id() );

        delete_transient( self::PENDING_TRANSIENT . get_current_user_id() );

        if ( is_array( $pending ) && ! empty( $pending['lines'] ) ) {
            $report = self::bulk_enable( $pending['lines'], false );
            set_transient( self::REPORT_TRANSIENT . get_current_user_id(), $report, 300 );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'bulk' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Stream the CSV template download.
     *
     * @return void
     */
    public static function handle_csv_template_download() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        check_admin_referer( 'ah_migration_csv_template' );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=migration-eligibility-template.csv' );

        $output = fopen( 'php://output', 'w' );

        fputcsv( $output, array( 'email' ) );
        fputcsv( $output, array( 'patient.demo1@example.com' ) );
        fputcsv( $output, array( 'patient.demo2@example.com' ) );
        fputcsv( $output, array( 'patient.demo3@example.com' ) );

        fclose( $output );
        exit;
    }

    /**
     * Bulk cancel handler: discard the pending list.
     *
     * @return void
     */
    public static function handle_bulk_cancel() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        check_admin_referer( 'ah_migration_bulk_cancel' );

        delete_transient( self::PENDING_TRANSIENT . get_current_user_id() );

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'bulk' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Core bulk enable logic, shared with WP-CLI. Only transitions
     * not_available → available; anything else is skipped.
     *
     * @param array $lines   User IDs or emails.
     * @param bool  $dry_run When true, nothing is written.
     * @return array Report: enabled, skipped, not_found, details.
     */
    public static function bulk_enable( array $lines, $dry_run = false ) {
        $report = array( 'enabled' => 0, 'skipped' => 0, 'not_found' => 0, 'invalid' => 0, 'details' => array() );

        foreach ( $lines as $line ) {
            if ( ! is_email( $line ) && ! is_numeric( $line ) ) {
                $report['invalid']++;
                $report['details'][] = array( 'entry' => $line, 'result' => 'invalid format — ignored' );
                continue;
            }

            $user = is_numeric( $line ) ? get_user_by( 'id', absint( $line ) ) : get_user_by( 'email', $line );

            if ( ! $user ) {
                $report['not_found']++;
                $report['details'][] = array( 'entry' => $line, 'result' => 'not found' );
                continue;
            }

            $label = is_numeric( $line ) ? sprintf( '#%d (%s)', $user->ID, $user->user_email ) : $line;

            $current = AH_Migration_Status::get( $user->ID );

            if ( AH_Migration_Status::NOT_AVAILABLE !== $current ) {
                $report['skipped']++;
                $report['details'][] = array( 'entry' => $label, 'result' => 'skipped (' . $current . ')' );
                continue;
            }

            if ( ! $dry_run ) {
                $result = AH_Migration_Status::transition( $user->ID, AH_Migration_Status::AVAILABLE );

                if ( is_wp_error( $result ) ) {
                    $report['skipped']++;
                    $report['details'][] = array( 'entry' => $label, 'result' => $result->get_error_message() );
                    continue;
                }
            }

            $report['enabled']++;
            $report['details'][] = array( 'entry' => $label, 'result' => $dry_run ? 'will be enabled' : 'enabled' );
        }

        if ( ! $dry_run ) {
            AH_Migration_Logger::log( sprintf(
                'Bulk enable by admin #%d: %d enabled, %d skipped, %d not found.',
                get_current_user_id(),
                $report['enabled'],
                $report['skipped'],
                $report['not_found']
            ) );
        }

        return $report;
    }

    /**
     * Render the migration status on the user profile screen.
     *
     * @param WP_User $user
     * @return void
     */
    public static function render_profile_field( $user ) {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        $status   = AH_Migration_Status::get( $user->ID );
        $error    = get_user_meta( $user->ID, AH_Migration_Status::META_ERROR, true );
        $accepted = AH_Migration_Consent::has_accepted( $user->ID );
        ?>
        <h2>Patient Platform Migration</h2>
        <table class="form-table">
            <tr>
                <th><label for="ah_migration_status_override">Migration status</label></th>
                <td>
                    <select name="ah_migration_status_override" id="ah_migration_status_override">
                        <?php foreach ( AH_Migration_Status::get_valid_statuses() as $value ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $value ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php wp_nonce_field( 'ah_migration_profile_override', 'ah_migration_profile_nonce' ); ?>
                    <?php if ( $accepted ) : ?>
                        <p class="description">✓ User accepted the migration on <?php echo esc_html( get_user_meta( $user->ID, AH_Migration_Consent::META_ACCEPTED_AT, true ) ); ?> UTC.</p>
                    <?php endif; ?>
                    <?php if ( $error ) : ?>
                        <p class="description">Last error: <?php echo esc_html( $error ); ?></p>
                    <?php endif; ?>
                    <p class="description">Admin override — bypasses transition rules. Use with care.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle the admin override from the profile screen.
     *
     * @param int $user_id
     * @return void
     */
    public static function handle_profile_update( $user_id ) {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        if ( empty( $_POST['ah_migration_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ah_migration_profile_nonce'] ) ), 'ah_migration_profile_override' ) ) {
            return;
        }

        if ( ! isset( $_POST['ah_migration_status_override'] ) ) {
            return;
        }

        $new_status = sanitize_text_field( wp_unslash( $_POST['ah_migration_status_override'] ) );

        if ( $new_status === AH_Migration_Status::get( $user_id ) ) {
            return;
        }

        AH_Migration_Status::force_set( $user_id, $new_status );
        AH_Migration_Logger::log( sprintf( 'Admin #%d force-set user #%d to "%s".', get_current_user_id(), $user_id, $new_status ), 'notice' );
    }
}