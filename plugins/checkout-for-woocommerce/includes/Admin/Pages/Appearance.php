<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Model\Template;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class Appearance extends PageAbstract {
	use TabbedAdminPageTrait;

	protected $settings_manager;

	public function __construct() {
		$this->settings_manager = SettingsManager::instance();

		parent::__construct( __( 'Appearance', 'checkout-wc' ), 'cfw_manage_appearance', 'appearance' );
	}

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 1000 );
		add_action( $this->settings_manager->prefix . '_settings_saved', array( $this, 'maybe_activate_theme' ) );

		$this->set_tabbed_navigation( new TabNavigation( 'templates' ) );
		$this->get_tabbed_navigation()->add_tab( __( 'Template', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'templates' ), $this->get_url() ), 'templates' );
		$this->get_tabbed_navigation()->add_tab( __( 'Design', 'checkout-wc' ), add_query_arg( array( 'subpage' => 'design' ), $this->get_url() ) );

		parent::init();
	}

	public function maybe_activate_theme() {
		$prefix = $this->settings_manager->prefix;

		$new_settings = wc_clean( wp_unslash( $_REQUEST[ "{$prefix}_setting" ] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $new_settings['active_template'] ) ) {
			return;
		}

		$active_template = new Template( $this->settings_manager->get_setting( 'active_template' ) );
		$active_template->init();
	}

	public function enqueue_assets() {
		wp_enqueue_media();
	}

	public function output() {
		$this->get_tabbed_navigation()->display_tabs();

		if ( $this->get_tabbed_navigation()->get_current_tab() === 'templates' ) {
			$this->templates_tab();
		}

		if ( $this->get_tabbed_navigation()->get_current_tab() === 'design' ) {
			$this->design_tab();
		}
	}

	public function templates_tab() {
		$settings        = SettingsManager::instance();
		$templates       = Template::get_all_available();
		$active_template = cfw_get_active_template()->get_slug();

		// Move active template to the top
		if ( isset( $templates[ $active_template ] ) ) {
			$templates = array_merge( array( $active_template => $templates[ $active_template ] ), $templates );
		}
		?>
		<div class="cfw-theme-browser">
			<div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-6">
				<?php
				foreach ( $templates as $template ) :
					$screenshot = $template->get_template_uri() . '/screenshot.png';

					$active      = $active_template === $template->get_slug();
					$locked      = ! $active && ! PlanManager::has_premium_plan_or_higher();
					$preview_url = wc_get_checkout_url();
					$products    = wc_get_products(
						array(
							'limit'  => 1,
							'status' => 'publish',
							'type'   => array( 'simple' ),
						)
					);

					if ( empty( $products ) ) {
						$products = wc_get_products(
							array(
								'parent_exclude' => 0,
								'limit'          => 1,
								'status'         => 'publish',
								'type'           => array( 'variable' ),
							)
						);
					}

					// Get any simple or variable woocommerce product
					if ( ! empty( $products ) ) {
						$product = $products[0];

						$preview_url = add_query_arg( array( 'add-to-cart' => $product->get_id() ), $preview_url );
					}

					$preview_url = add_query_arg( array( 'cfw-preview' => $template->get_slug() ), $preview_url );
					?>
					<div class="theme max-w-full shadow-lg <?php echo $active ? 'active' : ''; ?> <?php echo $locked ? 'locked' : ''; ?>">
						<div class="theme-screenshot">
							<img src="<?php echo esc_attr( $screenshot ); ?>" class="w-full"/>
						</div>
						<div
							class="flex flex-row justify-between items-center px-4 py-2 <?php echo $active ? 'bg-black text-white' : 'bg-gray-50'; ?> min-h-[50px] border-gray-200">

							<div class="text-base" id="<?php echo esc_attr( $template->get_slug() ); ?>-name">
								<strong>
									<?php echo $active ? esc_html( __( 'Active: ', 'checkout-wc' ) ) : ''; ?>
								</strong>
								<?php echo esc_html( $template->get_name() ); ?>
								<a class="<?php echo $active || $locked ? 'invisible' : ''; ?> block text-sm text-blue-600"
									target="_blank" href="<?php echo esc_attr( $preview_url ); ?>">Preview</a>
							</div>

							<?php if ( $locked ) : ?>
								<div class="flex items-center">
									<a href="https://www.checkoutwc.com/lite-upgrade/?utm_campaign=liteplugin&utm_medium=appearance-templates&utm_source=WordPress&utm_content=Upgrade+to+Premium+to+Unlock" target="_blank" class="button button-primary">
										<?php echo esc_html( __( 'Upgrade to Premium to Unlock', 'checkout-wc' ) ); ?>
									</a>
								</div>
							<?php else : ?>
								<form name="settings" action="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>" method="post">
									<input type="hidden"
											name="<?php echo esc_attr( $settings->get_field_name( 'active_template' ) ); ?>"
											value="<?php echo esc_attr( $template->get_slug() ); ?>"/>
									<?php $settings->the_nonce(); ?>
									<?php submit_button( __( 'Activate', 'checkout-wc' ), 'button-secondary', $name = 'submit', $wrap = false ); ?>
								</form>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function design_tab() {
		?>
		<div id="cfw-admin-pages-appearance-design"></div>
		<?php
	}

	public function get_fonts_list() {
		$cfw_google_fonts_list = get_transient( 'cfw_google_font_list' );

		if ( empty( $cfw_google_fonts_list ) ) {
			$cfw_google_fonts_list = wp_remote_get( 'https://www.googleapis.com/webfonts/v1/webfonts?key=AIzaSyAkSLrj88M_Y-rFfjRI2vgIzjIZ0N1fynE&sort=popularity' );
			$cfw_google_fonts_list = json_decode( wp_remote_retrieve_body( $cfw_google_fonts_list ) );

			set_transient( 'cfw_google_font_list', $cfw_google_fonts_list, 30 * DAY_IN_SECONDS );
		}

		// Remove inter
		foreach ( $cfw_google_fonts_list->items as $key => $font ) {
			if ( 'Inter' === $font->family ) {
				unset( $cfw_google_fonts_list->items[ $key ] );
			}
		}

		return $cfw_google_fonts_list->items;
	}

	public function get_font_settings(): array {
		$font_settings['inter-cfw']         = __( 'Inter (default)', 'checkout-wc' );
		$font_settings['System Font Stack'] = __( 'System Font Stack (fastest)', 'checkout-wc' );
		$cfw_google_fonts_list              = $this->get_fonts_list();

		foreach ( $cfw_google_fonts_list as $font ) {
			$font_settings[ $font->family ] = $font->family;
		}

		return $font_settings;
	}

	/**
	 * @param string|null $template_slug Optional template slug; when provided, use this template for supports/defaults instead of the active one.
	 * @return array
	 */
	public static function get_theme_color_settings( $template_slug = null ): array {
		$active_template = null !== $template_slug ? new Template( $template_slug ) : cfw_get_active_template();
		$color_settings  = array();

		// Body
		$color_settings['body'] = array(
			'title'    => __( 'Body', 'checkout-wc' ),
			'settings' => array(),
		);

		$color_settings['body']['settings']['body_background_color'] = __( 'Background', 'checkout-wc' );
		$color_settings['body']['settings']['body_text_color']       = __( 'Text', 'checkout-wc' );
		$color_settings['body']['settings']['link_color']            = __( 'Link', 'checkout-wc' );

		// Header
		$color_settings['header'] = array(
			'title'    => __( 'Header', 'checkout-wc' ),
			'settings' => array(),
		);

		if ( $active_template->supports( 'header-background' ) ) {
			$color_settings['header']['settings']['header_background_color'] = __( 'Background', 'checkout-wc' );
		}

		$color_settings['header']['settings']['header_text_color'] = __( 'Text', 'checkout-wc' );

		// Footer
		$color_settings['footer'] = array(
			'title'    => __( 'Footer', 'checkout-wc' ),
			'settings' => array(),
		);

		if ( $active_template->supports( 'footer-background' ) ) {
			$color_settings['footer']['settings']['footer_background_color'] = __( 'Background', 'checkout-wc' );
		}

		$color_settings['footer']['settings']['footer_color'] = __( 'Text', 'checkout-wc' );

		// Cart Summary
		$color_settings['cart_summary'] = array(
			'title'    => __( 'Cart Summary', 'checkout-wc' ),
			'settings' => array(),
		);

		if ( $active_template->supports( 'summary-background' ) ) {
			$color_settings['cart_summary']['settings']['summary_background_color'] = __( 'Background', 'checkout-wc' );
			$color_settings['cart_summary']['settings']['summary_text_color']       = __( 'Text', 'checkout-wc' );
		}

		$color_settings['cart_summary']['settings']['summary_link_color'] = __( 'Link', 'checkout-wc' );

		$color_settings['cart_summary']['settings']['summary_mobile_background_color'] = __( 'Mobile Background', 'checkout-wc' );

		$color_settings['cart_summary']['settings']['cart_item_quantity_color']      = __( 'Quantity Bubble Background', 'checkout-wc' );
		$color_settings['cart_summary']['settings']['cart_item_quantity_text_color'] = __( 'Quantity Bubble Text', 'checkout-wc' );

		// Breadcrumbs
		$color_settings['breadcrumbs'] = array(
			'title'    => __( 'Breadcrumbs', 'checkout-wc' ),
			'settings' => array(),
		);

		if ( $active_template->supports( 'breadcrumb-colors' ) ) {
			$color_settings['breadcrumbs']['settings']['breadcrumb_completed_text_color']   = __( 'Completed Text', 'checkout-wc' );
			$color_settings['breadcrumbs']['settings']['breadcrumb_current_text_color']     = __( 'Current Text', 'checkout-wc' );
			$color_settings['breadcrumbs']['settings']['breadcrumb_next_text_color']        = __( 'Next Text', 'checkout-wc' );
			$color_settings['breadcrumbs']['settings']['breadcrumb_completed_accent_color'] = __( 'Completed Accent', 'checkout-wc' );
			$color_settings['breadcrumbs']['settings']['breadcrumb_current_accent_color']   = __( 'Current Accent', 'checkout-wc' );
			$color_settings['breadcrumbs']['settings']['breadcrumb_next_accent_color']      = __( 'Next Accent', 'checkout-wc' );
		}

		$color_settings['buttons'] = array(
			'title'    => __( 'Buttons', 'checkout-wc' ),
			'settings' => array(),
		);

		// Buttons
		$color_settings['buttons']['settings']['button_color']                      = __( 'Primary Background', 'checkout-wc' );
		$color_settings['buttons']['settings']['button_text_color']                 = __( 'Primary Text', 'checkout-wc' );
		$color_settings['buttons']['settings']['button_hover_color']                = __( 'Primary Background Hover', 'checkout-wc' );
		$color_settings['buttons']['settings']['button_text_hover_color']           = __( 'Primary Text Hover', 'checkout-wc' );
		$color_settings['buttons']['settings']['secondary_button_color']            = __( 'Secondary Background', 'checkout-wc' );
		$color_settings['buttons']['settings']['secondary_button_text_color']       = __( 'Secondary Text', 'checkout-wc' );
		$color_settings['buttons']['settings']['secondary_button_hover_color']      = __( 'Secondary Background Hover', 'checkout-wc' );
		$color_settings['buttons']['settings']['secondary_button_text_hover_color'] = __( 'Secondary Text Hover', 'checkout-wc' );

		// Theme Specific Colors
		$color_settings['active_theme_colors'] = array(
			'title'    => __( 'Theme Specific Colors', 'checkout-wc' ),
			/**
			 * Filters the active theme colors settings.
			 *
			 * @param array $color_settings The active theme colors settings.
			 * @since 5.1.0
			 */
			'settings' => apply_filters( 'cfw_active_theme_color_settings', array() ),
		);

		/**
		 * Filters the theme color settings.
		 *
		 * @param array $color_settings The theme color settings.
		 * @since 5.1.0
		 */
		return apply_filters( 'cfw_theme_color_settings', $color_settings );
	}

	/**
	 * @param string|null $template_slug Optional template slug; when provided, use this template for defaults instead of the active one.
	 * @return array
	 */
	public static function get_theme_color_settings_defaults( $template_slug = null ): array {
		$template        = null !== $template_slug ? new Template( $template_slug ) : cfw_get_active_template();
		$color_settings  = self::get_theme_color_settings( $template_slug );
		$defaults        = array();

		foreach ( $color_settings as $color_setting_section ) {
			foreach ( $color_setting_section['settings'] as $key => $label ) {
				$defaults[ $key ] = $template->get_default_setting( $key );
			}
		}

		return $defaults;
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$template_slug      = cfw_get_active_template()->get_slug();
		$settings           = array(
			SettingsManager::instance()->add_suffix( 'logo_attachment_id', array( $template_slug ) ) => SettingsManager::instance()->get_setting( 'logo_attachment_id', array( $template_slug ) ),
			SettingsManager::instance()->add_suffix( 'label_style', array( $template_slug ) )        => SettingsManager::instance()->get_setting( 'label_style', array( $template_slug ) ),
			SettingsManager::instance()->add_suffix( 'footer_text', array( $template_slug ) )        => SettingsManager::instance()->get_setting( 'footer_text', array( $template_slug ) ),
			SettingsManager::instance()->add_suffix( 'custom_css', array( $template_slug ) )        => SettingsManager::instance()->get_setting( 'custom_css', array( $template_slug ) ),
			'footer_text_editor_mode' => SettingsManager::instance()->get_setting( 'footer_text_editor_mode' ),
		);
		$raw_color_settings = self::get_theme_color_settings();

		foreach ( $raw_color_settings as $color_setting_section ) {
			foreach ( $color_setting_section['settings'] as $key => $label ) {
				$settings[ SettingsManager::instance()->add_suffix( $key, array( $template_slug ) ) ] = SettingsManager::instance()->get_setting( $key, array( $template_slug ) );
			}
		}

		$settings[ SettingsManager::instance()->add_suffix( 'body_font', array( $template_slug ) ) ]    = SettingsManager::instance()->get_setting( 'body_font', array( $template_slug ) );
		$settings[ SettingsManager::instance()->add_suffix( 'heading_font', array( $template_slug ) ) ] = SettingsManager::instance()->get_setting( 'heading_font', array( $template_slug ) );

		$this->set_script_data(
			array(
				'settings' => $settings,
				'params'   => array(
					'font_options'            => $this->get_font_settings(),
					'template_path'           => cfw_get_active_template()->get_slug(),
					'color_settings'          => self::get_theme_color_settings(),
					'color_settings_defaults' => self::get_theme_color_settings_defaults(),
					'logo_preview_url'        => wp_get_attachment_url( SettingsManager::instance()->get_setting( 'logo_attachment_id', array( cfw_get_active_template()->get_slug() ) ) ),
				),
				'plan'     => $this->get_plan_data(),
			)
		);
	}
}
