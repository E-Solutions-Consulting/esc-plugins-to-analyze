<?php

namespace YayMailScoped\YayCommerce\AdminShell\Registry;

/**
 * Central registry for all plugin license info objects.
 *
 * Plugins call register() during 'yaycommerce_admin_shell_booted' action.
 * The Licenses page calls all() to render the unified list.
 * @internal
 */
class LicenseRegistry
{
    /** @var PluginLicenseInfo[] keyed by slug */
    private array $plugins = [];
    /**
     * Register a plugin's license info. Applies the decoration filter before storing.
     */
    public function register($info) : void
    {
        /** @var PluginLicenseInfo $info */
        $info = \apply_filters('yaycommerce_admin_shell_plugin_info', $info, $info->slug);
        $this->plugins[$info->slug] = $info;
    }
    /**
     * Return all registered PluginLicenseInfo objects, keyed by slug.
     *
     * @return PluginLicenseInfo[]
     */
    public function all() : array
    {
        // Refactor old licensing plugin integration
        $old_plugins = [];
        $handlers = ['YayMailScoped\\YAYDP\\License\\License_Handler', 'YayMailScoped\\YayMail\\License\\LicenseHandler', 'YayMailScoped\\Yay_Currency\\License\\LicenseHandler', 'YayMailScoped\\Yay_Swatches\\License\\LicenseHandler', 'YayMailScoped\\YayExtra\\License\\LicenseHandler', 'YayMailScoped\\YayRev\\License\\LicenseHandler', 'YayMailScoped\\YayWholesaleB2B\\License\\LicenseHandler', 'YayMailScoped\\YaySMTP\\License\\LicenseHandler'];
        foreach ($handlers as $handler) {
            if (\class_exists($handler) && \method_exists($handler, 'get_licensing_plugins')) {
                $plugins = $handler::get_licensing_plugins();
                if (\is_array($plugins)) {
                    $old_plugins = \array_merge($old_plugins, $plugins);
                }
            }
        }
        return \array_merge($this->plugins, $old_plugins);
    }
    /**
     * Return a single PluginLicenseInfo by slug, or null if not registered.
     */
    public function get(string $slug) : ?PluginLicenseInfo
    {
        return $this->plugins[$slug] ?? null;
    }
    /**
     * Check if a slug is registered.
     */
    public function has(string $slug) : bool
    {
        return isset($this->plugins[$slug]);
    }
}
