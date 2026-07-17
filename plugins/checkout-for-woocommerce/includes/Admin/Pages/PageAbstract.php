<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;
use WP_Admin_Bar;

/**
 * Class Admin
 *
 * @link checkoutwc.com
 * @since 1.0.0
 * @package Objectiv\Plugins\Checkout\Core
 */
abstract class PageAbstract {
	protected $title;
	protected $capability;
	protected $slug;
	protected $priority           = 100;
	protected static $parent_slug = 'cfw-settings';

	/**
	 * PageAbstract constructor.
	 *
	 * @param string      $title The title of the page.
	 * @param string      $capability The capability required to access the page.
	 * @param string|null $slug The slug of the page.
	 */
	public function __construct( string $title, string $capability, ?string $slug = null ) {
		$this->title      = $title;
		$this->capability = $capability;
		$this->slug       = join( '-', array_filter( [ self::$parent_slug, $slug ] ) );
	}

	/**
	 * Set priority of page in menu
	 *
	 * @param int $priority The priority of the page.
	 * @return $this
	 */
	public function set_priority( int $priority ): PageAbstract {
		$this->priority = $priority;

		return $this;
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'setup_menu' ], $this->priority );
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu_node' ], 100 + $this->priority );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_set_script_data' ], 1000 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 1001 );
	}

	public function setup_menu() {
		add_submenu_page( self::$parent_slug, $this->title, $this->title, $this->capability, $this->slug, [ $this, 'output_with_wrap' ], $this->priority );
	}

	public function get_url(): string {
		$url = add_query_arg( 'page', $this->slug, admin_url( 'admin.php' ) );

		return esc_url( $url );
	}

	public function is_current_page(): bool {
		return sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) ) === $this->slug; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	abstract public function output();

	/**
	 * The admin page wrap
	 *
	 * @since 1.0.0
	 */
	public function output_with_wrap() {
		cfw_do_action( 'cfw_admin_output_page', $this->get_slug() );
		$hide_settings_button = ( isset( $_GET['subpage'] ) && 'templates' === $_GET['subpage'] ) || ( isset( $_GET['page'] ) && 'cfw-settings-appearance' === $_GET['page'] && empty( $_GET['subpage'] ) ) || ( isset( $_GET['page'] ) && 'cfw-settings-support' === $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="cfw-admin-notices-container">
			<div class="wp-header-end"></div>
			<div id="cfw-custom-admin-notices"></div>
		</div>
		<div class="cfw-tw">
			<div id="cfw_admin_page_header" class="fixed top-0 divide-y shadow z-50">
				<?php
				/**
				 * Fires before the admin page header.
				 *
				 * @param PageAbstract The admin page.
				 * @since 7.0.0
				 */
				do_action( 'cfw_before_admin_page_header', $this );
				?>
				<div class="min-h-[64px] bg-white flex items-center pl-8 justify-between">
					<div class="flex items-center">
						<span>
							<?php echo file_get_contents( CFW_PATH . '/assets/images/cfw.svg' ); // phpcs:ignore ?>
						</span>
						<nav class="flex" aria-label="Breadcrumb">
							<ol role="list" class="flex items-center space-x-2">
								<li class="m-0">
									<div class="flex items-center">
										<span class="ml-2 text-sm font-medium text-gray-800">
											<?php _e( 'CheckoutWC', 'checkout-wc' ); ?>
										</span>
									</div>
								</li>
								<li class="m-0">
									<div class="flex items-center">
										<!-- Heroicon name: solid/chevron-right -->
										<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
											<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
										</svg>
										<span class="ml-2 text-sm font-medium text-gray-500" aria-current="page">
											<?php echo esc_html( wp_strip_all_tags( $this->title ) ); ?>
										</span>
									</div>
								</li>
							</ol>
						</nav>
					</div>

					<div class="flex items-center space-x-4 mr-10">
						<div id="cfw_unsaved_changes_notice" class="hidden flex items-center text-sm text-orange-600">
							<svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
							</svg>
							<span><?php _e( 'Unsaved changes.', 'checkout-wc' ); ?></span>
							<a href="#" id="cfw_discard_changes_button" class="ml-2 text-sm text-gray-500 hover:text-gray-700 underline">
								<?php _e( 'Discard?', 'checkout-wc' ); ?>
							</a>
						</div>
						<button type="button" id="cfw_admin_header_save_button" class="cfw-save-inactive cfw-shake-animation <?php echo $hide_settings_button ? 'invisible' : ''; ?> inline-flex items-center px-3.5 py-2 border border-transparent text-sm leading-4 font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
							<?php _e( 'Save Changes', 'checkout-wc' ); ?>
						</button>
					</div>
				</div>
				<?php
				/**
				 * Fires after the admin page header.
				 *
				 * @param PageAbstract The admin page.
				 * @since 7.0.0
				 */
				do_action( 'cfw_after_admin_page_header', $this );
				?>
			</div>

			<div class="cfw-admin-content-wrap cfw-admin-screen-<?php echo esc_attr( sanitize_title_with_dashes( $this->title ) ); ?> p-10 z-10">
				<?php $this->output(); ?>

				<?php if ( ! $hide_settings_button ) : ?>
					<div class="flex justify-end mt-2">
						<button type="button" id="cfw_admin_footer_save_button" class="inline-flex items-center px-3.5 py-2 border border-transparent text-sm leading-4 font-medium rounded-full shadow-sm text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
							<?php _e( 'Save Changes', 'checkout-wc' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function premium_lock_html( $required_plan = 'basic' ) {
		?>
		<!-- Lock Overlay -->
		<div class="fixed backdrop-blur-[2px] bg-white/40 inset-0 flex flex-col items-center justify-center z-50">
			<div class="space-y-4">
				<p class="flex justify-center">
					<a href="https://www.checkoutwc.com/lite-upgrade/?utm_campaign=liteplugin&utm_medium=admin-page-<?php echo esc_attr( $this->get_slug() ); ?>&utm_source=WordPress&utm_content=Unlock+with+Premium" class="bg-blue-600 hover:bg-blue-700 text-white hover:text-white font-bold py-4 px-6 rounded">
						Unlock with Premium
					</a>
				</p>

				<div class="text-center italic">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: Required plan name(s) */
							__( 'A %s plan is required to access this feature.', 'checkout-wc' ),
							PlanManager::get_english_list_of_required_plans_html( $required_plan )
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}

	public function maybe_show_overridden_setting_notice( $show = false, $replacement_text = '' ) {
		if ( ! $show ) {
			return;
		}
		?>
		<div class='cfw-notification-message'>
			<strong><?php _e( 'Setting Overridden', 'checkout-wc' ); ?></strong> &mdash;

			<?php if ( empty( $replacement_text ) ) : ?>
				<?php _e( 'This setting is currently programmatically overridden. To enable it remove your custom code.', 'checkout-wc' ); ?>
			<?php else : ?>
				<?php echo esc_html( $replacement_text ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function output_form_open( $id = null ) {
		$action = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		$action = remove_query_arg( 'cfw_welcome', $action );
		?>
		<form name="settings" id="<?php echo $id ? esc_attr( $id ) : 'cfw_settings_form'; ?>" action="<?php echo esc_attr( sanitize_text_field( $action ) ); ?>" method="post">
			<?php
			SettingsManager::instance()->the_nonce();
	}

	public function output_form_close( $include_button = true ) {
		?>
			<?php if ( $include_button ) : ?>
			<input type="submit" name="submit" class="cfw_admin_page_submit hidden" value="submit" />
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Output toggle checkbox
	 *
	 * @param string $setting The setting name.
	 * @param string $label The label of the checkbox.
	 * @param string $description The description of the checkbox.
	 * @param bool   $enabled Whether the checkbox is enabled.
	 * @param bool   $show_overridden_notice Whether to show the overridden notice.
	 * @param string $overridden_notice The overridden notice.
	 */
	public function output_toggle_checkbox( string $setting, string $label, string $description, bool $enabled = true, bool $show_overridden_notice = false, string $overridden_notice = '' ) {
		$settings   = SettingsManager::instance();
		$field_name = $settings->get_field_name( $setting );
		$value      = $settings->get_setting( $setting );
		$field_id   = "cfw_{$setting}";
		?>
		<div class="cfw-admin-field-container cfw-toggle-container relative flex items-start">
			<div class="flex items-center h-11">
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="no" />
				<input <?php echo ! $enabled ? 'disabled' : ''; ?> type="checkbox" class="cfw-toggle-checkbox cfw-toggle-checkbox-<?php echo esc_attr( $setting ); ?>" name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="yes" <?php echo 'yes' === $value ? 'checked' : ''; ?> />

				<label class="cfw-toggle-checkbox-label cfw-toggle-checkbox-label-<?php echo esc_attr( $setting ); ?>" for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			</div>

			<div class="ml-3 text-sm">
				<label class="cfw-toggle-checkbox-text-label font-medium text-gray-700" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>

				<p class="text-gray-500">
					<?php echo esc_html( $description ); ?>
				</p>
			</div>
		</div>
		<?php $this->maybe_show_overridden_setting_notice( $show_overridden_notice, $overridden_notice ); ?>
		<?php
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
				'id'     => $this->slug,
				'title'  => $this->title,
				'href'   => $this->get_url(),
				'parent' => self::$parent_slug,
			]
		);
	}

	/**
	 * Can show admin bar button?
	 *
	 * @return bool
	 */
	public function can_show_admin_bar_button(): bool {
		/**
		 * Filters whether to show the admin bar button
		 *
		 * @param bool $show Whether to show the admin bar button
		 * @since 3.0.0
		 */
		if ( ! apply_filters( 'cfw_do_admin_bar', current_user_can( 'manage_options' ) && ( SettingsManager::instance()->get_setting( 'hide_admin_bar_button' ) !== 'yes' || is_cfw_page() ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get parent slug
	 *
	 * @return string
	 */
	public static function get_parent_slug(): string {
		return self::$parent_slug;
	}

	/**
	 * Get slug
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * @param string $required_plans The required plans.
	 * @return string
	 */
	public function get_old_style_upgrade_required_notice( string $required_plans ): string {
		ob_start();
		?>
		<div class='cfw-license-upgrade-blocker-og cfw-tw'>
			<div class="inner text-base">
				<h3 class="text-xl font-bold mb-4">
					<?php _e( 'Upgrade Your Plan', 'checkout-wc' ); ?>
				</h3>

				<?php
				echo wp_kses_post(
				sprintf(
					/* translators: %s: Required plan name(s) */
					__( 'A %s plan is required to access this feature.', 'checkout-wc' ),
					$required_plans
				)
				);
				?>
				<p class="text-base">
					<?php
					echo wp_kses_post(
					sprintf(
						/* translators: %1$s: Account URL, %2$s: Help URL */
						__( 'You can upgrade your license in <a class="text-blue-600 underline" target="_blank" href="%1$s">Account</a>. For help upgrading your license, <a class="text-blue-600 underline" target="_blank" href="%2$s">click here.</a>', 'checkout-wc' ),
						'https://www.checkoutwc.com/account/',
						'https://kb.checkoutwc.com/article/53-upgrading-your-license'
					)
					);
					?>
				</p>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	public function enqueue_scripts() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		cfw_register_scripts( [ 'admin-settings' ] );

		wp_localize_script(
			'cfw-admin-settings',
			'cfwAdminPagesData',
			$this->get_script_data()
		);

		wp_enqueue_script( 'cfw-admin-settings' );
	}

	public function get_script_data(): array {
		/**
		 * Filter the admin page data
		 *
		 * @since 9.0.0
		 * @param array $data
		 */
		return apply_filters(
			'cfw_admin_page_data',
			[]
		);
	}

	public function maybe_set_script_data() {
		// Silence is golden
	}

	public function set_script_data( $data ) {
		add_filter(
			'cfw_admin_page_data',
			function () use ( $data ) {
				return $data;
			}
		);
	}

	public function get_plan_data(): array {
		$data = [
			'plan_id'    => UpdatesManager::instance()->get_license_price_id(),
			'plan_level' => PlanManager::get_user_plan_level(),
			'labels'     => [],
		];

		foreach ( PlanManager::PLAN_HIERARCHY as $plan => $level ) {
			$data['labels']['required_list'][ $level ] = PlanManager::get_english_list_of_required_plans_html( $plan );
		}

		return $data;
	}
}
