<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages\Premium;

use CheckoutWC\Pelago\Emogrifier\CssInliner;
use Exception;
use Objectiv\Plugins\Checkout\Admin\Pages\PageAbstract;
use Objectiv\Plugins\Checkout\Admin\TabNavigation;
use Objectiv\Plugins\Checkout\Admin\Pages\Traits\TabbedAdminPageTrait;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class AbandonedCartRecovery extends PageAbstract {
	use TabbedAdminPageTrait;

	protected $acr_feature;

	public function __construct( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery $acr_feature ) {
		parent::__construct( __( 'Cart Recovery', 'checkout-wc' ), 'cfw_manage_acr', 'acr' );

		$this->acr_feature = $acr_feature;
	}

	public function init() {
		parent::init();

		$this->set_tabbed_navigation( new TabNavigation( 'settings' ) );

		$this->get_tabbed_navigation()->add_tab( __( 'Settings', 'checkout-wc' ), add_query_arg( [ 'subpage' => 'settings' ], $this->get_url() ), false, 'cfw_manage_acr' );
		$this->get_tabbed_navigation()->add_tab(
			__( 'Emails', 'checkout-wc' ),
			add_query_arg(
				[
					'post_type' => \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type(),
				],
				admin_url( 'edit.php' )
			),
			'emails',
			'cfw_manage_acr'
		);
		$this->get_tabbed_navigation()->add_tab( __( 'Report', 'checkout-wc' ), add_query_arg( [ 'subpage' => 'report' ], $this->get_url() ) );

		add_filter( 'wp_insert_post_data', [ $this, 'maybe_prevent_post_publication' ], '99', 2 );
		add_action( 'admin_notices', [ $this, 'maybe_show_post_pending_notice' ] );
		add_filter( 'replace_editor', [ $this, 'replace_editor' ], 10, 2 );
		add_action( 'all_admin_notices', [ $this, 'output_post_type_editor_header' ] );

		/**
		 * Highlights ACR submenu item when appropriate
		 */
		add_filter( 'submenu_file', [ $this, 'maybe_highlight_acr_submenu_item' ] );

		/**
		 * Highlight parent menu
		 */
		add_filter( 'parent_file', [ $this, 'menu_highlight' ] );

		/**
		 * Highlight emails tab
		 */
		add_filter( 'cfw_selected_tab', [ $this, 'maybe_set_emails_tab' ] );

		/**
		 * MBs
		 */
		add_action( 'edit_form_after_title', [ $this, 'add_email_subject_line_and_prehead' ] );
		add_action( 'edit_form_after_editor', [ $this, 'add_other_email_options' ] );
		add_filter( 'enter_title_here', [ $this, 'change_email_title_placeholder' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_custom_fields' ], 10, 2 );

		// Enable font size & font family selects in the editor
		add_filter(
			'mce_buttons_2',
			function ( $buttons ) {
			// Only for our post type
				if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== get_post_type() ) {
					return $buttons;
				}

				array_unshift( $buttons, 'fontselect' ); // Add Font Select
				array_unshift( $buttons, 'fontsizeselect' ); // Add Font Size Select

				return $buttons;
			}
		);

		$post_type = \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type();

		add_filter(
			"manage_{$post_type}_posts_columns",
			function ( $columns ) {
				$date = array_pop( $columns );

				$columns['cfw_email_subject_col'] = __( 'Subject', 'checkout-wc' );
				$columns['cfw_send_after_col']    = __( 'Send After', 'checkout-wc' ) . wc_help_tip( __( 'Send this long after cart has been abandoned.', 'checkout-wc' ) );
				$columns['cfw_email_active_col']  = __( 'Active', 'checkout-wc' ) . wc_help_tip( __( 'Active (published) emails, are sent to customers. Inactive (draft / unpublished) emails are not.', 'checkout-wc' ) );

				return $columns;
			}
		);

		add_action(
			"manage_{$post_type}_posts_custom_column",
			function ( $column, $post_id ) {
				if ( 'cfw_email_subject_col' === $column ) {
					echo esc_html( get_post_meta( $post_id, 'cfw_subject', true ) );
				}

				if ( 'cfw_send_after_col' === $column ) {
					$cfw_email_wait      = get_post_meta( $post_id, 'cfw_wait', true );
					$cfw_email_wait_unit = get_post_meta( $post_id, 'cfw_wait_unit', true );

					echo esc_html( $cfw_email_wait . ' ' . $cfw_email_wait_unit );
				}

				if ( 'cfw_email_active_col' === $column ) {
					$active = get_post_status( $post_id ) === 'publish' ? 'Active' : 'Inactive';

					echo esc_html( $active );
				}
			},
			10,
			2
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'add_acr_localized_variables' ], 1010 );
		add_filter( 'mce_buttons', [ $this, 'add_mce_button' ] );
		add_filter( 'mce_external_plugins', [ $this, 'add_mce_plugin' ], 9 );

		// Send Preview Email
		add_action( 'wp_ajax_cfw_acr_preview_email_send', [ $this, 'send_preview_email' ] );
	}

	public function output() {
		if ( ! empty( $notice ) ) {
			echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$current_tab_function = $this->get_tabbed_navigation()->get_current_tab() . '_tab';
		$callable             = [ $this, $current_tab_function ];

		$this->get_tabbed_navigation()->display_tabs();

		call_user_func( $callable );
	}

	public function report_tab() {
		?>
		<style>
			#cfw_admin_header_save_button {
				display: none;
			}
		</style>
		<div id="cfw-acr-reports"></div>
		<div id="cfw-acr-carts"></div>
		<?php
	}

	public function settings_tab() {
		?>
		<div id="cfw-admin-pages-acr-settings"></div>
		<?php
	}

	protected function get_cron_notice() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return '';
		}

		ob_start();
		?>
		<div class="bg-white shadow sm:rounded-lg mb-6">
			<div class="px-4 py-5 sm:p-6">
				<h3 class="text-base font-semibold leading-6 text-gray-900">
					<?php _e( 'Error: WP Cron Configured Incorrectly!', 'checkout-wc' ); ?>
				</h3>
				<div class="mt-2 sm:flex sm:items-start sm:justify-between">
					<div class="max-w-xl text-sm text-gray-500">
						<p class="mb-2">
							<?php _e( 'It looks like WP Cron is enabled which will cause issues with tracking carts and sending emails.', 'checkout-wc' ); ?>
						</p>
						<p class="mb-2">
							<?php _e( 'To properly configure WP Cron for ACR, please read our guide:', 'checkout-wc' ); ?>
							<br/>
							<a class="text-blue-600 underline" target="_blank"
								href="https://www.checkoutwc.com/documentation/how-to-ensure-your-wordpress-cron-is-working-properly/">Properly
								configure WordPress cron for Abandoned Cart Recovery</a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The admin page wrap
	 *
	 * @since 1.0.0
	 */
	public function output_post_type_editor_header() {
		global $post;

		if ( isset( $_GET['post_type'] ) && \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		} elseif ( isset( $post ) && \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post->post_type ) {
			return;
		} elseif ( ! isset( $_GET['post_type'] ) && ! isset( $post ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<div class="cfw-admin-notices-container">
			<div class="wp-header-end"></div>
			<div id="cfw-custom-admin-notices"></div>
		</div>
		<div class="cfw-tw">
			<div id="cfw_admin_page_header" class="absolute left-0 right-0 top-0 divide-y shadow z-50">
				<?php
				/**
				 * Fires before the admin page header
				 *
				 * @param AbandonedCartRecovery $this The AbandonedCartRecovery instance.
				 *
				 * @since 7.0.0
				 */
				do_action( 'cfw_before_admin_page_header', $this );
				?>
				<div class="min-h-[64px] bg-white flex items-center pl-8">
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
									<svg class="flex-shrink-0 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
										<path fill-rule="evenodd"
												d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
												clip-rule="evenodd"/>
									</svg>
									<span class="ml-2 text-sm font-medium text-gray-500" aria-current="page">
										<?php echo wp_kses_post( $this->title ); ?>
									</span>
								</div>
							</li>
						</ol>
					</nav>
				</div>
				<?php
				/**
				 * Fires after the admin page header
				 *
				 * @param AbandonedCartRecovery $this The AbandonedCartRecovery instance.
				 *
				 * @since 7.0.0
				 */
				do_action( 'cfw_after_admin_page_header', $this );
				?>
			</div>

			<div class="mt-10 mr-4">
				<?php $this->get_tabbed_navigation()->display_tabs(); ?>
			</div>
		</div>
		<?php
	}

	public function is_current_page(): bool {
		global $post;

		if ( parent::is_current_page() ) {
			return true;
		}

		if ( isset( $_GET['post_type'] ) && \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( $post && \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() === $post->post_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Hide 'Emails' post type from CFW settings submenu
	 *
	 * This keeps the submenu open when editing an email
	 *
	 * @return void
	 */
	public function setup_menu() {
		parent::setup_menu();

		global $submenu;

		$post_type_slug = \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type();

		if ( empty( $submenu[ self::$parent_slug ] ) ) {
			return;
		}

		foreach ( (array) $submenu[ self::$parent_slug ] as $i => $item ) {
			if ( 'edit.php?post_type=' . $post_type_slug === $item[2] ) {
				unset( $submenu[ self::$parent_slug ][ $i ] );
			}
		}
	}

	/**
	 * Maybe highlight the ACR submenu item if we're on the posts sreen or editing a post
	 *
	 * @param mixed $submenu_file The submenu file.
	 *
	 * @return mixed
	 */
	public function maybe_highlight_acr_submenu_item( $submenu_file ) {
		global $post;

		$post_type = \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type();

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $this->get_slug();
		} elseif ( $post && $post->post_type === $post_type ) {
			return $this->get_slug();
		}

		return $submenu_file;
	}

	public function menu_highlight( $parent_file ) {
		global $plugin_page, $post_type;

		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() === $post_type ) {
			$plugin_page = PageAbstract::$parent_slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $parent_file;
	}

	/**
	 * Set the emails tab as active when on emails pages
	 *
	 * @param string $selected_tab The currently selected tab.
	 * @return string
	 */
	public function maybe_set_emails_tab( $selected_tab ) {
		global $post;

		$post_type = \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type();

		// Check if we're on any email related page
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === $post_type ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'emails';
		} elseif ( $post && $post->post_type === $post_type ) {
			return 'emails';
		}

		return $selected_tab;
	}

	public function add_email_subject_line_and_prehead( $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post->post_type ) {
			return;
		}

		$subject = get_post_meta( $post->ID, 'cfw_subject', true );

		?>
		<div>
			<div>
				<label id="cfw_email_subject-label"
						for="cfw_email_subject"><?php _e( 'Subject', 'checkout-wc' ); ?></label>
				<input type="text" placeholder="<?php _e( 'Enter Email Subject', 'checkout-wc' ); ?>"
						name="cfw_email_subject" size="30" value="<?php echo esc_attr( $subject ); ?>"
						id="cfw_email_subject" spellcheck="true" autocomplete="off"
						value="<?php echo esc_attr( $subject ); ?>">
			</div>
		</div>
		<?php

		$prehead = get_post_meta( $post->ID, 'cfw_preheader', true );

		?>
		<div>
			<div>
				<label id="cfw_email_preheader-label"
						for="cfw_email_preheader"><?php _e( 'Preview Text', 'checkout-wc' ); ?></label>
				<input type="text"
						placeholder="<?php _e( 'Shows in the email preview in the inbox before the content.', 'checkout-wc' ); ?>"
						name="cfw_email_preheader" size="30" value="<?php echo esc_attr( $prehead ); ?>"
						id="cfw_email_preheader" spellcheck="true" autocomplete="off"
						value="<?php echo esc_attr( $prehead ); ?>">
			</div>
		</div>
		<?php
	}

	public function add_other_email_options( $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post->post_type ) {
			return;
		}

		$wait      = get_post_meta( $post->ID, 'cfw_wait', true );
		$wait_unit = get_post_meta( $post->ID, 'cfw_wait_unit', true );

		if ( ! $wait_unit ) {
			$wait_unit = 'minutes';
		}

		if ( ! $wait ) {
			$wait = 5;
		}

		$email_address = wp_get_current_user()->user_email ?? get_option( 'admin_email' );

		$cfw_use_wc_template = get_post_meta( $post->ID, 'cfw_use_wc_template', true );
		?>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row" valign="top">
					<label id="cfw_email_wait-label"
							for="cfw_email_wait"><?php _e( 'Send After', 'checkout-wc' ); ?></label>
				</th>
				<td>
					<div class="cfw-admin-align-center">
						<input type="text" placeholder="<?php _e( 'Send After', 'checkout-wc' ); ?>"
								name="cfw_email_wait" size="30" value="<?php echo intval( $wait ); ?>"
								id="cfw_email_wait" autocomplete="off" value="<?php echo esc_attr( $wait ); ?>">

						<select name="cfw_email_wait_unit" id="cfw_email_wait_unit">
							<option
								value="minutes" <?php selected( $wait_unit, 'minutes' ); ?>><?php _e( 'Minutes', 'checkout-wc' ); ?></option>
							<option
								value="hours" <?php selected( $wait_unit, 'hours' ); ?>><?php _e( 'Hours', 'checkout-wc' ); ?></option>
							<option
								value="days" <?php selected( $wait_unit, 'days' ); ?>><?php _e( 'Days', 'checkout-wc' ); ?></option>
						</select>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">
					<label id="cfw_use_wc_template-label"
							for="cfw_use_wc_template"><?php _e( 'Template', 'checkout-wc' ); ?></label>
				</th>
				<td>
					<label>
						<input type="hidden" name="cfw_use_wc_template" value="no"/>
						<input type="checkbox" name="cfw_use_wc_template" value="yes"
								id="cfw_use_wc_template" <?php echo checked( $cfw_use_wc_template ); ?>>
						<?php _e( 'Use WooCommerce Email Template', 'checkout-wc' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top">
					<label id="cfw_send_preview-label"
							for="cfw_send_preview"><?php _e( 'Send Preview', 'checkout-wc' ); ?></label>
				</th>
				<td>
					<div class="cfw-admin-align-center">
						<input type="text" placeholder="<?php _e( 'Send Preview', 'checkout-wc' ); ?>"
								name="cfw_send_preview" size="30" value="<?php echo esc_attr( $email_address ); ?>"
								id="cfw_send_preview" autocomplete="off">
						<button type="button" class="button button-secondary" name="cfw_send_preview_button"
								value="sendit"
								id="cfw_send_preview_button"><?php _e( 'Send Preview', 'checkout-wc' ); ?></button>
					</div>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	public function change_email_title_placeholder( $title, $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() === $post->post_type ) {
			$title = __( 'Add Email Title', 'checkout-wc' );
		}

		return $title;
	}

	public function save_custom_fields( $post_id, $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post->post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['cfw_email_subject'] ) ) {
			update_post_meta( $post->ID, 'cfw_subject', sanitize_text_field( wp_unslash( $_POST['cfw_email_subject'] ) ) );
		}

		if ( isset( $_POST['cfw_email_preheader'] ) ) {
			update_post_meta( $post->ID, 'cfw_preheader', sanitize_text_field( wp_unslash( $_POST['cfw_email_preheader'] ) ) );
		}

		if ( isset( $_POST['cfw_email_wait'] ) ) {
			update_post_meta( $post->ID, 'cfw_wait', intval( $_POST['cfw_email_wait'] ) );
		}

		if ( isset( $_POST['cfw_email_wait_unit'] ) ) {
			update_post_meta( $post->ID, 'cfw_wait_unit', sanitize_text_field( wp_unslash( $_POST['cfw_email_wait_unit'] ) ) );
		}

		if ( isset( $_POST['cfw_use_wc_template'] ) ) {
			update_post_meta( $post->ID, 'cfw_use_wc_template', 'yes' === $_POST['cfw_use_wc_template'] );
		}

		// Get the number of seconds represented by cfw_email_wait and cfw_email_wait_unit
		$wait = intval( $_POST['cfw_email_wait'] );
		$unit = sanitize_text_field( wp_unslash( $_POST['cfw_email_wait_unit'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$current_epoch = time();
		$plus_interval = strtotime( "+{$wait} {$unit}", $current_epoch );
		$seconds       = $plus_interval - $current_epoch;

		update_post_meta( $post->ID, 'cfw_acr_email_interval', $seconds );
	}

	public function add_acr_localized_variables() {
		$vars = [
			'site_name'           => __( 'Site Name', 'checkout-wc' ),
			'cart_products_table' => __( 'Abandoned Cart Details Table', 'checkout-wc' ),
			'checkout_url'        => __( 'Checkout URL', 'checkout-wc' ),
			'checkout_button'     => __( 'Checkout Button', 'checkout-wc' ),
			'customer_email'      => __( 'Customer Email', 'checkout-wc' ),
			'customer_firstname'  => __( 'Customer First Name', 'checkout-wc' ),
			'customer_lastname'   => __( 'Customer Last Name', 'checkout-wc' ),
			'customer_full_name'  => __( 'Customer Full Name', 'checkout-wc' ),
			'cart_abandoned_date' => __( 'Abandoned Date', 'checkout-wc' ),
			'site_url'            => __( 'Site URL', 'checkout-wc' ),
			'unsubscribe_url'     => __( 'Unsubscribe Link', 'checkout-wc' ),
		];

		wp_localize_script( 'cfw-admin', 'cfw_acr_replacement_codes', $vars );
		wp_localize_script(
			'cfw-admin',
			'cfw_acr_preview',
			[
				'nonce' => wp_create_nonce( 'send_preview' ),
			]
		);
	}

	public function add_mce_button( $buttons ) {
		if ( get_post_type() !== \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() ) {
			return $buttons;
		}

		$buttons[] = 'cfw_acr';

		return $buttons;
	}

	public function add_mce_plugin( $plugins ) {
		if ( ! $this->is_current_page() ) {
			return $plugins;
		}

		$front = CFW_PATH_ASSETS;

		$plugins['cfw_acr'] = $front . '/js/mce.js';

		return $plugins;
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$roles = [];

		foreach ( wp_roles()->roles as $role => $role_data ) {
			if ( is_array( $role_data['name'] ) ) {
				continue;
			}

			$roles[ $role ] = $role_data['name'];
		}

		$clear_cart_data_url = add_query_arg(
			[
				'page'                => 'cfw-settings-acr',
				'subpage'             => 'settings',
				'clear-all-acr-carts' => 'true',
				'nonce'               => wp_create_nonce( 'clear-all-acr-carts' ),
			],
			admin_url( 'admin.php' )
		);

		$this->set_script_data(
			[
				'settings'             => [
					'enable_acr'                   => SettingsManager::instance()->get_setting( 'enable_acr' ) === 'yes',
					'acr_abandoned_time'           => SettingsManager::instance()->get_setting( 'acr_abandoned_time' ),
					'acr_from_name'                => SettingsManager::instance()->get_setting( 'acr_from_name' ),
					'acr_from_address'             => SettingsManager::instance()->get_setting( 'acr_from_address' ),
					'acr_reply_to_address'         => SettingsManager::instance()->get_setting( 'acr_reply_to_address' ),
					'acr_recovered_order_statuses' => SettingsManager::instance()->get_setting( 'acr_recovered_order_statuses' ),
					'acr_excluded_roles'           => SettingsManager::instance()->get_setting( 'acr_excluded_roles' ),
					'acr_simulate_only'            => SettingsManager::instance()->get_setting( 'acr_simulate_only' ) === 'yes',
				],
				'woocommerce_settings' => [
					'order_statuses' => wc_get_order_statuses(),
					'roles'          => $roles,
				],
				'params'               => [
					'post_content'        => $this->get_cron_notice() . cfw_get_sendwp_admin_banner(),
					'clear_cart_data_url' => $clear_cart_data_url,
				],
				'plan'                 => $this->get_plan_data(),
			]
		);
	}

	/**
	 * @throws Exception The exception.
	 */
	public function send_preview_email() {
		if ( ! current_user_can( 'cfw_manage_acr' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'checkout-wc' ) );
		}

		if ( empty( $_REQUEST['email_id'] ) ) {
			wp_send_json_error( __( 'Email ID is required.', 'checkout-wc' ) );
		}

		check_ajax_referer( 'send_preview', 'security' );

		$email_id = intval( $_REQUEST['email_id'] );
		$cart     = new \stdClass();

		if ( ! WC()->cart->get_cart_contents_count() ) {
			$args = [
				'status'       => 'publish',
				'type'         => 'simple',
				'stock_status' => 'instock',
				'orderby'      => 'rand',
				'limit'        => 1,
			];

			$random_products = wc_get_products( $args );

			if ( ! empty( $random_products ) ) {
				$random_product = reset( $random_products );
				WC()->cart->add_to_cart( $random_product->id );
			}
		}

		$cart->cart         = wp_json_encode( WC()->cart->get_cart() );
		$cart->subtotal     = WC()->cart->get_subtotal();
		$cart->id           = 0;
		$cart->email        = sanitize_email( wp_unslash( $_REQUEST['email_address'] ?? wp_get_current_user()->user_email ) );
		$cart->status       = 'abandoned';
		$cart->wp_user      = wp_get_current_user()->ID;
		$cart->created_unix = time();
		$cart->created      = date( 'Y-m-d H:i:s' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$cart->first_name   = wp_get_current_user()->first_name;
		$cart->last_name    = wp_get_current_user()->last_name;
		$cart->emails_sent  = 0;
		$cart->fields       = '{}';
		$email              = get_post( $email_id );
		$subject            = get_post_meta( $email->ID, 'cfw_subject', true );
		$preheader          = get_post_meta( $email->ID, 'cfw_preheader', true );
		$use_wc_template    = get_post_meta( $email->ID, 'cfw_use_wc_template', true );
		$raw_content        = wpautop( $email->post_content );
		$content            = cfw_get_email_template( $subject, $preheader, $raw_content );
		$content            = $this->acr_feature->process_replacements( $content, $cart, $email->ID );
		$content            = CssInliner::fromHtml( $content )->inlineCss( cfw_get_email_stylesheet() )->render();

		// Send email
		$from_name    = SettingsManager::instance()->get_setting( 'acr_from_name' );
		$from_address = SettingsManager::instance()->get_setting( 'acr_from_address' );
		$reply_to     = SettingsManager::instance()->get_setting( 'acr_reply_to_address' );

		$headers = [
			'From: ' . $from_name . ' <' . $from_address . '>',
			'Reply-To: ' . $reply_to,
			'Content-Type: text/html; charset=UTF-8',
		];

		if ( $use_wc_template ) {
			$mailer = WC()->mailer();
			$body   = cfw_get_email_body( $preheader, $raw_content );
			$body   = $this->acr_feature->process_replacements( $body, $cart, $email->ID );
			$body   = CssInliner::fromHtml( $body )->inlineCss( cfw_get_email_stylesheet() )->render();
			$body   = cfw_wc_wrap_message( $subject, $body );
			$sent   = $mailer->send( $cart->email, $subject, $body, $headers );
		} else {
			$sent = wp_mail( $cart->email, $subject, $content, $headers );
		}

		if ( $sent ) {
			wp_send_json_success( $sent );

			return;
		}

		wp_send_json_error( __( 'Failed to send preview email.', 'checkout-wc' ) );
	}

	public function maybe_prevent_post_publication( $data, $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post['post_type'] ) {
			return $data;
		}

		// If post create date is before March 14, 2024 then allow publishing
		if ( strtotime( $post['post_date'] ) < strtotime( '2024-03-14' ) ) {
			return $data;
		}

		$override = cfw_apply_filters( 'cfw_restricted_post_types_publish_override', false, $post );

		if ( $override ) {
			return $data;
		}

		if ( self::get_emails_count() >= self::get_allowed_email_count() && 'publish' !== $post['original_post_status'] && 'publish' === $data['post_status'] ) {
			// Change post status back to original status
			$data['post_status'] = $post['original_post_status'];

			// set a transient to show the admin notice
			set_transient( 'cfw_acr_publish_notice', true, 20 );
		}

		return $data;
	}

	public function maybe_show_post_pending_notice() {
		if ( ! get_transient( 'cfw_acr_publish_notice' ) ) {
			return;
		}

		$this->maybe_show_upgrade_notice();

		// delete the transient so we only show it once
		delete_transient( 'cfw_acr_publish_notice' );
	}

	public function replace_editor( $replace, $post ) {
		if ( \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() !== $post->post_type ) {
			return $replace;
		}

		add_filter(
			'cfw_selected_tab',
			function () {
				return 'emails';
			}
		);

		if ( ! self::is_post_new_screen() ) {
			return $replace;
		}

		$override = cfw_apply_filters( 'cfw_restricted_post_types_publish_override', false, $post );

		if ( $override ) {
			return $replace;
		}

		if ( self::get_emails_count() >= self::get_allowed_email_count() ) {
			require_once ABSPATH . 'wp-admin/admin-header.php';
			$this->maybe_show_upgrade_notice();

			return true;
		}

		return $replace;
	}

	public static function get_allowed_email_count(): int {
		/**
		 * Limits
		 * - Basic: 0
		 * - Plus: 2
		 * - Pro and Agency: Unlimited
		 */

		$limit = 0;

		if ( PlanManager::has_premium_plan_or_higher( 'plus' ) ) {
			$limit = 2;
		}

		if ( PlanManager::has_premium_plan_or_higher( 'pro' ) ) {
			$limit = 1000;
		}

		return $limit;
	}

	public static function get_emails_count(): int {
		$args = [
			'post_type'      => \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type(),
			'posts_per_page' => - 1,
			'post_status'    => [ 'publish' ],
		];

		/**
		 * Filters the arguments used to count emails
		 *
		 * @param array $args The arguments.
		 * @return array
		 * @since 9.0.0
		 */
		$args = apply_filters( 'cfw_restricted_post_types_count_args', $args );

		$emails = new \WP_Query( $args );

		return $emails->post_count;
	}

	public static function is_post_new_screen(): bool {
		global $pagenow, $typenow;

		if ( 'post-new.php' === $pagenow && \Objectiv\Plugins\Checkout\Features\AbandonedCartRecovery::get_post_type() === $typenow ) {
			return true;
		}

		return false;
	}

	public function maybe_show_upgrade_notice() {
		?>
		<div class='cfw-license-upgrade-blocker-og cfw-tw'>
			<div class="inner text-base">
				<h3 class="text-xl font-bold mb-4">
					<?php _e( 'Upgrade Your Plan', 'checkout-wc' ); ?>
				</h3>

				<?php
				echo esc_html(
					sprintf(
						/* translators: %1$d: Allowed email count, %2$d: Used email count */
						__( 'Your CheckoutWC plan allows you to create %1$d Abandoned Cart Emails. You have used %2$d.', 'checkout-wc' ),
						self::get_allowed_email_count(),
						self::get_emails_count()
					)
				);
				?>

				<p class="text-base italic mt-2 mb-2">
					<?php _e( 'You cannot create or publish new Abandoned Cart Recovery emails if you are over the limit.', 'checkout-wc' ); ?>
				</p>

				<p class="text-base">
					<?php echo wp_kses_post( sprintf( __( 'You can upgrade your license in <a class="text-blue-600 underline" target="_blank" href="%1$s">Account</a>. For help upgrading your license, <a class="text-blue-600 underline" target="_blank" href="%2$s">click here.</a>', 'checkout-wc' ), 'https://www.checkoutwc.com/account/', 'https://kb.checkoutwc.com/article/53-upgrading-your-license' ) ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
