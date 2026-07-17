<?php
/**
 * @package attentive_tag_wp_plugin
 * @version 1.0.0
 */
/*
Plugin Name: Attentive Tag WP Plugin
Description: Enables Attentive event collection on WordPress sites. 
Authors: Ivan Louguhman-Pawelko, Noah Berman
Version: 1.0.0
Author URI: attentivemobile.com
*/

//helper function 
function get_image ($id){
	$imageArr = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'single-post-thumbnail' );
	$image = '';
	if ($imageArr) {
		$image = $imageArr[0];
	}
	return $image;
}

function get_attentive_domain() {
    // pull from plugin settings
    $options = get_option( 'attentive_tag_plugin_settings' );
    $attentive_domain = $options['attentive_domain'];
    if (str_contains($attentive_domain, ".attn.tv")) {
        $attentive_domain = substr($attentive_domain, 0, -8);
    }
    if ($attentive_domain) {
        return "https://cdn.attn.tv/" . $attentive_domain . "/dtag.js";
    }
    
}

function get_dtag_script_tag() {
    $domain = get_attentive_domain();
    if ($domain) {
    return <<<MARKUP
        <script type="text/javascript" src="$domain" async></script>
MARKUP;
    }
}


function get_attentive_cart() {
	global $woocommerce;
    $cart = $woocommerce->cart;

    if ($cart) {
        $cart_line_items = $cart->get_cart();
        $cart_total = $cart->total;

        if ($cart_line_items) {
            foreach( $cart_line_items as $cart_key => $cart_val ) {
                //$cart_line_items does not automatically provide certain metadata we need - get and attach to object
                $image = get_image($cart_val['product_id']);
                $price = $cart_val['data']->get_price();
                $name = $cart_val['data']->get_title();
                // $cart_line_items[$cart_key] is an array - create a corresponding array for the extra data and merge in
                $extra_data_arr = array ('image' => $image, 'price' => $price, 'name' => $name);
                $cart_line_items[$cart_key] = $cart_line_items[$cart_key] + $extra_data_arr;
            }
            $cart_json = json_encode($cart_line_items);

            return <<<MARKUP
            <script type="text/javascript">
            window.attn_cart = {};
            window.attn_cart.products = $cart_json;
            window.attn_cart.cartTotal = "$cart_total";
            </script>
    MARKUP;
        }
    }
}

// single-product page enrichment
function get_attentive_product(){
    global $product;
    if (!is_object($product)) {
	    $product = wc_get_product(get_the_ID());
    }
    
	if ($product && is_object($product) && is_product()) {
        $name = $product->get_name();
        $type = $product->get_type();
        $price = $product->get_price();
        $id = $product->get_id();
        $variants = $product->get_children();
        $image = get_image($id);
        $selected_variant = empty($variants)
            ? null
            : $variants[0];
        $currency = get_woocommerce_currency();

        return generateJavascriptProductWindowAttribute(
            $price,
            $currency,
            $name,
            $type,
            $id,
            $selected_variant,
            $image
        );
    }
}

/**
 * Generates the Javascript code snippet that creates a WooCommerce Product
 * object and attaches it to the Javascript window object as a custom property.
 *
 * The variant can be null, so special handling of that key-value pair to ensure
 * a proper Javascript null is generated, rather than a string "null".
 */
function generateJavascriptProductWindowAttribute(
    $price,
    $currency,
    $name,
    $type,
    $id,
    $selected_variant,
    $image
) {
    $productVariantIdKeyAndValue = $selected_variant == null
        ? 'productVariantId: null'
        : sprintf('productVariantId: "%s"', $selected_variant);

    return <<<MARKUP
            <script type="text/javascript">
            try {
                const priceObj = {
                    value: "$price",
                    currency: "$currency",
                };
                const item = {
                    name: "$name",
                    category: "$type",
                    productId: "$id",
                    price: priceObj,
                    $productVariantIdKeyAndValue,
                    productImage: "$image",
                    quantity: 1,
                };
                window.attn_product = item;
            } catch(e) {
                console.error(e);
            }
            </script>
MARKUP;
}

function render() {
	echo get_dtag_script_tag();
	echo get_attentive_cart();
	echo get_attentive_product();
}

// order confirmation page enrichment
// this needs to be run on separate hook from product and order scripts
function get_attentive_order_confirmation($order_id) {
	$order = wc_get_order($order_id);
	
    // Check if this order confirmation page has been visited already (on initial purchase)
   $check = get_post_meta( $order_id, 'thank_you_page_first_visit_check', true );

   if ( $check ) {
      // this page has been loaded before, so exit
      return;
   }

   // Update order confirmation page that has been visited before
   update_post_meta( $order_id, 'thank_you_page_first_visit_check', true );

	if ($order_id && is_wc_endpoint_url( 'order-received' )) {
		$order_total = $order->get_total();
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();
		
    echo <<<MARKUP
        <script type="text/javascript">
            try {
                window.attn_order = {};
                window.attn_order.orderTotal = "$order_total";
                window.attn_order.phone = "$phone";
                window.attn_order.email = "$email";
                window.attn_order.orderId = "$order_id";
                window.attn_order.products = [];
            } catch(e) {
            console.log(e)
        }
        </script>
MARKUP;

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $subtotal = $item->get_subtotal();
            $image = get_image($product_id);
            $product_type = $item->get_type();
    echo <<<MARKUP
        <script type="text/javascript">
            var product = {};
            product.name = "$product_name";
            product.id = "$product_id";
            product.quantity = "$quantity";
            product.price = "$subtotal";
            product.image = "$image";
            product.variantId = "$variation_id";
            product.type = "$product_type";
            window.attn_order.products.push(product);
        </script>
MARKUP;
		}
	}
}

function my_plugin_settings_link($links) { 
    $settings_link = '<a href="options-general.php?page=attentive-tag-plugin">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
  }
$plugin = plugin_basename(__FILE__); 

add_filter("plugin_action_links_$plugin", 'my_plugin_settings_link' );
add_action ('wp_head', 'render');
add_action('woocommerce_thankyou', 'get_attentive_order_confirmation');
include('settings.php');
