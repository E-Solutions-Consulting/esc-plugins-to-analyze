<?php

namespace Objectiv\Plugins\Checkout\Interfaces;

/**
 * Interface for A/B Tests that provide UI components
 *
 * This interface allows A/B test types to optionally provide their own
 * UI components (metaboxes, AJAX handlers, scripts, etc.) for the admin interface.
 *
 * @link checkoutwc.com
 * @since 8.0.0
 * @package Objectiv\Plugins\Checkout\Interfaces
 */
interface ABTestUIProvidable {
	/**
	 * Check if this test type provides UI components
	 *
	 * @return bool True if UI provider is available
	 */
	public function has_ui_provider(): bool;

	/**
	 * Get the UI provider instance for this test type
	 *
	 * @return ABTestUIProviderInterface|null The UI provider or null if not available
	 */
	public function get_ui_provider(): ?ABTestUIProviderInterface;
}
