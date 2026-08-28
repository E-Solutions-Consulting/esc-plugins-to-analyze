<?php
/**
 * Migration consent banner.
 *
 * Early-cohort flow: bulk-enabled users (status = available) see a consent
 * box in My Account after logging into WooCommerce. Accepting records
 * consent in user meta — it does NOT change migration_status and does NOT
 * trigger the migration itself: the pipeline (PP-815) is triggered
 * externally for users who accepted.
 *
 * Meta keys:
 *   _ah_migration_accepted     'yes' once the user opts in
 *   _ah_migration_accepted_at  UTC datetime of acceptance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AH_Migration_Consent {

    const META_ACCEPTED    = '_ah_migration_accepted';
    const META_ACCEPTED_AT = '_ah_migration_accepted_at';
    const ACCEPT_ACTION    = 'ah_accept_migration';
    const NONCE_KEY        = 'ah_migration_consent_nonce';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'template_redirect', array( __CLASS__, 'handle_accept' ), 5 );
        add_action( 'woocommerce_before_account_navigation', array( __CLASS__, 'render_banner' ) );
    }

    /**
     * Whether the user has accepted the migration.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_accepted( $user_id ) {
        return 'yes' === get_user_meta( $user_id, self::META_ACCEPTED, true );
    }

    /**
     * Handle the accept button: record consent, never downgrade.
     *
     * @return void
     */
    public static function handle_accept() {
        if ( ! AH_Migration_Config::is_enabled() ) {
            return;
        }

        if ( empty( $_POST['ah_migration_action'] ) || self::ACCEPT_ACTION !== $_POST['ah_migration_action'] ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( empty( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_KEY ] ) ), self::ACCEPT_ACTION ) ) {
            wc_add_notice( 'Security check failed. Please try again.', 'error' );
            return;
        }

        $user_id = get_current_user_id();

        if ( AH_Migration_Status::AVAILABLE !== AH_Migration_Status::get( $user_id ) ) {
            return;
        }

        if ( self::has_accepted( $user_id ) ) {
            return;
        }

        update_user_meta( $user_id, self::META_ACCEPTED, 'yes' );
        update_user_meta( $user_id, self::META_ACCEPTED_AT, current_time( 'mysql', true ) );

        do_action( 'ah_migration_consent_accepted', $user_id );

        AH_Migration_Logger::log( sprintf( 'User #%d accepted the platform migration.', $user_id ) );

        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    /**
     * Render the consent box in My Account for eligible users.
     *
     * @return void
     */
    public static function render_banner() {
        if ( ! AH_Migration_Config::is_enabled() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( AH_Migration_Status::AVAILABLE !== AH_Migration_Status::get( $user_id ) ) {
            return;
        }

        if ( self::has_accepted( $user_id ) ) {
            self::render_accepted_state();
            return;
        }

        $title  = AH_Migration_Config::get( 'consent_title' );
        $text   = AH_Migration_Config::get( 'consent_text' );
        $button = AH_Migration_Config::get( 'consent_button' );
        ?>
        <div class="ah-migration-consent">
            <h3><?php echo esc_html( $title ); ?></h3>
            <p><?php echo esc_html( $text ); ?></p>
            <form method="post">
                <input type="hidden" name="ah_migration_action" value="<?php echo esc_attr( self::ACCEPT_ACTION ); ?>" />
                <?php wp_nonce_field( self::ACCEPT_ACTION, self::NONCE_KEY ); ?>
                <button type="submit" class="button"><?php echo esc_html( $button ); ?></button>
            </form>
        </div>
        <style>
            .ah-migration-consent {border: 1px solid #f1f1f1;box-shadow: 0 0 10px #ccc;padding: 1rem 2rem 2rem;margin-bottom: 2rem;border-radius: 1rem;margin-top: -1rem;}
            .ah-migration-consent button.button {background: #333;color: #fffaf2;padding: 10px 24px;border-radius: 4px;cursor: pointer;font-weight: 500;border: none;margin-top: 1rem;}
            .ah-migration-consent button.button:hover {background: #faf8a2;}
            .ah-migration-consent p:empty,
            .ah-migration-consent .button br{display:none;}
        </style>
        <?php
    }

    /**
     * Render the post-acceptance confirmation state.
     *
     * @return void
     */
    private static function render_accepted_state() {
        ?>
        <div class="ah-migration-consent" style="background:#fffaf2;border:2px solid #1f025a;border-radius:8px;padding:20px 24px;margin-bottom:24px;">
            <p style="color:#1f025a;margin:0;">✓ <?php echo esc_html( AH_Migration_Config::get( 'consent_accepted_message' ) ); ?></p>
        </div>
        <?php
    }
}