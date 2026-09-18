<?php
/**
 * My Account terms & conditions consent modal.
 *
 * Shows a short notice pointing to WooCommerce's configured "Terms and
 * conditions" page (Settings > Advanced > Page setup) the moment a
 * logged-in customer lands on any My Account page, until they accept it.
 * Accepting records consent in user meta and closes the modal in place
 * (AJAX, no page reload).
 *
 * CSS/JS are enqueued as real assets (not inline <style>/<script> printed
 * alongside the modal markup) on purpose: some account page renderers
 * (classic WooCommerce templates, at least) run their output through
 * wpautop, which only special-cases <pre> and mangles any inline
 * <style>/<script> block into <p>/<br> tags. Enqueued assets are printed
 * via wp_head/wp_footer, entirely outside that pipeline.
 *
 * The modal itself is hooked on wp_footer + is_account_page() rather than
 * a classic template hook like woocommerce_before_account_navigation,
 * because sites that build My Account with Elementor, WooCommerce Blocks,
 * or a custom template never fire those classic hooks at all.
 *
 * Meta keys:
 *   _ah_terms_accepted     'yes' once the user accepts
 *   _ah_terms_accepted_at  UTC datetime of acceptance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Terms_Consent' ) ) {

class AH_Terms_Consent {

    const META_ACCEPTED    = '_ah_terms_accepted';
    const META_ACCEPTED_AT = '_ah_terms_accepted_at';
    const ACCEPT_ACTION    = 'ah_accept_terms';
    const ASSET_VERSION    = '1.0.1';

    /**
     * Guards against printing the modal/banner twice when both hooks below
     * fire in the same request.
     *
     * @var bool
     */
    private static $rendered = false;

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'wp_ajax_' . self::ACCEPT_ACTION, array( __CLASS__, 'ajax_accept' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        // Hooked twice on purpose: woocommerce_before_account_navigation is
        // the "correct" classic template hook, but sites that build My
        // Account with Elementor/WooCommerce Blocks/a custom template may
        // never fire it at all. wp_footer always fires regardless, so it's
        // the fallback. render_modal() guards against double-printing if
        // both happen to fire in the same request.
        add_action( 'woocommerce_before_account_navigation', array( __CLASS__, 'render_modal' ) );
        add_action( 'wp_footer', array( __CLASS__, 'render_modal' ) );
        add_action( 'show_user_profile', array( __CLASS__, 'render_profile_field' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_field' ) );
        add_filter( 'woocommerce_rest_prepare_customer', array( __CLASS__, 'add_consent_to_customer_response' ), 10, 2 );
    }

    /**
     * Whether the user has already accepted the terms.
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_accepted( $user_id ) {
        return 'yes' === get_user_meta( $user_id, self::META_ACCEPTED, true );
    }

    /**
     * Add the consent status to the WC REST API customer response
     * (GET/PUT /wc/v3/customers/<id>), so external integrations can read it.
     *
     * @param WP_REST_Response $response
     * @param WP_User          $user
     * @return WP_REST_Response
     */
    public static function add_consent_to_customer_response( $response, $user ) {
        if ( ! $response instanceof WP_REST_Response || ! $user instanceof WP_User ) {
            return $response;
        }

        $data                        = $response->get_data();
        $data['terms_accepted']      = self::has_accepted( $user->ID );
        $data['terms_accepted_at']   = get_user_meta( $user->ID, self::META_ACCEPTED_AT, true );
        $response->set_data( $data );

        return $response;
    }

    /**
     * Show the consent status on the customer's profile screen in wp-admin
     * (Users > their profile), for shop managers/admins only.
     *
     * @param WP_User $user
     * @return void
     */
    public static function render_profile_field( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $accepted = self::has_accepted( $user->ID );
        ?>
        <h2><?php esc_html_e( 'Updated Terms & Conditions', 'bh-features' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Consent status', 'bh-features' ); ?></th>
                <td>
                    <?php if ( $accepted ) : ?>
                        <span style="color:#1f8a3b;font-weight:600;">&#10003; <?php esc_html_e( 'Accepted', 'bh-features' ); ?></span>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: UTC datetime the customer accepted the updated terms. */
                                esc_html__( 'Accepted on %s (UTC).', 'bh-features' ),
                                esc_html( get_user_meta( $user->ID, self::META_ACCEPTED_AT, true ) )
                            );
                            ?>
                        </p>
                    <?php else : ?>
                        <span style="color:#b32d2e;font-weight:600;">&#10007; <?php esc_html_e( 'Not accepted yet', 'bh-features' ); ?></span>
                        <p class="description"><?php esc_html_e( 'The customer will see the consent modal next time they visit My Account.', 'bh-features' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Whether the current session is an admin who switched into another
     * user's account via the "User Switching" plugin. Investigating an
     * account this way shouldn't be blocked by that account's own consent
     * modal.
     *
     * @return bool
     */
    private static function is_switched_session() {
        return function_exists( 'current_user_switched' ) && current_user_switched();
    }

    /**
     * WooCommerce's configured "Terms and conditions" page, only when it's
     * actually set and published.
     *
     * @return WP_Post|null
     */
    private static function get_terms_page() {
        $page_id = wc_terms_and_conditions_page_id();

        if ( $page_id <= 0 ) {
            return null;
        }

        $page = get_post( $page_id );

        if ( ! $page || 'publish' !== $page->post_status ) {
            return null;
        }

        return $page;
    }

    /**
     * The terms page, only when the current user is logged in and hasn't
     * accepted yet.
     *
     * @return WP_Post|null
     */
    private static function get_eligible_page() {
        if ( ! is_user_logged_in() ) {
            return null;
        }

        if ( self::has_accepted( get_current_user_id() ) ) {
            return null;
        }

        return self::get_terms_page();
    }

    /**
     * Enqueue the modal/banner CSS + JS on any My Account page the current
     * user is logged into.
     *
     * @return void
     */
    public static function enqueue_assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        $base_url = plugin_dir_url( __FILE__ );

        wp_enqueue_style( 'ah-terms-consent', $base_url . 'assets/css/terms-consent.css', array(), self::ASSET_VERSION );

        wp_enqueue_script( 'ah-terms-consent', $base_url . 'assets/js/terms-consent.js', array(), self::ASSET_VERSION, true );

        wp_localize_script( 'ah-terms-consent', 'ahTermsConsent', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => self::ACCEPT_ACTION,
        ) );
    }

    /**
     * AJAX handler for the "I've Reviewed & Agree" button: record consent,
     * never downgrade.
     *
     * @return void
     */
    public static function ajax_accept() {
        check_ajax_referer( self::ACCEPT_ACTION, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Not logged in.' ), 403 );
        }

        $user_id = get_current_user_id();

        if ( ! self::has_accepted( $user_id ) ) {
            update_user_meta( $user_id, self::META_ACCEPTED, 'yes' );
            update_user_meta( $user_id, self::META_ACCEPTED_AT, current_time( 'mysql', true ) );

            do_action( 'ah_terms_consent_accepted', $user_id );
        }

        wp_send_json_success();
    }

    /**
     * When an admin switched into this account (see is_switched_session()),
     * show a small non-blocking status banner instead of the modal — an
     * investigation shouldn't be gated by the account's own consent state.
     *
     * @param int $user_id
     * @return void
     */
    private static function render_switched_banner( $user_id ) {
        if ( ! self::get_terms_page() ) {
            return;
        }

        $accepted    = self::has_accepted( $user_id );
        $banner_text = $accepted
            ? sprintf(
                /* translators: %s: UTC datetime the customer accepted the updated terms. */
                esc_html__( 'This customer accepted the updated Terms & Conditions on %s (UTC).', 'bh-features' ),
                esc_html( get_user_meta( $user_id, self::META_ACCEPTED_AT, true ) )
            )
            : esc_html__( 'This customer has not accepted the updated Terms & Conditions yet.', 'bh-features' );
        $icon_svg = $accepted
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M8 12.5l2.5 2.5L16 9.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="16.3" r="1" fill="currentColor" stroke="none"/></svg>';
        ?>
        <div class="ah-terms-consent-banner <?php echo $accepted ? 'ah-terms-consent-banner--accepted' : 'ah-terms-consent-banner--pending'; ?>"><span class="ah-terms-consent-banner__icon" aria-hidden="true"><?php echo $icon_svg; ?></span><span class="ah-terms-consent-banner__text"><?php echo $banner_text; ?></span><button type="button" class="ah-terms-consent-banner__close" aria-label="<?php esc_attr_e( 'Dismiss', 'bh-features' ); ?>">&times;</button></div>
        <?php
    }

    /**
     * Render the terms modal markup on My Account pages for users who
     * haven't accepted yet. No inline <style>/<script> here on purpose
     * (see file header) — assets are enqueued separately.
     *
     * While switched into another account (see is_switched_session()) this
     * shows the non-blocking banner instead, never the modal — so
     * investigating an account isn't gated by that account's consent state.
     *
     * @return void
     */
    public static function render_modal() {
        if ( self::$rendered ) {
            return;
        }

        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        if ( self::is_switched_session() ) {
            self::$rendered = true;
            self::render_switched_banner( get_current_user_id() );
            return;
        }

        $page = self::get_eligible_page();

        if ( ! $page ) {
            return;
        }

        self::$rendered = true;

        $terms_url  = get_permalink( $page );
        $nonce      = wp_create_nonce( self::ACCEPT_ACTION );
        $show_close = current_user_can( 'manage_woocommerce' );
        ?>
        <div id="ah-terms-consent-modal" class="ah-terms-consent-modal" role="dialog" aria-modal="true" aria-labelledby="ah-terms-consent-modal-title">
            <div class="ah-terms-consent-modal__panel">
                <?php if ( $show_close ) : ?>
                    <button type="button" class="ah-terms-consent-modal__close" aria-label="<?php esc_attr_e( 'Close', 'bh-features' ); ?>">&times;</button>
                <?php endif; ?>
                <div class="ah-terms-consent-modal__accent"></div>
                <div class="ah-terms-consent-modal__header"><span class="ah-terms-consent-modal__icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5l-8-3Z" stroke="#453796" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 12.5l2 2 4-4.5" stroke="#453796" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><h2 id="ah-terms-consent-modal-title" class="ah-terms-consent-modal__title"><?php esc_html_e( 'Updated Terms & Conditions', 'bh-features' ); ?></h2></div>
                <p class="ah-terms-consent-modal__body"><?php esc_html_e( "We've updated our Terms & Conditions (Terms of Use). Please review the updated Terms of Use, which govern your access to and use of Brello. By continuing to use Brello, you acknowledge that you have reviewed and agree to the updated Terms.", 'bh-features' ); ?></p>
                <p class="ah-terms-consent-modal__error" style="display:none;"></p>
                <div class="ah-terms-consent-modal__actions"><a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener noreferrer" class="button ah-terms-consent-modal__review"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 5h5v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 5l-8.5 8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 5H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><?php esc_html_e( 'Review Updated Terms', 'bh-features' ); ?></a><button type="button" class="button ah-terms-consent-modal__accept" data-nonce="<?php echo esc_attr( $nonce ); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php esc_html_e( "I've Reviewed & Agree", 'bh-features' ); ?></button></div>
            </div>
        </div>
        <?php
    }
}

/**
 * Register hooks only once WooCommerce (and wc_terms_and_conditions_page_id())
 * are available.
 */
add_action( 'woocommerce_loaded', array( 'AH_Terms_Consent', 'init' ) );

}
