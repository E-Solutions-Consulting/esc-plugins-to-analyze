<?php
wc_maybe_define_constant( 'CFW_BUILD_PROCESS', 2 );

add_action( 'cfw_checkout_main_container_start', 'copify_desktop_header', 10 );
add_action( 'cfw_thank_you_main_container_start', 'copify_desktop_header', 10 );
add_action( 'cfw_order_pay_main_container_start', 'copify_desktop_header', 10 );

function copify_desktop_header() {
	if ( ! has_action( 'cfw_custom_header' ) ) : ?>
	<div id="cfw-logo-container">
		<?php cfw_logo(); ?>
	</div>
		<?php
	endif;
}

// 5 makes sure this is above notices
add_action( 'cfw_checkout_main_container_start', 'copify_mobile_logo', 5 );
add_action( 'cfw_thank_you_main_container_start', 'copify_mobile_logo', 5 );
add_action( 'cfw_order_pay_main_container_start', 'copify_mobile_logo', 5 );

function copify_mobile_logo() {
	if ( ! has_action( 'cfw_custom_header' ) ) :
		?>
		<div id="cfw-logo-container-mobile">
			<?php cfw_logo(); ?>
		</div>
		<?php
	endif;
}

add_action( 'cfw_checkout_after_order_review', 'copify_footer' );
add_action( 'cfw_thank_you_after_order_review', 'copify_footer' );
add_action( 'cfw_order_pay_after_order_review', 'copify_footer' );
function copify_footer() {
	if ( ! has_action( 'cfw_custom_footer' ) ) :
		?>
		<footer id="cfw-footer" class="row">
			<div class="col-lg-12">
				<div class="cfw-footer-inner entry-footer">
					<?php
					/**
					 * Fires at the top of footer
					 *
					 * @since 3.0.0
					 */
					do_action( 'cfw_before_footer' );

					/**
					 * Hook to output footer content
					 *
					 * @since 8.0.0
					 */
					do_action( 'cfw_footer_content' );

					/**
					 * Fires at the bottom of footer
					 *
					 * @since 3.0.0
					 */
					do_action( 'cfw_after_footer' );
					?>
				</div>
			</div>
		</footer>
		<?php
	endif;
}

// Move notices inside container
remove_action( 'cfw_order_pay_main_container_start', 'cfw_wc_print_notices_with_wrap', 10 );
add_action( 'cfw_order_pay_before_order_review', 'cfw_wc_print_notices_with_wrap', 0 );

remove_action( 'cfw_checkout_main_container_start', 'cfw_wc_print_notices_with_wrap', 10 );
add_action( 'cfw_checkout_before_order_review', 'cfw_wc_print_notices', 0 );

add_filter( 'cfw_trust_badges_output_action', 'cfw_copify_trust_badges_output_action', 10, 2 );

/**
 * Filter the action to output the trust badges
 *
 * @param string $action The action to output the trust badges.
 * @param string $position The position of the trust badges.
 *
 * @return string
 */
function cfw_copify_trust_badges_output_action( string $action, string $position ): string {
	if ( 'below_checkout_form' === $position ) {
		$action = 'cfw_checkout_tabs';
	}

	return $action;
}