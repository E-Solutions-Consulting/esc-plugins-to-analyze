<?php
class TelemdNow_Webhook_API_Logs {

    private $table_name;


    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'telemdnow_webhook_logs';
        add_action('admin_menu', array($this, 'register_plugin_menus'), 100);
        add_action('admin_head', array($this, 'my_column_width'));
    }

    public  function register_plugin_menus() {

         add_submenu_page('telemdnow', 'Webhook Logs', 'Webhook Logs', 'manage_options', 'telemdnow-webhook-logs',  array( $this,'index') );
         add_submenu_page('hidden', 'View Webhook Logs', 'View Webhook Logs', 'manage_options', 'telemdnow-webhook-logs-edit', array( $this,'view'));

        
    }
    public function index() {

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'templates/telemdnow-webhook-logs-listing.php';


        $table = new Telemdnow_Webhook_Logs_Table();
        $table->prepare_items();
      ?>
        <div class="wrap">
          <h2>Webhook Logs</h2>
          <?php $table->display(); ?>
        </div>
      <?php
    }

    function view() {
        if (!isset($_GET['webhook_log']) || !is_numeric($_GET['webhook_log'])) {
            wp_die('Invalid Log ID.');
        }
        
        $item_id = intval($_GET['webhook_log']);
        
        global $wpdb;
        
        
        $item = $wpdb->get_row(
            "SELECT * from {$this->table_name} where id={$item_id}"
        );
        
        
        if (!$item) {
            wp_die('Item not found.');
        }
        
        ?>
        <div class="wrap">
            <h2>Webhook Log</h2>
           
                
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="request_status">Request Status</label></th>
                        <td><input type="text" name="request_status" id="request_status" value="<?php echo esc_attr($item->request_status); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="request_url">Request URL</label></th>
                        <td><input type="text" name="request_url" id="request_url" value="<?php echo esc_attr($item->request_url); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="request_type">Request Type</label></th>
                        <td><input type="text" name="request_type" id="request_type" value="<?php echo esc_attr($item->request_type); ?>" class="regular-text" /></td>
                    </tr>
                   
                    <tr>
                        <th scope="row"><label for="data_received">Data Received</label></th>
                        <td><textarea name="data_received" cols="50" rows="10" id="data_received"  class="regular-text" ><?php echo esc_attr($item->data_received); ?> </textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="data_sent">Request Response</label></th>
                        <td><textarea name="data_sent" cols="50" rows="4" id="data_sent"  class="regular-text" ><?php echo esc_attr($item->request_response); ?> </textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="created_at">Created At</label></th>
                        <td><input type="text" name="created_at" id="created_at" value="<?php echo esc_attr($item->created_at); ?>" class="regular-text" /></td>
                    </tr>
                </table>
           
        </div>
        <?php
        
      }
      function my_column_width() {
        $page = (isset($_GET['page'])) ? esc_attr($_GET['page']) : false;
        if ('telemdnow-webhook-logs' != $page) {
            return;
        }
        echo '<style type="text/css">';
        echo '.wp-list-table .column-id { width:5%; }';
        echo '.wp-list-table .column-request_url { width:35%; }';
        echo '</style>';
    }

}
new TelemdNow_Webhook_API_Logs();