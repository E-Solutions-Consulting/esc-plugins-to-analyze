<?php

/*
*	Capture Automatic If order is not a Subscription Product
*/
$this->loader->add_filter( 'woocommerce_stripe_request_body', $plugin_public, 'custom_handle_stripe_payment_capture', 10, 2 );
/*
*	Capture Automatic If order is not a Subscription Product
*/
function custom_handle_stripe_payment_capture($request, $api) {
    if ($api === 'payment_intents' && isset($request['metadata']['order_id'])) {
        $order = wc_get_order($request['metadata']['order_id']);
        bh_plugins_log(['custom_stripe_capture_non_subscriptions', json_encode($request['metadata'])]);
        if ($order && class_exists('WC_Subscriptions_Product')) {
            $has_subscription = false;
            
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (WC_Subscriptions_Product::is_subscription($product)) {
                    $has_subscription = true;
                    break;
                }
            }
            bh_plugins_log('has_subscription=' . var_export($has_subscription, true) );
            if (!$has_subscription) {
                unset($request['capture_method']);
            }
        }
    }
    return $request;
}



/**
 * Add Subscription Export Menu
 */
add_action('admin_menu', [$plugin_admin, 'add_export_subscriptions_page'] );
add_action('wp_ajax_process_subscriptions_batch', [$plugin_admin, 'process_subscriptions_batch']);
add_action('wp_ajax_check_export_file', [$plugin_admin, 'check_export_file']);

	/*
	*	Export Subscriptions
	*/

	function add_export_subscriptions_page() {
		add_submenu_page(
			'woocommerce',
			'Export Subscriptions',
			'Export Subscriptions',
			'manage_options',
			'export-subscriptions',
			[$this, 'export_subscriptions_page']
		);
	}

	function export_subscriptions_page() {
		//delete_transient('subscription_export_active');
		$active_process = get_transient('subscription_export_active');
		?>
		<div class="wrap">
			<h1>Export Subscriptions</h1>
			
			<form method="post" id="export-form">
				<div class="input-filters">
					<div>
						<label>Start date:</label> <input type="date" name="start_date" required>
					</div>
					<div>
						<label>End date:</label> <input type="date" name="end_date" required>
					</div>
					<div>
						<label>Batch size:</label>
						<select name="batch_size">
							<option value="100">100 records</option>
							<option value="250">250 records</option>
							<option value="500">500 records</option>
							<option value="1000">1,000 records</option>
							<option value="5000" selected>5,000 records</option>
							<option value="10000">10,000 records</option>
						</select>					
					</div>
					<div>
						<input type="submit" name="start_export" class="button button-primary" value="Start Export" <?php echo $active_process ? 'disabled' : ''; ?>>
					</div>
				</div>
			</form>
			
			<div id="progress-container" style="display:none; margin-top:20px;">
				<h2>Export Progress</h2>
				<div id="progress-bar" style="background:#fff; height:30px; width:100%;">
					<div id="progress" style="background:#2271b1; height:100%; width:0%;"></div>
				</div>
				<p id="progress-text">Preparing for export...</p>
				<button id="stop-export" class="button button-secondary" style="display:none; margin-right:10px;">Stop Export</button>
				<a id="download-link" href="#" style="display:none;" class="button button-primary">Download CSV File</a>
				<p id="stop-message" style="color:#d63638; display:none;">Export stopped. You can download the data processed so far.</p>
			</div>
			<style>
				.input-filters {display: flex;gap: 20px;}
				.input-filters > div {display: flex;flex-direction: column;gap: 5px;justify-content: flex-end;}
			</style>
			
			<script>
			jQuery(document).ready(function($) {
				var exportProcess = {
					active: false,
					stopRequested: false,
					currentOffset: 0,
					currentTotal: 0,
					file_url: ''
				};
				$('#export-form').on('submit', function(e) {
					e.preventDefault();
					
					if (exportProcess.active) return;
					
					exportProcess.active = true;
					exportProcess.stopRequested = false;
					$('#progress-container').show();
					$('#stop-export').show();
					$('#stop-message').hide();
					
					const formData = $(this).serialize();
					exportProcess.currentOffset = 0;
					processBatch(formData, 0);
				});
				
				$('#stop-export').on('click', function() {
					if (exportProcess.active) {
						exportProcess.stopRequested = true;
						$('#stop-export').prop('disabled', true).text('Stopping...');
						$('#progress-text').html('Stopping process...');
					}
				});
				
				function processBatch(formData, offset) {console.log('processBatch', formData, exportProcess.stopRequested);
					if (exportProcess.stopRequested) {
						finishExport(true);
						return;
					}
					
					$.post(ajaxurl, {
						action: 'process_subscriptions_batch',
						form_data: formData,
						offset: offset,
						total: exportProcess.currentTotal
					}, function(response) {
						if (response.success) {
							if (offset === 0) {
								exportProcess.currentTotal = response.data.total;
							}
							
							const percent = Math.round((response.data.processed / response.data.total) * 100);
							$('#progress').css('width', percent + '%');
							$('#progress-text').html(`Processing: ${response.data.processed} of ${response.data.total} records (${percent}%)`);
							exportProcess.currentOffset = response.data.next_offset;
							
							if (response.data.complete) {
								exportProcess.file_url	=	response.data.file_url;
								finishExport(false);
							} else if (!exportProcess.stopRequested) {
								setTimeout(function() {
									processBatch(formData, response.data.next_offset);
								}, 500);
							}
						} else {
							$('#progress-text').html('Error: ' + response.data);
							finishExport(true);
						}
					}, 'json').fail(function() {
						$('#progress-text').html('Error in AJAX request');
						finishExport(true);
					});
				}
				
				function finishExport(stopped) {console.log('finishExport', stopped);
					exportProcess.active = false;
					$('#stop-export').hide().prop('disabled', false).text('Stop Export');
					
					if (stopped) {
						$('#stop-message').show();

						$.post(ajaxurl, {
							action: 'check_export_file'
						}, function(response) {
							if (response.success && response.data.file_url) {
								$('#download-link').attr('href', response.data.file_url).show();
							}
						});
					} else {
						$('#download-link').attr('href', exportProcess.file_url).show();
					}
					
					$('input[name="start_export"]').prop('disabled', false);
				}
			});
			</script>
		</div>
		<?php
	}

	function process_subscriptions_batch_original() {
		global $wpdb;
		
		set_transient('subscription_export_active', true, 3600);
		
		parse_str($_POST['form_data'], $form_data);
		$offset = intval($_POST['offset']);
		$batch_size = intval($form_data['batch_size']);
		
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['basedir'] . '/subscriptions_export_temp.csv';
		
		if ($offset === 0) {
			$headers = [
				'subscription_id', 'status', 'start_date', 'customer_email',
				'parent_order_id', 'initial_payment', 'renewal_count',
				'renewal_total', 'total_amount', 'last_payment_date'
			];
			
			$file = fopen($file_path, 'w');
			fputcsv($file, $headers);
			fclose($file);
		}
		
		if ($offset === 0) {
			$total = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
				WHERE type = 'shop_subscription'
				AND  status = 'wc-active' 
				AND date_created_gmt BETWEEN %s AND %s",
				$form_data['start_date'], $form_data['end_date']
			));
		} else {
			$total = intval($_POST['total']);
		}
		
		$sql=$wpdb->prepare(
			"SELECT id, status, billing_email, date_created_gmt, parent_order_id
			FROM {$wpdb->prefix}wc_orders
			WHERE type = 'shop_subscription' 
			AND  status = 'wc-active' 
			AND date_created_gmt BETWEEN %s AND %s
			ORDER BY id ASC
			LIMIT %d OFFSET %d",
			$form_data['start_date'], $form_data['end_date'], $batch_size, $offset
		);

		$subscriptions = $wpdb->get_results($sql);
		bh_plugins_log($sql);
		
		$processed = $offset;
		$file = fopen($file_path, 'a');
		
		foreach ($subscriptions as $sub) {			
			$parent_order = $wpdb->get_row($wpdb->prepare(
				"SELECT id, total_amount, date_created_gmt 
				FROM {$wpdb->prefix}wc_orders 
				WHERE id = %d AND type = 'shop_order' AND status = 'wc-completed'",
				$sub->parent_order_id
			));
			
			$renewals = $wpdb->get_results($wpdb->prepare(
				"SELECT o.id, o.total_amount, o.date_created_gmt
				FROM {$wpdb->prefix}wc_orders_meta rm
				JOIN {$wpdb->prefix}wc_orders o ON o.id = rm.order_id
				WHERE rm.meta_key = '_subscription_renewal'
				AND rm.meta_value = %d
				AND o.type = 'shop_order'
				AND o.status = 'wc-completed'",
				$sub->id
			));
			
			$renewal_count = count($renewals);
			$renewal_total = array_sum(array_column($renewals, 'total_amount'));
			$total_amount = ($parent_order ? $parent_order->total_amount : 0) + $renewal_total;
			
			$last_payment_date = $parent_order ? $parent_order->date_created_gmt : '';
			foreach ($renewals as $renewal) {
				if ($renewal->date_created_gmt > $last_payment_date) {
					$last_payment_date = $renewal->date_created_gmt;
				}
			}
			
			// Escribir fila en CSV
			fputcsv($file, [
				$sub->id,
				$sub->status,
				$sub->date_created_gmt,
				$sub->billing_email,
				$parent_order ? $parent_order->id : '',
				$parent_order ? $parent_order->total_amount : 0,
				$renewal_count,
				$renewal_total,
				$total_amount,
				$last_payment_date
			]);
			
			$processed++;
		}
		
		fclose($file);
		
		$next_offset = $offset + $batch_size;
		$complete = $processed >= $total;
		
		if ($complete) {
			// Renombrar archivo final
			$final_path = $upload_dir['basedir'] . '/subscriptions_export_' . date('Y-m-d-His') . '.csv';
			rename($file_path, $final_path);
			$file_url = $upload_dir['baseurl'] . '/subscriptions_export_' . date('Y-m-d-His') . '.csv';
			
			// Limpiar transient
			delete_transient('subscription_export_active');
		}
		
		wp_send_json_success([
			'processed' => $processed,
			'total' => $total,
			'next_offset' => $next_offset,
			'complete' => $complete,
			'file_url' => $complete ? $file_url : ''
		]);
	}

	function process_subscriptions_batch() {
		global $wpdb;
		try {
		
			set_transient('subscription_export_active', true, 3600);
			
			parse_str($_POST['form_data'], $form_data);
			$offset = intval($_POST['offset']);
			$batch_size = intval($form_data['batch_size']);
			
			$upload_dir = wp_upload_dir();
			$file_path = $upload_dir['basedir'] . '/subscriptions_export_temp.csv';
			
			if ($offset === 0) {
				$headers = [
					'subscription_id', 'status', 'start_date',
					'first_name', 'last_name', 'customer_email', 'phone', 'state', 'state_name', 'city', 
					'parent_order_id', 'initial_payment', 'renewal_count', 
					'renewal_total', 'total_amount', 'last_payment_date'
				];
				
				$file = fopen($file_path, 'w');
				fputcsv($file, $headers);
				fclose($file);
			}
			
			if ($offset === 0) {
				$total = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
					WHERE type = 'shop_subscription'
					AND  status = 'wc-active' 
					AND date_created_gmt BETWEEN %s AND %s",
					$form_data['start_date'], $form_data['end_date']
				));
			} else {
				$total = intval($_POST['total']);
			}
			
			$sql=$wpdb->prepare(
				"SELECT id, status, date_created_gmt, parent_order_id
				FROM {$wpdb->prefix}wc_orders
				WHERE type = 'shop_subscription' 
				AND  status = 'wc-active' 
				AND date_created_gmt BETWEEN %s AND %s
				ORDER BY id ASC
				LIMIT %d OFFSET %d",
				$form_data['start_date'], $form_data['end_date'], $batch_size, $offset
			);

			$subscriptions = $wpdb->get_results($sql);
			//bh_plugins_log(['process_subscriptions_batch($offset=' . $offset . ', $batch_size=' . $batch_size . ', $total='. $total . ')', $sql]);
			
			$processed = $offset;
			$file = fopen($file_path, 'a');
			
			$base_country	=	WC()->countries->get_base_country();
			$country_states =	WC()->countries->get_states($base_country);

			foreach ($subscriptions as $sub) {
				$sql	=	$wpdb->prepare(
						"SELECT first_name, last_name, email, phone, 
								state, city 
						FROM {$wpdb->prefix}wc_order_addresses
						WHERE order_id = %d AND address_type = 'billing'",
						$sub->id
					);
				$address = $wpdb->get_row($sql);
				//bh_plugins_log($sql);
		
				$parent_order = $wpdb->get_row($wpdb->prepare(
					"SELECT id, total_amount, date_created_gmt 
					FROM {$wpdb->prefix}wc_orders 
					WHERE id = %d AND type = 'shop_order' AND status = 'wc-completed'",
					$sub->parent_order_id
				));
				
				$renewals = $wpdb->get_results($wpdb->prepare(
					"SELECT o.id, o.total_amount, o.date_created_gmt
					FROM {$wpdb->prefix}wc_orders_meta rm
					JOIN {$wpdb->prefix}wc_orders o ON o.id = rm.order_id
					WHERE rm.meta_key = '_subscription_renewal'
					AND rm.meta_value = %d
					AND o.type = 'shop_order'
					AND o.status = 'wc-completed'",
					$sub->id
				));
				
				$renewal_count = count($renewals);
				$renewal_total = array_sum(array_column($renewals, 'total_amount'));
				$total_amount = ($parent_order ? $parent_order->total_amount : 0) + $renewal_total;
				
				$last_payment_date = $parent_order ? $parent_order->date_created_gmt : '';
				foreach ($renewals as $renewal) {
					if ($renewal->date_created_gmt > $last_payment_date) {
						$last_payment_date = $renewal->date_created_gmt;
					}
				}
				$state_name	=	'';
				if($address)
					$state_name	=	$country_states[$address->state] ?? $address->state;
				// Escribir fila en CSV
				fputcsv($file, [
					$sub->id,
					$sub->status,
					$sub->date_created_gmt,
					$address ? $address->first_name : '',
					$address ? $address->last_name : '',
					$address ? $address->email : '',
					$address ? $address->phone : '',
					$address ? $address->state : '',
					$state_name ? $state_name : '',					
					$address ? $address->city : '',
					$parent_order ? $parent_order->id : '',
					$parent_order ? $parent_order->total_amount : 0,
					$renewal_count,
					$renewal_total,
					$total_amount,
					$last_payment_date
				]);
				
				$processed++;
				
			}
			
			fclose($file);
			
			$next_offset = $offset + $batch_size;
			$complete = $processed >= $total;
			
			if ($complete) {
				// Renombrar archivo final
				$final_path = $upload_dir['basedir'] . '/subscriptions_export_' . date('Y-m-d-His') . '.csv';
				rename($file_path, $final_path);
				$file_url = $upload_dir['baseurl'] . '/subscriptions_export_' . date('Y-m-d-His') . '.csv';
				
				// Limpiar transient
				delete_transient('subscription_export_active');
			}
			//bh_plugins_log('$processed=' . $processed . ', $next_offset=' . $next_offset . ', $complete=' . $complete);		
			wp_send_json_success([
				'processed' 	=>	$processed,
				'total' 		=>	$total,
				'next_offset' 	=>	$next_offset,
				'complete' 		=>	$complete,
				'file_url' 		=>	$complete ? $file_url : ''
			]);

		} catch (\Throwable $th) {
			bh_plugins_log($th);
		}
		//bh_plugins_log('wp_send_json_success');
	}

	function check_export_file() {
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['basedir'] . '/subscriptions_export_temp.csv';
		
		if (file_exists($file_path)) {
			// Renombrar archivo parcial
			$partial_path = $upload_dir['basedir'] . '/subscriptions_export_PARTIAL_' . date('Y-m-d-His') . '.csv';
			rename($file_path, $partial_path);
			$file_url = $upload_dir['baseurl'] . '/subscriptions_export_PARTIAL_' . date('Y-m-d-His') . '.csv';
			
			delete_transient('subscription_export_active');
			wp_send_json_success(['file_url' => $file_url]);
		} else {
			wp_send_json_error(['message' => 'No se encontró archivo parcial']);
		}
	}

/**
	 * Add Subscription Tools Menu
	 */
	function add_subscription_tools_menu() {
		add_submenu_page(
			'tools.php',
			'Subscriptions Tools',
			'Subscriptions Tools',
			'manage_options',
			'subscription_tools',
			[$this, 'subscription_tools_page']
		);
	}
	function subscription_tools_page() {
		$filtered_subscriptions = [];
		
		if (isset($_POST['filter_subscriptions'])) {
			$filtered_subscriptions = $this->get_filtered_subscriptions($_POST['product_name']);
		}

		if (isset($_POST['export_subscriptions_csv'])) {
			$filtered_subscriptions = $this->get_filtered_subscriptions($_POST['product_name']);
			$this->download_subscriptions_csv($filtered_subscriptions);
		}
		
		if (isset($_POST['send_massive_mail_customers'])) {
			$this->send_massive_mail_customers();
		}
		
		?>
		<div class="wrap">
			<h1>Brello Tools</h1>
			<div class="tablenav top">
				<div class="alignleft actions">
					<form method="post">
						<button type="submit" name="send_massive_mail_customers" class="button">Send Emails To Customers</button>
					</form>
				</div>
			</div>
			<br class="clear">
			<div class="tablenav top">
				<div class="alignleft actions">
					<form method="post">
						<?php
							wp_enqueue_style( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );
							wp_enqueue_script( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true );
							wp_enqueue_script( 'checkbox-product-variations', plugin_dir_url( __DIR__ ) . 'admin/js/checkbox-product-variations.js', array('jquery', 'hb-select2'), null, true );
							wp_localize_script('checkbox-product-variations', 'ajaxurl', admin_url('admin-ajax.php'));


							$args = array(
								'post_type'     =>	'product',
								'posts_per_page'=>	-1,
								'post_status'   =>	['publish', 'draft'],
								'order'			=>	'DESC'
							);

							$productos = new WP_Query( $args );

							if ( $productos->have_posts() ) {
								$product_ids	=	[];
								if(isset($_POST['product_name']))
									$product_ids	=	$_POST['product_name'];

								echo '<select name="product_name[]" id="productos-select2" name="" class="productos-select" multiple="multiple" style="max-width: 500px;">';
								while ( $productos->have_posts() ) {
									$productos->the_post();
									global $product;
									
									$product_name	=	get_the_title();
									$product_id		=	get_the_ID();
									$selected	=	'';
									if(in_array($product_id, $product_ids))
										$selected	=	' selected="selected"';

									echo '<option class="producto-option" value="' . $product_id . '"' . $selected . '>' . $product_name . '</option>';
									
									// Mostrar variaciones si el producto es variable
									if ( $product->is_type( 'variable' ) ) {
										$variaciones = $product->get_available_variations();
										foreach ( $variaciones as $variacion ) {
											$atributos = '';
											foreach ( $variacion['attributes'] as $nombre_atributo => $valor_atributo ) {
												$atributos .= ucfirst( str_replace( 'attribute_pa_', '', $nombre_atributo ) ) . ': ' . $valor_atributo . ' ';
											}
											$selected	=	'';
											if(in_array($variacion['variation_id'], $product_ids))
												$selected	=	' selected="selected"';

											echo '<option class="variacion-option" data-parent="' . $product_name . '" value="' . $variacion['variation_id'] . '" data-producto="' . $product->get_id() . '"' . $selected . '>-- ' . $atributos . '</option>';
										}
									}
								}

								echo '</select>';
							}

						?>
						<button type="submit" name="filter_subscriptions" class="button button-primary">Filter Subscriptions</button>
						<button type="submit" name="export_subscriptions_csv" class="button">Download CSV</button>
					</form>
					
				</div>
			</div>

			
			<hr>

			<?php if(isset($_POST)) : ?>
			<div class="tablenav top">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo count($filtered_subscriptions) ?> items</span>
				</div>
			</div>
			<?php endif; ?>

			<?php
				global $sent_to;
				if(!empty($sent_to)){
					echo '<h3>Emails Notifications:</h3>';
					echo '<ol>';
					foreach ($sent_to as $msg) {
						echo '<li>' . $msg . '</li>';
					}
					echo '</ol>';
				}
			?>

			<?php if (!empty($filtered_subscriptions)) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th width="50">#</th>
							<th width="50">Subscription ID</th>
							<th width="150">Customer</th>
							<th width="150">Email</th>
							<th width="100">Phone</th>
							<th width="150">Product</th>
							<th width="50">Status</th>
							<th width="100">Start Date</th>
							<th width="100">Next Payment</th>
							<th width="50">Days</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($filtered_subscriptions as $key=>$subscription) : ?>
							<tr>
								<td><?php echo ($key+1); ?></td>
								<td><?php echo esc_html($subscription['ID']); ?></td>
								<td><?php echo esc_html($subscription['Customer']); ?></td>
								<td><?php echo esc_html($subscription['Email']); ?></td>
								<td><?php echo esc_html($subscription['Phone']); ?></td>
								<td><?php echo esc_html($subscription['Product']); ?></td>
								<td><?php echo esc_html($subscription['Status']); ?></td>
								<td><?php echo esc_html($subscription['Start Date']); ?></td>
								<td><?php echo esc_html($subscription['Next Payment']); ?></td>
								<td><?php echo $subscription['Days']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php elseif (isset($_POST['filter_subscriptions'])) : ?>
				<p>No subscriptions found for the specified product.</p>
			<?php endif; ?>
		</div>
		<?php
	}
	function get_filtered_subscriptions($product_name) {
		if (!class_exists('WC_Subscriptions')) {
			return [];
		}
		$product_id	=	$product_name;
		$subscriptions = wcs_get_subscriptions_for_product( $product_id,
			'objects', 
			[
				'subscription_status'	=> array( 'active' ),
			] );
		$filtered_subscriptions = [];
		foreach ($subscriptions as $subscription) {
			foreach ($subscription->get_items() as $item) {
				$item_name	=	'';
				$product = $item->get_product();
				if ($product) {
					$data	=	$product->get_data();
					$item_name	=	$data['name'];
				}
				$start_date			=	$subscription->get_date('start'); // Start Date
				$next_payment 		=	$subscription->get_date('next_payment'); // Next Payment Date
				try {
					$start_date_obj 	=	new DateTime($start_date);
					$next_payment_obj 	=	new DateTime($next_payment);

					$diffInSeconds 		=	$next_payment_obj->getTimestamp() - $start_date_obj->getTimestamp();
					$days				=	round($diffInSeconds / (60 * 60 * 24));

					//$days				=	$days . '&nbsp; <a class="update_next_payment" href="#" data-subscription_id="' . $subscription->get_id() . '">Change</a>';
					$days				=	$days;
				} catch (\Throwable $th) {
					$days	=	$th->getMessage();
				}				

				$filtered_subscriptions[] = [
					'ID'	=>	$subscription->get_id(),
					'Customer' 			=>	$subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name(),
					'Email' 			=>	$subscription->get_billing_email(),
					'Phone' 			=>	$subscription->get_billing_phone(),
					'Product' 			=>	$item_name,
					'Status' 			=>	$subscription->get_status(),
					'Start Date' 		=>	$subscription->get_date_to_display('start'),
					'Next Payment'		=>	$subscription->get_date_to_display('next_payment'),
					'Days'				=>	$days
				];
			}
		}

		return $filtered_subscriptions;
	}
	function download_subscriptions_csv($subscriptions) {
		if (empty($subscriptions)) {
			wp_die('No subscriptions found to export.');
		}
		ob_end_clean();
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="subscriptions.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');
		$output = fopen('php://output', 'w');
		fputcsv($output, array_keys($subscriptions[0]));
		foreach ($subscriptions as $row) {
			fputcsv($output, $row);
		}
		fclose($output);
		exit;
	}
	function send_massive_mail_customers(){
		$from_email	=	'info@brellohealth.com';
		$from_name	=	'Brello Health';
		$reply_to	=	'mariana@alliahealth.co';
		$cc_email	=	'mariana@alliahealth.co';
		$bcc_email	=	'jaime@solutionswebonline.com';

		$subject	=	'[Action Required] Your Tirzepatide Subscription Needs Update';		
		$message	=	'';
		ob_start();
		$template_path	=	plugin_dir_path(__FILE__) . 'partials/template-email-notification.php';
		if(file_exists($template_path))
			include $template_path;
		else
			return 'Error: No se puedo encontrar la plantilla. ' . $template_path;

		$message	=	ob_get_clean();

		$recipients	=	[
			[
				'email'	=>	'mariana@alliahealth.co',
				'name'	=>	'Mariana'
			],
			[
				'email'	=>	'jaime@solutionswebonline.com',
				'name'	=>	'Jaime',
			],
			[
				'email'	=>	'inv_jaime@yahoo.com',
				'name'	=>	'Jaime',
			],
			[
				'email'	=>	'ing.jaime.isidro@gmail.com',
				'name'	=>	'Jaime',
			],
			[
				'email'	=>	'xavier.n@telegramd.com',
				'name'	=>	'Xavier',
			],
			[
				'email'	=>	'nick@telegramd.com',
				'name'	=>	'Nick',
			],
			[
				'email'	=>	'marianamaglioni@gmail.com',
				'name'	=>	'Mariana',
			],
			[
				'email'	=>	'mariana@brellohealth.com',
				'name'	=>	'Mariana'
			],
		];
		
		$subscriptions	=	[
			[
				'subscription_id'	=>	546,
				'email'				=>	'camilo@brainpower.agency',
				'customer'			=>	'Camilo',
				'variation_id'		=>	368,
				'subscription'		=>	'MONTHLY'
			],
			[
				'subscription_id'	=>	1608,
				'email'				=>	'camilo@hellowellness.ai',
				'customer'			=>	'Camilo',
				'variation_id'		=>	368,
				'subscription'		=>	'MONTHLY'
			],
			[
				'subscription_id'	=>	6486,
				'email'				=>	'marianamaglioni@gmail.com',
				'customer'			=>	'Mariana',
				'variation_id'		=>	333,
				'subscription'		=>	'MONTHLY'
			],
			[
				'subscription_id'	=>	10763,
				'email'				=>	'mariana@brellohealth.com',
				'customer'			=>	'Mariana',
				'variation_id'		=>	10123,
				'subscription'		=>	'3 MONTH PLAN'
			],
			[
				'subscription_id'	=>	10658,
				'email'				=>	'marianamaglioni@gmail.com',
				'customer'			=>	'Mariana',
				'variation_id'		=>	331,
				'subscription'		=>	'Tirzepatide VIP'
			],
		];
		/*
		$subscriptions	=	[
			[
				'subscription_id'	=>	546,
				'email'				=>	'jaime@solutionswebonline.com',
				'customer'			=>	'Camilo',
				'variation_id'		=>	368,
				'subscription'		=>	'MONTHLY'
			],
			[
				'subscription_id'	=>	1608,
				'email'				=>	'inv_jaime@yahoo.com',
				'customer'			=>	'Camilo',
				'variation_id'		=>	368,
				'subscription'		=>	'MONTHLY'
			],
			[
				'subscription_id'	=>	6486,
				'email'				=>	'ing.jaime.isidro@gmail.com',
				'customer'			=>	'Mariana',
				'variation_id'		=>	333,
				'subscription'		=>	'MONTHLY'
			],
		];
		*/

		$batch_size	=	20;
		$batches	=	array_chunk($subscriptions, $batch_size);
		$headers	=	[
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . '<' . $from_email . '>',
			'Reply-To: ' . $reply_to,
			'CC: ' . $cc_email,
			'BCC: ' . $bcc_email,
		];
		/*
		$headers	=	[
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . '<' . $from_email . '>',
			'Reply-To: ' . $reply_to,
		];
		*/


		/**
		 * Export Subscriptions With Products
		 * 
		 * ID: 251 		Semaglutide Black Friday — Draft
		 * ID: 331 		Tirzepatide VIP
		 * ID: 366 		Tirzepatide Black Friday — Draft
		 * ID: 369		Semaglutide VIP
		 * ID: 10122 	Tirzepatide With B6 (Pyridoxine)
		 * 		ID: 10123	3-Month
		 * ID: 10116	Semaglutide With B6 (Pyridoxine)
		 * 		ID: 10119	3-Month
		 * 		ID: 10120	Monthly
		 */

		 $subscription_id	=	594;

		 $product_id		=	10122;
		 $variation_id		=	10123;

		 $products	=	[
			'tirzepatide'	=>	[
								'id'	=> 	10122,
								'3-m'	=>	10123
							],
			'semaglutide'	=>	[
								'id'	=> 	10116,
								'1-m'	=>	10120,
								'3-m'	=>	10119,
							]
		 ];

		global $sent_to;
		foreach ($batches as $batch) {
			foreach ($batch as $recipient) {
				try {
					$subscription_id	=	$recipient['subscription_id'];
					$product_suggested	=	[
						[
							'product_name'	=>	'Update to 3 Months Tirzepatide',
							'link'			=>	esc_url($this->generate_product_switch_link($subscription_id, ['product_id'=> $products['tirzepatide']['id'], 'variation_id'=>$products['tirzepatide']['3-m']])),
						],
						[
							'product_name'	=>	'Update to Monthly Semaglutide',
							'link'			=>	esc_url($this->generate_product_switch_link($subscription_id, ['product_id'=> $products['semaglutide']['id'], 'variation_id'=>$products['semaglutide']['1-m']])),
						],
						[
							'product_name'	=>	'Cancel my subscription before it renews again.',
							'link'			=>	esc_url($this->generate_product_switch_link($subscription_id, ['cancelled'=>'yes'])),
						],
					];
					
					$links	=	'<ul style="list-style: none;line-height: 28px;padding-left: 20px;">';
					foreach ($product_suggested as $key => $value) {
						$links	.=	'<li><a style="color:#000" title="' . $value['product_name'] . '" href="' . $value['link'] . '" target="_blank">' . $value['product_name'] . '</a></li>';
					}
					$links	.=	'</ul>';
					
					$body	=	'<p>Dear ' . $recipient['customer'] . ',</p>';
					$body	.=	str_replace( '{{product_links}}', $links, $message );

					$email_sent	=	wp_mail($recipient['email'], $subject, $body, $headers);
					$email_sent	=	$email_sent? 'Sent!':'Not Sent';
				} catch (\Throwable $th) {
					$mgs	=	$th->getMessage();
					$email_sent	=	'Not Sent.' . print_r($mgs, true);
				}
				$sent_to[]	=	'Subscription #<a href="' . admin_url('admin.php?page=wc-orders--shop_subscription&action=edit&id=' . $subscription_id) . '" target="_blank">' . $subscription_id . '</a> ' . $recipient['email'] . ' ' . $email_sent ;
			}
			sleep(2);
		}
	}


		// line 650
		if (is_a($theorder, 'WC_Order')  && function_exists('send_order_to_telegra')) {
			$actions['resend_to_telegra'] = __('Resend to Telegra', 'bh-features');
		}

	add_action('woocommerce_order_action_resend_to_telegra', [$plugin_admin, 'hb_woocommerce_order_action_resend_to_telegra']);
	function hb_woocommerce_order_action_resend_to_telegra($order) {
		if (is_a($order, 'WC_Order') && function_exists('send_order_to_telegra')) {
			$order_id = $order->get_id();
			send_order_to_telegra($order_id);
		}
	}



		/**
		 * Send To Telegra when a Payment for Renewal Order is completed
		 */
		$this->loader->add_action('woocommerce_payment_complete', $plugin_public, 'analyze_renewal_order_payment_complete', 10, 2);

		/**
	 	 * Analizing the current sent to Telegra
	 	 */
		// $this->loader->add_action('woocommerce_order_status_changed', $plugin_public, 'analize_order_renewal_payment_completed_send_to_telegram', 9, 4);



	/**
	 * Send To Telegra when a Payment for Renewal Order is completed
	 */
	function analyze_renewal_order_payment_complete($order_id, $transaction_id) {
		try {
			// Validación básica del order_id
			if (empty($order_id) || !is_numeric($order_id)) {
				bh_plugins_log([
					'error' => 'Invalid order ID',
					'order_id' => $order_id,
					'transaction_id' => $transaction_id,
					'timestamp' => current_time('mysql')
				], 'bh_plugins_renewal_analysis');
				return;
			}
			
			// Iniciar log con todos los datos relevantes
			$log_data = array(
				'order_id' => $order_id,
				'transaction_id' => $transaction_id,
				'timestamp' => current_time('mysql'),
				'checks' => array(),
				'debug_info' => array()
			);
			
			// 1. Verificar si es renovación
			$is_renewal = wcs_order_contains_renewal($order_id);
			$log_data['checks']['is_renewal'] = $is_renewal? 'yes':'no';
			
			if (!$is_renewal) {
				$log_data['decision'] = 'SKIP - Not a renewal order';
				// Info adicional para debugging
				$log_data['debug_info']['order_type'] = 'regular';
				bh_plugins_log($log_data, 'bh_plugins_renewal_analysis');
				return;
			}
			
			// 2. Verificar upsell
			$upsell_order_id = get_post_meta($order_id, '_cuw_offer_order_id', true);
			$is_upsell = (!empty($upsell_order_id) && $upsell_order_id != $order_id);
			$log_data['checks']['upsell_order_id'] = $upsell_order_id;
			$log_data['checks']['is_upsell'] = $is_upsell;
			
			if ($is_upsell) {
				$log_data['decision'] = 'SKIP - Upsell order';
				$log_data['debug_info']['order_type'] = 'upsell';
				bh_plugins_log($log_data, 'bh_plugins_renewal_analysis');
				return;
			}
			
			// 3. Verificar si ya fue procesada por socioApi
			$telemdnow_entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
			$already_processed = !empty($telemdnow_entity_id);
			$log_data['checks']['telemdnow_entity_id'] = $telemdnow_entity_id;
			$log_data['checks']['already_processed'] = $already_processed;
			
			if ($already_processed) {
				$log_data['decision'] = 'SKIP - Already processed by socioApi';
				$log_data['debug_info']['order_type'] = 'already_processed';
				bh_plugins_log($log_data, 'bh_plugins_renewal_analysis');
				return;
			}
			
			// 4. Verificar estado actual (con manejo de error por si la orden no existe)
			$order = wc_get_order($order_id);
			if (!$order) {
				$log_data['decision'] = 'ERROR - Order not found';
				$log_data['debug_info']['order_exists'] = false;
				bh_plugins_log($log_data, 'bh_plugins_renewal_analysis');
				return;
			}
			
			$current_status = $order->get_status();
			$log_data['checks']['current_status'] = $current_status;
			$log_data['debug_info']['order_exists'] = true;
			$log_data['debug_info']['payment_method'] = $order->get_payment_method();
			
			// 5. Decisión final (solo logging, sin acción)
			if ($current_status === 'processing') {
				$log_data['decision'] = 'WOULD CHANGE - Would change from processing to on-hold';
				$log_data['action'] = 'update_status("on-hold")';
				$log_data['debug_info']['action_required'] = true;
			} else {
				$log_data['decision'] = 'NO ACTION - Current status not processing';
				$log_data['debug_info']['action_required'] = false;
				$log_data['debug_info']['current_status_reason'] = 'Status is ' . $current_status . ', expected processing';
			}
			
			bh_plugins_log($log_data, 'bh_plugins_renewal_analysis');
			
		} catch (Exception $e) {
			// Log completo del error
			bh_plugins_log([
				'error' => 'Exception caught',
				'order_id' => $order_id,
				'transaction_id' => $transaction_id,
				'exception_message' => $e->getMessage(),
				'exception_code' => $e->getCode(),
				'exception_file' => $e->getFile(),
				'exception_line' => $e->getLine(),
				'timestamp' => current_time('mysql'),
				'severity' => 'CRITICAL'
			], 'bh_plugins_renewal_analysis');
			
			// Opcional: también loguear el trace completo si es necesario
			bh_plugins_log([
				'error_trace' => $e->getTraceAsString(),
				'order_id' => $order_id,
				'timestamp' => current_time('mysql')
			], 'bh_plugins_renewal_analysis_errors');
		} catch (Throwable $t) {
			// Para PHP 7+ que captura tanto Exception como Error
			bh_plugins_log([
				'error' => 'Throwable caught',
				'order_id' => $order_id,
				'transaction_id' => $transaction_id,
				'throwable_message' => $t->getMessage(),
				'throwable_code' => $t->getCode(),
				'throwable_file' => $t->getFile(),
				'throwable_line' => $t->getLine(),
				'timestamp' => current_time('mysql'),
				'severity' => 'CRITICAL'
			], 'bh_plugins_renewal_analysis');
		}
	}

	/**
	 * Analizing the current sent to Telegra
	 * */
	function analize_order_renewal_payment_completed_send_to_telegram($order_id, $old_status, $new_status, $order) {
		try {
			// Datos iniciales para el log
			$log_data = [
				'hook' => 'analizing_send_to_telegra(' . $order_id . ', ' . $old_status . ' -> ' . $new_status . ')',
				'steps' => []
			];

			$file_log	=	'bh_plugins_renewal_analysis_status_changed';
			
			if (!$order) {
				$log_data['steps']['order_valid'] = 'No';
				bh_plugins_log($log_data, $file_log);
				return;
			}
			
			$current_status = $order->get_status();
			// $log_data['order_status'] = $current_status;
			$log_data['steps']['order_valid'] = 'Yes';

			// Paso 2: Verificación de estado processing
			if ($current_status !== 'processing') {
				$log_data['steps']['is_status_processing'] = 'No';
				bh_plugins_log($log_data, $file_log);
				return;
			}
			unset($log_data['steps']['order_valid']);
			
			$log_data['steps']['order_valid_status_processing'] = 'Yes';

			// Paso 3: Verificación de renovación
			$is_renewal = wcs_order_contains_renewal($order);
			// $log_data['is_renewal_order'] = $is_renewal? 'yes':'no';
			
			if (!$is_renewal) {
				$log_data['steps']['is_renewal'] = 'No';
				bh_plugins_log($log_data, $file_log);
				return;
			}
			
			$log_data['steps']['is_renewal'] = 'Yes';

			// Paso 4: Obtener y verificar suscripciones
			$subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
			// $log_data['subscriptions_count'] = count($subscriptions);
			
			$active_subscriptions = [];
			$inactive_subscriptions = [];
			
			foreach ($subscriptions as $subscription) {
				$sub_data = [
					'id' => $subscription->get_id(),
					'status' => $subscription->get_status(),
					'is_active' => ($subscription->get_status() === 'active')
				];
				
				if ($subscription->get_status() === 'active') {
					$active_subscriptions[] = $sub_data;
				} else {
					$inactive_subscriptions[] = $sub_data;
				}
			}
			
			// $log_data['active_subscriptions'] = $active_subscriptions;
			//$log_data['inactive_subscriptions'] = $inactive_subscriptions;

			if (empty($active_subscriptions)) {
				$log_data['steps']['active_subscription'] = 'No';
				bh_plugins_log($log_data, $file_log);
				return;
			}
			
			$log_data['steps']['active_subscription'] = 'Yes';

			// Paso 5: Verificar meta datos de socioApi
			$telemdnow_entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
			// $telemdnow_order_id = get_post_meta($order_id, 'telemdnow_order_id', true);
			
			// $log_data['socioapi_meta'] = [
			// 	'telemdnow_entity_id' => $telemdnow_entity_id,
			// 	'telemdnow_order_id' => $telemdnow_order_id
			// ];

			if (!empty($telemdnow_entity_id)) {
				$log_data['steps']['has_telemdnow_entity_id'] = 'Yes';
				bh_plugins_log($log_data, $file_log);
				return;
			}
			
			$log_data['steps']['has_telemdnow_entity_id'] = 'No';

			// Paso 6: Intentar cambiar estado a on-hold
			$log_data['steps']['result'] = 'Available for Send to Telegra';
			
			// $result = $order->update_status('on-hold');
			
			// $log_data['status_change_result'] = $result;
			
			// if ($result) {
			// 	$log_data['steps']['status_change'] = 'SUCCESS - Changed to on-hold';
			// 	// Agregar nota de orden para tracking
			// 	$order->add_order_note('Order status changed to on-hold for socioApi processing by renewal hook');
			// } else {
			// 	$log_data['steps']['status_change'] = 'FAILED - Could not change to on-hold';
			// }

			// Paso 7: Log final
			//$log_data['steps']['completion'] = 'Process completed';
			bh_plugins_log($log_data, $file_log);

		} catch (\Throwable $th) {
			// Log de excepción
			$error_data = [
				'order_id' => $order_id,
				'error' => $th->getMessage(),
				'file' => $th->getFile(),
				'line' => $th->getLine(),
				'timestamp' => current_time('mysql')
			];
			
			bh_plugins_log($error_data, $file_log .'_error');
			return;
		}
	}
