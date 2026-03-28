<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bh_FriendBuy {

    public function __construct() {
        $this->init();
        add_action('plugins_loaded', [$this, 'update_database']);
    }
    private function init() {
        new FriendBuy_Webhook_Handler();
        new FriendBuy_MyAccount_Handler();

    }

    public function update_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'referral_rewards';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // Check columns and add if they don't exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $column_names = array_column($columns, 'Field');
        
        $alter_queries = [];
        
        if (!in_array('used_amount', $column_names)) {
            $alter_queries[] = "ADD COLUMN `used_amount` DECIMAL(10,2) DEFAULT 0 AFTER `amount`";
        }
        
        if (!in_array('status', $column_names)) {
            $alter_queries[] = "ADD COLUMN `status` VARCHAR(20) DEFAULT 'active' AFTER `amount`";
        }
        
        if (!in_array('expires_at', $column_names)) {
            $alter_queries[] = "ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `created_on`";
        }
        
        if (!in_array('order_id', $column_names)) {
            $alter_queries[] = "ADD COLUMN `order_id` BIGINT(20) DEFAULT NULL AFTER `friend_id`";
        }
        
        // Execute all alter queries
        if (!empty($alter_queries)) {
            $sql = "ALTER TABLE $table_name " . implode(', ', $alter_queries);
            $wpdb->query($sql);
            
            // Update existing records
            $wpdb->query("UPDATE $table_name 
                        SET status = IF(used = 1, 'used', 'active'),
                            expires_at = DATE_ADD(created_on, INTERVAL 1 YEAR)
                        WHERE status IS NULL OR expires_at IS NULL");
        }
    }
}

// Inicializar el plugin
new Bh_FriendBuy();
