<?php 

class telemdNow_Activator{

    public static function activate() {
    global $wpdb;
    $telemdnow_logs = $wpdb->prefix . 'telemdnow_logs';
    $telemdnow_webhook_logs = $wpdb->prefix . 'telemdnow_webhook_logs';
    $charset_collate = $wpdb->get_charset_collate();
  
    //Check to see if the table exists already, if not, then create it
    if ($wpdb->get_var("show tables like '$telemdnow_logs'") != $telemdnow_logs) {
      $table_log = "CREATE TABLE $telemdnow_logs (
                  id int(11) NOT NULL auto_increment,
                  request_status varchar(60) NOT NULL,
                  request_url text NOT NULL,
                  request_type varchar(60) NOT NULL,
                  data_sent text,
                  data_received text,
                  created_at timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`)
          ) $charset_collate;";
  
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($table_log);
    }
    if ($wpdb->get_var("show tables like '$telemdnow_webhook_logs'") != $telemdnow_webhook_logs) {
      $table_webhook = "CREATE TABLE $telemdnow_webhook_logs (
                  id int(11) NOT NULL auto_increment,
                  request_url text NOT NULL,
                  data_received text,
                  request_type varchar(60) NOT NULL,
                  request_status varchar(60) NOT NULL,
                  request_response text,
                  created_at timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`)
          ) $charset_collate;";
  
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($table_webhook);
    }
}
}

