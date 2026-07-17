<?php

use Objectiv\Plugins\Checkout\Managers\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<footer id="cfw-footer">
	<div class="container">
		<div class="row">
			<div class="col-12">
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
	</div>
</footer>
