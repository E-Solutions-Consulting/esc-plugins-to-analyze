<?php
/*
Template Name: Telemdnow Thank You
*/
?>
<div class="container">
    <?php 
      $data=get_telemdnow_entity();
      $order_key=$_GET['order_key'];
      $orderid = wc_get_order_id_by_order_key($order_key);
      $order = wc_get_order($orderid);
      $custom_logo_id = get_theme_mod( 'custom_logo' );
      $custom_logo_url = wp_get_attachment_image_src( $custom_logo_id , 'full' );
    ?>
    <div class="reminder">
      <div class="message">
        <h3 class="message_header">WAIT! YOU ARE NOT DONE!</h3>
      </div>
		<div class="logo-mobile">
			<a href="<?php echo $data["visit_link"]; ?>" target="_blank" rel="noopener noreferrer"><button class="start-visit">START ONLINE VISIT</button></a>	
		</div>
	  <div class="logo-container">
		
        <div class="logo">
            <img src="<?php echo "https://telegramd.com/wp-content/uploads/2023/01/telegra-md-logo-sm.png" ?>" alt="Logo 1">
        </div>
      </div>
      <div class="steps-container">
        <div class="step">
            <div>
				<p>
					<h3>
						Intake Form
					</h3>
					
				</p>
			</div>
			<div>
				<p>
					This must be done prior to a practitioner reviewing your order request.
				</p>
			</div>
				
			<div>
				
			</div>
        </div>
		<div class="step">
            <div>
				<h3>
					Practitioner Review
				</h3>
			</div>
			<div>
				<p>
					Once your intake form is submitted, a practitioner will conduct a thorough discussion with you and develop an appropriate course of action.
				</p>
			</div>
				
			<div>
				
			</div>
          </div>
		  <div class="step">
            <div>
				<h3>
					Recommendations
				</h3>
			</div>
			<div>
				<p>
					Following the consultation, our practitioners will provide you with personalized recommendations tailored to your specific situation.
				</p>
			</div>
				
			<div>
				
			</div>
        </div>
      </div>
	  <a href="<?php echo $data["visit_link"]; ?>" target="_blank" rel="noopener noreferrer"><button class="start-visit">START ONLINE VISIT</button></a>
    </div>
    <?php if ( $order ) : ?>
      <div class='reminder' style="margin-top: 20px; text-align: left">
        <div class='reminder-container'>
          <div class='reminder-column'>
            <h2>Enrollment Details</h2>
            <p><strong>Enrollment Total:</strong> $<?php echo $order->get_total(); ?></p>
            <p><strong>Enrollment Number:</strong> <?php echo $order->get_order_number(); ?></p>
            <p><strong>Enrollmnet Date:</strong> <?php echo $order->get_date_created()->format( 'jS F Y h:i:s A' ); ?></p>
          </div>
          <div class='reminder-column'>
            <h2>Enrollment Items</h2>
            <ul>
              <?php
                  foreach ( $order->get_items() as $item_id => $item ) :
                      $_product = $item->get_product();
                      echo '<li>('. $item->get_quantity() . ') - ' . $_product->get_name() . ' - $' . $item->get_total() . '</li>';
                  endforeach;
              ?>
            </ul>
          </div>  
        </div>
          
        
      </div>

      <div class='reminder' style="margin-top: 20px;">
        <div class='reminder-container'>
          <div class='reminder-column'>
            <h2>Billing Address</h2>
            <p><?php echo $order->get_formatted_billing_address(); ?></p>
          </div>
          <div class='reminder-column'>
            <h2>Shipping Address</h2>
            <p><?php echo $order->get_formatted_shipping_address(); ?></p>
          </div>  
        </div>
    <?php endif; ?>

</div>