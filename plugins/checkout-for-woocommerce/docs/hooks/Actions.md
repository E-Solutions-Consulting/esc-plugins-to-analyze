# Actions


## `$hook_name`

Action hook

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `hook-wrapper-functions.php`

## `'cfw_order_bump_add_to_cart_product_type_' . $product_type`

Fires before order bump is added to the cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$product_type` | `string` | The product type |
| `$product_id` | `int` | The product ID |
| `$quantity` | `int` | The quantity |
| `$variation_id` | `int` | The variation ID |
| `$variation_data` | `array` | The variation data |
| `$metadata` | `array` | The metadata |
| `$offer_product` | `\WC_Product` | The product |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `'cfw_updated_setting_' . $setting`

Fires when setting updates

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$value` | `mixed` | The new value. |
| `$old_value` | `mixed` | The old value. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.7` | Introduced. |

Source: `Managers/SettingsManagerAbstract.php`

## `cfw_acr_activate`

Action: cfw_acr_activate

Fires when the plugin is activated.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_after_admin_page_header`

Fires after the admin page header.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$` | `\PageAbstract` | The admin page. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `Admin/Pages/PageAbstract.php`

## `cfw_after_breadcrumb_navigation`

Fires after breadcrumb navigation is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_after_bump_display_price_calculation`

Action fired after calculating cart item display price for order bumps

This allows compatibility classes to re-enable price filters after display price calculations are complete.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bump` | `\BumpInterface` | The bump object |
| `$cart_item` | `array` | The cart item data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.8` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_after_cart_summary`

Fires after cart summary before closing </div> tag

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_after_cart_summary_totals`

Fires at end of cart summary totals table before </table> tag

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_after_coupon_module`

Fires after coupon module

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_after_customer_info_account_details`

Fires before account details on customer info tab

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_after_customer_info_address_heading`

Fires after customer info address heading

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_after_customer_info_billing_address_heading`

Fires after customer info address billing heading

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.4` | Introduced. |

Source: `template-functions.php`

## `cfw_after_customer_info_shipping_address_heading`

Fires after customer info address shipping heading

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.4` | Introduced. |

Source: `template-functions.php`

## `cfw_after_delivery_method`

Fires after the delivery method radio buttons are rendered.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_after_enhanced_login_prompt`

Fires after enhanced login prompt

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_after_footer`

Fires at the bottom of footer

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/footer.php`

## `cfw_after_header`

Fires after header is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_after_modal_order_bump_regular_product_form`

Action after modal order bump regular product form.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$product` | `\WC_Product` |  |
| `$bump` | `\BumpInterface` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `functions.php`

## `cfw_after_modal_order_bump_variable_product_form`

Action after modal order bump variable product form.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$variable_product` | `\WC_Product` |  |
| `$bump` | `\BumpInterface` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `functions.php`

## `cfw_after_modal_variable_product_form`

Action after modal order bump variable product form.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$variable_product` | `\WC_Product` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `API/GetVariationFormAPI.php`

## `cfw_after_payment_information_address_heading`

Fires after the billing address heading on the payment tab

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.2` | Introduced. |

Source: `template-functions.php`

## `cfw_after_payment_methods_block`

Fires after the payment methods block

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.7` | Introduced. |

Source: `template-functions.php`

## `cfw_after_plugin_data_upgrades`

Fires after plugin data upgrades.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$this` | `string` | -&gt;db_version The current db version. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_after_same_as_shipping_address_label`

Fires after same as shipping address label

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_after_shipping_packages`

Fires after shipping packages component

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.8` | Introduced. |

Source: `template-functions.php`

## `cfw_after_thank_you_order_updates_text`

Fires after the order updates text is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.7` | Introduced. |

Source: `template-functions.php`

## `cfw_after_update_checkout_calculated`

Fires after shipping and totals calculated during update_checkout refresh

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_amazon_payment_gateway_found`

Fires after amazon gateway is found

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$wc_gateway` | `mixed` | The gateway |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Gateways/AmazonPayV1.php`

## `cfw_angelleye_paypal_ec_is_express_checkout`

Hook for when PayPal for WooCommerce is in express checkout mode

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.0` | Introduced. |

Source: `Compatibility/Gateways/PayPalForWooCommerce.php`

## `cfw_before_admin_page_header`

Fires before the admin page header.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$` | `\PageAbstract` | The admin page. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `Admin/Pages/PageAbstract.php`

## `cfw_before_breadcrumb_navigation`

Fires before breadcrumb navigation is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_before_bump_display_price_calculation`

Action fired before calculating cart item display price for order bumps

This allows compatibility classes to temporarily disable price filters to prevent double-discounting in display calculations.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bump` | `\BumpInterface` | The bump object |
| `$cart_item` | `array` | The cart item data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.8` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_before_cart_item_subtotal`

This filter documented in elsewhere in this file

Source: `functions.php`

## `cfw_before_cart_summary_totals`

Fires at start of cart summary totals table

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_before_coupon_module`

Fires before coupon module

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_customer_info_account_details`

Fires before account details on customer info tab

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_enhanced_login_prompt`

Fires before enhanced login prompt

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_footer`

Fires at the top of footer

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/footer.php`

## `cfw_before_get_data`

Fires before gathering data

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.38` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_before_header`

Fires before header is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_before_order_bump_add_to_cart`

Fires before order bump is added to the cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bump` | `\BumpInterface` | The order bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_before_order_pay_submit`

Fires before order pay submit

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_payment_method_heading`

Fires above the payment method heading

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.1.1` | Introduced. |

Source: `functions.php`

## `cfw_before_payment_methods_block`

Fires before payment methods block

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.7` | Introduced. |

Source: `template-functions.php`

## `cfw_before_plugin_data_upgrades`

Fires before plugin data upgrades.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$db_version` | `int` | The current database version. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_before_print_notices`

Fires before printing notices

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_process_checkout`

Fires before checkout is processed in complete order action

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Action/CompleteOrderAction.php`

## `cfw_before_thank_you_customer_information`

Fires before thank you customer information output (after Information heading)

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_before_update_side_cart_action`

Fires before updating the side cart.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_data` | `array` | The cart data. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.6` | Introduced. |

Source: `Action/UpdateSideCart.php`

## `cfw_cart_html_before_cart_container`

Before cart html table output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.39` | Introduced. |

Source: `template-functions.php`

## `cfw_cart_updated`

Fires after the cart is updated

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_updated` | `bool` | Whether the cart was updated |
| `$context` | `string` | The context of the cart update |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.1.7` | Introduced. |

Source: `functions.php`

## `cfw_checkout_after_billing_address`

Fires after billing address

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_checkout_after_cart_summary_container`

Fires after inside the cart summary sidebar container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_after_customer_info_address`

Fires at the bottom of customer info address module after closing </div>

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_customer_info_tab_nav`

Fires after customer info tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_email`

Fires after email field output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_main_container`

Fires after the <main> container

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$checkout` | `\WC_Checkout` | The checkout object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_after_order_review`

Fires at the bottom of the #order_review container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_after_order_review_container`

Fires after the #order_review container inside the checkout form

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_after_payment_method_tab_nav`

Fires after payment method tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_payment_methods`

Fires at end of payment methods container before </div> tag

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_checkout_after_payment_tab_billing_address`

Fires after payment method tab billing address

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_shipping_address`

Fires after shipping address

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_after_shipping_method_tab_nav`

Fires after shipping method tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_billing_address`

Fires before billing address radio group is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_checkout_before_customer_info_address`

Fires before customer info address module

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_customer_info_tab_nav`

Fires before customer info tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_main_container`

Fires before <main> container

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$checkout` | `\WC_Checkout` | The checkout object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_before_order_review`

Fires at the top of the #order_review container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_before_order_review_container`

Fires before the #order_review container inside the checkout form

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_before_payment_method_tab_nav`

Fires before payment method tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_payment_method_terms_checkbox`

Fires before payment method terms and conditions output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_payment_methods`

Fires after payment methods heading and before transaction are encrypted statement

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_checkout_before_shipping_address`

Fires before shipping address

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_before_shipping_method_tab_nav`

Fires before shipping method tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_cart_summary`

Fires inside the cart summary sidebar container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_customer_info_tab`

Outputs customer info tab content

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_form`

Fires to allow standard CheckoutWC form to be replaced.

Only fires when cfw_replace_form is true

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_loaded_pre_head`

Fires after the thank you page is initiated

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/LoaderAbstract.php`

## `cfw_checkout_main_container_end`

Fires at the bottom of <main> container

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$checkout` | `\WC_Checkout` | The checkout object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_main_container_start`

Fires at the beginning of the <main> container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_order_review_tab`

Outputs order review step content

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.0` | Introduced. |

Source: `Features/OrderReviewStep.php`

## `cfw_checkout_payment_method_tab`

Outputs customer info tab content

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_shipping_method_tab`

Outputs customer info tab content

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_checkout_tabs`

Fires in the #order_review container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_checkout_update_order_review`

Fires when updating CheckoutWC order review

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$post_data` | `string` | The POST data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_custom_footer`

Fires when custom footer is hooked

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_custom_header`

Fires when custom header is hooked

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_delivery_method_changed`

Fires when delivery method changes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$delivery_method` | `string` | The current delivery method |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_do_plugin_deactivation`

Fires after plugin deactivation.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `1.0.0` | Introduced. |

Source: `init.php`

## `cfw_end_billing_address_container`

Fires before billing address inside billing address container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.4` | Introduced. |

Source: `template-functions.php`

## `cfw_end_shipping_address_container`

Fires after shipping address inside shipping address container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.4` | Introduced. |

Source: `template-functions.php`

## `cfw_enqueue_scripts`

Fires after script setup

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_footer_content`

Hook to output footer content

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `futurist/footer.php`

## `cfw_get_payment_methods_html`

Fires before payment methods html is fetched

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_init_ab_tests`

Load AB tests here

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.8` | Introduced. |

Source: `premium-init.php`

## `cfw_load_template_assets`

Fires to trigger Templates to load their assets

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_offer_accepted`

Fires after successful offer acceptance

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | Parent order. |
| `$product_data` | `array` | Product data. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Action/ProcessOfferPaymentAction.php`

## `cfw_offer_added_to_order`

Fires after offer added to existing order

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | Parent order. |
| `$product_data` | `array` | Product data. |
| `$item_id` | `int` | Order item ID. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Features/OneClick/OfferOrderManager.php`

## `cfw_offer_child_order_created`

Fires after child order created

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$child_order` | `\WC_Order` | Child order. |
| `$order` | `\WC_Order` | Parent order. |
| `$product_data` | `array` | Product data. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Features/OneClick/OfferOrderManager.php`

## `cfw_offer_payment_failed`

Fires when offer payment failed

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | Parent order. |
| `$product_data` | `array` | Product data. |
| `$gateway` | `\GatewayInterface` | Gateway instance. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Action/ProcessOfferPaymentAction.php`

## `cfw_offer_payment_succeeded_order_update_failed`

Fires when offer payment succeeded but order update failed

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | Parent order. |
| `$product_data` | `array` | Product data. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Action/ProcessOfferPaymentAction.php`

## `cfw_offer_refunded`

Fires after offer refunded successfully

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | Order object. |
| `$item` | `\WC_Order_Item` | Order item. |
| `$bump_id` | `int` | Bump ID. |
| `$refund_txn_id` | `string` | Refund transaction ID. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Features/OneClick/OfferRefundManager.php`

## `cfw_order_bump_added_to_cart`

Action hook to run after an order bump is added to the cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bump_id` | `int` | The ID of the order bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_order_item_after_data`

Fires after cart item data output

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$item` | `array` | -&gt;get_raw_item() Raw item data |
| `$item` | `string` | -&gt;get_item_key() Item key |
| `$item` | `\OrderItem` | Order item object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.3` | Introduced. |

Source: `functions.php`

## `cfw_order_pay_after_main_container`

Fires after <main> container on order pay page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_after_order_review`

Fires at bottom of #order_review on order pay page

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_before_main_container`

Fires before <main> container on order pay page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_before_order_review`

Fires at top of #order_review on order pay page

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_cart_summary`

Fires in cart summary sidebar container

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_content`

Fires in #order_review container on order pay page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |
| `$call_receipt_hook` | `bool` | Whether to call receipt hook |
| `$available_gateways` | `array` | The available gateways |
| `$order_button_text` | `string` | The text to use for the place order button |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_main_container_end`

Fires at bottom of <main> container on order pay page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_order_pay_main_container_start`

Fires at top of <main> container on order pay page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/order-pay.php`

## `cfw_output_fieldset`

Filter the account checkout fields.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$field_set` | `array` | The checkout fieldset |
| `$type` | `string` | The fieldset type |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `functions.php`

## `cfw_payment_gateway_list_{$gateway->id}_alternate`

Fires after payment method LI to allow alternate / additional output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_methods_ul_end`

Fires after bottom of payment methods UL

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_methods_ul_start`

Fires at start of payment methods UL

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_nav_place_order_button`

Fires in the order review tab place order button container.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_payment_request_buttons`

Hook for adding payment request buttons

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_permissioned_init`

Permissioned Init

This hook runs on init if CheckoutWC is enabled and the license is valid or free, or the current user is an admin

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.11` | Introduced. |

Source: `init.php`

## `cfw_register_ab_test_types`

Allow third parties to register additional test types

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$registry` | `\ABTestTypeRegistry` | The registry instance |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Features/ABTesting/ABTestTypeRegistry.php`

## `cfw_start_billing_address_container`

Fires before billing address inside billing address container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_start_shipping_address_container`

Fires before shipping address inside shipping address container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.4` | Introduced. |

Source: `template-functions.php`

## `cfw_template_after_init_order_pay`

Fires when order pay page is initted

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$global_template_parameters` | `array` | The global template parameters |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.1.6` | Introduced. |

Source: `Loaders/LoaderAbstract.php`

## `cfw_template_after_load`

Fires after template pieces are loaded

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$template_file` | `string` | The template file |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/LoaderAbstract.php`

## `cfw_template_before_load`

Fires before template pieces are loaded

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$template_file` | `string` | The template file |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/LoaderAbstract.php`

## `cfw_template_load_after_{$template_name}_{$template_piece_name}`

Fires after template has been echoed out

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Model/Template.php`

## `cfw_template_load_before_{$template_name}_{$template_piece_name}`

Fires before template is output

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Model/Template.php`

## `cfw_thank_you_after_main_container`

Fires after <main> container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_after_order_review`

Fires at the end of <main> container on thank you page

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_before_main_container`

Fires before <main> container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_before_order_review`

Fires at top of #order_review on thank you page

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_cart_summary`

Fires in cart summary sidebar container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_content`

Fires before <main> container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |
| `$order_statuses` | `array` | The order statuses we are progressing through |
| `$show_downloads` | `bool` | Whether to show downloads section |
| `$downloads` | `array` | The downloads |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_main_container_end`

Fires at the bottom of <main> container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_thank_you_main_container_start`

Fires at top of <main> container on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `futurist/thank-you.php`

## `cfw_update_checkout_after_customer_save`

Fires after customer address data has been updated. This is where we do cart updates

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_updated_setting`

Fires when setting updates

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$setting` | `string` | The setting key |
| `$value` | `mixed` | The new value. |
| `$old_value` | `mixed` | The old value. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.7` | Introduced. |

Source: `Managers/SettingsManagerAbstract.php`

## `cfw_updated_to_1017`

Action hook fired after the 10.1.7 database update routine runs.

Used to trigger actions like initial telemetry sync.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.7` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_updated_to_1018`

Action hook fired after the 10.1.8 database update routine runs.

Used to trigger actions like initial telemetry sync.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.8` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_updated_to_1020`

Action hook fired after the 10.2.0 database update routine runs.

Used to trigger actions when Turnstile feature is added.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.0` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_updated_to_1032`

Action hook fired after the 10.3.2 database update routine runs.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.3.2` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_updated_to_1036`

Action hook fired after the 10.3.2 database update routine runs.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.3.6` | Introduced. |

Source: `DatabaseUpdatesManager.php`

## `cfw_wp_footer`

Fires after wp_footer() is called

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_wp_head`

Fires after wp_head()

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `post_edd_sl_plugin_updater_setup`

Fires after the $edd_plugin_data is setup.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$edd_plugin_data` | `array` | Array of EDD SL plugin data. |

Source: `Managers/Helpers/EDD_SL_Plugin_Updater.php`

## `woocommerce_review_order_before_submit`

Fires before payment method tab navigation container

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`
