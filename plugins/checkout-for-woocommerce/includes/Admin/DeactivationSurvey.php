<?php

namespace Objectiv\Plugins\Checkout\Admin;

use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;

/**
 * Handles the plugin deactivation survey.
 *
 * Extracted from AdminPluginsPageManager to work for both Pro and Lite versions.
 *
 * @since 11.0.1
 */
class DeactivationSurvey {
	private $dev_remote_url = 'https://cfw-stat-collector.test/api/v1/deactivation_survey';
	private $remote_url     = 'https://stats.checkoutwc.com/api/v1/deactivation_survey';

	public function init(): void {
		add_action( 'admin_footer', [ $this, 'output_survey_html' ] );
		add_filter( 'cfw_deactivation_form_fields', [ $this, 'get_form_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1000 );
	}

	public function output_survey_html(): void {
		global $pagenow;

		foreach ( [
			'functions/deactivation-survey.php',
			'sources/php/deactivation-survey.php', // Legacy shim; remove with sources/php/ tree.
		] as $relative_path ) {
			$path = CFW_PATH_BASE . $relative_path;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}

	/**
	 * Get the base form fields for the deactivation survey.
	 *
	 * Pro-specific fields (license_status, price_id) are added via filter
	 * in premium-init.php.
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		$current_user = wp_get_current_user();
		if ( ! empty( $current_user ) ) {
			$current_user_email = $current_user->user_email ? $current_user->user_email : '';
		}

		$store_name = get_bloginfo( 'name ' );
		$store_url  = get_home_url();

		return [
			[
				'id'          => 'deactivation_reason',
				'label'       => '',
				'type'        => 'radio',
				'name'        => 'reason',
				'value'       => '',
				'multiple'    => 'no',
				'required'    => 'yes',
				'extra-class' => '',
				'options'     => [
					'temporary_deactivation_for_debug' => __( '<strong>It is a temporary deactivation.</strong> I am just debugging an issue.', 'checkout-wc' ),
					'site-layout_broke'                => __( 'The plugin <strong>broke my layout</strong> or some functionality.', 'checkout-wc' ),
					'complicated_configuration'        => __( 'The plugin is <strong>too complicated to configure.</strong>', 'checkout-wc' ),
					'other'                            => __( 'Other', 'checkout-wc' ),
				],
			],

			[
				'id'          => 'reason_other',
				'label'       => __( 'Let us know why you are deactivating CheckoutWC so we can improve the plugin', 'checkout-wc' ),
				'type'        => 'textarea',
				'name'        => 'reason_other',
				'value'       => '',
				'required'    => 'yes',
				'extra-class' => 'hidden',
			],

			[
				'id'          => 'admin_email',
				'label'       => '',
				'type'        => 'hidden',
				'name'        => 'admin_email',
				'value'       => $current_user_email ?? '',
				'required'    => '',
				'extra-class' => '',
			],

			[
				'id'          => 'store_name',
				'label'       => '',
				'type'        => 'hidden',
				'name'        => 'store_name',
				'value'       => $store_name,
				'required'    => '',
				'extra-class' => '',
			],

			[
				'id'          => 'url',
				'label'       => '',
				'type'        => 'hidden',
				'name'        => 'url',
				'value'       => $store_url,
				'required'    => '',
				'extra-class' => '',
			],

			[
				'id'          => 'version',
				'label'       => '',
				'type'        => 'hidden',
				'name'        => 'version',
				'value'       => CFW_VERSION,
				'required'    => '',
				'extra-class' => '',
			],
		];
	}

	public function enqueue_scripts(): void {
		global $pagenow;

		if ( empty( $pagenow ) || 'plugins.php' !== $pagenow ) {
			return;
		}

		$front    = CFW_PATH_ASSETS;
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );

		// PHP 8.1+ Fix
		foreach ( $manifest['chunks'] as $chunk_name => $chunk ) {
			add_filter(
				"wpecp/register/{$chunk_name}",
				function ( $args ) use ( $chunk_name ) {
					if ( 'admin-plugins' !== $chunk_name ) {
						return $args;
					}

					$args['deps'][] = 'wp-api';

					return $args;
				}
			);
		}

		cfw_register_scripts( [ 'admin-plugins' ] );
		wp_enqueue_script( 'cfw-admin-plugins' );

		if ( isset( $manifest['chunks']['admin-plugins-styles']['file'] ) ) {
			wp_enqueue_style( 'objectiv-cfw-admin-plugins-styles', "{$front}/{$manifest['chunks']['admin-plugins-styles']['file']}", [], $manifest['chunks']['admin-plugins-styles']['hash'] );
		}

		wp_localize_script(
			'cfw-admin-plugins',
			'cfwAdminPluginsScreenData',
			[
				'remote_url' => CFW_DEV_MODE ? $this->dev_remote_url : $this->remote_url,
			]
		);
	}

	/**
	 * Returns form fields html.
	 *
	 * @since       1.4.0
	 * @param       array  $attr               The attributes of this field.
	 * @param       string $base_class         The basic class for the label.
	 */
	public static function render_field_html( $attr = [], $base_class = 'on-boarding' ) {

		$id       = ! empty( $attr['id'] ) ? 'cfw_' . $attr['id'] : '';
		$name     = ! empty( $attr['name'] ) ? $attr['name'] : '';
		$label    = ! empty( $attr['label'] ) ? $attr['label'] : '';
		$type     = ! empty( $attr['type'] ) ? $attr['type'] : '';
		$class    = ! empty( $attr['extra-class'] ) ? $attr['extra-class'] : '';
		$value    = ! empty( $attr['value'] ) ? $attr['value'] : '';
		$options  = ! empty( $attr['options'] ) ? $attr['options'] : [];
		$multiple = ! empty( $attr['multiple'] ) && 'yes' === $attr['multiple'] ? 'yes' : 'no';
		$required = ! empty( $attr['required'] ) ? 'required="required"' : '';

		$html = '';

		if ( 'hidden' !== $type ) : ?>
			<div class ="mt-6 space-y-6">
			<?php
		endif;

		switch ( $type ) {

			case 'radio':
				// If field requires multiple answers.
				if ( ! empty( $options ) && is_array( $options ) ) :
					?>

					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_attr( $label ); ?></label>

					<?php
					$is_multiple = ! empty( $multiple ) && 'yes' !== $multiple ? 'name = "' . $name . '"' : '';

					foreach ( $options as $option_value => $option_label ) :
						?>
						<div class="flex items-center gap-x-3">
							<input type="<?php echo esc_attr( $type ); ?>" class="cfw-deactivation-survey-<?php echo esc_attr( $type ); ?>-field <?php echo esc_attr( $class ); ?> h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-600" value="<?php echo esc_attr( $option_value ); ?>" id="<?php echo esc_attr( 'cfw_' . $option_value ); ?>" <?php echo esc_html( $required ); ?> <?php echo $is_multiple; ?>>
							<label for="<?php echo esc_attr( 'cfw_' . $option_value ); ?>"><?php echo wp_kses_post( $option_label ); ?></label>
						</div>
					<?php endforeach; ?>

					<?php
				endif;

				break;

			case 'checkbox':
				// If field requires multiple answers.
				if ( ! empty( $options ) && is_array( $options ) ) :
					?>

					<label class="on-boarding-label" for="<?php echo esc_attr( $id ); ?>'"><?php echo esc_attr( $label ); ?></label>

					<?php foreach ( $options as $option_id => $option_label ) : ?>
					<div class="wps-<?php echo esc_html( $base_class ); ?>-checkbox-wrapper">
						<input type="<?php echo esc_html( $type ); ?>" class="on-boarding-<?php echo esc_html( $type ); ?>-field <?php echo esc_html( $class ); ?>" value="<?php echo esc_html( $value ); ?>" id="<?php echo esc_html( $option_id ); ?>">
						<label class="on-boarding-field-label" for="<?php echo esc_html( $option_id ); ?>"><?php echo esc_html( $option_label ); ?></label>
					</div>

				<?php endforeach; ?>
					<?php
				endif;

				break;

			case 'select':
			case 'select2':
				// If field requires multiple answers.
				if ( ! empty( $options ) && is_array( $options ) ) {

					$is_multiple = 'yes' === $multiple ? 'multiple' : '';
					$select2     = ( 'yes' === $multiple && 'select' === $type ) || 'select2' === $type ? 'on-boarding-select2 ' : '';
					?>

					<label class="on-boarding-label"  for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
					<select class="on-boarding-select-field <?php echo esc_html( $select2 ); ?> <?php echo esc_html( $class ); ?>" id="<?php echo esc_html( $id ); ?>" name="<?php echo esc_html( $name ); ?>[]" <?php echo esc_html( $required ); ?> <?php echo esc_html( $is_multiple ); ?>>

						<?php if ( 'select' === $type ) : ?>
							<option class="on-boarding-options" value=""><?php esc_html_e( 'Select Any One Option...', 'upsell-order-bump-offer-for-woocommerce' ); ?></option>
						<?php endif; ?>

						<?php foreach ( $options as $option_value => $option_label ) : ?>

							<option class="on-boarding-options" value="<?php echo esc_attr( $option_value ); ?>"><?php echo esc_html( $option_label ); ?></option>

						<?php endforeach; ?>
					</select>

					<?php
				}

				break;

			case 'label':
				/**
				 * Only a text in label.
				 */
				?>
				<label class="" for="<?php echo( esc_attr( $id ) ); ?>"><?php echo( esc_html( $label ) ); ?></label>
				<?php
				break;

			case 'textarea':
				/**
				 * Text Area Field.
				 */
				?>
				<textarea rows="3" cols="50" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 <?php echo esc_attr( $class ); ?>" placeholder="<?php echo( esc_attr( $label ) ); ?>" id="<?php echo( esc_attr( $id ) ); ?>" name="<?php echo( esc_attr( $name ) ); ?>"><?php echo( esc_attr( $value ) ); ?></textarea>

				<?php
				break;

			default:
				/**
				 * Text/ Password/ Email.
				 */
				?>
				<label for="<?php echo( esc_attr( $id ) ); ?>"><?php echo( esc_html( $label ) ); ?></label>
				<input type="<?php echo( esc_attr( $type ) ); ?>" class="on-boarding-<?php echo( esc_attr( $type ) ); ?>-field <?php echo( esc_attr( $class ) ); ?>" value="<?php echo( esc_attr( $value ) ); ?>"  name="<?php echo( esc_attr( $name ) ); ?>" id="<?php echo( esc_attr( $id ) ); ?>" <?php echo( esc_html( $required ) ); ?>>

				<?php
		}

		if ( 'hidden' !== $type ) :
			?>
			</div>
			<?php
		endif;
	}
}
