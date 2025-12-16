<?php

/* 

Plugin Name: Telegra
 
Plugin URI: https://telegramd.com/ 
 
Description: Used to send prospect patients to the Telegra System.
 
Version: 2.1.2
 
Author: Nick Hebert
 
Author URI: https://telegramd.com
 
License: GPLv2 or later
 
Text Domain: Nick Hebert

 */
if (!defined('ABSPATH')) {
  exit;
}


register_activation_hook(__FILE__, 'telemdnow_activate');

function telemdnow_activate() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-telemdNow-activator.php';
	telemdNow_Activator::activate();
}

add_action('admin_menu', 'register_telemdNow_main_menu', 99);


function register_telemdNow_main_menu() {
  add_menu_page('Telegra', 'Telegra', 'manage_options', 'telemdnow', 'telemdnow_main_menu', '', 56);
  
}
function telemdnow_main_menu() {
  require_once("general.php");
}

add_action( 'woocommerce_subscription_status_on-hold', 'change_telegra_subscription_status_to_active', 10, 1 );

function change_telegra_subscription_status_to_active( $subscription ) {
  if (defined('REST_REQUEST') && REST_REQUEST) {
    return;
  }
  if ( is_admin() ) {
    $current_screen = get_current_screen();
    if ( $current_screen && $current_screen->post_type === 'shop_subscription' ) {
      return;
    }
  }
    $order = $subscription->get_parent();    
    if ( $order && $order->get_meta( 'telemdnow_entity_id' )) {
      if ( $subscription->get_status() === 'on-hold' ) {
          $subscription->update_status( 'active' );
      }
  }
}

/**************** include js ***************/

add_action('admin_enqueue_scripts', 'telemdnow_script_init');

function telemdnow_script_init() {

  wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . '/js/script.js', false, array(), true, true);

  wp_register_style('telemdnow-style', plugin_dir_url(__FILE__) . '/css/telemdnow-style.css', false, '1.0.0');
  wp_enqueue_style('telemdnow-style');
}

function mytheme_enqueue_styles() {
  wp_enqueue_style('telemdnow-thank-you', plugin_dir_url(__FILE__) . '/css/thank_you_telemdnow.css');
}
add_action('wp_enqueue_scripts', 'mytheme_enqueue_styles');

add_action("wp_ajax_talemdnow_api_auth", "authenticate_telemdnow");

function authenticate_telemdnow() {
  $affiliate_token = $_POST['affiliate_token'];
  $affiliate_id = $_POST['affiliate_id'];
  $affiliate_secret_token = $_POST['affiliate_secret_token'];
  $environment = $_POST['environment'];
  $username = $_POST['username'];
  $Password = $_POST['password'];
  $sso_secret_key = $_POST['sso_secret_key'];
  $PatientUrl = $_POST['affiliate_patient_url'];
  $affiliate_channel_notifications = $_POST['affiliate_channel_notifications'];
  $affiliate_channel_errors = $_POST['affiliate_channel_errors'];
  $tmd_action = $_POST['tmd_action'];

  if ($environment == 'production') {
    update_option('telemdnow_environment', "production");
    update_option('telemdnow_rest_url', "https://telegramd-rest.telegramd.com");
    update_option('telemdnow_patient_url', $PatientUrl);
  }

  if ($environment == 'development') {
    update_option('telemdnow_environment', "development");
    update_option('telemdnow_rest_url', "https://dev-core-ias-rest.telegramd.com");
    update_option('telemdnow_patient_url', $PatientUrl);
  }

  $authenticationToken = base64_encode($username . ':' . $Password);
  $curl = curl_init();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $api_url = $telemdnow_rest_url . '/auth/client';
  curl_setopt_array($curl, array(
    CURLOPT_URL => $telemdnow_rest_url . '/auth/client',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic ' . $authenticationToken
    ),
  ));

  $response = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);

  if ($httpCode != 200 && $httpCode != 201) {

    telemdnow_api_error($httpCode, $api_url, 'POST', 'Authorization: Basic ' . $authenticationToken, $response);
  }

  if ($httpCode == 401) {
    $res = array('code' => 200, 'msg' => 'Unable to authenticate with Telemdnow');
    echo json_encode($res);
    die();
  }

  if (!empty($response)) {
    $res_data = json_decode($response);
    error_log(json_encode($response));
    $token = $res_data->token;
    update_option('telemdnow_affiliate_public_token', $affiliate_token);
    update_option('telemdnow_affiliate_id', $affiliate_id);
    update_option('telemdnow_affiliate_secret_token', $affiliate_secret_token);
    update_option('telemdnow_affiliate_private_token', $token);
    update_option('telemdnow_affiliate_username', $username);
    update_option('telemdnow_affiliate_password', $Password);
    update_option('telemdnow_sso_secret_key', $sso_secret_key);
    update_option('telemdnow_affiliate_channel_notifications', $affiliate_channel_notifications);
    update_option('telemdnow_affiliate_channel_errors', $affiliate_channel_errors);
    update_option('telemdnow_affiliate_password', $Password);
    update_option('telemdnow_trigger_action', $tmd_action);
    $msg = 'data save successfully';
  } else {
    $msg = 'Something went wrong';
  }
  $res = array('code' => 200, 'msg' => $msg);
  echo json_encode($res);
  die();
}



function get_authenticationToken__original() {
  $username = get_option('telemdnow_affiliate_username');
  $Password = get_option('telemdnow_affiliate_password');
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $authenticationToken = base64_encode($username . ':' . $Password);
  $api_url = $telemdnow_rest_url . '/auth/client';
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic ' . $authenticationToken
    ),
  ));
  $response = curl_exec($curl);

  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {

    telemdnow_api_error($httpCode, $api_url, 'POST', 'Authorization: Basic ' . $authenticationToken, $response);
  }


  $token = '';
  if (!empty($response)) {
    $res_data = json_decode($response);
    $token = $res_data->token;
  }
  return $token;
}


function get_authenticationToken() {
  $cached_token = get_transient('telemdnow_auth_token');
  if ($cached_token !== false) {
      return $cached_token;
  }

  $username = get_option('telemdnow_affiliate_username');
  $Password = get_option('telemdnow_affiliate_password');
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $authenticationToken = base64_encode($username . ':' . $Password);
  $api_url = $telemdnow_rest_url . '/auth/client';
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Basic ' . $authenticationToken
    ),
  ));
  $response = curl_exec($curl);

  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {

    telemdnow_api_error($httpCode, $api_url, 'POST', 'Authorization: Basic ' . $authenticationToken, $response);
    return '';
  }

  $token = '';
  if (!empty($response)) {
    $res_data = json_decode($response);
    if (isset($res_data->token)) {
      $token = $res_data->token;        
      set_transient('telemdnow_auth_token', $token, 0);
    }
  }
  return $token;
}


function get_telemdnow_product() {
  $curl = curl_init();
  $affiliate_private_token = get_authenticationToken();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $api_url = $telemdnow_rest_url . '/productVariations?access_token=' . $affiliate_private_token;
  curl_setopt_array($curl, array(
    CURLOPT_URL => $telemdnow_rest_url . '/productVariations?access_token=' . $affiliate_private_token,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(),
  ));

  $response = curl_exec($curl);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {

    telemdnow_api_error($httpCode, $api_url, 'GET', '', $response);
  }
  curl_close($curl);
  return json_decode($response);
}

function action_woocommerce_order_status_changed($order_id, $old_status, $new_status, $order) {
  $precheckout = get_post_meta($order_id, 'telemdnow_order_id', true);
  // print_r($precheckout ); die('dddd');
  if (empty($precheckout)) {
    $order = wc_get_order($order_id);
    $telemdnow_entity_id = get_post_meta($order_id, 'telemdnow_entity_id', true);
    if (!empty($telemdnow_entity_id)) {
      return;
    }
    $action = get_option('telemdnow_trigger_action');
    if ($action == $new_status) {
      send_order_to_telegra($order_id);
    }
  }
}

function sanitize_phone_number($phone_number) {
  return substr(preg_replace('/\D/', '', $phone_number), -10);
}

add_action('woocommerce_order_status_changed', 'action_woocommerce_order_status_changed', 10, 4);

function get_patient_from_telegra($order, $affiliate_private_token) {
  $order_id = $order->get_id();
  $curl = curl_init();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $affiliate_id = get_option('telemdnow_affiliate_id');
  $data = array(
    'email' => $order->get_billing_email(),
    'firstName' => $order->get_billing_first_name(),
    'lastName' => $order->get_billing_last_name(),
    'phone' => sanitize_phone_number($order->get_billing_phone()),
    'affiliate' => $affiliate_id
  );

  $api_url = $telemdnow_rest_url . '/patients/actions/createOrGetPatientByEmailAndAffiliate?access_token=' . $affiliate_private_token;
  $data_sent = json_encode($data);
  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data_sent,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  $res = json_decode($response);

  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {

    telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);

    $post_data = array(
      'ID'          => $order_id,
      'post_status' => 'wc-telemdnow-review', // Replace 'new_status' with the desired post status.
    );
    wp_update_post($post_data);
    $final_result["message"] = "Order #" . $order_id . " was not sent to Telemdnow";
    $final_result["status"] = "error";
    $final_result["error"] = 'An error has occurred when transferring over to Telemdnow: ' . $response;
    $order->add_order_note('An error has occurred when transferring over to Telemdnow: ' . $response);
  }
  curl_close($curl);
  $data['patient_id'] = $res->id;
  $data['patient_user_id'] = is_object($res->user) ? $res->user->id : $res->user;

  return $data;
}

function activate_telegra_order_subscription($order) {
  if (function_exists('wcs_get_subscriptions_for_order')) {
    $subscriptions = wcs_get_subscriptions_for_order($order->get_id());
    if (is_array($subscriptions) && (bool) count($subscriptions)) {
      foreach ($subscriptions as $subscription) {
        $subscription->update_status('active');
      }
    }
  }
}

function send_order_to_telegra($order_id) {
  $affiliate_private_token = get_authenticationToken();
  $order = wc_get_order($order_id);
  $patient = get_patient_from_telegra($order, $affiliate_private_token);
  $patient_id = $patient['patient_id'];
  $patient_user_id = $patient['patient_user_id'];
  $ssoToken = getSSOtoken($patient_id, $order->get_billing_email(), $patient_user_id, $affiliate_private_token);
  $product_variations_array = [];
  $final_result = array('message' => '', 'status' => 'success', 'error' => '', 'description' => '');
  foreach ($order->get_items() as $item_id => $item) {
    $product_id = $item->get_product_id();
    $product_id;
    $variation_id = $item->get_variation_id();
    if ($variation_id) {
        // If variation_id exists, use it
        $product_id = $variation_id;
    } else {
        // If no variation_id, use the product_id
        $product_id = $item->get_product_id();
    }
    $telemdnow_product_variation_id = get_post_meta($product_id, 'telemdnow_product_variation_id', true);
    if ($telemdnow_product_variation_id != 'no_telemdnow_processing') {
      $quantity = $item->get_quantity();
      $telemdnow_product_variation_qty = get_post_meta($product_id, 'telemdnow_product_dispensingQuantity', true);
     
      if(!empty($telemdnow_product_variation_qty)) {
        $quantity = intval($telemdnow_product_variation_qty)* $quantity;
      }
      $new_pv_entry = array("productVariation" => $telemdnow_product_variation_id, "quantity" => $quantity);
      $product_variations_array[] = $new_pv_entry;
    }
  }

  // In case product variations array is empty in such case order should not be created on telegra
  if(empty($product_variations_array))
  {
    return;
  }

  $saddress1 = !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() : $order->get_billing_address_1();
  $saddress2 = !empty($order->get_shipping_address_2()) ?  $order->get_shipping_address_2()  : $order->get_billing_address_2();
  $scity =  !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_billing_city();
  $sstate = !empty($order->get_shipping_state()) ? $order->get_shipping_state() : $order->get_billing_state();
  $spin = !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $order->get_billing_postcode();

  $data = array(
    'externalIdentifier' => $order_id, 'patient' => $patient_id, 'email' => $order->get_billing_email(), 'firstName' => $order->get_billing_first_name(), 'lastName' => $order->get_billing_last_name(), 'phone' => $order->get_billing_phone(),
    'productVariations' => $product_variations_array, 'address' => array('billing' => array(
      'address1' => $order->get_billing_address_1(), 'address2' => $order->get_billing_address_2(),
      'city' => $order->get_billing_city(), 'state' => $order->get_billing_state(), 'zipcode' => $order->get_billing_postcode()
    ), 'shipping' => array(
      'address1' => $saddress1, 'address2' => $saddress2,
      'city' => $scity, 'state' => $sstate, 'zipcode' => $spin
    ))
  );

  $curl = curl_init();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $api_url = $telemdnow_rest_url . '/orders/actions/getOrCreateOrder?access_token=' . $affiliate_private_token;
  $data_sent = json_encode($data);
  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data_sent,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  update_post_meta($order_id, 'telemdnow_order_res', $response);
  $res = json_decode($response);

  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {

    // telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);
    $inserted_id    =   telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);

    $post_data = array(
      'ID'          => $order_id,
      'post_status' => 'wc-telemdnow-review', // Replace 'new_status' with the desired post status.
    );
    wp_update_post($post_data);
    $final_result["message"] = "Order #" . $order_id . " was not sent to Telemdnow";
    $final_result["status"] = "error";
    $final_result["error"] = 'An error has occurred when transferring over to Telemdnow: ' . $response;
    
    $telemdnow_patient_url = get_option('telemdnow_patient_url');
    $telemdnow_affiliate_id = get_option('telemdnow_affiliate_id');
    $product_variations_json = urlencode(json_encode($product_variations_array));
    $onlineVisitUrl = $telemdnow_patient_url . '/startVisit?affiliate=' . $telemdnow_affiliate_id . '&productVariations=' . $product_variations_json;
    
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();

    $email = $current_user->user_email;
    $first_name = $current_user->user_firstname;
    $last_name = $current_user->user_lastname;
    $phone = get_user_meta($current_user->ID, 'billing_phone', true);
    $address_1 = get_user_meta($current_user->ID, 'billing_address_1', true);
    $address_2 = get_user_meta($current_user->ID, 'billing_address_2', true);
    $city = get_user_meta($current_user->ID, 'billing_city', true);
    $state = get_user_meta($current_user->ID, 'billing_state', true);
    $postcode = get_user_meta($current_user->ID, 'billing_postcode', true);

    $query_args = array();

    if ($current_user_id) {
      $query_args['firstName'] = sanitize_text_field($first_name);
      $query_args['lastName'] = sanitize_text_field($last_name);
      $query_args['email'] = sanitize_text_field($email);
      $query_args['phone'] = sanitize_phone_number(sanitize_text_field($phone));
      $query_args['gender'] = '';
      $query_args['dateOfBirth'] = '';
      $query_args['address1'] = sanitize_text_field($address_1);
      $query_args['address2'] = sanitize_text_field($address_2);
      $query_args['city'] = sanitize_text_field($city);
      $query_args['state'] = sanitize_text_field($state);
      $query_args['zipcode'] = sanitize_text_field($postcode);
    }    
    $full_url = add_query_arg($query_args, $onlineVisitUrl);
    // $order->add_order_note('An error has occurred when transferring over to Telemdnow: ' . $response);
    $order->add_order_note(
                    sprintf(
                        'An error has occurred when transferring over to Telemdnow%s. Please check <a href="%s" target="_blank">error logs</a> for more detail.',
                        telemdnow_api_error_message($response),
                        telemdnow_api_error_log_url($inserted_id)
                    )
                );

    $order->update_meta_data('telemdnow_visit_link',$full_url);
    $order->update_meta_data('telemdnow_order_creation','false');
    $order->save();
  }

  if (isset($res->id)) {
    $telemdnow_patient_url = get_option('telemdnow_patient_url');
    $telemdnow_affiliate_id = get_option('telemdnow_affiliate_id');
    update_post_meta($order_id, 'telemdnow_entity_id', $res->id);
    $order->update_meta_data('telemdnow_entity_id', $res->id);
    $readyForSubmission = isset($res->readyForSubmission) ? $res->readyForSubmission : false;
    $order->update_meta_data('telemdnow_entity_ready_for_submission', $readyForSubmission);
    $order->update_meta_data('telemdnow_entity_type', 'Order');
    $accessToken = '';
    if (!empty($ssoToken)) {
      $accessToken = '&access_token=' . $ssoToken;
    }
    $order->update_meta_data('telemdnow_visit_link', $telemdnow_patient_url . "/startVisit?orderId=" . $res->id . "&affiliate=" . $telemdnow_affiliate_id . "&email=" . $order->get_billing_email() . "&phone=" . $order->get_billing_phone() . $accessToken);
    $order->save();
    $final_result["message"] = "Order #" . $order_id . "  was successfully sent over to Telegra";
    $final_result["description"] = "Telegra Entity: Order ID: " . $res->id . ")";
    activate_telegra_order_subscription($order);
  }
  curl_close($curl);

  $order->add_order_note($final_result["message"]);
  if ($final_result["status"] === "error") {
    send_slack_message($final_result["message"], 'error', $final_result["error"]);
  }
  send_slack_message($final_result["message"], 'log', $final_result["error"], $final_result["description"]);
}

function slider_metaboxes() {
  global $post;
  global $wp_meta_boxes;
  add_meta_box('postfunctiondiv', __('Telegra Order Information'), 'slider_metaboxes_html', 'shop_order', 'normal', 'high');
}

function slider_metaboxes_html() {
  global $post;
  $order_id = $post->ID;
  $mdata = get_post_meta($order_id, 'telemdnow_order_res', true);
  $visitLink = get_post_meta($order_id, 'telemdnow_visit_link', true);
  $readyForOrderSubmission = get_post_meta($order_id, 'telemdnow_entity_ready_for_submission', true);
  $readyForOrderSubmission = $readyForOrderSubmission == 1 ? 'true' : 'false';
  if (!empty($mdata)) {
    $jmdata = json_decode($mdata);
    $html = '';
    $html .= '<table class="table_order">
		 <tr><th>ID</th><td>' . $jmdata->id . '</td></tr>
		  <tr><th>Visit Link</th><td><a href="' . $visitLink . '">Click To View Online Visit.</a></td></tr>
		  <tr><th>Project ID</th><td>' . $jmdata->project->_id . '</td></tr>
		  <tr><th>Order Ready For Submission: </th><td>' . $readyForOrderSubmission . '</td></tr>
		</table>';
    echo $html;
    echo '<a href="#" data-id="' . $post->ID . '" class="btn btn-primary patirnd_email_remder">Patient Email Reminder</a>';
    echo '<style>
		
.table_order th:first-child {
    text-align: left;
    min-width: 110px;
}
#order_data .order_data_column .form-field .date-picker {
    width: 45%;
}
a.patirnd_email_remder {
    display: inline-block;
    text-decoration: none;
    font-size: 13px;
    line-height: 2.15384615;
    min-height: 30px;
    margin: 20px 0 0;
    padding: 0 10px;
    cursor: pointer;
    border-width: 1px;
    border-style: solid;
    -webkit-appearance: none;
    border-radius: 3px;
    white-space: nowrap;
    box-sizing: border-box;
}



</style>';
    echo '<script>
		 jQuery(".patirnd_email_remder").click(function(e){
			e.preventDefault();
            var id=jQuery(this).data("id");
				 jQuery.ajax({
						type : "POST",
						dataType : "json",
						url : "' . admin_url('admin-ajax.php') . '",
						data : {id:id,action:"tmd_order_reminder_email"},
						success: function(data) {
							if(data.code==200){
								
								alert(data.msg);
							}else if(data.code==201){
								alert(data.msg);
							}
						}
					});


      			
		 });
		</script>';
  }
}

add_action('add_meta_boxes_shop_order', 'slider_metaboxes');


add_action("wp_ajax_affiliate_product_action", "affiliate_product_action");
function affiliate_product_action() {
  $productMapping= array();
  foreach ($_POST['tmd'] as $tmd) {
    $product = array();
    if (!empty($tmd['product']) && !empty($tmd['vid'])) {
      update_post_meta($tmd['product'], 'telemdnow_product_variation_id', $tmd['vid']);
      $product["productVariation"]=$tmd['vid'];
      $product["ecommerceIdentifier"]=$tmd['product'];
    }
    if ( !empty($tmd['product'])) {
      update_post_meta( $tmd['product'], 'telemdnow_product_dispensingQuantity',$tmd['dispensingQuantity']);
      if(!empty($tmd['dispensingQuantity']) && !empty($product["productVariation"]) && !empty($product["ecommerceIdentifier"]) ){
        $product["dispensingQuantity"]=$tmd['dispensingQuantity'];
      }
    }
    if(!empty($product)){
      $productMapping[] = $product;
    }
  }
  $response = setProductMappings($productMapping);

  if($response){
    $res = array('code' => 200, 'msg' => 'Product update successfully');
    echo json_encode($res);
    die();
  }
  $res = array('code' => 500, 'msg' => 'There is an error while mapping the product');
  echo json_encode($res);
  die();
}

add_action("wp_ajax_telemdnow_order_status_mapping", "telemdnow_order_status_mapping");
function telemdnow_order_status_mapping() {
 update_option('telegra_woo_status', json_encode($_POST['telegra_woo_status']));
  $res = array('code' => 200, 'msg' => 'Saved successfully');
  echo json_encode($res);
  die();
}

add_action("wp_ajax_telemdnow_woo_order_status_change_actions", "telemdnow_woo_order_status_change_actions");
function telemdnow_woo_order_status_change_actions() {
 update_option('telegra_woo_actions', json_encode($_POST['telegra_woo_actions']));
  $res = array('code' => 200, 'msg' => 'Saved successfully');
  echo json_encode($res);
  die();
}

function setProductMappings($productMappings) {
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $affiliate_private_token = get_authenticationToken();

  $url = $telemdnow_rest_url . '/ecommerce/configurations/setProductMappings?access_token=' . $affiliate_private_token;

  $data = json_encode(['productMappings' => $productMappings]);

  $ch = curl_init($url);

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json'
  ]);

  $response = curl_exec($ch);

  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($httpCode == 200 || $httpCode == 201) {
    return true;
  }

  telemdnow_api_error($httpCode, $url, 'POST', '', $response);

  return false;

}

add_action("wp_ajax_change_order_status", "change_order_status");

function change_order_status()
{
  $telemdnow_environment = get_option('telemdnow_environment');
  if ($telemdnow_environment !== 'development') {
    die();
  }
  $orderId = $_POST['orderId'];
  $affiliate_private_token = get_authenticationToken();

  $curl = curl_init();
  $api_url = 'https://dev-core-ias-rest.telegramd.com/orders/' . $orderId . '/actions/lifecycleProcessor/approvePrescription?access_token=' . $affiliate_private_token;

  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  $res = json_decode($response);
  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode == 200 || $httpCode == 201) {
    $res = array('code' => 200, 'msg' => 'Order Status Updated');
    echo json_encode($res);
    die();
  }

  telemdnow_api_error($httpCode, $api_url, 'POST', $orderId, $response);

  $res = array('code' => 500, 'msg' => 'Something went wrong');

  echo json_encode($res);

  die();
}


function get_telemdnow_entity()
{
  $order_key = $_GET['order_key'];
  $telemdnow_connectivity_data = [];
  if (!empty($order_key)) {
    $orderid = wc_get_order_id_by_order_key($order_key);
    $telemdnow_entity_type = get_post_meta($orderid, 'telemdnow_entity_type', true);
    if (!empty($telemdnow_entity_type)) {
      $telemdnow_entity_id = get_post_meta($orderid, 'telemdnow_entity_id', true);
      $telemdnow_connectivity_data["entity_type"] = $telemdnow_entity_type;
      $telemdnow_connectivity_data["entity_id"] = $telemdnow_entity_id;
      $telemdnow_connectivity_data["orderid"] = $orderid;
      $telemdnow_connectivity_data["visit_link"] = get_post_meta($orderid, 'telemdnow_visit_link', true);
    }
  }
  return $telemdnow_connectivity_data;
}

add_shortcode('get_telemdnow_action_button', 'get_telemdnow_action_button');
function get_telemdnow_action_button() {
  $orderid = $_GET['order_id'];
  $url = "#";
  $html = '';
  if (!empty($orderid)) {
    if (!empty(get_post_meta($orderid, 'telemdnow_entity_type', true))) {
      $entityType = get_post_meta($orderid, 'telemdnow_entity_type', true);
      if ($entityType === 'prospective_patient') {
        $html .= '<button>CONTINUE WITH VISIT PROSPECT</button>';
        return $html;
      } else {
        $html .= '<button>CONTINUE WITH VISIT ORDER</button>';
        return $html;
      }
    }
  }
  return $url;
}

if (!function_exists('plugin_log')) {
  function plugin_log($entry, $mode = 'a', $file = 'telemdnow_plugin') {
    // Get WordPress uploads directory.
    $upload_dir = wp_upload_dir();
    $upload_dir = $upload_dir['basedir'];
    // If the entry is array, json_encode.
    if (is_array($entry)) {
      $entry = json_encode($entry);
    }
    // Write the log file.
    $file  = $upload_dir . '/' . $file . '.log';
    //error_log($file);
    $file  = fopen($file, $mode);
    $bytes = fwrite($file, current_time('mysql') . "::" . $entry . "\n");
    fclose($file);
    return $bytes;
  }
}

function send_slack_message($title, $log_level, $description = null, $content = null) {
  $channelOption = $log_level === 'log' ? 'telemdnow_affiliate_channel_notifications' : 'telemdnow_affiliate_channel_errors';
  $slack_url = get_option($channelOption);
  $telemdnow_patient_url = get_option('telemdnow_patient_url');
  $data = array('text' => $title);
  $data["attachments"] = array();
  if ($description) {
    $data["attachments"][] = array(
      "pretext" => $description,
      "color" => '#44694d'
    );
  }
  if ($content) {
    $data["attachments"][] = array(
      "pretext" => $content,
      "color" => '#44694d'
    );
  }
  $data["attachments"][] = array(
    "pretext" => 'Telegra Affiliate: ' . $telemdnow_patient_url,
    "color" => '#44694d'
  );
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => $slack_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  if (!empty($response)) {
    $res = array('code' => 200, 'msg' => 'send mail');
  } else {
    $res = array('code' => 201, 'msg' => 'Something Went Wrong');
  }
  curl_close($curl);
}

function thank_you_telemdnow_shortcode() {
  ob_start();
  include 'thank_you_telemdnow.php';
  return ob_get_clean();
}
add_shortcode('thank_you_telemdnow', 'thank_you_telemdnow_shortcode');

function telemdnow_redirect_after_purchase($order_id) {
  WC()->session->set('telemdnow_order_id', '');
  $order = wc_get_order($order_id);
  $precheckout = get_post_meta($order_id, 'telemdnow_order_id', true);
  $telemdnow_order_creation = $order->get_meta('telemdnow_order_creation', true);
  $telemdnow_entity_id = $order->get_meta('telemdnow_entity_id', true);
  
  if (isset($_GET['orderId']) && $telemdnow_order_creation === 'false' && empty($telemdnow_entity_id)) {
    $orderId = $_GET['orderId'];
    $order->update_meta_data('telemdnow_entity_id', $orderId);
    $order->update_meta_data('telemdnow_order_creation','true');
    $order->save();
  }

  $is_thankyou_redirect_enabled = (!isset($_GET['orderId']));

  if (empty($precheckout)) {
    $base_url = site_url();
    $order_key = $order->get_order_key();
    $telemdnow_entity_ready_for_submission = $order->get_meta('telemdnow_entity_ready_for_submission', true);
    $visit_link = $order->get_meta('telemdnow_visit_link', true);
    
    if(!empty($telemdnow_order_creation) && $telemdnow_order_creation !== 'false') {
      $thank_you_url = wc_get_endpoint_url('order-received', $order_id, wc_get_checkout_url()) . '?key=' . $order_key;
      $visit_link = $order->get_meta('telemdnow_visit_link', true) . '&redirectUrl=' . $thank_you_url;
      if (empty($telemdnow_entity_id)) {
        return;
      }
      if ($telemdnow_entity_ready_for_submission) {
        return;
      }
    }
    /**
     *  Filter for Disable the redirect in ThankYou page 
     *  Use mode:
     *    1)  add_filter('telemd_thankyou_redirect_enabled', '__return_false');
     *    2)  add_filter('telemd_thankyou_redirect_enabled', 'function_name'], 10, 2);
     */
    $is_thankyou_redirect_enabled = apply_filters('telemd_thankyou_redirect_enabled', $is_thankyou_redirect_enabled, $visit_link);
    if($is_thankyou_redirect_enabled){
      wp_redirect($visit_link);
      exit;
    }

  } else {
    $affiliate_private_token = get_authenticationToken();
    $data = ['externalIdentifier' => $order_id];

    $curl = curl_init();
    $telemdnow_rest_url = get_option('telemdnow_rest_url');
    $api_url = $telemdnow_rest_url . '/ecommerce/orders/'.$precheckout.'/actions/synchronizeEcommerceOrder?access_token=' . $affiliate_private_token;
    $data_sent = json_encode($data);
    curl_setopt_array($curl, array(
      CURLOPT_URL => $api_url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $data_sent,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);
    $res = json_decode($response);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpCode != 200 && $httpCode != 201) {
      telemdnow_api_error($httpCode, $api_url, 'POST', $data_sent, $response);
      $order->add_order_note("Order #" . $order_id . " 'externalIdentifier' could not be synced on telegra. Error::" . $response);
    } else {
      $order->add_order_note("Order #" . $order_id . " 'externalIdentifier' were synced on telegra.");
    }
    activate_telegra_order_subscription($order);
  }
  if($is_thankyou_redirect_enabled)
    exit;
}
add_action('woocommerce_thankyou', 'telemdnow_redirect_after_purchase');

function getSSOtoken($patientId, $customerEmail, $userId, $affiliate_private_token) {
  try {
    $ssoKey = get_option('telemdnow_ssoKey_' . $customerEmail);
    if (empty($ssoKey)) {
      $ssoKey = setPatientSSOKey($patientId, $customerEmail, $affiliate_private_token);
    }
    if (!empty($ssoKey)) {

      $ssoToken = setPatientSSOSession($userId, $ssoKey);
      return $ssoToken;
    }
    return null;
  } catch (Exception $e) {
    plugin_log($e->getMessage());
  }
}

function setPatientSSOKey($patientId, $customerEmail, $affiliate_private_token) {
  $ssoKey = generateRandomString(8);
  $curl = curl_init();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $affiliate_id = get_option('telemdnow_affiliate_id');
  $data = array(
    'ssoKey' => $ssoKey,
  );
  $api_url = $telemdnow_rest_url . '/patients/' . $patientId . '/setSSOKey?access_token=' . $affiliate_private_token;
  $data_sent = json_encode($data);
  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => $data_sent,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  $res = json_decode($response);

  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {
    telemdnow_api_error($httpCode, $api_url, 'PUT', $data_sent, $response);
    return null;
  }
  curl_close($curl);
  update_option('telemdnow_ssoKey_' . $customerEmail, $ssoKey);
  return $ssoKey;
}

function setPatientSSOSession($userId, $ssoKey) {

  $sso_secret_token = get_option('telemdnow_sso_secret_key');
  $curl = curl_init();
  $telemdnow_rest_url = get_option('telemdnow_rest_url');
  $api_url =  $telemdnow_rest_url . '/auth/sso?access_token=' . $sso_secret_token . '&userId=' . $userId . '&ssoKey=' . $ssoKey;

  curl_setopt_array($curl, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  $res = json_decode($response);



  $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  // Check if the HTTP response code is the one you are interested in (e.g., HTTP 200 OK)
  if ($httpCode != 200 && $httpCode != 201) {
    telemdnow_api_error($httpCode, $api_url, 'POST', '', $response);
    return null;
  }
  curl_close($curl);

  return $res->token;
}

add_shortcode('getOnlineVisitUrl', 'getOnlineVisitUrl_Fun');
function getOnlineVisitUrl_Fun() {
  global $post;
  global $wp;
  $onlineVisitUrl = '';
  $telemdnow_product_variation_id = get_post_meta($post->ID, 'telemdnow_product_variation_id', true);
  $telemdnow_product_variation_qty = get_post_meta($post->ID, 'telemdnow_product_dispensingQuantity', true);
  $product_qty = 1;
  if(!empty($telemdnow_product_variation_qty)) {
    $product_qty = intval($telemdnow_product_variation_qty)* $product_qty;
  }
  $current_url = home_url(add_query_arg(array(), $wp->request));
  $checkout_url = wc_get_checkout_url();
  $cartpage_url = wc_get_cart_url();
  if (!empty($telemdnow_product_variation_id) && $telemdnow_product_variation_id != 'no_telemdnow_processing') {
    $telemdnow_patient_url = get_option('telemdnow_patient_url');
    $telemdnow_affiliate_id = get_option('telemdnow_affiliate_id');

    $onlineVisitUrl = $telemdnow_patient_url . '/startVisit?affiliate=' . $telemdnow_affiliate_id . '&productVariations=[{%22productVariation%22:%22' . $telemdnow_product_variation_id . '%22,%22quantity%22:%22'.$product_qty.'%22}]';

    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();

    $email = $current_user->user_email;
    $first_name = $current_user->user_firstname;
    $last_name = $current_user->user_lastname;
    $phone = get_user_meta($current_user->ID, 'billing_phone', true);
    $address_1 = get_user_meta($current_user->ID, 'billing_address_1', true);
    $address_2 = get_user_meta($current_user->ID, 'billing_address_2', true);
    $city = get_user_meta($current_user->ID, 'billing_city', true);
    $state = get_user_meta($current_user->ID, 'billing_state', true);
    $postcode = get_user_meta($current_user->ID, 'billing_postcode', true);

    $query_args = array();

    if ($current_user_id) {
      $query_args['firstName'] = sanitize_text_field($first_name);
      $query_args['lastName'] = sanitize_text_field($last_name);
      $query_args['email'] = sanitize_text_field($email);
      $query_args['phone'] = sanitize_text_field($phone);
      $query_args['gender'] = '';
      $query_args['dateOfBirth'] = '';
      $query_args['address1'] = sanitize_text_field($address_1);
      $query_args['address2'] = sanitize_text_field($address_2);
      $query_args['city'] = sanitize_text_field($city);
      $query_args['state'] = sanitize_text_field($state);
      $query_args['zipcode'] = sanitize_text_field($postcode);

    }
    $query_args['redirectUrl'] = $checkout_url . '?variantId=' . $post->ID;
    $query_args['attachDemographicsToRedirectUrl'] = 'true';


    $full_url = add_query_arg($query_args, $onlineVisitUrl);

    return esc_url($full_url);
  }
}
add_action('init', 'telemdnow_add_product_to_cart_automatically');
add_action('template_redirect', 'telemdnow_add_product_to_cart_automatically');

function telemdnow_add_product_to_cart_automatically() {

  $product_id = isset($_GET['variantId']) ? $_GET['variantId'] : '';
  $order_id = isset($_GET['orderId']) ? $_GET['orderId'] : '';

  if (!empty($product_id) && !empty($order_id)) {
    if (WC()->cart->get_cart_contents_count() == 0) {
      WC()->cart->add_to_cart($product_id);
      WC()->session->set('telemdnow_order_id', $_GET['orderId']);
    } else {
      $product_in_cart = false;
      foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $product_id) {
          $product_in_cart = true;
          break;
        }
      }

      if (!$product_in_cart) {
        WC()->cart->add_to_cart($product_id);
        WC()->session->set('telemdnow_order_id', $_GET['orderId']);
      }
    }
  }
}

add_filter('woocommerce_add_cart_item_data', 'telemdnow_save_custom_data_in_cart_object', 9999, 3);

function telemdnow_save_custom_data_in_cart_object($cart_item_data, $product_id, $variation_id) {
  $topopulate = array(
    'b_firstName' => 'billing_first_name',
    'b_lastName' => 'billing_last_name',
    'b_email' => 'billing_email',
    'b_phoneNumber' => 'billing_phone',
    'b_state' => 'billing_state',
    'b_addressLine1' => 'billing_address_1',
    'b_addressLine2' => 'billing_address_2',
    'b_city' => 'billing_city',
    'b_zip' => 'billing_postcode',
    's_firstName' => 'shipping_first_name',
    's_lastName' => 'shipping_last_name',
    's_email' => 'shipping_email',
    's_phoneNumber' => 'shipping_phone',
    's_state' => 'shipping_state',
    's_addressLine1' => 'shipping_address_1',
    's_addressLine2' => 'shipping_address_2',
    's_city' => 'shipping_city',
    's_zip' => 'shipping_postcode'
  );
  foreach ($topopulate as $urlparam => $checkout_field) {

    switch (substr($checkout_field, 0, 7)) {
      case 'billing':
        $param = explode('b_', $urlparam);
        $param = $param[1];
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
          $cart_item_data[$checkout_field] = esc_attr($_GET[$param]);
        }
        break;
      case 'shippin':
        $param = explode('s_', $urlparam);
        $param = $param[1];
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
          $cart_item_data[$checkout_field] = esc_attr($_GET[$param]);
        }
        break;
    }
  }
  $cart_item_data['billing_country'] = 'US';
  $cart_item_data['shipping_country'] = 'US';

  return $cart_item_data;
}

add_filter('woocommerce_checkout_fields', 'telemdnow_populate_checkout', 9999);
function telemdnow_populate_checkout($fields) {
  if (is_admin() || !function_exists('WC') || !WC()->cart)
    return $fields;

  $topopulate = array(
    'b_firstName' => 'billing_first_name',
    'b_lastName' => 'billing_last_name',
    'b_email' => 'billing_email',
    'b_phoneNumber' => 'billing_phone',
    'b_state' => 'billing_state',
    'b_addressLine1' => 'billing_address_1',
    'b_addressLine2' => 'billing_address_2',
    'b_city' => 'billing_city',
    'b_zip' => 'billing_postcode',
    'b_country' => 'billing_country',
    's_firstName' => 'shipping_first_name',
    's_lastName' => 'shipping_last_name',
    's_email' => 'shipping_email',
    's_phoneNumber' => 'shipping_phone',
    's_state' => 'shipping_state',
    's_addressLine1' => 'shipping_address_1',
    's_addressLine2' => 'shipping_address_2',
    's_city' => 'shipping_city',
    's_zip' => 'shipping_postcode',
    's_country' => 'shipping_country'
  );
  foreach (WC()->cart->get_cart() as $cart_item) {
    foreach ($topopulate as $urlparam => $checkout_field) {
      if (isset($cart_item[$checkout_field]) && !empty($cart_item[$checkout_field])) {
        switch (substr($checkout_field, 0, 7)) {
          case 'billing':
            $fields['billing'][$checkout_field]['default'] = $cart_item[$checkout_field];
            break;
          case 'shippin':
            $fields['shipping'][$checkout_field]['default'] = $cart_item[$checkout_field];
            break;
        }
      }
    }
  }

  return $fields;
}

add_action('woocommerce_after_order_notes', 'telemdnow_custom_checkout_field');

function telemdnow_custom_checkout_field($checkout) {
  echo '<style>#my_custom_checkout_field {display:none;}</style>';
  echo '<div id="my_custom_checkout_field">';

  woocommerce_form_field('telemdnow_order_id', array(
    'type'          => 'hidden',
    'class'         => array('my-field-class form-row-wide'),
    'label'         => __('Fill in this field'),
    'placeholder'   => __('Enter something'),
  ), isset($_GET['orderId']) ? $_GET['orderId'] : WC()->session->get('telemdnow_order_id'));

  echo '</div>';
}
add_action('woocommerce_checkout_update_order_meta', 'telemdnow_custom_checkout_field_update_order_meta');

function telemdnow_custom_checkout_field_update_order_meta($order_id) {
  if (!empty($_POST['telemdnow_order_id'])) {
    update_post_meta($order_id, 'telemdnow_order_id', sanitize_text_field($_POST['telemdnow_order_id']));
    $order = wc_get_order($order_id);
    $order->add_order_note('telemdnow Pre checkout Processed with telegra order Id ' . $_POST['telemdnow_order_id']);
  }
}

function telemdnow_show_new_checkout_field_order($order) {
  $order_id = $order->get_id();
  if (get_post_meta($order_id, 'telemdnow_order_id', true)) echo '<p><strong>Telegra Order Id:</strong> ' . get_post_meta($order_id, 'telemdnow_order_id', true) . '</p>';
}

function generateRandomString($length = 8) {
  $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[random_int(0, $charactersLength - 1)];
  }
  return $randomString;
}

function add_this_script_footer() { ?>
  <script>
    var urll = jQuery(".uk-button-large").attr('href');
    var dd = urll.replace('<span class="xlwcty_order_meta_label"></span>', '');

    jQuery(".uk-button-large").attr('href', dd);
  </script>
<?php }

add_action('wp_footer', 'add_this_script_footer');

function telemdnow_api_error($status, $url, $type, $data_sent, $data_received) {
  try {
    global $wpdb;

    $table = $wpdb->prefix . 'telemdnow_logs';
    $inserted_id = -1;
    $insert = $wpdb->insert(
      $table,
      array(
        'request_status' => $status,
        'request_url' =>  $url,
        'request_type' => $type,
        'data_sent' => $data_sent,
        'data_received' => $data_received,
        'created_at' => current_time('mysql', false),

      )
    );
    if ($insert) {
      //successfully inserted.
      $inserted_id = $wpdb->insert_id;
    } else {
      plugin_log('error in telegra logs insert query' . $wpdb->last_error);
    }
  } catch (Exception $e) {
    plugin_log('error in telemdnow_api_error' . $e->getMessage());
  }
  return $inserted_id;
}

function telemdnow_api_error_message( $response ) {
    $message = '';
    $decoded = json_decode( $response );

    if ( json_last_error() === JSON_ERROR_NONE && isset( $decoded->message ) ) {
        $message = $decoded->message;
    } elseif ( ! empty( $response ) ) {
        $message = $response;
    }

    $message = trim( (string) $message );

    if ( $message !== '' ) {
        $message = rtrim( $message, '.' );
        $message = ': ' . $message;
    } else {
        $message = ': No response from server';
    }

    return '<span class="bh error-message">' . esc_html( $message ) . '</span>';
}

function telemdnow_api_error_log_url($inserted_id){
  $log_url        =   add_query_arg(
                                    array(
                                        'page'   => 'telemdnow-logs-edit',
                                        'action' => 'edit',
                                        'log'    => $inserted_id,
                                    ),
                                    admin_url( 'admin.php' )
                                );
  return esc_url( $log_url );
}


/****** REST API  */

require_once plugin_dir_path( __FILE__ ) . 'includes/telemdNow_index.php';

function run_telemdNow() {

	new TelemdNow();

}
run_telemdNow();
?>
