<?php

namespace Objectiv\Plugins\Checkout\Admin;

/**
 * WP Tabbed Navigation
 *
 * Automate creating a tabbed navigation and maintaining tabbed states
 *
 * @since      0.2.0
 * @package    Advanced_Content_Templates
 * @subpackage Advanced_Content_Templates/includes
 */
class TabNavigation {
	/**
	 * Added tabs.
	 *
	 * @since 0.1.0
	 * @var array $tabs Array of added tabs.
	 */
	private $tabs = array();

	/**
	 * Selected tab query arg.
	 *
	 * @since 0.2.0
	 * @var boolean|string $selected_tab_query_arg False defaults to subpage, string if set
	 */
	private $selected_tab_query_arg = 'subpage';

	private $default_tab;

	/**
	 * Constructor.
	 *
	 * @param string $default_tab (optional) Default tab.
	 *
	 * @since 0.1.0
	 */
	public function __construct( $default_tab = false ) {
		$this->default_tab = $default_tab;
	}

	/**
	 * Adds tab to navigation.
	 *
	 * @param string         $title Tab title.
	 * @param string         $url Admin page URL.
	 * @param string|boolean $tab_slug (optional) The tab slug used for matching active tab.
	 * @param string|boolean $capability (optional) Capability required to view tab.
	 *
	 * @since 0.1.0
	 */
	public function add_tab( string $title, string $url, $tab_slug = false, $capability = false ) {
		if ( $capability && ! current_user_can( $capability ) ) {
			return;
		}

		if ( false === $tab_slug ) {
			$tab_slug = sanitize_key( $title );
		}
		$this->tabs[ $tab_slug ] = array(
			'title' => $title,
			'url'   => $url,
		);
	}

	/**
	 * Removes tab from navigation.
	 *
	 * @param string $title Tab title.
	 *
	 * @since 0.1.0
	 */
	public function remove_tab( string $title ) {
		$key = sanitize_key( $title );

		if ( isset( $this->tabs[ $key ] ) ) {
			unset( $this->tabs[ $key ] );
		}
	}

	/**
	 * Returns markup for tab navigation.
	 *
	 * @return string Tab markup.
	 * @since 0.1.0
	 */
	public function get_tabs(): string {
		$html = '<nav class="relative z-0 rounded-lg shadow flex divide-x divide-gray-200 mb-6" aria-label="Tabs">';

		$tab_matches_url = false;

		foreach ( $this->tabs as $slug => $tab ) {
			$match_url = str_replace( get_site_url(), '', $tab['url'] );
			if ( html_entity_decode( $match_url, ENT_COMPAT ) === $_SERVER['REQUEST_URI'] ?? '' ) {
				$tab_matches_url = true;
				break;
			}
		}

		foreach ( $this->tabs as $slug => $tab ) {
			$active_class = 'bg-transparent';
			$class        = 'text-gray-500 first:rounded-l-lg last:rounded-r-lg hover:text-gray-700 group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-4 text-sm font-medium text-center hover:bg-gray-50 focus:z-10';

			$match_url = str_replace( get_site_url(), '', $tab['url'] );
			if ( ( $tab_matches_url && html_entity_decode( $match_url, ENT_COMPAT ) === $_SERVER['REQUEST_URI'] ?? '' ) || ( ! $tab_matches_url && $this->get_current_tab() === $slug ) ) {
				$active_class = 'bg-blue-500';
				$class        = 'text-gray-900 first:rounded-l-lg last:rounded-r-lg group relative min-w-0 flex-1 overflow-hidden bg-white py-4 px-4 text-sm font-medium text-center hover:bg-gray-50 focus:z-10';
			}

			$html .= sprintf( '<a href="%s" class="%s"><span>%s</span><span aria-hidden="true" class="%s absolute inset-x-0 bottom-0 h-0.5"></span></a>', esc_attr( $tab['url'] ), $class, esc_html( $tab['title'] ), $active_class );
		}

		$html .= '</nav>';

		return $html;
	}

	public function get_current_tab(): string {
		/**
		 * Filters the selected_tab
		 *
		 * Represents the currently selected tab in a user interface.
		 *
		 * @since 9.0.0
		 * @param string $selected_tab The currently selected settings tab.
		 */
		return apply_filters( 'cfw_selected_tab', empty( $_GET[ $this->selected_tab_query_arg ] ) ? $this->default_tab : sanitize_text_field( wp_unslash( $_GET[ $this->selected_tab_query_arg ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Outputs tab markup.
	 *
	 * @since 0.1.0
	 */
	public function display_tabs() {
		echo wp_kses( $this->get_tabs(), cfw_get_allowed_html() );
	}
}
