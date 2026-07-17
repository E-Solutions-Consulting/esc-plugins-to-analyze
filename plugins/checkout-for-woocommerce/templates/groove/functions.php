<?php

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

wc_maybe_define_constant( 'CFW_BUILD_PROCESS', 2 );

add_action( 'cfw_checkout_main_container_start', 'cfw_groove_desktop_header', 10 );
add_action( 'cfw_thank_you_main_container_start', 'cfw_groove_desktop_header', 10 );
add_action( 'cfw_order_pay_main_container_start', 'cfw_groove_desktop_header', 10 );

function cfw_groove_desktop_header() {
	if ( ! has_action( 'cfw_custom_header' ) ) : ?>
		<div id="cfw-logo-container">
			<?php cfw_logo(); ?>
		</div>
	<?php
	endif;
}

// 5 makes sure this is above notices
add_action( 'cfw_checkout_main_container_start', 'cfw_groove_mobile_logo', 5 );
add_action( 'cfw_thank_you_main_container_start', 'cfw_groove_mobile_logo', 5 );
add_action( 'cfw_order_pay_main_container_start', 'cfw_groove_mobile_logo', 5 );

function cfw_groove_mobile_logo() {
	if ( ! has_action( 'cfw_custom_header' ) ) :
		?>
		<div id="cfw-logo-container-mobile">
			<?php cfw_logo(); ?>
		</div>
	<?php
	endif;
}

add_action( 'cfw_checkout_after_order_review', 'cfw_groove_footer' );
add_action( 'cfw_thank_you_after_order_review', 'cfw_groove_footer' );
add_action( 'cfw_order_pay_after_order_review', 'cfw_groove_footer' );
function cfw_groove_footer() {
	if ( ! has_action( 'cfw_custom_footer' ) ) :
		?>
		<footer id="cfw-footer">
			<div class="row">
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

// Add custom classes to cart summary
add_filter( 'cfw_groove_cart_summary_classes', 'cfw_groove_cart_summary_classes' );

/**
 * Add custom cart summary classes
 *
 * @param array $classes The classes to add.
 */
function cfw_groove_cart_summary_classes( $classes ): array {
	// Get cart summary bg color
	$cart_summary_bg_color = SettingsManager::instance()->get_setting( 'summary_background_color', array( basename( __DIR__ ) ) );

	if ( ! is_hex_color_light( $cart_summary_bg_color ) ) {
		$classes[] = 'cfw-cart-summary-dark';
	}

	return $classes;
}

/**
 * Determine whether a color is a considered a light color
 *
 * @param string $hex_color The hex color to check.
 *
 * @return bool
 */
function is_hex_color_light( $hex_color ): bool {
	// Normalize the hex color
	$color = str_replace( '#', '', $hex_color );

	// Return false (treat as dark) for invalid color values
	if ( strlen( $color ) !== 3 && strlen( $color ) !== 6 ) {
		return false;
	}

	// Convert the 3-character color to 6 characters if necessary
	if ( strlen( $color ) === 3 ) {
		$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
	}

	// Extract the RGB components
	$r = hexdec( substr( $color, 0, 2 ) );
	$g = hexdec( substr( $color, 2, 2 ) );
	$b = hexdec( substr( $color, 4, 2 ) );

	// Calculate the relative luminance
	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

	// Determine whether the color is light or dark based on a threshold value
	if ( $luminance > 0.5 ) {
		return true;
	} else {
		return false;
	}
}

add_filter( 'cfw_trust_badges_output_action', 'cfw_groove_trust_badges_output_action', 10, 2 );

/**
 * Filter the action to output the trust badges
 *
 * @param string $action The action to output the trust badges.
 * @param string $position The position of the trust badges.
 *
 * @return string
 */
function cfw_groove_trust_badges_output_action( string $action, string $position ): string {
	if ( 'below_checkout_form' === $position ) {
		$action = 'cfw_checkout_tabs';
	}

	return $action;
}