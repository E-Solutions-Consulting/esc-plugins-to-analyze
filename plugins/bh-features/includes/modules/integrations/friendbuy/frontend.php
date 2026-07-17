<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AH_Friendbuy_Frontend {

    public function __construct() {
        add_action( 'wp_head',   [ $this, 'bootstrap' ],        20 );
        add_action( 'wp_footer', [ $this, 'customer_tracking'], 20 );
    }

    /**
     * Friendbuy bootstrap + merchant init.
     */
    public function bootstrap() {
        ?>
        <!-- Friendbuy -->
        <script>
        window["friendbuyAPI"] = friendbuyAPI = window["friendbuyAPI"] || [];
        friendbuyAPI.merchantId = "ed5ba9c9-6754-48e0-af79-63d3c33e4030";
        friendbuyAPI.push(["merchant", friendbuyAPI.merchantId]);
        (function(f, r, n, d, b, u, y) {
            while ((u = n.shift())) {
                (b = f.createElement(r)), (y = f.getElementsByTagName(r)[0]);
                b.async = 1;
                b.src = u;
                y.parentNode.insertBefore(b, y);
            }
        })(document, "script", [
            "https://static.fbot.me/friendbuy.js",
            "https://campaign.fbot.me/" + friendbuyAPI.merchantId + "/campaigns.js",
        ]);
        </script>
        <!-- /Friendbuy -->
        <?php
    }

    /**
     * Friendbuy customer tracking for logged-in users.
     */
    public function customer_tracking() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        $user = wp_get_current_user();
        ?>
        <script>
        friendbuyAPI.push(["track", "customer", {
            email: "<?php echo esc_js( $user->user_email ); ?>",
            id:    "<?php echo esc_js( $user->ID ); ?>",
        }]);
        </script>
        <?php
    }
}