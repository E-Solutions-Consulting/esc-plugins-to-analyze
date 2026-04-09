<?php

class Telemdnow_Logs_Table extends WP_List_Table {
    function extra_tablenav($which) {
        if ($which == 'top') {
            $current_page   =   isset($_REQUEST['page']) ? sanitize_text_field($_REQUEST['page']) : '';
            $order_id       =   isset($_REQUEST['telegra_order_id_filter']) ? sanitize_text_field($_REQUEST['telegra_order_id_filter']) : '';

            $current_page   =   trim($current_page);
            $order_id   =   trim($order_id);
            ?>
            <form method="get">
            <div class="alignleft actions bh" style="margin-bottom:20px">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>" />
                <label for="order_id_filter">Telegra Order ID:</label><br>
                <input type="text" name="telegra_order_id_filter" id="telegra_order_id_filter" value="<?php echo esc_attr($order_id); ?>" style="width:350px;max-width:100%" placeholder="order::8f0c31e3..." />
                <?php submit_button('Filter', 'button', 'filter_action', false); ?>
            </div>
            </form>
            <?php
        }
    }
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort($data, array(&$this, 'sort_data'));

        $perPage = 12;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ));

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns() {
        $columns = array(
            'id'          => 'ID',
            'request_status'       => 'Request Status',
            'request_url' => 'URL',
            'request_type'        => 'Type',            
            'woo_order_id'          =>  'Order ID',
            'telegra_order_id'      =>  'Telegra Order ID',
            'message'        => 'Message',
            'data_sent'    => 'Data Sent',
            'data_received'      => 'Data Received',
            'created_at'      => 'Created at'
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('data_sent', 'data_received');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        return array(
            'id' => array('id', false),
            'request_status' => array('request_status', false),
            'request_type'  => array('request_type', false),
            'created_at' => array('created_at', true),
        );
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data() {
        $data = array();
        global $wpdb;

        $telegra_order_id   = isset($_REQUEST['telegra_order_id_filter']) ? sanitize_text_field($_REQUEST['telegra_order_id_filter']) :'';
        $table      = $wpdb->prefix . 'telemdnow_logs';
        $sql    =   "SELECT * from {$table}";
        $telegra_order_id   =   trim($telegra_order_id);
        if(!empty($telegra_order_id)){
            $sql    .=  ' WHERE request_url like \'%' . $telegra_order_id . '%\'';
        }
        echo '<pre style="display:none">' . $sql . '</pre>';
        return $wpdb->get_results(
            $sql,
            ARRAY_A
        );
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'woo_order_id':
                $echo='';
                $url    =   $item['request_url'];
                preg_match('/order::[a-zA-Z0-9-]+(?=[\/?&\s]|$)/', $url, $matches);
                $order_full_id = $matches[0] ?? null;
                if($order_full_id){
                    global $wpdb;
                    $order_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->prefix}telemdnow_logs WHERE meta_key='telemdnow_entity_id' AND meta_value='%s'",
                        $order_full_id
                    ));
                    if($order_id){
                        $url    =   add_query_arg([
                                                    'page' => 'wc-orders',
                                                    'action' => 'edit',
                                                    'id' => $order_id
                                                ], admin_url('admin.php'));
                        
                        $echo =     sprintf(
                                            '<a target="_blank" href="%s">%s</a>',
                                            $url,
                                            $order_id
                                        );
                    }
                }
                echo $echo;
                break;
            case 'telegra_order_id':
                $echo='';
                $url    =   $item['request_url'];
                preg_match('/order::[a-zA-Z0-9-]+(?=[\/?&\s]|$)/', $url, $matches);
                $order_full_id = $matches[0] ?? null; // "order::13213" o "order::8f0c31e3-7748-4e5c-8e65-1bf9988b6f53"
                if(!empty($order_full_id)){
                            $url    =   'https://affiliate-admin.telegramd.com/orders/' . $order_full_id;                        
                            $echo =     sprintf(
                                                '<a target="_blank" href="%s">%s</a>',
                                                $url,
                                                $order_full_id
                                            );
                }
                echo $echo;
                break;
            case 'message':
                 if(isset($item['data_received'])){
                    $data_received  = json_decode($item['data_received'], true);
                    $limit  =   70;
                    $length =   strlen($data_received['message']);
                    $new_text=  substr($data_received['message'],0, $limit);
                    if($length>$limit)
                        $new_text .= '...';
                    echo $new_text;
                    //echo $data_received['message'];
                    // if(isset($data_received['message'])){
                    //     echo $data_received['message'];
                    // }
                }
                break;
            case 'request_url':
                $url    =   $item[$column_name];
                $query_string = parse_url($url, PHP_URL_QUERY);
                parse_str($query_string, $params);
                $access_token = $params['access_token'] ?? '';

                if (!empty($access_token)) {
                    $first_five = substr($access_token, 0, 5);                    
                    $last_five  = substr($access_token, -5);                    
                    $new_url = str_replace(
                        $access_token, 
                        $first_five . '...' . $last_five, 
                        $url
                    );
                    
                    echo $new_url;
                } else {
                    echo "No se encontró access_token en la URL";
                }
                break;
            case 'id':
            case 'request_status':
            case 'request_type':
            case 'data_sent':
            case 'data_received':
            case 'created_at':
                return $item[$column_name];

            default:
                return print_r($item, true);
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data($a, $b) {
        // Set defaults
        $orderby = 'created_at';
        $order = 'desc';

        // If orderby is set, use this as the sort column
        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if (!empty($_GET['order'])) {
            $order = $_GET['order'];
        }


        $result = strcmp($a[$orderby], $b[$orderby]);

        if ($order === 'asc') {
            return $result;
        }

        return -$result;
    }
    function column_id($item)
    {
        $actions = array(
                'edit'      => sprintf('<a href="?page=telemdnow-logs-edit&action=%s&log=%s">' . __('View') . '</a>',  'edit', $item['id']),
               
        );

        return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions));
    }
}
