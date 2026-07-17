<?php

namespace YayMail\Integrations;

use YayMail\Utils\SingletonTrait;
// @yaymail-only-start
use YayMail\Integrations\AdminAndSiteEnhancements\AdminAndSiteEnhancements;
use YayMail\Integrations\DHL\DHLIntegration;
use YayMail\Integrations\F4ShippingPhoneAndEmailForWooCommerce\F4ShippingPhoneAndEmailForWooCommerce;
// @yaymail-only-end
/**
 * IntegrationsLoader
 * * @method static IntegrationsLoader get_instance()
 */
class IntegrationsLoader {
    use SingletonTrait;

    protected function __construct() {
        RankMath::get_instance();
        // @yaymail-only-start
        F4ShippingPhoneAndEmailForWooCommerce::get_instance();
        AdminAndSiteEnhancements::get_instance();
        DHLIntegration::get_instance();
        // @yaymail-only-end
    }
}
