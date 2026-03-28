<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Tools
 * @subpackage Bh_Tools/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Bh_Tools
 * @subpackage Bh_Tools/public
 * @author     Jaime Isidro <jaime@solutionswebonline.com>
 */
class Bh_Tools_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Tools_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Tools_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bh-tools-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Bh_Tools_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Bh_Tools_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bh-tools-public.js', array( 'jquery' ), $this->version, false );

	}

	function send_add_to_cart_email_first( WC_Order $order, $cart_links ) {

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-missing-payment-email'];

	    if ( $order->get_meta('_ah_add_to_cart_email_sent') ) {
	        return false;
	    }

	    $email = $order->get_billing_email();
	    if ( ! $email ) {
	        return false;
	    }

	    //$email = 'jaime+testemailmissing@alliahealth.co';

	    $subject = 'Quick Step to Complete Your Order 💛';

	    $links_html = '';
	    foreach ( $cart_links as $link ) {
	        $links_html .= '<p>👉 <a href="'.esc_url($link).'">Complete your payment here</a></p>';
	    }

	    $logo_url = 'https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-brello-logo-2026.png';
	    ob_start();
	    ?>

	    <table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
			<tbody>
				<tr>
					<td valign="top" id="m_-7320618254298215061body_content_inner_cell" style="padding:48px 48px 32px">
						<div id="m_-7320618254298215061body_content_inner" style="color:#353d59;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left;margin: auto;max-width: 600px;border:1px solid #e5e5e5;border-radius: 3px;padding:2.5rem" align="left">

	    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
		    <tr>
		        <td align="center">
		            <img src="<?php echo esc_url($logo_url); ?>"
		                 alt="Brello"
		                 style="max-width:220px;height:auto;display:block;border:0;">
		        </td>
		    </tr>
		</table>
		<br>

	    <p style="text-align: left;">Hi there,</p>

	    <p style="text-align: left;">
	    We tried to process your recent Brello order, but the payment didn’t go through or may have expired.
	    </p>

	    <p style="text-align: left;">No worries — it happens 😊</p>

	    <p style="text-align: left;">To keep things moving, just use the secure link below to re-enter your payment details:</p>

	    <?php echo $links_html; ?>

	    <p style="text-align: left;">
	    Once that’s done, your order will resume right away and you’ll be directed
	    to complete your intake for provider review.
	    </p>

	    <p style="text-align: left;">
	    If payment isn’t updated, the order will stay pending and may be cancelled.
	    </p>

	    <p style="text-align: left;">
	    Need help? We’re here for you 💬
	    </p>

	    <p style="text-align: left;">
	    Warmly,<br>
	    <strong>The Brello Team 💛</strong>
	    </p>

	    				</div>
					</td>
				</tr>
			</tbody>
		</table>
	    <?php

	    $message = ob_get_clean();

	    $mailer  = WC()->mailer();

	    $headers = ['Content-Type: text/html; charset=UTF-8'];

	    $sent = $mailer->send( $email, $subject, $message, $headers );

	    if ( $sent ) {

	        $order->update_meta_data(
	            '_ah_add_to_cart_email_sent',
	            current_time('mysql')
	        );

	        $order->add_order_note('Add-to-cart recovery email sent.');
	        $order->save();

	        $logger->info('Recovery email sent', [
	            'order_id' => $order->get_id(),
	            'email'    => $email
	        ]);
	    }

	    return $sent;
	}

	function send_notification_missing_payments_first() {

	    if ( ! isset($_GET['ah_export_missing_payments']) ) {
	        return;
	    }

	    // Safety gate (MUY recomendado)
	    // if ( isset($_GET['send_emails']) && ! current_user_can('manage_woocommerce') ) {
	    //     wp_die('Unauthorized');
	    // }

	    global $wpdb;

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-export-payments'];

	    // ---- QUERY HPOS ----
	    $sql="
	        SELECT o.id, o.status
	        FROM {$wpdb->prefix}wc_orders o

	        LEFT JOIN {$wpdb->prefix}wc_orders_meta intent
	            ON o.id = intent.order_id
	            AND intent.meta_key = '_stripe_intent_id'

	        WHERE o.type = 'shop_order'
	        AND o.id >= 527516 
	        AND o.id NOT IN (SELECT om.order_id FROM {$wpdb->prefix}wc_orders_meta om WHERE om.meta_key = '_ah_add_to_cart_email_sent') 
	        AND o.status IN ('wc-pending')
	        AND intent.meta_value IS NULL
	        ORDER BY o.id DESC
	    ";
	    //_print($sql);
	    $results = $wpdb->get_results($sql);

	    //_print($results);die('testing');

	    if ( empty($results) ) {
	        wp_die('No orders found.');
	    }

	    $unique_emails = [];
	    $rows          = [];
	    $duplicates    = 0;

	    foreach ( $results as $row ) {

	        $order = wc_get_order( $row->id );
	        if ( ! $order ) {
	            continue;
	        }

	        $email = strtolower( trim( $order->get_billing_email() ) );

	        // ---- DEDUP EMAIL ----
	        if ( isset( $unique_emails[$email] ) ) {
	            $duplicates++;
	            continue;
	        }

	        $unique_emails[$email] = true;

	        $order_id = $order->get_id();

	        $products   = [];
	        $cart_items = [];

	        foreach ( $order->get_items('line_item') as $item ) {

	            $product_id   = $item->get_product_id();
	            $variation_id = $item->get_variation_id();
	            $qty          = max(1, (int) $item->get_quantity());

	            $parent_product = wc_get_product( $product_id );

	            $product_url = $parent_product
	                ? get_permalink( $parent_product->get_id() )
	                : '';

	            // Build variation URL
	            $variation_url = $product_url;

	            if ( $variation_id && $product_url ) {

	                $variation_obj = wc_get_product( $variation_id );

	                if ( $variation_obj ) {

	                    $query = [];

	                    foreach ( $variation_obj->get_attributes() as $tax => $value ) {

	                        if ( is_array($value) ) {
	                            $value = reset($value);
	                        }

	                        if ( ! empty($value) ) {
	                            $query['attribute_'.$tax] = (string)$value;
	                        }
	                    }

	                    if ( $query ) {
	                        $variation_url = add_query_arg($query, $product_url);
	                    }
	                }

	                // Guardamos items para carrito combinado
	                $cart_items[] = [
	                    'id'  => $variation_id,
	                    'qty' => $qty,
	                ];
	            }

	            $products[] = [
	                'name' => $item->get_name(),
	                'url'  => $variation_url,
	            ];
	        }

	        /**
	         * ✅ BUILD COMBINED CART URL
	         */
	        $combined_cart_url = wc_get_cart_url();

	        foreach ( $cart_items as $ci ) {
	            $combined_cart_url = add_query_arg(
	                [
	                    'add-to-cart' => $ci['id'],
	                    'quantity'    => $ci['qty'],
	                ],
	                $combined_cart_url
	            );
	        }


	        $_row = [
	            'order_id' => $order_id,
	            'email'    => $email,
	            'products' => $products,
	            'cart_url' => $combined_cart_url,
	        ];

	        // ---- SEND EMAIL ----
	        if ( isset($_GET['send_emails']) ) {

	            $sent=$this->send_add_to_cart_email(
	                $order,
	                [ $combined_cart_url ]
	            );
	            $_row['email_info']	=	$_row? 'Sent':'No';

	            //sleep(1); // anti Gmail throttle
	        }
	        $rows[] = $_row;
	    }

	    // ---- PREVIEW HTML ----
	    nocache_headers();

	    echo '<h2>Missing Payments Preview</h2>';
	    echo '<p>Total: '.count($results).' | Unique: '.count($rows).' | Duplicates: '.$duplicates.'</p>';

	    echo '<table border="1" cellpadding="6">';
	    echo '<tr><th>Order</th><th>Email</th><th>Add to Cart</th><th>Products</th>';
	    if ( isset($_GET['send_emails']) )
	    	echo '<th>Email Info</th>';
	    echo '</tr>';

	    foreach ( $rows as $r ) {

	        echo '<tr>';
	        echo '<td>#'.esc_html($r['order_id']).'</td>';
	        echo '<td>'.esc_html($r['email']).'</td>';
	        echo '<td><a target="_blank" href="'.esc_url($r['cart_url']).'">Recover Cart</a></td>';
	        echo '<td>';

	        foreach ( $r['products'] as $p ) {
	            echo '<a target="_blank" href="'.esc_url($p['url']).'">'
	                .esc_html($p['name']).'</a><br>';
	        }

	        echo '</td>';

	        if ( isset($_GET['send_emails']) )
	    		echo '<td>' . $r['email_info'] . '</td>';

	    	echo '</tr>';
	    }

	    echo '</table>';

	    exit;
	}

	function send_add_to_cart_email_second( WC_Order $order, $cart_links ) {

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-missing-payment-email'];

	    if ( $order->get_meta('_ah_add_to_cart_email_resend') ) {
	        return false;
	    }

	    $email = $order->get_billing_email();
	    if ( ! $email ) {
	        return false;
	    }

	    //$email = 'jaime+testemailmissing@alliahealth.co';

	    $subject = 'Quick Step to Complete Your Order 💛';

	    $links_html = '';
	    foreach ( $cart_links as $link ) {
	        $links_html .= '<p>👉 <a href="'.esc_url($link).'">Complete your payment here</a></p>';
	    }

	    $logo_url = 'https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-brello-logo-2026.png';
	    ob_start();
	    ?>

	    <table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
			<tbody>
				<tr>
					<td valign="top" id="m_-7320618254298215061body_content_inner_cell" style="padding:48px 48px 32px">
						<div id="m_-7320618254298215061body_content_inner" style="color:#353d59;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left;margin: auto;max-width: 600px;border:1px solid #e5e5e5;border-radius: 3px;padding:2.5rem" align="left">

						    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
							    <tr>
							        <td align="center">
							            <img src="<?php echo esc_url($logo_url); ?>"
							                 alt="Brello"
							                 style="max-width:220px;height:auto;display:block;border:0;">
							        </td>
							    </tr>
							</table>
							<br>

						    <p style="text-align: left;">Hi there,</p>

						    <p style="text-align: left;">
						    We tried to process your recent Brello order, but the payment didn’t go through or may have expired.
						    </p>

						    <p style="text-align: left;">No worries — it happens 😊</p>

						    <p style="text-align: left;">To keep things moving, just use the secure link below to re-enter your payment details:</p>

						    <?php echo $links_html; ?>

						    <p style="text-align: left;">
						    Once that’s done, your order will resume right away and you’ll be directed
						    to complete your intake for provider review.
						    </p>

						    <p style="text-align: left;">
						    If payment isn’t updated, the order will stay pending and may be cancelled.
						    </p>

						    <p style="text-align: left;">
						    Need help? We’re here for you 💬
						    </p>

						    <p style="text-align: left;">
						    Warmly,<br>
						    <strong>The Brello Team 💛</strong>
						    </p>

	    				</div>
					</td>
				</tr>
			</tbody>
		</table>
	    <?php

	    $message = ob_get_clean();

	    $mailer  = WC()->mailer();

	    $headers = ['Content-Type: text/html; charset=UTF-8'];

	    $sent = $mailer->send( $email, $subject, $message, $headers );
	    // $sent = $this->resend->emails->send([
        //     'from' => 'Brello Health <info@brellohealth.com>',
        //     'to' => [$email],
        //     'reply_to' => 'info@brellohealth.com',
        //     'subject' =>$subject,
        //     'html' => $message
        // ]);

	    if ( $sent ) {

	        $order->update_meta_data(
	            '_ah_add_to_cart_email_resend',
	            current_time('mysql')
	        );

	        $order->add_order_note('Add-to-cart recovery email resent.');
	        $order->save();

	        $logger->info('Recovery email resent', [
	            'order_id' => $order->get_id(),
	            'email'    => $email
	        ]);
	    }

	    return $sent;
	}

	function send_notification_missing_payments_second() {

	    if ( ! isset($_GET['ah_export_missing_payments']) ) {
	        return;
	    }

	    global $wpdb;

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-export-payments'];

	    // ---- QUERY HPOS ----
	    $sql = "
		    SELECT o.id, o.status
		    FROM {$wpdb->prefix}wc_orders_meta sent

		    INNER JOIN {$wpdb->prefix}wc_orders o
		        ON o.id = sent.order_id
		        AND o.type = 'shop_order'
		        AND o.status = 'wc-pending'

		    LEFT JOIN {$wpdb->prefix}wc_orders_meta resend
		        ON resend.order_id = o.id
		        AND resend.meta_key = '_ah_add_to_cart_email_resend'

		    WHERE sent.meta_key = '_ah_add_to_cart_email_sent'
		    AND resend.id IS NULL

		    ORDER BY o.id ASC
		";

	    $results = $wpdb->get_results($sql);

	    //_print($sql);_print($results);die('testing');

	    if ( empty($results) ) {
	        wp_die('No orders found.');
	    }
    

	    $unique_emails = [];
	    $rows          = [];
	    $duplicates    = 0;

	    foreach ( $results as $row ) {

	        $order = wc_get_order( $row->id );
	        if ( ! $order ) {
	            continue;
	        }

	        $email = strtolower( trim( $order->get_billing_email() ) );

	        // ---- DEDUP EMAIL ----
	        if ( isset( $unique_emails[$email] ) ) {
	            $duplicates++;
	            continue;
	        }

	        $unique_emails[$email] = true;

	        $order_id = $order->get_id();

	        $products   = [];
	        $cart_items = [];

	        foreach ( $order->get_items('line_item') as $item ) {

	            $product_id   = $item->get_product_id();
	            $variation_id = $item->get_variation_id();
	            $qty          = max(1, (int) $item->get_quantity());

	            $parent_product = wc_get_product( $product_id );

	            $product_url = $parent_product
	                ? get_permalink( $parent_product->get_id() )
	                : '';

	            // Build variation URL
	            $variation_url = $product_url;

	            if ( $variation_id && $product_url ) {

	                $variation_obj = wc_get_product( $variation_id );

	                if ( $variation_obj ) {

	                    $query = [];

	                    foreach ( $variation_obj->get_attributes() as $tax => $value ) {

	                        if ( is_array($value) ) {
	                            $value = reset($value);
	                        }

	                        if ( ! empty($value) ) {
	                            $query['attribute_'.$tax] = (string)$value;
	                        }
	                    }

	                    if ( $query ) {
	                        $variation_url = add_query_arg($query, $product_url);
	                    }
	                }

	                // Guardamos items para carrito combinado
	                $cart_items[] = [
	                    'id'  => $variation_id,
	                    'qty' => $qty,
	                ];
	            }

	            $products[] = [
	                'name' => $item->get_name(),
	                'url'  => $variation_url,
	            ];
	        }

	        /**
	         * ✅ BUILD COMBINED CART URL
	         */
	        $combined_cart_url = wc_get_cart_url();

	        foreach ( $cart_items as $ci ) {
	            $combined_cart_url = add_query_arg(
	                [
	                    'add-to-cart' => $ci['id'],
	                    'quantity'    => $ci['qty'],
	                ],
	                $combined_cart_url
	            );
	        }


	        $_row = [
	            'order_id' => $order_id,
	            'email'    => $email,
	            'products' => $products,
	            'cart_url' => $combined_cart_url,
	        ];

	        // ---- SEND EMAIL ----
	        if ( isset($_GET['send_emails']) ) {

	            $sent=$this->send_add_to_cart_email(
	                $order,
	                [ $combined_cart_url ]
	            );
	            $_row['email_info']	=	$_row? 'Sent':'No';

	            //sleep(1); // anti Gmail throttle
	        }
	        $rows[] = $_row;
	    }

	    // ---- PREVIEW HTML ----
	    nocache_headers();

	    echo '<h2>Missing Payments Preview</h2>';
	    echo '<p>Total: '.count($results).' | Unique: '.count($rows).' | Duplicates: '.$duplicates.'</p>';

	    echo '<table border="1" cellpadding="6">';
	    echo '<tr><th>Order</th><th>Email</th><th>Add to Cart</th><th>Products</th>';
	    if ( isset($_GET['send_emails']) )
	    	echo '<th>Email Info</th>';
	    echo '</tr>';

	    foreach ( $rows as $r ) {

	        echo '<tr>';
	        echo '<td>#'.esc_html($r['order_id']).'</td>';
	        echo '<td>'.esc_html($r['email']).'</td>';
	        echo '<td><a target="_blank" href="'.esc_url($r['cart_url']).'">Recover Cart</a></td>';
	        echo '<td>';

	        foreach ( $r['products'] as $p ) {
	            echo '<a target="_blank" href="'.esc_url($p['url']).'">'
	                .esc_html($p['name']).'</a><br>';
	        }

	        echo '</td>';

	        if ( isset($_GET['send_emails']) )
	    		echo '<td>' . $r['email_info'] . '</td>';

	    	echo '</tr>';
	    }

	    echo '</table>';

	    exit;
	}

	function send_add_to_cart_email_reresend( WC_Order $order, $cart_links ) {

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-missing-payment-email'];

	    if ( $order->get_meta('_ah_add_to_cart_email_reresend') ) {
	        return false;
	    }

	    $email = $order->get_billing_email();
	    if ( ! $email ) {
	        return false;
	    }

	    //$email = 'jaime+testemailmissing@alliahealth.co';

	    $subject = 'Quick Step to Complete Your Order 💛';

	    $links_html = '';
	    foreach ( $cart_links as $link ) {
	        $links_html .= '<p>👉 <a href="'.esc_url($link).'">Complete your payment here</a></p>';
	    }

	    $logo_url = 'https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-brello-logo-2026.png';
	    ob_start();
	    ?>

	    <table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
			<tbody>
				<tr>
					<td valign="top" id="m_-7320618254298215061body_content_inner_cell" style="padding:48px 48px 32px">
						<div id="m_-7320618254298215061body_content_inner" style="color:#353d59;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left;margin: auto;max-width: 600px;border:1px solid #e5e5e5;border-radius: 3px;padding:2.5rem" align="left">

						    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
							    <tr>
							        <td align="center">
							            <img src="<?php echo esc_url($logo_url); ?>"
							                 alt="Brello"
							                 style="max-width:220px;height:auto;display:block;border:0;">
							        </td>
							    </tr>
							</table>
							<br>

						    <p style="text-align: left;">Hi there,</p>

						    <p style="text-align: left;">
						    We tried to process your recent Brello order, but the payment didn’t go through or may have expired.
						    </p>

						    <p style="text-align: left;">No worries — it happens 😊</p>

						    <p style="text-align: left;">To keep things moving, just use the secure link below to re-enter your payment details:</p>

						    <?php echo $links_html; ?>

						    <p style="text-align: left;">
						    Once that’s done, your order will resume right away and you’ll be directed
						    to complete your intake for provider review.
						    </p>

						    <p style="text-align: left;">
						    If payment isn’t updated, the order will stay pending and may be cancelled.
						    </p>

						    <p style="text-align: left;">
						    Need help? We’re here for you 💬
						    </p>

						    <p style="text-align: left;">
						    Warmly,<br>
						    <strong>The Brello Team 💛</strong>
						    </p>

	    				</div>
					</td>
				</tr>
			</tbody>
		</table>
	    <?php

	    $message = ob_get_clean();

	    $mailer  = WC()->mailer();

	    $headers = ['Content-Type: text/html; charset=UTF-8'];

	    $sent = $mailer->send( $email, $subject, $message, $headers );

	    if ( $sent ) {

	        $order->update_meta_data(
	            '_ah_add_to_cart_email_reresend',
	            current_time('mysql')
	        );

	        $order->add_order_note('Add-to-cart recovery email resent.');
	        $order->save();

	        $logger->info('Recovery email resent', [
	            'order_id' => $order->get_id(),
	            'email'    => $email
	        ]);
	    }

	    return $sent;
	}

	function send_notification_missing_payments_reresend() {

	    if ( ! isset($_GET['ah_export_missing_payments']) ) {
	        return;
	    }

	    global $wpdb;

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-export-payments'];

	    // ---- QUERY HPOS ----
	    $sql = "
		    SELECT o.id, o.status
		    FROM {$wpdb->prefix}wc_orders_meta sent

		    INNER JOIN {$wpdb->prefix}wc_orders o
		        ON o.id = sent.order_id
		        AND o.type = 'shop_order'
		        AND o.status = 'wc-pending'

		    LEFT JOIN {$wpdb->prefix}wc_orders_meta resend
		        ON resend.order_id = o.id
		        AND resend.meta_key = '_ah_add_to_cart_email_reresend'

		    WHERE sent.meta_key = '_ah_add_to_cart_email_resend'
		    AND resend.id IS NULL

		    ORDER BY o.id ASC
		";

	    $results = $wpdb->get_results($sql);

	    _print($sql);//_print($results);die('testing');

	    if ( empty($results) ) {
	        wp_die('No orders found.');
	    }
    

	    $unique_emails = [];
	    $rows          = [];
	    $duplicates    = 0;

	    foreach ( $results as $row ) {

	        $order = wc_get_order( $row->id );
	        if ( ! $order ) {
	            continue;
	        }

	        $email = strtolower( trim( $order->get_billing_email() ) );

	        // ---- DEDUP EMAIL ----
	        if ( isset( $unique_emails[$email] ) ) {
	            $duplicates++;
	            continue;
	        }

	        $unique_emails[$email] = true;

	        $order_id = $order->get_id();

	        $products   = [];
	        $cart_items = [];

	        foreach ( $order->get_items('line_item') as $item ) {

	            $product_id   = $item->get_product_id();
	            $variation_id = $item->get_variation_id();
	            $qty          = max(1, (int) $item->get_quantity());

	            $parent_product = wc_get_product( $product_id );

	            $product_url = $parent_product
	                ? get_permalink( $parent_product->get_id() )
	                : '';

	            // Build variation URL
	            $variation_url = $product_url;

	            if ( $variation_id && $product_url ) {

	                $variation_obj = wc_get_product( $variation_id );

	                if ( $variation_obj ) {

	                    $query = [];

	                    foreach ( $variation_obj->get_attributes() as $tax => $value ) {

	                        if ( is_array($value) ) {
	                            $value = reset($value);
	                        }

	                        if ( ! empty($value) ) {
	                            $query['attribute_'.$tax] = (string)$value;
	                        }
	                    }

	                    if ( $query ) {
	                        $variation_url = add_query_arg($query, $product_url);
	                    }
	                }

	                // Guardamos items para carrito combinado
	                $cart_items[] = [
	                    'id'  => $variation_id,
	                    'qty' => $qty,
	                ];
	            }

	            $products[] = [
	                'name' => $item->get_name(),
	                'url'  => $variation_url,
	            ];
	        }

	        /**
	         * ✅ BUILD COMBINED CART URL
	         */
	        $combined_cart_url = wc_get_cart_url();

	        foreach ( $cart_items as $ci ) {
	            $combined_cart_url = add_query_arg(
	                [
	                    'add-to-cart' => $ci['id'],
	                    'quantity'    => $ci['qty'],
	                ],
	                $combined_cart_url
	            );
	        }


	        $_row = [
	            'order_id' => $order_id,
	            'email'    => $email,
	            'products' => $products,
	            'cart_url' => $combined_cart_url,
	        ];

	        // ---- SEND EMAIL ----
	        if ( isset($_GET['send_emails']) ) {

	            $sent=$this->send_add_to_cart_email(
	                $order,
	                [ $combined_cart_url ]
	            );
	            $_row['email_info']	=	$_row? 'Sent':'No';

	            //sleep(1); // anti Gmail throttle
	        }
	        $rows[] = $_row;
	    }

	    // ---- PREVIEW HTML ----
	    nocache_headers();

	    echo '<h2>Missing Payments Preview</h2>';
	    echo '<p>Total: '.count($results).' | Unique: '.count($rows).' | Duplicates: '.$duplicates.'</p>';

	    echo '<table border="1" cellpadding="6">';
	    echo '<tr><th>Order</th><th>Email</th><th>Add to Cart</th><th>Products</th>';
	    if ( isset($_GET['send_emails']) )
	    	echo '<th>Email Info</th>';
	    echo '</tr>';

	    foreach ( $rows as $r ) {

	        echo '<tr>';
	        echo '<td>#'.esc_html($r['order_id']).'</td>';
	        echo '<td>'.esc_html($r['email']).'</td>';
	        echo '<td><a target="_blank" href="'.esc_url($r['cart_url']).'">Recover Cart</a></td>';
	        echo '<td>';

	        foreach ( $r['products'] as $p ) {
	            echo '<a target="_blank" href="'.esc_url($p['url']).'">'
	                .esc_html($p['name']).'</a><br>';
	        }

	        echo '</td>';

	        if ( isset($_GET['send_emails']) )
	    		echo '<td>' . $r['email_info'] . '</td>';

	    	echo '</tr>';
	    }

	    echo '</table>';

	    exit;
	}


	function get_recovery_order_template_email($cart_links){

	    $links_html = '';
	    foreach ( $cart_links as $link ) {
	        $links_html .= '<p>👉 <a href="'.esc_url($link).'">Complete your payment here</a></p>';
	    }
		$logo_url = 'https://www.brellohealth.com/wp-content/uploads/2026/01/cropped-brello-logo-2026.png';
	    ob_start();
	    ?>

	    <table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
			<tbody>
				<tr>
					<td valign="top" id="m_-7320618254298215061body_content_inner_cell" style="padding:48px 48px 32px">
						<div id="m_-7320618254298215061body_content_inner" style="color:#353d59;font-family:&quot;Helvetica Neue&quot;,Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left;margin: auto;max-width: 600px;border:1px solid #e5e5e5;border-radius: 3px;padding:2.5rem" align="left">

						    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
							    <tr>
							        <td align="center">
							            <img src="<?php echo esc_url($logo_url); ?>"
							                 alt="Brello"
							                 style="max-width:220px;height:auto;display:block;border:0;">
							        </td>
							    </tr>
							</table>
							<br>

						    <p style="text-align: left;">Hi there,</p>

						    <p style="text-align: left;">
						    We tried to process your recent Brello order, but the payment didn’t go through or may have expired.
						    </p>

						    <p style="text-align: left;">No worries — it happens 😊</p>

						    <p style="text-align: left;">To keep things moving, just use the secure link below to re-enter your payment details:</p>

						    <?php echo $links_html; ?>

						    <p style="text-align: left;">
						    Once that’s done, your order will resume right away and you’ll be directed
						    to complete your intake for provider review.
						    </p>

						    <p style="text-align: left;">
						    If payment isn’t updated, the order will stay pending and may be cancelled.
						    </p>

						    <p style="text-align: left;">
						    Need help? We’re here for you 💬
						    </p>

						    <p style="text-align: left;">
						    Warmly,<br>
						    <strong>The Brello Team 💛</strong>
						    </p>

	    				</div>
					</td>
				</tr>
			</tbody>
		</table>
	    <?php

	    $message = ob_get_clean();
	    return $message;
	}

	function print_preview_table_html($results, $rows, $duplicates, $display_sent_mail_column){

		echo '<html><head><title>Missing Payments Preview</title>
		    <style>
		        body{font-family:Arial;padding:20px;background:#f6f7f7}
		        table{border-collapse:collapse;width:100%;background:#fff}
		        th,td{border:1px solid #ccc;padding:8px;text-align:left;}
		        th{background:#23282d;color:#fff}
		        tr:nth-child(even){background:#f9f9f9}
		        .stats{margin-bottom:15px;padding:10px;background:#fff;border:1px solid #ccc;display:flex;gap:1rem}
		        a{color:#2271b1;text-decoration:none}
		    </style>
		    </head><body>';

		//echo '<h2>Missing Payments Preview</h2>';
	    //echo '<p>Total: '.count($results).' | Unique: '.count($rows).' | Duplicates: '.$duplicates.'</p>';

		echo '<h2>Missing Payments — Preview</h2>';
	    echo '<div class="stats">';
	    echo '<strong>Total orders found:</strong> ' . count($results) . '<br>';
	    echo '<strong>Unique emails:</strong> ' . count($rows) . '<br>';
	    echo '<strong>Duplicates skipped:</strong> ' . $duplicates;
	    echo '</div>';


	    echo '<table><thead>';
	    echo '<tr><th>Order</th><th>Email</th><th>Add to Cart</th><th>Products</th>';
	    if ( $display_sent_mail_column )
	    	echo '<th>Email Info</th>';
	    echo '</thead></tr>';

	    echo '<tbody>';

	    foreach ( $rows as $r ) {

	        echo '<tr>';
	        echo '<td>#'.esc_html($r['order_id']).'</td>';
	        echo '<td>'.esc_html($r['email']).'</td>';
	        echo '<td><a target="_blank" href="'.esc_url($r['cart_url']).'">Recover Cart</a></td>';
	        echo '<td>';

	        foreach ( $r['products'] as $p ) {
	            echo '<a target="_blank" href="'.esc_url($p['url']).'">'
	                .esc_html($p['name']).'</a><br>';
	        }

	        echo '</td>';

	        if ( $display_sent_mail_column )
	    		echo '<td>' . $r['email_info'] . '</td>';

	    	echo '</tr>';
	    }

	    echo '</tbody></table>';
    	echo '</body></html>';
	}

	function send_add_to_cart_email( WC_Order $order, $cart_links, $meta_key ) {

	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-missing-payment-email'];

	    if ( $order->get_meta($meta_key) ) {
	        return false;
	    }

	    $email = $order->get_billing_email();
	    if ( ! $email ) {
	        return false;
	    }
	    //$email = 'jaime+testemailmissing@alliahealth.co';
	    $subject = 'Quick Step to Complete Your Order 💛';
	    $message =	$this->get_recovery_order_template_email($cart_links);
	    $mailer  =	WC()->mailer();
	    $headers =	['Content-Type: text/html; charset=UTF-8'];
	    $sent 	 =	$mailer->send( $email, $subject, $message, $headers );

	    if ( $sent ) {
	        $order->update_meta_data( $meta_key, current_time('mysql') );
	        $order->add_order_note('Add-to-cart recovery email resent.');
	        $order->save();
	        $logger->info('Recovery email resent', [ 'order_id' => $order->get_id(), 'email'    => $email ]);
	    }
	    return $sent;
	}

	function send_notification_missing_payments() {
	    if ( ! isset($_GET['ah_export_missing_payments']) ) {
	        return;
	    }
	    global $wpdb;
	    $logger  = wc_get_logger();
	    $context = ['source' => 'ah-export-payments'];
	    // ---- QUERY HPOS ----
	    $meta_key='_ah_add_to_cart_email_rereresend';
	    $sql = "
		    SELECT o.id, o.status
		    FROM {$wpdb->prefix}wc_orders_meta sent

		    INNER JOIN {$wpdb->prefix}wc_orders o
		        ON o.id = sent.order_id
		        AND o.type = 'shop_order'
		        AND o.status = 'wc-pending'

		    LEFT JOIN {$wpdb->prefix}wc_orders_meta resend
		        ON resend.order_id = o.id
		        AND resend.meta_key = '{$meta_key}'

		    WHERE sent.meta_key = '_ah_add_to_cart_email_reresend'
		    AND resend.id IS NULL

		    ORDER BY o.id ASC
		";
	    $results = $wpdb->get_results($sql);
	    //echo '<small>';_print($sql);echo '</small>';//_print($results);die('testing');
	    if ( empty($results) ) {
	        wp_die('No orders found.');
	    }
	    $unique_emails = [];
	    $rows          = [];
	    $duplicates    = 0;
	    $display_sent_mail_column	=	isset($_GET['send_emails']);

	    foreach ( $results as $row ) {
	        $order = wc_get_order( $row->id );
	        if ( ! $order ) {
	            continue;
	        }
	        $email = strtolower( trim( $order->get_billing_email() ) );
	        // ---- DEDUP EMAIL ----
	        if ( isset( $unique_emails[$email] ) ) {
	            $duplicates++;
	            continue;
	        }

	        $unique_emails[$email] = true;
	        $order_id = $order->get_id();
	        $products   = [];
	        $cart_items = [];

	        foreach ( $order->get_items('line_item') as $item ) {
	            $product_id   = $item->get_product_id();
	            $variation_id = $item->get_variation_id();
	            $qty          = max(1, (int) $item->get_quantity());
	            $parent_product = wc_get_product( $product_id );
	            $product_url = $parent_product
	                ? get_permalink( $parent_product->get_id() )
	                : '';
	            // Build variation URL
	            $variation_url = $product_url;
	            if ( $variation_id && $product_url ) {
	                $variation_obj = wc_get_product( $variation_id );
	                if ( $variation_obj ) {
	                    $query = [];
	                    foreach ( $variation_obj->get_attributes() as $tax => $value ) {
	                        if ( is_array($value) ) {
	                            $value = reset($value);
	                        }
	                        if ( ! empty($value) ) {
	                            $query['attribute_'.$tax] = (string)$value;
	                        }
	                    }
	                    if ( $query ) {
	                        $variation_url = add_query_arg($query, $product_url);
	                    }
	                }

	                $cart_items[] = [
	                    'id'  => $variation_id,
	                    'qty' => $qty,
	                ];
	            }

	            $products[] = [
	                'name' => $item->get_name(),
	                'url'  => $variation_url,
	            ];
	        }

	        $combined_cart_url = wc_get_cart_url();
	        foreach ( $cart_items as $ci ) {
	            $combined_cart_url = add_query_arg(
	                [
	                    'add-to-cart' => $ci['id'],
	                    'quantity'    => $ci['qty'],
	                ],
	                $combined_cart_url
	            );
	        }

	        $_row = [
	            'order_id' => $order_id,
	            'email'    => $email,
	            'products' => $products,
	            'cart_url' => $combined_cart_url,
	        ];

	        if ( $display_sent_mail_column ) {
	            $sent=$this->send_add_to_cart_email(
	                $order,
	                [ $combined_cart_url ],
	                $meta_key
	            );
	            $_row['email_info']	=	$_row? 'Sent':'No';
	            //sleep(1); // anti Gmail throttle
	        }
	        $rows[] = $_row;
	    }
	    // ---- PREVIEW HTML ----
	    nocache_headers();
	    $this->print_preview_table_html($results, $rows, $duplicates, $display_sent_mail_column);
	    exit;
	}



}

/**
 * Export CSV of orders without Stripe intent (HPOS)
 * Browser execution
 */
add_action( 'init', function () {

    if ( ! isset($_GET['ah_export_missing_payments__old']) ) {
        return;
    }

    // if ( ! current_user_can('manage_woocommerce') ) {
    //     wp_die('Unauthorized');
    // }

    global $wpdb;

    $logger  = wc_get_logger();
    $context = ['source' => 'ah-export-payments'];

    // ---- QUERY HPOS ----
    $results = $wpdb->get_results("
        SELECT o.id, o.status
        FROM {$wpdb->prefix}wc_orders o

        LEFT JOIN {$wpdb->prefix}wc_orders_meta intent
            ON o.id = intent.order_id
            AND intent.meta_key = '_stripe_intent_id'

        WHERE o.type = 'shop_order' 
        AND o.id >= 527516
        AND o.status IN ('wc-pending')
        AND intent.meta_value IS NULL
        ORDER BY o.id DESC
    ");

    if ( empty($results) ) {
        wp_die('No orders found.');
    }

    // ---- CSV HEADERS ----
    $filename = 'missing-payments-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");

    $output = fopen('php://output', 'w');

    // CSV columns
    fputcsv($output, [
        'Order ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'State',
        'Order Status',
        'Payment URL',
        'Admin URL'
    ]);

    // ---- LOOP ORDERS ----
    foreach ( $results as $row ) {

        $order = wc_get_order( $row->id );

        if ( ! $order ) {
            continue;
        }

        $order_id = $order->get_id();

        $payment_url = $order->get_checkout_payment_url();

        // HPOS admin edit URL
        $admin_url = admin_url(
            "admin.php?page=wc-orders&action=edit&id={$order_id}"
        );

        fputcsv($output, [
            $order_id,
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_billing_email(),
            $order->get_billing_phone(),
            $order->get_shipping_state(),
            $order->get_status(),
            $payment_url,
            $admin_url,
        ]);
    }

    fclose($output);

    $logger->info(
        'CSV export generated',
        array_merge($context, [
            'orders_count' => count($results)
        ])
    );

    exit;
});

add_action( 'init', function () {

    if ( ! isset($_GET['ah_export_missing_payments_']) ) {
        return;
    }

    // if ( ! current_user_can('manage_woocommerce') ) {
    //     wp_die('Unauthorized');
    // }

    global $wpdb;

    $logger  = wc_get_logger();
    $context = ['source' => 'ah-export-payments'];

    // ---- QUERY HPOS ----
    $results = $wpdb->get_results("
        SELECT o.id, o.status
        FROM {$wpdb->prefix}wc_orders o

        LEFT JOIN {$wpdb->prefix}wc_orders_meta intent
            ON o.id = intent.order_id
            AND intent.meta_key = '_stripe_intent_id'

        WHERE o.type = 'shop_order' 
        AND o.id >= 527516
        AND o.status IN ('wc-pending')
        AND intent.meta_value IS NULL
        ORDER BY o.id DESC
    ");

    if ( empty($results) ) {
        wp_die('No orders found.');
    }

    // ---- DEDUP EMAILS ----
    $unique_emails = [];
    $rows          = [];
    $duplicates    = 0;

    foreach ( $results as $row ) {

        $order = wc_get_order( $row->id );
        if ( ! $order ) {
            continue;
        }

        $email = strtolower( trim( $order->get_billing_email() ) );

        // Skip duplicated emails
        if ( isset( $unique_emails[$email] ) ) {
            $duplicates++;
            continue;
        }

        $unique_emails[$email] = true;

        $order_id   = $order->get_id();
        $payment_url = $order->get_checkout_payment_url();
        $admin_url   = admin_url(
            "admin.php?page=wc-orders&action=edit&id={$order_id}"
        );

        $rows[] = [
            'order_id'  => $order_id,
            'first'     => $order->get_billing_first_name(),
            'last'      => $order->get_billing_last_name(),
            'email'     => $email,
            'phone'     => $order->get_billing_phone(),
            'state'     => $order->get_shipping_state(),
            'status'    => $order->get_status(),
            'payment'   => $payment_url,
            'admin'     => $admin_url,
        ];
    }

    // ---- OUTPUT HTML ----
    nocache_headers();

    echo '<html><head><title>Missing Payments Preview</title>
    <style>
        body{font-family:Arial;padding:20px;background:#f6f7f7}
        table{border-collapse:collapse;width:100%;background:#fff}
        th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:13px}
        th{background:#23282d;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .stats{margin-bottom:15px;padding:10px;background:#fff;border:1px solid #ccc}
        a{color:#2271b1;text-decoration:none}
    </style>
    </head><body>';

    echo '<h2>Missing Payments — Preview</h2>';

    echo '<div class="stats">';
    echo '<strong>Total orders found:</strong> ' . count($results) . '<br>';
    echo '<strong>Unique emails:</strong> ' . count($rows) . '<br>';
    echo '<strong>Duplicates skipped:</strong> ' . $duplicates;
    echo '</div>';

    echo '<table>';
    echo '<thead>
        <tr>
            <th>Order</th>
            <th>First</th>
            <th>Last</th>
            <th>Email</th>
            <th>Phone</th>
            <th>State</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Admin</th>
        </tr>
    </thead><tbody>';

    foreach ( $rows as $r ) {

        echo '<tr>
            <td>#' . esc_html($r['order_id']) . '</td>
            <td>' . esc_html($r['first']) . '</td>
            <td>' . esc_html($r['last']) . '</td>
            <td>' . esc_html($r['email']) . '</td>
            <td>' . esc_html($r['phone']) . '</td>
            <td>' . esc_html($r['state']) . '</td>
            <td>' . esc_html($r['status']) . '</td>
            <td><a target="_blank" href="' . esc_url($r['payment']) . '">Pay</a></td>
            <td><a target="_blank" href="' . esc_url($r['admin']) . '">Edit</a></td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';

    $logger->info(
        'Missing payments preview generated',
        array_merge($context, [
            'orders_found' => count($results),
            'unique_emails'=> count($rows),
            'duplicates'   => $duplicates
        ])
    );

    exit;
});

add_action( 'init', function () {

    if ( ! isset($_GET['ah_export_missing_payments__']) ) {
        return;
    }

    global $wpdb;

    $logger  = wc_get_logger();
    $context = ['source' => 'ah-export-payments'];

    // ---- QUERY HPOS ----
    $results = $wpdb->get_results("
        SELECT o.id, o.status
        FROM {$wpdb->prefix}wc_orders o

        LEFT JOIN {$wpdb->prefix}wc_orders_meta intent
            ON o.id = intent.order_id
            AND intent.meta_key = '_stripe_intent_id'

        WHERE o.type = 'shop_order'
        AND o.id >= 527516
        AND o.status IN ('wc-pending')
        AND intent.meta_value IS NULL
        ORDER BY o.id DESC
    ");

    if ( empty($results) ) {
        wp_die('No orders found.');
    }

    // ---- DEDUP EMAILS ----
    $unique_emails = [];
    $rows          = [];
    $duplicates    = 0;

    foreach ( $results as $row ) {

        $order = wc_get_order( $row->id );
        if ( ! $order ) {
            continue;
        }

        $email = strtolower( trim( $order->get_billing_email() ) );

        // Skip duplicated emails
        if ( isset( $unique_emails[$email] ) ) {
            $duplicates++;
            continue;
        }

        $unique_emails[$email] = true;

        $order_id    = $order->get_id();
        $payment_url = $order->get_checkout_payment_url();
        $admin_url   = admin_url(
            "admin.php?page=wc-orders&action=edit&id={$order_id}"
        );

        /**
         * ==========================
         * PRODUCTS EXTRACTION
         * ==========================
         */
        $products = [];

        foreach ( $order->get_items('line_item') as $item_id => $item ) {

            $product      = $item->get_product();
            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            // real purchased product
            $real_id = $variation_id ?: $product_id;

            $products[] = [
                'name'         => $item->get_name(),
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'qty'          => $item->get_quantity(),
                'total'        => wc_format_decimal( $item->get_total(), 2 ),
                'sku'          => $product ? $product->get_sku() : '',
                'type'         => $product ? $product->get_type() : '',
            ];
        }

        $rows[] = [
            'order_id'  => $order_id,
            'first'     => $order->get_billing_first_name(),
            'last'      => $order->get_billing_last_name(),
            'email'     => $email,
            'phone'     => $order->get_billing_phone(),
            'state'     => $order->get_shipping_state(),
            'status'    => $order->get_status(),
            'payment'   => $payment_url,
            'admin'     => $admin_url,
            'products'  => $products,
        ];
    }

    // ---- OUTPUT HTML ----
    nocache_headers();

    echo '<html><head><title>Missing Payments Preview</title>
    <style>
        body{font-family:Arial;padding:20px;background:#f6f7f7}
        table{border-collapse:collapse;width:100%;background:#fff}
        th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:13px}
        th{background:#23282d;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .stats{margin-bottom:15px;padding:10px;background:#fff;border:1px solid #ccc}
        a{color:#2271b1;text-decoration:none}
        .products{font-size:12px;line-height:1.4}
    </style>
    </head><body>';

    echo '<h2>Missing Payments — Preview</h2>';

    echo '<div class="stats">';
    echo '<strong>Total orders found:</strong> ' . count($results) . '<br>';
    echo '<strong>Unique emails:</strong> ' . count($rows) . '<br>';
    echo '<strong>Duplicates skipped:</strong> ' . $duplicates;
    echo '</div>';

    echo '<table>';
    echo '<thead>
        <tr>
            <th>Order</th>
            <th>First</th>
            <th>Last</th>
            <th>Email</th>
            <th>Phone</th>
            <th>State</th>
            <th>Status</th>
            <th>Products</th>
            <th>Payment</th>
            <th>Admin</th>
        </tr>
    </thead><tbody>';

    foreach ( $rows as $r ) {

        echo '<tr>
            <td>#' . esc_html($r['order_id']) . '</td>
            <td>' . esc_html($r['first']) . '</td>
            <td>' . esc_html($r['last']) . '</td>
            <td>' . esc_html($r['email']) . '</td>
            <td>' . esc_html($r['phone']) . '</td>
            <td>' . esc_html($r['state']) . '</td>
            <td>' . esc_html($r['status']) . '</td>
            <td class="products">';

        foreach ( $r['products'] as $p ) {
            echo esc_html(
                "{$p['name']} | SKU: {$p['sku']} | VID: {$p['variation_id']} | Qty: {$p['qty']}"
            ) . '<br>';
        }

        echo '</td>
            <td><a target="_blank" href="' . esc_url($r['payment']) . '">Pay</a></td>
            <td><a target="_blank" href="' . esc_url($r['admin']) . '">Edit</a></td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';

    $logger->info(
        'Missing payments preview generated',
        array_merge($context, [
            'orders_found' => count($results),
            'unique_emails'=> count($rows),
            'duplicates'   => $duplicates
        ])
    );

    exit;
});

add_action( 'init', function () {

    if ( ! isset($_GET['ah_export_missing_payments___']) ) {
        return;
    }

    global $wpdb;

    $logger  = wc_get_logger();
    $context = ['source' => 'ah-export-payments'];

    // ---- QUERY HPOS ----
    $results = $wpdb->get_results("
        SELECT o.id, o.status
        FROM {$wpdb->prefix}wc_orders o

        LEFT JOIN {$wpdb->prefix}wc_orders_meta intent
            ON o.id = intent.order_id
            AND intent.meta_key = '_stripe_intent_id'

        WHERE o.type = 'shop_order'
        AND o.id >= 527516
        AND o.status IN ('wc-pending')
        AND intent.meta_value IS NULL
        ORDER BY o.id DESC
    ");

    if ( empty($results) ) {
        wp_die('No orders found.');
    }

    // ---- DEDUP EMAILS ----
    $unique_emails = [];
    $rows          = [];
    $duplicates    = 0;

    try {
    	
    

    foreach ( $results as $row ) {

        $order = wc_get_order( $row->id );
        if ( ! $order ) {
            continue;
        }

        $email = strtolower( trim( $order->get_billing_email() ) );

        // Skip duplicated emails
        if ( isset( $unique_emails[$email] ) ) {
            $duplicates++;
            continue;
        }

        $unique_emails[$email] = true;

        $order_id    = $order->get_id();
        $payment_url = $order->get_checkout_payment_url();
        $admin_url   = admin_url(
            "admin.php?page=wc-orders&action=edit&id={$order_id}"
        );

        /**
         * ==========================
         * PRODUCTS EXTRACTION
         * ==========================
         */
        $products = [];

		foreach ( $order->get_items('line_item') as $item_id => $item ) {

		    $product      = $item->get_product();
		    $product_id   = $item->get_product_id();
		    $variation_id = $item->get_variation_id();

		    $parent_product = wc_get_product( $product_id );

		    // Base product URL
		    $product_url = $parent_product
		        ? get_permalink( $parent_product->get_id() )
		        : '';

		    /**
		     * Build variation URL
		     * Woo selects variation using attributes in query string
		     */
		    $variation_url = $product_url;

			if ( $variation_id && $product_url ) {

			    $variation_obj = wc_get_product( $variation_id );

			    if ( $variation_obj ) {

			        $var_attrs = $variation_obj->get_attributes(); // e.g. [ 'pa_duration' => 'monthly' ]
			        $query     = [];

			        foreach ( $var_attrs as $tax_or_name => $value ) {

			            if ( $value === '' || $value === null ) {
			                continue;
			            }

			            // Woo expects "attribute_{taxonomy}" or "attribute_{name}"
			            $key = 'attribute_' . $tax_or_name;

			            // Ensure scalar string
			            if ( is_array( $value ) ) {
			                $value = reset( $value );
			            }

			            $query[ $key ] = (string) $value;
			        }

			        if ( ! empty( $query ) ) {
			            $variation_url = add_query_arg( $query, $product_url );
			        }
			    }
			}

			 /**
		     * DIRECT ADD TO CART URL (SAFE VERSION)
		     */
		    $cart_url = '';

		    if ( $variation_id ) {

		        $cart_url = add_query_arg(
		            [
		                'add-to-cart' => $variation_id,
		                'quantity'    => max(1, intval($qty)),
		            ],
		            wc_get_cart_url()
		        );
		    }

		    

		    $products[] = [
		        'name'          => $item->get_name(),
		        'product_id'    => $product_id,
		        'variation_id'  => $variation_id,
		        'url'           => $variation_url,
        		'cart_url'     => $cart_url,
		    ];
		}


        $rows[] = [
            'order_id'  => $order_id,
            'first'     => $order->get_billing_first_name(),
            'last'      => $order->get_billing_last_name(),
            'email'     => $email,
            'phone'     => $order->get_billing_phone(),
            'state'     => $order->get_shipping_state(),
            'status'    => $order->get_status(),
            'payment'   => $payment_url,
            'admin'     => $admin_url,
            'products'  => $products,
        ];
    }


    } catch (Exception $e) {
    	die($e);	
    }

    // ---- OUTPUT HTML ----
    nocache_headers();

    echo '<html><head><title>Missing Payments Preview</title>
    <style>
        body{font-family:Arial;padding:20px;background:#f6f7f7}
        table{border-collapse:collapse;width:100%;background:#fff}
        th,td{border:1px solid #ccc;padding:8px;text-align:left;font-size:13px}
        th{background:#23282d;color:#fff}
        tr:nth-child(even){background:#f9f9f9}
        .stats{margin-bottom:15px;padding:10px;background:#fff;border:1px solid #ccc}
        a{color:#2271b1;text-decoration:none}
        .products{font-size:12px;line-height:1.4}
    </style>
    </head><body>';

    echo '<h2>Missing Payments — Preview</h2>';

    echo '<div class="stats">';
    echo '<strong>Total orders found:</strong> ' . count($results) . '<br>';
    echo '<strong>Unique emails:</strong> ' . count($rows) . '<br>';
    echo '<strong>Duplicates skipped:</strong> ' . $duplicates;
    echo '</div>';

    echo '<table>';
    echo '<thead>
        <tr>
            <th>Order</th>
            <th>First</th>
            <th>Last</th>
            <th>Email</th>
            <th>Phone</th>
            <th>State</th>
            <th>Status</th>
            <th>Add to Cart</th>
            <th>Products</th>
            <th>Payment</th>
            <th>Admin</th>
        </tr>
    </thead><tbody>';

    foreach ( $rows as $r ) {

        echo '<tr>
            <td>#' . esc_html($r['order_id']) . '</td>
            <td>' . esc_html($r['first']) . '</td>
            <td>' . esc_html($r['last']) . '</td>
            <td>' . esc_html($r['email']) . '</td>
            <td>' . esc_html($r['phone']) . '</td>
            <td>' . esc_html($r['state']) . '</td>
            <td>' . esc_html($r['status']) . '</td>';

        // Recreate cart
        echo '<td>';
        foreach ( $r['products'] as $p ) {
            if ( empty($p['cart_url']) ) continue;

            echo '<a target="_blank" href="'.esc_url($p['cart_url']).'">
                    Add to Cart
                  </a><br>';
        }
        echo '</td>';

        // Products
        echo '<td class="products">';
        foreach ( $r['products'] as $p ) {
            echo '<a target="_blank" href="'.esc_url($p['url']).'">'
                . esc_html($p['name'])
                . '</a><br>';
        }
        echo '</td>';


        echo '
            <td><a target="_blank" href="' . esc_url($r['payment']) . '">Pay</a></td>
            <td><a target="_blank" href="' . esc_url($r['admin']) . '">Edit</a></td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';

    $logger->info(
        'Missing payments preview generated',
        array_merge($context, [
            'orders_found' => count($results),
            'unique_emails'=> count($rows),
            'duplicates'   => $duplicates
        ])
    );

    exit;
});

