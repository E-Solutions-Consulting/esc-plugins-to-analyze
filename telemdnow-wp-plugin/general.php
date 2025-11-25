<?php
$telemdnow_environment = get_option('telemdnow_environment');
?>
<main>
  <div class="container">
    <div class="telemdnow_tab">
      <ul class="tab">
        <li class="tab_one active-a"><a href="#" data-id="general">General</a></li>
        <li class="tab_one"><a href="#" data-id="talemdnowvar">Telegra Product Variation</a></li>
        <li class="tab_one"><a href="#" data-id="telemdnowstatusmapping">Telegra Order Status Mapping</a></li>
        <li class="tab_one"><a href="#" data-id="wooactions">Woocommerce Order Status Actions</a></li>
        <?php if ($telemdnow_environment == 'development') { ?>
          <li class="tab_one">
            <a href="#" data-id="orderStatusChanges">Woocommerce Order Status Change</a>
          </li>
        <?php } ?>
      </ul>


    </div>

    <div class="talemdnow_area">
      <div class="tab_area general">
        <?php
        $tmd_action = get_option('telemdnow_trigger_action');
        $order_statuses = wc_get_order_statuses();
        $custom_statuses = wc_get_order_statuses(array(
          'status' => 'custom',
          'context' => 'edit'
        ));

        // Merge the custom statuses with the registered statuses
        $order_statuses = array_merge($order_statuses, $custom_statuses);
        ?>
        <form class="general_form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
          <input type="hidden" name="action" value="talemdnow_api_auth">

          <div class="form-group">
            <label style="margin-right: 20px;">Telegra Environment:</label>
            <input type="radio" name="environment" value="production" <?php if ($telemdnow_environment == 'production') {
                                                                        echo "checked";
                                                                      } ?>><label>Production</label>
            <input type="radio" name="environment" value="development" <?php if ($telemdnow_environment == 'development') {
                                                                          echo "checked";
                                                                        } ?>> <label>Development</label>
          </div>

          <div class="form-group">
            <label>Affiliate Patient URL:</label>
            <input type="text" name="affiliate_patient_url" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_patient_url'))) {
                                                                                                    echo get_option('telemdnow_patient_url');
                                                                                                  } ?>">
          </div>

          <div class="form-group">
            <label>Affiliate ID:</label>
            <input type="text" name="affiliate_id" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_affiliate_id'))) {
                                                                                            echo get_option('telemdnow_affiliate_id');
                                                                                          } ?>">
          </div>

          <div class="form-group">
            <label>Affiliate Public Key:</label>
            <input type="text" name="affiliate_token" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_affiliate_public_token'))) {
                                                                                              echo get_option('telemdnow_affiliate_public_token');
                                                                                            } ?>">
          </div>

          <div class="form-group">
            <label>Affiliate Secret Token:</label>
            <input type="text" name="affiliate_secret_token" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_affiliate_secret_token'))) {
                                                                                                      echo get_option('telemdnow_affiliate_secret_token');
                                                                                                    } ?>">
          </div>

          <div class="form-group">
            <label>Username :</label>
            <input type="text" name="username" value="<?php if (!empty(get_option('telemdnow_affiliate_username'))) {
                                                        echo get_option('telemdnow_affiliate_username');
                                                      } ?>" class="form-control  spce_form" required>

          </div>
          <div class="form-group">
            <label>Password :</label>
            <input type="password" name="password" value="<?php if (!empty(get_option('telemdnow_affiliate_password'))) {
                                                            echo get_option('telemdnow_affiliate_password');
                                                          } ?>" class="form-control spce_form_1" required>
          </div>
          <div class="form-group">
            <label>SSO Secret Key :</label>
            <input type="text" name="sso_secret_key" value="<?php if (!empty(get_option('telemdnow_sso_secret_key'))) {
                                                              echo get_option('telemdnow_sso_secret_key');
                                                            } ?>" class="form-control spce_form_1" required>
          </div>
          <div class="form-group">
            <label>Slack Notifications Channel URL</label>
            <input type="text" name="affiliate_channel_notifications" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_affiliate_channel_notifications'))) {
                                                                                                              echo get_option('telemdnow_affiliate_channel_notifications');
                                                                                                            } ?>">
          </div>

          <div class="form-group">
            <label>Slack Error Channel URL</label>
            <input type="text" name="affiliate_channel_errors" class="form-control spce_form" value="<?php if (!empty(get_option('telemdnow_affiliate_channel_errors'))) {
                                                                                                        echo get_option('telemdnow_affiliate_channel_errors');
                                                                                                      } ?>">
          </div>

          <div class="form-group">
            <label>Send To Telegra on Status</label>
            <select name="tmd_action" class="form-control spce_form_1">
              <option value=""> Select Action</option>

              <?php foreach ($order_statuses as $key => $status) : ?>
                <option value="<?php echo str_replace('wc-', '', $key); ?>" <?php if ($tmd_action == str_replace('wc-', '', $key)) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $status ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <button type="submit" class="myBtn">Connect</button>
          </div>
          <div class="succ_msg">

          </div>

        </form>

      </div>
      <div class="tab_area talemdnowvar" style="display:none;">

        <form method="post" class="affiliate_leave">
          <input type="hidden" name="action" value="affiliate_product_action">
          <?php
          $data = get_telemdnow_product();
          $data = $data->productVariations;
          $pdata = get_posts(array('post_type' => ['product', 'product_variation'], 'posts_per_page' => -1));
          ?>
          <ul>
            <li>
              <div class="form-group">
                <label>Product variation</label>

              </div>
            </li>
            <li>
              <div class="form-group">
                <?php
                if (!empty($pdata)) {
                  $i = 0;

                  foreach ($pdata as $pvdata) {
                    $select = '';
                    if (!empty(get_post_meta($pvdata->ID, 'telemdnow_product_variation_id', true))) {
                      $select = get_post_meta($pvdata->ID, 'telemdnow_product_variation_id', true);
                    }
                    $dispensingQuantity = get_post_meta($pvdata->ID, 'telemdnow_product_dispensingQuantity', true);
                ?>
                    <input type="hidden" name="tmd[<?php echo $i; ?>][product]" value="<?php echo $pvdata->ID; ?>">
                    <div class="list_vie">
                      <label><?php echo $pvdata->post_title; ?> (<?php echo $pvdata->ID; ?>)</label>

                      <select name="tmd[<?php echo $i; ?>][vid]" class="form-control">
                        <option value="">Select Product variation</option>
                        <option value="no_telemdnow_processing" <?php if ($select == 'no_telemdnow_processing') {
                                                                  echo 'selected';
                                                                } ?>>No Product Variation</option>
                        <?php foreach ($data as  $vdata) {
                          $vselect = '';
                          if ($select == $vdata->id) {
                            $vselect = 'selected';
                          }
                        ?>
                          <option value="<?php echo $vdata->id; ?>" <?php echo $vselect; ?>><?php echo $vdata->description; ?></option>
                        <?php } ?>
                      </select>

                      <label> Dispensing Quantity</label>
                      <input class="form-control" type="number" name="tmd[<?php echo $i; ?>][dispensingQuantity]" value="<?php echo $dispensingQuantity; ?>">

                    </div>

                <?php
                    ++$i;
                  }
                }
                ?>
              </div>

            </li>
          </ul>


          <p class="Affiliate_mess"></p>
          <div class="form-group">
            <button class="btn btn-primary myBtn">Save</button>
          </div>
        </form>


      </div>

      <div class="tab_area telemdnowstatusmapping" style="display:none;">
        <form method="post" class="telemdnowstatusmapping">
          <input type="hidden" name="action" value="telemdnow_order_status_mapping">
          <?php

          $telegra_statuses = TelemdNow::get_telegra_order_status();
          $custom_woo_statuses = TelemdNow::get_custom_woo_order_status();

          $data  = json_decode(get_option('telegra_woo_status'), true);
          ?>
          <ul>
            <li>
              <div class="heading">
                <label>Telegra Status mapping</label>

              </div>
            </li>
            <li>
              <div class="form-group">

                <?php foreach ($telegra_statuses as $i => $status) {
                ?>
                  <div class="list_vie">
                    <label> Telegra <?php echo $status ?></label>

                    <select name="telegra_woo_status[<?php echo $i; ?>]" class="form-control">
                      <option value="">Select Woocommerce Status</option>
                      <?php foreach ($custom_woo_statuses as $key => $woo_status) {
                        $selected = '';
                        if ($data[$i] == $key) {
                          $selected = 'selected';
                        }
                      ?>
                        <option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $woo_status; ?></option>
                      <?php } ?>
                    </select>

                  </div>
                <?php
                }
                ?>


              </div>

            </li>
          </ul>


          <p class="telemdnowstatusmapping_mess"></p>
          <div class="form-group">
            <button class="btn btn-primary myBtn">Save</button>
          </div>
        </form>

      </div>

      <div class="tab_area wooactions" style="display:none;">
        <form method="post" class="wooactions_form">
          <input type="hidden" name="action" value="telemdnow_woo_order_status_change_actions">
          <?php

          $telegra_actions = TelemdNow::get_telegra_order_actions();
          $custom_woo_statuses = TelemdNow::get_custom_woo_order_status();

          $data  = json_decode(get_option('telegra_woo_actions'), true);
          ?>
          <ul>
            <li>
              <div class="heading">
                <label>Woocommerce Order status change actions to telegra </label>

              </div>
            </li>
            <li>
              <div class="form-group">

                <?php foreach ($custom_woo_statuses as $i => $status) {
                ?>
                  <div class="list_vie">
                    <label><?php echo $status ?></label>

                    <select name="telegra_woo_actions[<?php echo $i; ?>]" class="form-control">
                      <option value="">Select Telegra action</option>
                      <?php foreach ($telegra_actions as $key => $woo_status) {
                        $selected = '';
                        if ($data[$i] == $key) {
                          $selected = 'selected';
                        }
                      ?>
                        <option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $woo_status; ?></option>
                      <?php } ?>
                    </select>

                  </div>
                <?php
                }
                ?>


              </div>

            </li>
          </ul>


          <p class="wooactions_mess"></p>
          <div class="form-group">
            <button class="btn btn-primary myBtn">Change order status</button>
          </div>
        </form>

      </div>
    <?php if ($telemdnow_environment == 'development') { ?>
      <div class="tab_area orderStatusChanges" style="display:none;">
        <form method="post" class="orderStatusChanges">
          <input type="hidden" name="action" value="change_order_status">
          <ul>
            <li>
              <div class="form-group">
                <label>Order id</label>
                <input type="text" name="orderId" class="form-control spce_form">
              </div>

            </li>
          </ul>


          <p class="orderStatusChanges_mess"></p>
          <div class="form-group">
            <button class="btn btn-primary myBtn">Save</button>
          </div>
        </form>

      </div>
    <?php } ?>



    </div>


  </div>




</main>
<script>
  jQuery(".general_form").submit(function(e) {
    e.preventDefault();
    var action = jQuery(this).attr('action');

    var newCustomerForm = jQuery(this).serialize();
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: "<?php echo admin_url('admin-ajax.php'); ?>",
      data: newCustomerForm,
      success: function(data) {
        if (data.code == 200) {
          jQuery('.succ_msg').html('<p style="color:green">' + data.msg + '</p>');

        } else if (data.code == 201) {
          jQuery('.succ_msg').html('<p style="color:red">' + data.msg + '</p>');
        }
      }
    });

  });
  jQuery(".affiliate_leave").submit(function(e) {
    e.preventDefault();
    var action = jQuery(this).attr('action');

    var newCustomerForm = jQuery(this).serialize();
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: "<?php echo admin_url('admin-ajax.php'); ?>",
      data: newCustomerForm,
      success: function(data) {
        if (data.code == 200) {
          jQuery('.Affiliate_mess').html('<p style="color:green">' + data.msg + '</p>');

        } else if (data.code == 201) {
          jQuery('.Affiliate_mess').html('<p style="color:red">' + data.msg + '</p>');
        }
      }
    });

  });

  jQuery(".telemdnowstatusmapping").submit(function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var action = jQuery(this).attr('action');

    var newCustomerForm = jQuery(this).serialize();
    console.log('click')
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: "<?php echo admin_url('admin-ajax.php'); ?>",
      data: newCustomerForm,
      success: function(data) {
        if (data.code == 200) {
          jQuery('.telemdnowstatusmapping_mess').html('<p style="color:green">' + data.msg + '</p>');

        } else if (data.code == 201) {
          jQuery('.telemdnowstatusmapping_mess').html('<p style="color:red">' + data.msg + '</p>');
        }
      }
    });

  });

  jQuery(".wooactions_form").submit(function(e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var action = jQuery(this).attr('action');

    var newCustomerForm = jQuery(this).serialize();
    console.log('click')
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: "<?php echo admin_url('admin-ajax.php'); ?>",
      data: newCustomerForm,
      success: function(data) {
        if (data.code == 200) {
          jQuery('.wooactions_mess').html('<p style="color:green">' + data.msg + '</p>');

        } else if (data.code == 201) {
          jQuery('.wooactions_mess').html('<p style="color:red">' + data.msg + '</p>');
        }
      }
    });

  });

<?php if ($telemdnow_environment == 'development') { ?>
  jQuery(".orderStatusChanges").submit(function(e) {
    e.preventDefault();
    var action = jQuery(this).attr('action');

    var newCustomerForm = jQuery(this).serialize();
    jQuery.ajax({
      type: "POST",
      dataType: "json",
      url: "<?php echo admin_url('admin-ajax.php'); ?>",
      data: newCustomerForm,
      success: function(data) {
        if (data.code == 200) {
          jQuery('.orderStatusChanges_mess').html('<p style="color:green">' + data.msg + '</p>');

        } else if (data.code == 500) {
          jQuery('.orderStatusChanges_mess').html('<p style="color:red">' + data.msg + '</p>');
        }
      }
    });

  });
<?php } ?>
</script>
