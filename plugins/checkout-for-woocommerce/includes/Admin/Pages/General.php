<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;
use WP_Admin_Bar;

/**
 * Start Here admin page
 *
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class General extends PageAbstract {
	use TabbedAdminPageTrait;

	protected $appearance_page;

	public function __construct( Appearance $appearance_page ) {
		$this->appearance_page = $appearance_page;
		parent::__construct( __( 'Start Here', 'checkout-wc' ), 'cfw_manage_general' );
	}

	public function init() {
		parent::init();

		add_action( 'admin_bar_menu', [ $this, 'add_parent_node' ], 100 );
		add_action( 'admin_menu', [ $this, 'setup_main_menu_page' ], $this->priority - 5 );
	}

	public function setup_menu() {
		add_submenu_page( self::$parent_slug, $this->title, $this->title, $this->capability, $this->slug, null, $this->priority );

		add_submenu_page(
			self::$parent_slug,
			__( 'Checkout Editor', 'checkout-wc' ),
			__( 'Checkout Editor', 'checkout-wc' ),
			'cfw_manage_pages',
			'admin.php?page=cfw-settings-checkout-editor',
			null
		);
	}

	public function setup_main_menu_page() {
		add_menu_page( 'CheckoutWC', 'CheckoutWC', 'cfw_manage_general', self::$parent_slug, array( $this, 'output_with_wrap' ), 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ) ) ); // phpcs:ignore
	}

	public function output() {
		if ( isset( $_GET['upgrade'] ) && '10' === $_GET['upgrade'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->output_upgrade_notice();
			return;
		}

		?>
		<div class="max-w-3xl pb-8">
			<div>
				<p class="text-5xl font-bold text-gray-900">
					<?php _e( 'Welcome to the new standard for WooCommerce stores.', 'checkout-wc' ); ?>
				</p>
				<p class="max-w-xl mt-5 text-2xl text-gray-500">
					<?php _e( 'We hate complex configurations too. Get up and running with CheckoutWC in 5 minutes or less. ⚡️', 'checkout-wc' ); ?>
				</p>
				<p class="mt-6">
					<a href="https://kb.checkoutwc.com" target="_blank" class="inline-flex items-center px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
						<?php _e( 'Read Our Documentation', 'checkout-wc' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php

		$this->getting_started_tab();
	}

	public function output_upgrade_notice() {
		?>
		<div class="max-w-5xl pb-8">
			<div>
				<p class="text-5xl font-bold text-gray-900">
					<?php _e( 'Welcome to CheckoutWC 10.0', 'checkout-wc' ); ?>
				</p>
				<p class="max-w-xl mt-5 text-2xl text-gray-500">
					<?php _e( 'CheckoutWC 10.0 is a major update with new features and optimizations to make your WooCommerce store run better.', 'checkout-wc' ); ?>
				</p>

				<div class="grid grid-cols-2 gap-8 mt-12">
					<div>
						<p class="text-4xl font-bold text-gray-900">
							<?php _e( 'Just Say When', 'checkout-wc' ); ?>
						</p>
						<p class="max-w-xl mt-5 text-2xl text-gray-500">
							<?php _e( 'Our new rules engine puts you in charge. Want to show an Order Bump only to first time customers who use a specific coupon code? You can do that. Want to show specific trust badges based on what is in the cart? You can do that too!', 'checkout-wc' ); ?>
						</p>
					</div>
					<div>
						<img src="<?php echo trailingslashit( CFW_PATH_URL_BASE ); ?>assets/images/rules.png" alt="Rules Engine" />
					</div>
				</div>

				<div class="grid grid-cols-2 gap-8 mt-12">
					<div>
						<img src="<?php echo trailingslashit( CFW_PATH_URL_BASE ); ?>assets/images/quick-start.avif" alt="Order Bumps Quick Start" />
					</div>
					<div>
						<p class="text-4xl font-bold text-gray-900">
							<?php _e( 'Get Started Faster', 'checkout-wc' ); ?>
						</p>
						<p class="max-w-xl mt-5 text-2xl text-gray-500">
							<?php _e( 'Creating Order Bumps has lots of options. Our new Quick Start will help you get started faster.', 'checkout-wc' ); ?>
						</p>
						<p class="mt-5">
							<a href="<?php echo esc_attr( add_query_arg( [ 'page' => 'cfw-settings-order_bumps' ], admin_url( 'admin.php' ) ) ); ?>" target="_blank" class="inline-flex items-center px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
								<?php _e( 'Configure Order Bumps', 'checkout-wc' ); ?>
							</a>
						</p>
					</div>
				</div>

				<div class="grid grid-cols-2 gap-8 mt-12">
					<div>
						<p class="text-4xl font-bold text-gray-900">
							<?php _e( 'And a lot more', 'checkout-wc' ); ?>
						</p>
						<p class="max-w-xl mt-5 text-2xl text-gray-500">
							<?php _e( 'To view the full list of changes, checkout our change log.', 'checkout-wc' ); ?>
						</p>
						<p class="mt-5">
							<a href="https://www.checkoutwc.com/documentation/change-log/" target="_blank" class="inline-flex items-center px-6 py-3 border border-transparent text-lg shadow font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
								<?php _e( 'Read Our Change Log', 'checkout-wc' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function getting_started_tab() {
		$this->output_form_open();
		?>
		<div class="space-y-8 mt-4">
			<?php
			cfw_admin_page_section(
				PlanManager::has_premium_plan_or_higher() ? __( 'Step 1: Activate Your License', 'checkout-wc' ) : __( 'Step 1: Try CheckoutWC Premium Free For 7 Days', 'checkout-wc' ),
				PlanManager::has_premium_plan_or_higher() ? __( 'Enter your license key. An active license is required for all functionality.', 'checkout-wc' ) : __( 'Supercharge your WooCommerce store.', 'checkout-wc' ),
				defined( 'CFW_PREMIUM_PLAN_IDS' ) ? $this->get_licensing_settings() : $this->trial_offer()
			);

			cfw_admin_page_section(
				__( 'Step 2: Customize Checkout', 'checkout-wc' ),
				__( 'Use the Checkout Editor to customize your checkout layout, fields, and content.', 'checkout-wc' ),
				$this->get_checkout_editor_content()
			);

			cfw_admin_page_section(
				__( 'Step 3: Go Live', 'checkout-wc' ),
				__( 'Enable templates for all visitors.', 'checkout-wc' ),
				$this->get_activation_settings()
			);
			?>
		</div>
		<?php
		$this->output_form_close();

		if ( isset( $_GET['cfw_debug_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$all_settings = SettingsManager::instance()->get_settings_obj();

			echo '<div class="max-w-lg">';
			foreach ( $all_settings as $key => $value ) {
				echo '<h3 class="text-base font-bold mb-4">' . $key . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<pre class="shadow-sm bg-white p-6 focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md mb-6">' . $value . '</pre>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		}
	}

	public function get_activation_settings() {
		ob_start();

		$free_plan = ! defined( 'CFW_PREMIUM_PLAN_IDS' );

		$prefix = '';

		if ( ! $free_plan ) {
			$prefix = __( 'Requires a valid and active license key.', 'checkout-wc' ) . ' ';
		}

		$this->output_toggle_checkbox(
			'enable',
			__( 'Activate CheckoutWC Templates', 'checkout-wc' ),
			$prefix . __( 'CheckoutWC Templates are always activated for admin users.', 'checkout-wc' )
		);

		?>
		<p class="mt-1 text-sm text-gray-500 font-bold">
			<?php echo esc_html__( 'This step can also be completed directly from the Checkout Editor!', 'checkout-wc' ); ?>
		</p>
		<?php

		return ob_get_clean();
	}

	public function get_licensing_settings() {
		ob_start();

		UpdatesManager::instance()->admin_page_fields();

		return ob_get_clean();
	}

	public function trial_offer() {
		ob_start();
		?>
		<div class="flex flex-row items-center">
			<a href="https://www.checkoutwc.com/lite-upgrade/?utm_campaign=liteplugin&utm_medium=start-here-step1&utm_source=WordPress&utm_content=Upgrade%20to%20CheckoutWC%20Premium%20Now%20-%20Save%2025" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<?php _e( 'Upgrade to CheckoutWC Premium Now - Save 25%', 'checkout-wc' ); ?>
			</a>
			<svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-label="<?php _e( 'Opens in new tab' ); ?>">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}

	public function get_checkout_editor_content() {
		ob_start();
		?>
		<div class="flex flex-row items-center">
			<a href="<?php echo esc_attr( admin_url( 'admin.php?page=cfw-settings-checkout-editor' ) ); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<?php _e( 'Customize Your Checkout', 'checkout-wc' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	public function get_pick_template_content() {
		ob_start();
		?>
		<div class="flex flex-row items-center">
			<a href="<?php echo esc_attr( add_query_arg( [ 'subpage' => 'templates' ], $this->appearance_page->get_url() ) ); ?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<?php _e( 'Choose a Template', 'checkout-wc' ); ?>
			</a>
			<svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-label="<?php _e( 'Opens in new tab' ); ?>">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}

	public function get_design_content() {
		ob_start();
		?>
		<div class="flex flex-row items-center">
			<a href="<?php echo esc_attr( add_query_arg( 'subpage', 'design', $this->appearance_page->get_url() ) ); ?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<?php _e( 'Customize Logo and Colors', 'checkout-wc' ); ?>
			</a>
			<svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-label="<?php _e( 'Opens in new tab' ); ?>">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}

	public function get_preview_content() {
		$url = wc_get_checkout_url();

		$products = wc_get_products(
			[
				'limit'        => 1,
				'status'       => 'publish',
				'type'         => [ 'simple' ],
				'stock_status' => 'instock',
			]
		);

		if ( empty( $products ) ) {
			$products = wc_get_products(
				[
					'parent_exclude' => 0,
					'limit'          => 1,
					'status'         => 'publish',
					'type'           => [ 'variable' ],
					'stock_status'   => 'instock',
				]
			);
		}

		// Get any simple or variable woocommerce product
		if ( ! empty( $products ) ) {
			$product = $products[0];

			$url = add_query_arg( [ 'add-to-cart' => $product->get_id() ], $url );
		}

		ob_start();
		?>
		<div class="flex flex-row items-center">
			<a href="<?php echo esc_attr( $url ); ?>" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
				<?php _e( 'Preview Your Checkout Page', 'checkout-wc' ); ?>
			</a>
			<svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-label="<?php _e( 'Opens in new tab' ); ?>">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
			</svg>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add parent node
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar object.
	 */
	public function add_parent_node( WP_Admin_Bar $admin_bar ) {
		if ( ! $this->can_show_admin_bar_button() ) {
			return;
		}

		if ( cfw_is_checkout() ) {
			// Remove irrelevant buttons
			$admin_bar->remove_node( 'new-content' );
			$admin_bar->remove_node( 'updates' );
			$admin_bar->remove_node( 'edit' );
			$admin_bar->remove_node( 'comments' );
		}

		$url = $this->get_url();

		$admin_bar->add_node(
			[
				'id'     => self::$parent_slug,
				'title'  => '<span class="ab-icon dashicons dashicons-cart"></span>' . __( 'CheckoutWC', 'checkout-wc' ),
				'href'   => $url,
				'parent' => false,
			]
		);

		if ( ! is_cfw_page() ) {
			return;
		}

		$admin_bar->add_node(
			[
				'id'     => self::$parent_slug . '-bypass',
				'title'  => isset( $_GET['bypass-cfw'] ) ? '<span class="ab-icon dashicons dashicons-controls-play"></span>' . __( 'Unbypass CheckoutWC Template', 'checkout-wc' ) : '<span class="ab-icon dashicons dashicons-controls-pause"></span>' . __( 'Bypass CheckoutWC Template', 'checkout-wc' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'href'   => isset( $_GET['bypass-cfw'] ) ? remove_query_arg( 'bypass-cfw' ) : add_query_arg( 'bypass-cfw', 'true' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'parent' => false,
				'meta'   => [
					'onclick' => ! isset( $_GET['bypass-cfw'] ) ? 'alert("' . esc_js( __( 'CheckoutWC template and functionality will be temporarily bypassed (just for you!). This is helpful for testing and debugging. You can click Unbypass CheckoutWC Template once you are done.', 'checkout-wc' ) ) . '")' : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				],
			]
		);
	}

	/**
	 * Add admin bar menu node
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar object.
	 */
	public function add_admin_bar_menu_node( WP_Admin_Bar $admin_bar ) {
		if ( ! $this->can_show_admin_bar_button() ) {
			return;
		}

		$admin_bar->add_node(
			[
				'id'     => $this->slug . '-general',
				'title'  => $this->title,
				'href'   => $this->get_url(),
				'parent' => self::$parent_slug,
			]
		);
	}
}
