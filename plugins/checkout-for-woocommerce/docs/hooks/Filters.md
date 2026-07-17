# Filters


## `$hook_name`

Filter the value of a hook

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `hook-wrapper-functions.php`

## `'cfw_thank_you_status_icon_' . $order_status`

Filters thank you status icon class

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thank_you_status_icon` | `string` | Thank you status icon class |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_ab_test_url_parameter`

Filters the URL parameter for loading AB tests by URL

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$url_parameter` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.8` | Introduced. |

Source: `ab-testing-api.php`

## `cfw_account_creation_statement`

Filters create account statement

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$create_account_statement` | `string` | Create account statement |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_acr_cart_meta`

Filter the meta fields to be tracked for abandoned carts.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$meta_fields` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.7` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_acr_carts`

Filter the carts for the abandoned cart recovery report stats dashboard.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$carts` | `array` | The carts. |
| `$context` | `string` | The calling context |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.28` | Introduced. |

Source: `API/AbandonedCartRecoveryReportAPI.php`

## `cfw_acr_email_custom_css`

Filter the cart table custom styles

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$styles` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.0.2` | Introduced. |

Source: `functions.php`

## `cfw_acr_email_headers`

Filter the email headers

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$headers` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.9` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_acr_exclude_cart`

Filter whether to exclude tracking the cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$exclude_cart` | `bool` |  |
| `$email` | `string` |  |
| `$cart_contents` | `array` |  |
| `$subtotal` | `float` |  |
| `$first_name` | `string` |  |
| `$last_name` | `string` |  |
| `$fields` | `array` |  |
| `$meta` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.37` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_acr_send_to_email`

Filter the email send to address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$send_to_email` | `string` | The email address to send the email to |
| `$cart` | `\stdClass` | The tracked cart object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.37` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_acr_track_cart_without_emails`

Filter whether to track carts without emails setup

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$track_cart_without_emails` | `bool` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_active_campaign_checkbox_hook`

Filters hook to render Active Campaign checkbox output

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$render_on` | `string` | The action hook to render on |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Plugins/ActiveCampaign.php`

## `cfw_active_theme_color_settings`

Filters the active theme colors settings.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$color_settings` | `array` | The active theme colors settings. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.1.0` | Introduced. |

Source: `Admin/Pages/Appearance.php`

## `cfw_add_to_cart_redirect`

Filter the add to cart redirect URL.

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Action/AddToCartAction.php`

## `cfw_additional_side_cart_trigger_selectors`

Filter whether to automatically generate password for new accounts

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$additional_side_cart_trigger_selectors` | `string` | CSS selector for additional side cart open buttons / links |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.4.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_address_autocomplete_billing_countries`

Filter list of billing country restrictions for Google Maps address autocomplete

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address_autocomplete_billing_countries` | `array` | List of country restrictions for Google Maps address autocomplete |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_address_autocomplete_shipping_countries`

Filter list of shipping country restrictions for Google Maps address autocomplete

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address_autocomplete_shipping_countries` | `array` | List of country restrictions for Google Maps address autocomplete |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Features/GoogleAddressAutocomplete.php`

## `cfw_address_field_priorities`

Filter address field priorities

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$priorities` | `array` | The address field priorities keyed by field key |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `AddressFieldsAugmenter.php`

## `cfw_admin_integrations_checkbox_fields`

Filters third party checkboxes here:  WP Admin > CheckoutWC > Advanced > Integrations

Use to add additional integration settings

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$integrations` | `array` | The integrations admin page class |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Admin/Pages/Integrations.php`

## `cfw_admin_page_data`

Filter the admin page data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Admin/Pages/PageAbstract.php`

## `cfw_admin_pages`

Filters the admin pages.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$admin_pages` | `array` | the admin pages |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `init.php`

## `cfw_admin_preview_message`

Filter the admin preview message.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$admin_message` | `string` | the admin preview message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

- `@return` the admin preview message

Source: `init.php`

## `cfw_allow_html_in_formatted_item_data_value`

Filter whether to allow HTML in formatted item data value.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow_html_in_formatted_item_data_value` | `bool` | Whether to allow HTML in formatted item data value. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.33` | Introduced. |

Source: `Model/CartItem.php`

## `cfw_allow_international_phone_field_country_dropdown`

Filter to allow the country dropdown to be disabled

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow` | `bool` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.5` | Introduced. |

Source: `Features/InternationalPhoneField.php`

## `cfw_allow_order_bump_coupons`

Filter whether to allow order bump coupons

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow` | `bool` | Whether to allow order bump coupons |
| `$bump_id` | `int` | The ID of the order bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.14` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_already_have_account_text`

Filters already have account text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$already_have_account_text` | `string` | Already have an account text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_amazon_suppress_shipping_field_validation`

Filters whether to suppress shipping field validation when logged into Amazon Pay

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$suppress_validation` | `bool` | True suppress validation (Default), false validate |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Gateways/AmazonPayLegacy.php`

## `cfw_available_shipping_methods`

Filter the available shipping methods displayed on checkout page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$package` | `array` | The shipping package |
| `$i` | `int` | The package index |
| `$rates` | `array` | The shipping rates |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `functions.php`

## `cfw_billing_address_description`

Filters billing address description

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$billing_address_description` | `string` | Billing address description |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_billing_address_different_address_label`

Filters the label for the different billing address radio

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$different_billing_address_label` | `string` | The label for the different billing address radio |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `functions.php`

## `cfw_billing_address_heading`

Filters billing address heading on payment method tab

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$billing_address_heading` | `string` | Billing address heading on payment method tab |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_billing_address_same_as_shipping_label`

Filters the label for the same as shipping address radio

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$same_as_shipping_label` | `string` | The label for the same as shipping address radio |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `functions.php`

## `cfw_billing_shipping_address_heading`

Filters billing and shipping address heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$billing_and_shipping_address_heading` | `string` | Billing and shipping address heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_blocked_script_handles`

Filters blocked script handles

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$blocked_script_handles` | `array` | The blocked script handles |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_blocked_style_handles`

Filters blocked stylesheet handles

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$blocked_style_handles` | `array` | The blocked stylesheet handles |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_body_classes`

Filter CheckoutWC specific body classes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$css_classes` | `array` | The body css classes |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/Redirect.php`

## `cfw_breadcrumb_cart_label`

Filters breadcrumb cart link label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$breadcrumb_cart_link_label` | `string` | Breadcrumb cart link label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_breadcrumb_cart_url`

Filters breadcrumb cart link URL

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$breadcrumb_cart_link_url` | `string` | Breadcrumb cart link URL |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_breadcrumb_customer_info_label`

Filters the breadcrumb customer info label.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | the breadcrumb customer info label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_breadcrumb_payment_label`

Filters the breadcrumb payment label.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | the breadcrumb payment label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_breadcrumb_review_step_label`

Filter review step breadcrumb label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | The review step breadcrumb label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.5.0` | Introduced. |

Source: `Features/OrderReviewStep.php`

## `cfw_breadcrumb_shipping_label`

Filters the breadcrumb shipping label.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | the breadcrumb shipping label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_breadcrumbs`

Filters breadcrumbs

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$breadcrumbs` | `string` | Breadcrumbs |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_bypass_login_modal_shown_cookie`

Bypass cookie for automatically showing login modal

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bypass_login_modal_shown_cookie` | `bool` | Bypass cookie for automatically showing login modal (default: false, do not bypass) |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.16` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_cart_edit_redirect_suppress_notice`

Filters whether to suppress checkout is not available message when editing cart results in empty cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$supress_notice` | `bool` | Whether to suppress the message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.14.0` | Introduced. |

Source: `Features/CartEditingAtCheckout.php`

## `cfw_cart_item_data_expanded`

Filter the order item data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$item_data` | `array` | The order item data |
| `$item` | `\WC_Order_Item` | The order item |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `Model/OrderItem.php`

## `cfw_cart_item_discount`

Filters the discount HTML for a cart item

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$discount_html` | `string` | The discount HTML |
| `$raw_item` | `array` | The raw cart item |
| `$product` | `\WC_Product` | The product |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.0` | Introduced. |

Source: `functions.php`

## `cfw_cart_item_quantity_max_value`

Filters cart item quantity control max value

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_item_key` | `string` | The cart item key |
| `$max_value` | `int` | The max value |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `functions.php`

## `cfw_cart_item_quantity_min_value`

Filters cart item minimum quantity

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |
| `$min_quantity` | `int` | Cart item minimum quantity |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_cart_item_quantity_step`

Filters cart item quantity step

Determines how much to increment or decrement by

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |
| `$quantity_step` | `int` | Cart item quantity step amount |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_cart_item_row_class`

Filter the item row class

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$woocommerce_filtered_cart_item_row_class` | `string` | The filtered row class |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Model/CartItem.php`

## `cfw_cart_quantity_input_has_override`

Filters whether the cart quantity input has been overridden

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$has_override` | `bool` | Whether the cart quantity input has been overridden |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `functions.php`

## `cfw_cart_table_styles`

Filter the cart table styles

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$styles` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |
| `10.1.0` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_cart_thumb_height`

Filter cart thumbnail height

0 indicates auto height

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_width` | `int` | The height of thumbnails in cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_cart_thumb_width`

Filter cart thumbnail width

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_width` | `int` | The width of thumbnails in cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_cart_totals_shipping_label`

Filters cart totals shipping label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_totals_shipping_label` | `string` | Cart totals shipping label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_check_create_account_by_default`

Filter whether to check create account by default

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$check_create_account_by_default` | `bool` | Check create account by default |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_checkbox_like_field_types`

The field type that are like checkboxes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$field_types` | `string[]` | The field types |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.10` | Introduced. |

Source: `FormFieldAugmenter.php`

## `cfw_checkout_data`

Filter extra data available to JavaScript

Merge with default data. Even though we load this by default for non-checkout pages, we have to do this so that the data gets the default action values during AJAX updates

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` | The checkout data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_compatibility_all_products_for_subscriptions_run_on_side_cart`

Filter whether to load our customizations for All Products for Subscriptions on Side Cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$load_on_side_cart` | `bool` | Whether to load our customizations for All Products for Subscriptions on Side Cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `Compatibility/Plugins/AllProductsForSubscriptions.php`

## `cfw_compatibility_free_gifts_for_woocommerce_prevent_redirect`

Whether to prevent redirecting during add to cart when Free Gifts for WooComemrce is active

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$prevent_redirect` | `bool` | Whether to prevent redirecting during add to cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `Compatibility/Plugins/FreeGiftsforWooCommerce.php`

## `cfw_compatibility_nexcessmu_prevent_disable_fragments`

Prevent disabling fragments when Nexcess MU is active

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$prevent_disable_fragments` | `bool` | Prevent disabling fragments |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `Compatibility/Plugins/NexcessMU.php`

## `cfw_compatibility_woocommerce_germanized_render_hook`

Filter the rendering hook for WooCommerce Germanized compatibility

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$hook` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGermanized.php`

## `cfw_compatibility_woocommerce_germanized_render_priority`

Filter the priority of the render hook for WooCommerce Germanized compatibility

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$priority` | `int` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGermanized.php`

## `cfw_compatibility_woocommerce_gift_cards_field_label`

Filter CheckoutWC WooCommerce Gift Cards field label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | Field label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.7` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGiftCards.php`

## `cfw_compatibility_woocommerce_gift_cards_field_placeholder`

Filter CheckoutWC WooCommerce Gift Cards field placeholder

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$label` | `string` | Field placeholder |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.7` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGiftCards.php`

## `cfw_compatibility_woocommerce_gift_cards_heading_text`

Filter CheckoutWC WooCommerce Gift Cards Heading Text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$heading_text` | `string` | Heading text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.7` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGiftCards.php`

## `cfw_continue_to_order_review_label`

Filter continue to order review button label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$continue_to_order_review_label` | `string` | Continue to order review button label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_continue_to_payment_method_label`

Filter continue to payment method button label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$continue_to_payment_method_label` | `string` | Continue to payment method button label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_continue_to_shipping_method_label`

Filter continue to shipping method button label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$continue_to_shipping_method_label` | `string` | Continue to shipping method button label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_copy_pickup_details_to_order_notes`

Determine whether to copy pickup details to order notes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$copy_pickup_details_to_order_notes` | `bool` | Whether to copy pickup details to order notes |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.7.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_create_account_checkbox_label`

Filters create account checkbox label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$create_account_checkbox_label` | `string` | Create account checkbox label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_create_account_site_name`

Filters create account checkbox site name

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$create_account_site_name` | `string` | Create account checkbox site name |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_crop_cart_thumbs`

Filter whether to crop cart thumbnails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$crop` | `bool` | True allows cropping |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_crop_order_bump_thumbs`

Filter whether to crop order bump thumbnails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$crop` | `bool` | True allows cropping |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.1` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_crop_trust_badge_thumbs`

Filter whether to crop cart thumbnails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$crop` | `bool` | True allows cropping |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `TrustBadgeImageSizeAdder.php`

## `cfw_custom_css_properties`

Filter the CSS custom property overrides

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$overrides` | `array` | The CSS custom properties |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `Managers/StyleManager.php`

## `cfw_customer_information_heading`

Filters customer info tab heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$customer_info_heading` | `string` | Customer info tab heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_default_billing_address_radio_selection`

Filters default billing address radio selection

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$default` | `string` | Default billing address radio selection |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.28` | Introduced. |

Source: `functions.php`

## `cfw_detected_gateways`

Filter detected gateways

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$gateways` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Admin/Pages/ExpressCheckout.php`

## `cfw_disable_cart_editing`

Filters whether to disable cart editing

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_cart_editing` | `int` | Whether to disable cart editing |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.7` | Introduced. |

Source: `Model/CartItem.php`

## `cfw_disable_cart_quantity_prompt`

Filter whether to disable cart quantity prompt

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_cart_quantity_prompt` | `bool` | Disable cart quantity prompt |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.19` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_disable_cart_variation_editing`

Filters whether to disable cart variation editing

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_cart_variation_editing` | `bool` | Whether to disable cart editing |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |
| `$context` | `string` | The calling context |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Model/CartItem.php`

## `cfw_disable_cart_variation_editing_checkout`

Filters whether to disable cart variation editing

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_cart_variation_editing_checkout` | `bool` | Whether to disable cart editing |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |
| `$context` | `string` | The calling context |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.6` | Introduced. |

Source: `Model/CartItem.php`

## `cfw_disable_email_domain_validation`

Filter whether to disable email domain validation

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_email_domain_validation` | `bool` | Disable email domain validation |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.26` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_disable_side_cart`

Filter to disable side cart on specific pages/contexts

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable` | `bool` | Whether to disable side cart. Default false. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.1` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_disable_side_cart_auto_open`

Filter whether to auto open the side cart on add to cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_side_cart_auto_open` | `bool` | Disable side cart auto open |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.5` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_disable_side_cart_item_quantity_control`

Filters whether to disable cart item quantity control on the Side Cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable` | `bool` | Whether to disable cart item quantity control on the Side Cart |
| `$cart_item` | `array` | The cart item |
| `$cart_item_key` | `string` | The cart item key |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_disable_tracking_checkin`

Filters whether to send a checkin.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$override` | `bool` | Whether to override the default behavior. |
| `$home_url` | `string` | The home url. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.8.8` | Introduced. |

Source: `Stats/StatCollection.php`

## `cfw_disable_woocommerce_gift_cards_compatibility`

Filter whether to disable CheckoutWC WooCommerce Gift Cards compatibility class

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable` | `bool` | Whether to disable compatibility class |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.5` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceGiftCards.php`

## `cfw_display_bump`

Filter whether to display the bump

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$display_bump` | `bool` | Whether to display the bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_do_admin_bar`

Filters whether to show the admin bar button

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | Whether to show the admin bar button |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Admin/Pages/PageAbstract.php`

## `cfw_email_domain_valid`

Filters whether to validate email domain

If you don't append dot to the domain, every domain will validate because it will fetch your local MX handler

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$valid` | `bool` | Whether the email domain is valid |
| `$email_domain` | `string` | The email domain |
| `$email_address` | `string` | The email address |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.3` | Introduced. |

Source: `Action/ValidateEmailDomainAction.php`

## `cfw_email_exists`

Filters whether an email address has an account

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$exists` | `bool` | Whether an email exists or not |
| `$email` | `string` | The email address we are checking |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `1.0.0` | Introduced. |

Source: `Action/AccountExistsAction.php`

## `cfw_empty_side_cart_heading`

Fires before the empty cart message is output.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$message` | `string` | the message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.2.0` | Introduced. |

Source: `template-functions.php`

## `cfw_enable_account_exists_check`

Filter whether to check whether an existing account matches provided email address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_account_exists_check` | `bool` | Enable account exists check when billing email field changed |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.7` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_enable_editable_admin_shipping_phone_field`

Filter whether to enable editable shipping phone field in admin

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_editable_admin_phone_field` | `bool` | True show editable field, false show label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Admin/ShippingPhoneController.php`

## `cfw_enable_field_persistence`

Filter whether to enable field peristence with Garlic.js

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_enable_field_persistence` | `bool` | Enable field persistence |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.10` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_enable_fullname_field`

Filter whether to enable full name field

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_fullname_field` | `array` | Whether to enable full name field |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.0` | Introduced. |

Source: `AddressFieldsAugmenter.php`

## `cfw_enable_separate_address_1_fields`

Filter whether to enable separate address 1 fields

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_separate_address_1_fields` | `array` | Whether to enable separate address 1 fields |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.0` | Introduced. |

Source: `AddressFieldsAugmenter.php`

## `cfw_enable_side_cart_woocommerce_after_cart_totals_hook`

Whether to enable woocommerce_after_cart_totals hook for side cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_side_cart_woocommerce_after_cart_totals_hook` | `bool` | Whether to enable woocommerce_after_cart_totals hook for side cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.37` | Introduced. |

Source: `functions.php`

## `cfw_enable_smartystreets_integration`

Whether to enable Smarty integration

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable` | `bool` | Whether to enable Smarty integration |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.2.1` | Introduced. |

Source: `Features/SmartyStreets.php`

## `cfw_enable_zip_autocomplete`

Filter whether to enable zip autocomplete

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_zip_autocomplete` | `bool` | Enable zip autocomplete |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_ensure_selected_payment_method`

Filter whether to ensure a payment method is selected

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$ensure_selected_payment_method` | `array` | Whether to ensure a payment method is selected |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.11` | Introduced. |

Source: `functions.php`

## `cfw_estimated_pickup_time`

Filters the pickup location estimated time

NOTE: Use cfw_pickup_times to extend the list of available pickup times

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$pickup_time` | `string` | The estimated time |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.5.0` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_event_object`

Filter cfw_event_object array

Localized data available via DataService

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_event_object` | `array` | The data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `1.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_express_pay_separator_text`

Filters payment request button separator text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$separator_label` | `string` | The separator label (default: Or) |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_failed_login_error_message`

Filters failed login error message

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$error` | `string` | The error message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Action/LogInAction.php`

## `cfw_fetchify_address_autocomplete_countries`

Filter list of shipping country restrictions for Google Maps address autocomplete

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address_autocomplete_shipping_countries` | `array` | List of country restrictions for Google Maps address autocomplete |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Features/FetchifyAddressAutocomplete.php`

## `cfw_fetchify_address_autocomplete_default_country`

Filter Fetchify address autocomplete default country

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$default_country` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.2` | Introduced. |

Source: `Features/FetchifyAddressAutocomplete.php`

## `cfw_fetchify_address_autocomplete_enable_geolocation`

Filter whether to enable geolocation

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_geolocation` | `bool` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.2` | Introduced. |

Source: `Features/FetchifyAddressAutocomplete.php`

## `cfw_fetchify_search_placeholder`

Filter the fetchify search placeholder

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$fetchify_default_placeholder` | `string` | Fetchify search placeholder |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.3` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_field_data_persistence_excludes`

Filter list of field persistence service excludes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$field_persistence_excludes` | `array` | List of field persistence service excludes |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_force_display_billing_address`

Filters whether to force displaying the billing address (no accordion)

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$force_display_billing_address` | `bool` | Force displaying billing address |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_form_attributes`

Filters the form attributes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$attributes` | `array` | The form attributes |
| `$id` | `string` | The form ID |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.1.7` | Introduced. |

Source: `template-functions.php`

## `cfw_form_field_suppress_optional_in_placeholder`

Whether to suppress 'optional' from field placeholder

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$append` | `bool` | Whether to suppress optional from field placeholder |
| `$key` | `mixed` | The key. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.13` | Introduced. |

Source: `FormFieldAugmenter.php`

## `cfw_gateway_order_button_text`

Filters gateway order button text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$gateway_order_button_text` | `string` | The gateway order button text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_account_checkout_fields`

Filters account address checkout fields

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$account_checkout_fields` | `array` | Account checkout fields |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_billing_checkout_fields`

Filters billing address checkout fields

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$billing_checkout_fields` | `array` | Billing address checkout fields |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_cart_actions_data`

Filters the cart actions data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` | The cart actions data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_cart_static_actions_data`

Filters the cart actions data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` | The cart actions data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_cart_totals_data`

Filters the cart totals data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` | The cart totals data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.0` | Introduced. |

Source: `functions.php`

## `cfw_get_checkout_tabs`

Filters the checkout tabs

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$tabs` | `array` | The checkout tabs |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_get_data_clear_notices`

Filters whether to clear notices when gathering data

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$clear_notices` | `bool` | Clear notices |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.36` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_get_gateway_icons`

Filters gateway order button text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$icons` | `string` | The gateway icon HTML |
| `$` | `\WC_Payment_Gateway` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_logo_attachment_id`

Filters header logo attachment ID

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$logo_attachment_id` | `int` | The logo attachment ID |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.23` | Introduced. |

Source: `functions.php`

## `cfw_get_order_bumps`

Filter order bumps before processing

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$bumps` | `array` | Array of bump objects |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_order_bumps_data`

Filter order bumps data before returning

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` | The order bumps data array |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_review_pane_billing_address`

Filters review pane billing address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$billing_details_address` | `array` | Review pane billing address |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_review_pane_shipping_address`

Filters review pane formatted shipping address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$formatted_address` | `string` | Formatted shipping address |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `functions.php`

## `cfw_get_shipping_checkout_fields`

Filters shipping address checkout fields

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipping_checkout_fields` | `array` | Shipping address checkout fields |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_shipping_details_address`

Filters review pane shipping address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipping_details_address` | `array` | Review pane shipping address |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_get_suggested_products`

Filter suggested products

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cross_sells` | `array` |  |
| `$limit` | `int` |  |
| `$random_fallback` | `bool` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

- `@return` The suggested products

Source: `functions.php`

## `cfw_get_woocommerce_notices`

Filters WooCommerce notices before display

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$all_notices` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.23` | Introduced. |

Source: `functions.php`

## `cfw_google_address_autocomplete_type`

Filter Google address autocomplete type

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$autocomplete_type` | `string` | Google address autocomplete type |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/GoogleAddressAutocomplete.php`

## `cfw_google_font_configurations`

Filter the Google Font configurations before generating the URL.

This is the primary filter for customizing font loading. You can add, remove, or modify font configurations.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$font_configs` | `array` | An associative array of font configurations, keyed by the font family name (e.g., 'Open Sans'). Example: $font_configs[ $font_name ] = array( 'family' =&gt; $font_name, 'weights' =&gt; array( '400', '700' ), 'italic' =&gt; true, ); |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.16` | Introduced. |

Source: `Managers/StyleManager.php`

## `cfw_google_font_display`

Filter the font-display property for the Google Fonts URL.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$display` | `string` | The CSS font-display property. Accepts 'auto', 'block', 'swap', 'fallback', 'optional'. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.16` | Introduced. |

Source: `Managers/StyleManager.php`

## `cfw_google_maps_compatibility_mode`

Whether to enable Google Maps compatibility mode

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$compatibility_mode` | `bool` | Whether to enable Google Maps compatibility mode |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.3.7` | Introduced. |

Source: `Features/GoogleAddressAutocomplete.php`

## `cfw_google_maps_language_code`

Filter Google Maps language code

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$lanugage_code` | `string` | Google Maps language code |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.3.7` | Introduced. |

Source: `Features/GoogleAddressAutocomplete.php`

## `cfw_groove_cart_summary_classes`

Filters the classes for the cart summary

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_summary_classes` | `array` | The classes for the cart summary |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `groove/thank-you.php`

## `cfw_header_blog_name`

Filters header logo / title link URL

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$url` | `string` | The link URL |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.0` | Introduced. |

Source: `functions.php`

## `cfw_header_home_url`

Filters header logo / title link URL

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$url` | `string` | The link URL |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_hide_bump_if_offer_product_in_cart`

Filters whether to show a bump if the offer product is already in the cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_bump_if_offer_product_in_cart` | `bool` | Whether to show a bump if the offer product is already in the cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_hide_email_field_for_logged_in_users`

Shows the email field if the user is not logged in and the setting is enabled

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_email_field` | `bool` | Whether to show the email field |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_hide_optional_fields_behind_links`

Filters whether to hide the optional address line 2 field behind a link.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$hide` | `bool` | Whether to hide the optional address line 2 field behind a link. |
| `$fieldset` | `string` | The fieldset. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `Features/HideOptionalAddressFields.php`

## `cfw_hide_optional_fiscal_code`

Filters whether to hide optional fiscal code field generated by Fatture in Cloud plugin

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$hide` | `bool` | True hide, false show |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Plugins/Fattureincloud.php`

## `cfw_highlighted_countries`

The list of highlighted countries

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$highlighted_countries` | `array` | The highlighted countries |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.0` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceCore.php`

## `cfw_international_phone_field_placeholder_mode`

Filter international phone field placeholder mode

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$mode` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.19` | Introduced. |

Source: `Features/InternationalPhoneField.php`

## `cfw_invalid_full_name_validation_error_message`

Filter the invalid fullname error message

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$invalid_fullname_message` | `string` | Invalid fullname error message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.2.4` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_invalid_phone_validation_error_message`

Filter the invalid phone number error message

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$invalid_phone_number_message` | `string` | Invalid phone number error message |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.5` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_is_cart_bump_valid`

Filters whether the bump is valid

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$is_cart_bump_valid` | `string` | Whether the categories bump in the cart is still valid |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.3.0` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_is_checkout`

Filter cfw_is_checkout()

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$is_checkout` | `bool` | Whether we are on the checkout page |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_is_checkout_pay_page`

Filter is_checkout_pay_page()

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$is_checkout_pay_page` | `bool` | Whether we are on the checkout pay page |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_is_order_received_page`

Filter is_order_received_page()

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$is_order_received_page` | `bool` | Whether we are on the order received page |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_items_summary_table_html`

This filter is documented elsewhere in this file

Source: `functions.php`

## `cfw_klaviyo_output_hook`

Where to output Klaviyo checkboxes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$location` | `string` | Where to output the checkbox. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.1.2` | Introduced. |

Source: `Compatibility/Plugins/Klaviyo.php`

## `cfw_legacy_suppress_php_errors_output`

Filters whether to suppress PHP errors output.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$suppress` | `bool` | Whether to suppress PHP errors output. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `PhpErrorOutputSuppressor.php`

## `cfw_link_cart_items`

Filters whether to link cart items to products

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$link_cart_items` | `bool` | Link cart items to products |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `1.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_load_checkout_template`

Filters whether to load checkout template

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$load` | `bool` | True load, false don't load |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Loaders/Content.php`

## `cfw_load_order_pay_template`

Filters whether to load order pay template

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$load` | `bool` | True load, false don't load |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Loaders/Content.php`

## `cfw_load_order_received_template`

Filters whether to load order received template

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$load` | `bool` | True load, false don't load |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Loaders/Content.php`

## `cfw_load_tabs`

Filter whether to load tabs

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$load_tabs` | `bool` | Load tabs |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_local_pickup_disable_pickup_option`

Filters whether the pickup option should be disabled

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_pickup_option` | `bool` | Whether the pickup option should be disabled |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.1.6` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_disable_shipping_option`

Filters whether the shipping option should be disabled

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$disable_shipping_option` | `bool` | Whether the shipping option should be disabled |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.1.6` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_option_label`

Filters the local pickup option label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$pickup_option_label` | `string` | The pickup option label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.1` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_shipping_option_label`

Filters the local pickup shipping option label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$ship_option_label` | `string` | The shipping option label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.1` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_thank_you_address`

Filter the local pickup address shown to customers on the thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address` | `string` | The local pickup address shown to customers |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_use_default_billing_address_as_default_shipping_address`

Filters whether to use billing address as shipping address when local pickup is selected

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$use_default_billing_address_as_default_shipping_address` | `bool` | Whether to use billing address as shipping address when local pickup is selected |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.1.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_local_pickup_use_google_address_link`

Whether to link the local pickup address to Google Maps for directions

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$link` | `bool` | Whether to link the local pickup address to Google Maps |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_locale_prefix`

Filter locale prefix

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$locale` | `string` | Locale prefix |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.1.5` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_login_faster_text`

Filters login faster text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$login_faster_text` | `string` | Login faster text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_login_form_account_does_not_exist_text`

Filters the text before the login form for users who have shopped with us before

Default: If you have shopped with us before, please enter your login details below.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$text` | `string` | The text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.34` | Introduced. |

Source: `functions.php`

## `cfw_login_form_account_exists_text`

Filters the text for users who already have an account

Default: It looks like you already have an account. Please enter your login details below.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$text` | `string` | The text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.34` | Introduced. |

Source: `functions.php`

## `cfw_login_form_continue_as_guest_button_text`

Filters the text for the continue as guest button

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$text` | `string` | The text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.34` | Introduced. |

Source: `functions.php`

## `cfw_login_modal_last_password_link`

Filters the link to the Lost Password page.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$link` | `string` | the link to the Lost Password page |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.34` | Introduced. |

Source: `functions.php`

## `cfw_maybe_output_tracking_numbers`

Filter tracking numbers output on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |
| `$tracking_numbers_output` | `string` | The tracking numbers output HTML |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_no_payment_required_text`

Filters no payment required text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$no_payment_required_text` | `string` | No payment required text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_no_shipping_method_selected_message`

Filters shipping total text when no shipping methods are available

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$new_shipping_total_not_available_text` | `string` | Shipping total text when no shipping methods are available |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_non_floating_label_field_types`

The non-floating label field types

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$field_types` | `array` | The nonfloating label field types |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.2.3` | Introduced. |

Source: `FormFieldAugmenter.php`

## `cfw_offer_product_data`

Filter offer product data before payment processing

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$product_data` | `array` | Product data array. |
| `$order` | `\WC_Order` | Parent order. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Action/ProcessOfferPaymentAction.php`

## `cfw_offer_should_create_child_order`

Filter whether to create a child order for the offer

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$create_child_order` | `bool` | Whether to create a child order |
| `$order` | `\WC_Order` | The parent order |
| `$product_data` | `array` | Product data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `Features/OneClick/OfferOrderManager.php`

## `cfw_one_click_supported_gateways`

Filter supported one-click upsell gateways

Allows third parties to register gateway support. Mirrors CartFlows' cartflows_offer_supported_payment_gateways filter.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$supported_gateways` | `array` | Gateway array keyed by WooCommerce gateway ID. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.4.0` | Introduced. |

Source: `Features/OneClick/GatewayRegistry.php`

## `cfw_optional_address_2_link_text`

Filters the link text for adding the optional address line 2 field.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address_2_link_text` | `string` | The link text. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.17` | Introduced. |

Source: `Features/HideOptionalAddressFields.php`

## `cfw_optional_company_link_text`

Filters the link text for adding the optional address line 2 field.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$company_link_text` | `string` | The link text. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.17` | Introduced. |

Source: `Features/HideOptionalAddressFields.php`

## `cfw_order_bump_captured_revenue`

Filter the captured revenue

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$new_revenue` | `float` | The new captured revenue |
| `$bump` | `\BumpInterface` | The bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_order_bump_get_price`

Filter the order bump price.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$price` | `float` | The price of the order bump. |
| `$context` | `string` | The context of the price. |
| `$order_bump` | `\BumpInterface` | The order bump object. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_order_bump_get_price_context`

Filter the context for the bump price

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$context` | `string` | The context for the bump price |
| `$cart_item` | `array` | The cart item |
| `$bump` | `\BumpInterface` | The bump |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.1.6` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_order_bump_thumb_height`

Filter order bump thumbnail height

0 indicates auto height

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_height` | `int` | The height of thumbnails in order bumps |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.1` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_order_bump_thumb_width`

Filter order bump thumbnail width

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_width` | `int` | The width of thumbnails in order bumps |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.1` | Introduced. |

Source: `CartImageSizeAdder.php`

## `cfw_order_bump_upsell_quantity_to_replace`

The max number of items that upsell can replace (-1 is unlimited)

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$replace_up_to_quantity` | `int` |  |
| `$bump` | `\BumpInterface` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.6.1` | Introduced. |

Source: `Model/Bumps/BumpAbstract.php`

## `cfw_order_email_pickup_address`

Filter the local pickup address shown in order emails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address` | `string` | The formatted pickup address |
| `$raw_address` | `string` | The raw pickup address |
| `$order` | `\WC_Order` | The order object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.8` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_order_item_row_class`

Filter the order item row class

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$row_class` | `string` | The order item row class |
| `$item` | `\WC_Order_Item` | The order item |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `Model/OrderItem.php`

## `cfw_order_item_thumbnail`

Filter the order item thumbnail

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumbnail` | `string` | The order item thumbnail |
| `$item` | `\WC_Order_Item` | The order item |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `Model/OrderItem.php`

## `cfw_order_review_tab_heading`

Filters order review tab heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order_review_tab_heading` | `string` | Order review tab heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_order_totals_html`

Filters order totals HTML

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order_totals_html` | `string` | Cart totals HTML |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_order_updates_heading`

Filter the pickup instructions

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$instructions` | `string` | The pickup instructions |
| `$order` | `\WC_Order` | The order |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.2` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_order_updates_text`

Filters order updates text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order_updates_text` | `string` | Thank you page order updates text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_parsley_locale`

Filter Parsley validation service locale

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$locale` | `string` | Parsley validation service locale |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_payment_gateway_field_html_{$gateway->id}`

Filters gateway payment field output HTML

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$gateway_output` | `string` | Payment gateway output HTML |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_gateway_field_html_{$kp->id}`

Filters klarna payment gateway output

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$output` | `string` | The gateway output |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Gateways/KlarnaPayment.php`

## `cfw_payment_gateway_{$gateway->id}_content`

Filters whether to show gateway content

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | Show gateway content |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_gateway_{$kp->id}_content`

Filters whether to show custom klarna payment box HTML

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | Whether to show custom payment box HTML |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Gateways/KlarnaPayment.php`

## `cfw_payment_method_address_review_shipping_method`

Filters chosen shipping methods label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$chosen_shipping_methods_labels` | `string` | The chosen shipping methods |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_method_heading`

Filters payment methods heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$payment_methods_heading` | `string` | Payment methods heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_payment_method_li_class`

Filters the class attribute of the payment method list item.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$gateway` | `\WC_Payment_Gateway` | the payment gateway object |
| `$li_class_attribute` | `string` | the payment method list item class attribute |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |
| `10.1.0` | Introduced. |

Source: `functions.php`

## `cfw_phone_field_highlighted_countries`

Filter intl-tel-input preferred countries

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$phone_field_preferred_countries` | `array` | List of preferred countries |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.22` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_pickup_instructions_text`

Filters pickup instructions text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$pickup_instructions_text` | `string` | Thank you page order updates text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_pickup_times`

Filters the pickup times

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$pickup_times` | `array` | The pickup times |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_place_order_button_container_classes`

Filters place order button container classes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$place_order_button_container_classes` | `array` | Place order button container classes |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_pre_output_fieldset_field_args`

Filters fieldset field args

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$field` | `array` | Field args |
| `$key` | `string` | Field key |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `functions.php`

## `cfw_promo_code_apply_button_label`

Filters promo code button label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$promo_code_button_label` | `string` | Promo code button label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_promo_code_label`

Filters promo code label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$promo_code_label` | `string` | Promo code label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_promo_code_placeholder`

Filters promo code placeholder

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$promo_code_placeholder` | `string` | Promo code placeholder |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_promo_code_toggle_link_text`

Filters promo code toggle link text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$promo_code_toggle_link_text` | `string` | Filters promo code toggle link text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_reload_checkout`

Filters whether to reload checkout

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$reload_checkout` | `bool` | Whether to reload checkout |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.3.9` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_remove_coupon_response`

Filters remove coupon action response object

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$response` | `array` | The response object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.14.0` | Introduced. |

Source: `Action/RemoveCouponAction.php`

## `cfw_replace_form`

Filters whether to use the standard CheckoutWC form or allow it to be overridden

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$replace` | `bool` | Whether to replace the form or use the standard one |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `futurist/content.php`

## `cfw_restricted_post_types_count_args`

Filters the arguments used to count emails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$args` | `array` | The arguments. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Admin/Pages/Premium/AbandonedCartRecovery.php`

## `cfw_return_to_cart_link`

Filter return to cart link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cart_link` | `string` | Return to cart link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_cart_link_text`

Filter return to cart link text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_cart_link_text` | `string` | Return to cart link text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_cart_link_url`

Filter return to cart link URL

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_cart_link_url` | `string` | Return to cart link URL |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_customer_info_label`

Filter return to customer information tab label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_customer_info_label` | `string` | Return to customer information tab label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_customer_information_link`

Filter return to customer information tab link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_customer_info_link` | `string` | Return to customer information tab link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_payment_method_label`

Filter return to payment method tab label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_payment_method_label` | `string` | Return to payment method tab label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_payment_method_link`

Filter return to payment method tab link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_payment_method_link` | `string` | Return to payment method tab link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_shipping_method_label`

Filter return to shipping method tab label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_shipping_method_label` | `string` | Return to shipping method tab label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_return_to_shipping_method_link`

Filter return to shipping method tab link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$return_to_shipping_method_link` | `string` | Return to shipping method tab link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_review_pane_contact_value`

Filters the contact value for the review pane

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$content` | `string` | The contact value |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.0` | Introduced. |

Source: `functions.php`

## `cfw_review_pane_show_shipping_method`

Filters whether to show the shipping method tab in the review pane.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | whether to show the shipping method tab in the review pane |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.33` | Introduced. |

Source: `functions.php`

## `cfw_run_woocommerce_cart_actions`

Filter to enable or disable the WooCommerce cart actions

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_run_woocommerce_cart_actions` | `bool` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.0.0` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_select_field_options`

Filters the select field options for edge cases

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$options` | `array` | The select field options |
| `$args` | `array` | The field arguments |
| `$key` | `string` | The field key |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.4.0` | Introduced. |

Source: `FormFieldAugmenter.php`

## `cfw_selected_tab`

Filters the selected_tab

Represents the currently selected tab in a user interface.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$selected_tab` | `string` | The currently selected settings tab. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Admin/TabNavigation.php`

## `cfw_session_expired_target_element`

Filters which element to update with session expired notice

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$element` | `string` | Element to update with session expired notice |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.2.0` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_ship_to_label`

Filters ship to label in review pane

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$ship_to_label` | `string` | Ship to label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_shipping_address_heading`

Filters shipping address heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipping_address_heading` | `string` | Shipping address heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_shipping_bar_data`

Filters the free shipping data when no free shipping methods are available

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$data` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.0.5` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_shipping_free_text`

Filters the text displayed when free shipping is available.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$text` | `string` | the text to display |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.0.0` | Introduced. |

Source: `functions.php`

## `cfw_shipping_method_heading`

Filter the shipping methods heading

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipping_methods_heading` | `string` | Shipping methods heading |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_shipping_total_address_required_text`

Filters shipping total address required text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address_required_text` | `string` | Shipping total address required text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_shipping_total_not_available_text`

Filters shipping total text when no shipping methods are available

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$new_shipping_total_not_available_text` | `string` | Shipping total text when no shipping methods are available |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_show_cart_item_discount`

Filters whether to show cart item discount on cart item

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_cart_item_discount` | `bool` | Show cart item discount on cart item |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_show_customer_information_tab`

Filters whether to show customer information tab

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_customer_information_tab` | `bool` | Show customer information tab |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_show_gateway_{$gateway->id}`

Filters whether to show gateway in list of gateways

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | Show gateway output |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_show_klarna_checkout_express_button`

Whether to show the Klarna Checkout button

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_button` | `bool` | Whether to show the Klarna Checkout button |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.7` | Introduced. |

Source: `Compatibility/Gateways/KlarnaCheckout.php`

## `cfw_show_logout_link`

Filters whether to show logout link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_logout_link` | `bool` | Show logout link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_show_order_summary_hide_link_text`

Filters hide order summary link label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$hide_order_summary_label` | `string` | The hide order summary label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_show_order_summary_link_text`

Filters show order summary link label

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_order_summary_label` | `string` | The show order summary link label |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_show_pickup_location_in_email`

Filter whether to show pickup location info in order emails

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_pickup_info` | `bool` | Whether to show pickup location info |
| `$order` | `\WC_Order` | The order object |
| `$email` | `\WC_Email` | The email object |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.8` | Introduced. |

Source: `Features/LocalPickup.php`

## `cfw_show_return_to_cart_link`

Filters whether to show the return to cart link

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show` | `bool` | Whether to show the return to cart link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `6.0.0` | Introduced. |

Source: `functions.php`

## `cfw_show_review_order_before_cart_contents_hook`

Filters whether woocommerce_review_order_before_cart_contents hook is allowed to output

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_hook` | `bool` | Whether to output hook |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.3.2` | Introduced. |

Source: `template-functions.php`

## `cfw_show_shipping_tab`

Filters whether to show shipping tab

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_shipping_tab` | `string` | Show shipping tab |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_show_shipping_total`

Filters whether to show shipping total

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_shipping_total` | `string` | Show shipping total |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_side_cart_enable_continue_shopping_button`

Filters whether to enable continue shopping button in side cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$enable_continue_shopping_btn` | `bool` | Whether to enable continue shopping button in side cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.7.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_side_cart_event_object`

Filter cfw_event_object array

Localized data available via DataService

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_event_object` | `array` | The data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `1.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_side_cart_free_shipping_progress_bar_amount_remaining_message_format`

Filter the message format for the amount remaining for free shipping

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$amount_remaining_message` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_free_shipping_progress_bar_free_shipping_message`

Filter the message displayed when the cart qualifies for free shipping.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$free_shipping_message` | `string` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.3.0` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_free_shipping_threshold`

Filters the free shipping threshold amount

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$threshold` | `float` | The free shipping threshold amount |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.1.12` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_icon`

The contents of the side cart icon file

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$path` | `string` | The contents of the side cart icon file |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.7` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_icon_file_path`

The path to the side cart icon file

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$path` | `string` | The path to the side cart icon file |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.7` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_shipping_bar_data_exclude_discounts`

Filters whether to exclude discounts from the subtotal when calculating the free shipping bar

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$exclude_discounts` | `bool` | Whether to exclude discounts from the subtotal when calculating the free shipping bar |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.10.2` | Introduced. |

Source: `Features/SideCart.php`

## `cfw_side_cart_show_total`

Filters whether to show shipping and tax totals in side cart

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$show_total` | `bool` | Whether to show shipping and tax totals in side cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.7.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_skip_bump_cart_item_discount_html`

Filter to determine if cart item discount HTML should be skipped

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$skip` | `bool` | Whether to skip this cart item discount HTML |
| `$cart_item` | `array` | The cart item data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.0` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_skip_bump_cart_item_pricing`

Filter to determine if cart item pricing should be skipped

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$skip` | `bool` | Whether to skip this cart item |
| `$cart_item` | `array` | The cart item data |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.0` | Introduced. |

Source: `Features/OrderBumps.php`

## `cfw_smarty_address_validation_address`

Filter the address before it's sent to SmartyStreets

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address` | `array` | The address to be sent to SmartyStreets |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.10.3` | Introduced. |

Source: `Action/SmartyStreetsAddressValidationAction.php`

## `cfw_smarty_use_zip4`

Filter whether to use the zip4 code

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$use_zip4` | `bool` | Whether to use the zip4 code |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.26` | Introduced. |

Source: `Action/SmartyStreetsAddressValidationAction.php`

## `cfw_square_payment_requests_ignore_shipping_phone`

Filters whether to override Stripe payment request button heights

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow` | `bool` | Whether to ignore shipping phone requirement during payment requests |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.3.3` | Introduced. |

Source: `Compatibility/Gateways/Square.php`

## `cfw_stripe_force_checkout_express_checkout`

Filter to force enable express checkout on checkout page

Useful when Stripe settings are misconfigured or empty

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$force_enable` | `bool` | Whether to force enable express checkout |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.3.10` | Introduced. |

Source: `Compatibility/Gateways/Stripe.php`

## `cfw_stripe_payment_requests_ignore_shipping_phone`

Filters whether to override Stripe payment request button heights

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow` | `bool` | Whether to ignore shipping phone requirement during payment requests |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.3.3` | Introduced. |

Source: `Compatibility/Gateways/Stripe.php`

## `cfw_suppress_add_to_cart_notices`

Filters whether to suppress add to cart notices at checkout

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$supress_notices` | `bool` | True suppress, false allow |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Compatibility/Plugins/WooCommerceCore.php`

## `cfw_template_cart_el`

Filters order totals element ID

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order_totals_list_element_id` | `string` | Order totals element ID |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_template_global_params`

Filters global template parameters available to templates

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$global_params` | `array` | The global template parameters |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Loaders/LoaderAbstract.php`

## `cfw_template_redirect_priority`

Filters CheckoutWC template redirect priority

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$priority` | `int` | The priority of the template_redirect action |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_thank_you_continue_shopping_text`

Filters thank you page continue shopping button text

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_thank_you_continue_shopping_text` | `string` | Thank you page continue shopping button text |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_thank_you_heading_icon`

Filters thank you page heading icon

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$cfw_thank_you_heading_icon` | `string` | Thank you page heading icon output |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.4.0` | Introduced. |

Source: `template-functions.php`

## `cfw_thank_you_page_map_address`

Filter thank you page map address

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$address` | `array` | The address for the map |
| `$order` | `\WC_Order` | The order |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.9` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_thank_you_shipment_tracking_header`

Filters tracking link header on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipment_tracking_header` | `string` | Tracking link header |
| `$tracking_provider` | `string` | The shipping provider for tracking link |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.14.0` | Introduced. |

Source: `functions.php`

## `cfw_thank_you_shipment_tracking_link`

Filters tracking link output on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$shipment_tracking_link` | `string` | Tracking link output |
| `$tracking_link` | `string` | The tracking link |
| `$tracking_number` | `string` | The tracking number |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.14.0` | Introduced. |

Source: `functions.php`

## `cfw_thank_you_subtitle`

Filters thank you page heading subtitle

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thank_you_subtitle` | `string` | Thank you page heading subtitle |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_thank_you_title`

Filters thank you page heading title

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thank_you_title` | `string` | Thank you page heading title |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_thank_you_tracking_numbers`

Filter to handle custom shipment tracking links output on thank you page

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$order` | `\WC_Order` | The order object |
| `$custom_tracking_numbers_output` | `string` | The tracking numbers output |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `cfw_theme_color_settings`

Filters the theme color settings.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$color_settings` | `array` | The theme color settings. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.1.0` | Introduced. |

Source: `Admin/Pages/Appearance.php`

## `cfw_totals_itemize_shipping_costs`

Whether to itemize shipping costs

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$itemize_shipping_costs` | `array` | Whether to itemize shipping costs in totals (default: false) |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.1.7` | Introduced. |

Source: `functions.php`

## `cfw_transactions_encrypted_statement`

Filters payment methods transactions are encrypted statement

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$transactions_encrypted_statement` | `string` | Payment methods transactions are encrypted statement |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `functions.php`

## `cfw_trust_badge_thumb_height`

Filter cart thumbnail height

0 indicates auto height

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_width` | `int` | The height of thumbnails in cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `TrustBadgeImageSizeAdder.php`

## `cfw_trust_badge_thumb_width`

Filter cart thumbnail width

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$thumb_width` | `int` | The width of thumbnails in cart |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `TrustBadgeImageSizeAdder.php`

## `cfw_trust_badges`

Filter to add additional trust badges (like WooCommerce reviews)

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$badges` | `array` | Existing trust badges |
| `$apply_rules` | `bool` | Whether to apply rules |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.2.9` | Introduced. |

Source: `functions.php`

## `cfw_trust_badges_output_action`

Filter the action to output the trust badges

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$action` | `string` | The action to output the trust badges |
| `$position` | `string` | The position of the trust badges |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Features/TrustBadges.php`

## `cfw_typescript_compatibility_classes_and_params`

Filter TypeScript compatibility classes and params

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$compatibility` | `array` | TypeScript compatibility classes and params |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_unique_billing_fields`

Filters the unique billing fields.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$unique_fields` | `array` | the unique billing fields |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.2.1` | Introduced. |

Source: `functions.php`

## `cfw_unsubscribe_successful_message`

Filter the message shown when a user unsubscribes from cart reminder emails.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$unsubscribe_notice` | `string` | The message to show. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `9.0.0` | Introduced. |

Source: `Features/AbandonedCartRecovery.php`

## `cfw_update_checkout_redirect`

Filters whether to redirect the checkout page during refresh

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$` | `bool|string` | Boolean false means don't redirect, string means redirect to URL |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_update_payment_methods`

Filters payment methods during update_checkout refresh

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$` | `string` | The payment methods container and content |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `4.0.2` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_updates_manager_home_url`

Filters the home URL.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$url` | `string` | The complete home URL including scheme and path. |
| `$path` | `string` | Path relative to the home URL. Blank string if no path is specified. |
| `$orig_scheme` | `string|null` | Scheme to give the home URL context. Accepts 'http', 'https', 'relative', 'rest', or null. |
| `$blog_id` | `int|null` | Site ID, or null for the current site. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/UpdatesManager.php`

## `cfw_validate_required_registration`

Filter whether to validate required registration

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$validate_required_registration` | `bool` | Validate required registration |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `Managers/AssetManager.php`

## `cfw_validate_update_order_review_nonce`

Filters whether to validate nonce for update order review

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$validate_nonce` | `bool` | Whether to validate nonce for update order review |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `10.0.2` | Introduced. |

Source: `Action/UpdateCheckoutAction.php`

## `cfw_wc_print_notices`

Filters WooCommerce notices before display

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$all_notices` | `array` |  |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.19` | Introduced. |

Source: `template-functions.php`

## `cfw_wc_review_badges`

Filter WC review badges.

Allows external review sources (like reviewbird) to replace the badges.

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$badges` | `array` | Array of badge data arrays. |
| `$source` | `string` | Review source setting (cart_only, cart_first, sitewide). |
| `$min_rating` | `int` | Minimum star rating. |
| `$limit` | `int` | Maximum number of reviews. |
| `$cart_product_ids` | `array` | Array of product IDs in cart. |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `11.0.4` | Introduced. |

Source: `Features/TrustBadges.php`

## `cfw_wcpay_payment_requests_ignore_shipping_phone`

Filters whether to override Stripe payment request button heights

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$allow` | `bool` | Whether to ignore shipping phone requirement during payment requests |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `5.3.3` | Introduced. |

Source: `Compatibility/Gateways/WooCommercePayments.php`

## `cfw_welcome_back_email`

Filters welcome back statement customer email

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$welcome_back_email` | `string` | Welcome back statement customer email |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_welcome_back_name`

Filters welcome back statement customer name

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$welcome_back_name` | `string` | Welcome back statement customer name |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `2.0.0` | Introduced. |

Source: `template-functions.php`

## `cfw_welcome_back_text`

Filters welcome back statement

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$welcome_back_text` | `string` | Welcome back statement |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `7.1.10` | Introduced. |

Source: `template-functions.php`

## `cfw_{$context}_main_container_classes`

Filters main container classes

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$classes` | `string` | Main container classes |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `3.0.0` | Introduced. |

Source: `functions.php`

## `checkoutwc_cart_shortcode_additional_classes`

Filters additional classes for the cart icon shortcode

**Parameters**

| Name | Type | Description |
| ---- | ---- | ----------- |
| `$additional_classes` | `array` | Additional classes for the cart icon shortcode |

**Changelog**

| Version | Description |
| ------- | ----------- |
| `8.2.18` | Introduced. |

Source: `Features/SideCart.php`
