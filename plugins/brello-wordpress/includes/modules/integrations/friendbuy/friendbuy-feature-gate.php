<?php 
if (!defined('ABSPATH')) {
    exit;
}

class AH_Friendbuy_Feature_Gate {

    public static function is_enabled( $user_id = null ) {

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return false;
        }

        // Always allow administrators
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return true;
        }

        $user_email = strtolower( $user->user_email );

        // Allowlisted beta customers
        // $allowed_emails = (array) get_option( 'ah_friendbuy_beta_emails', [] );
        $allowed_emails = array_map(
            'strtolower',
            (array) get_option( 'ah_friendbuy_beta_emails', [] )
        );
        // echo '<div style="display:none">';
        // _print([$user->user_email, $allowed_emails, (in_array( $user->user_email, $allowed_emails, true )? 'yes':'no')], 'validation GATE');
        // echo '</div>';
        return in_array( $user->user_email, $allowed_emails, true );
    }

}
