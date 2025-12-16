<?php

class Telemdnow_Webhook_Logs_Table extends WP_List_Table {
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
            'request_status'        =>  'Request Status',
            'woo_order_id'          =>  'Order ID',
            'woo_order_new_status'  =>  'Order New Status',            
            // 'request_url' => 'URL',
            // 'request_type'        => 'Type',
            'telegra_order_id'      =>  'Telegra Order ID',
            'request_response'      =>  'Request Response',
            'data_received'         =>  'Data Received',
            'created_at'            =>  'Created at'
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('request_response', 'data_received');
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

        $table = $wpdb->prefix . 'telemdnow_webhook_logs';

        return $wpdb->get_results(
            "SELECT * from {$table}",
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
            case 'woo_order_new_status':
                $newStatus  =   '';
                if(isset($item['data_received'])){
                    $data_received  = json_decode($item['data_received'], true);
                    if(isset($data_received['eventData'])){
                        $eventData   =   $data_received['eventData'];
                       if(isset($eventData['newStatus']))
                            $newStatus = $eventData['newStatus'];
                    }
                }
                echo $newStatus;
                break;
                
                break;
            case 'woo_order_id':
                $woo_order_id   =   $item['request_response'];
                if(isset($item['data_received'])){
                    $data_received  = json_decode($item['data_received'], true);
                    if(isset($data_received['targetEntity'])){
                        $targetEntity   =   $data_received['targetEntity'];
                       if(isset($targetEntity['externalIdentifier'])){
                            $url    =   add_query_arg([
                                                    'page' => 'wc-orders',
                                                    'action' => 'edit',
                                                    'id' => $targetEntity['externalIdentifier']
                                                ], admin_url('admin.php'));
                        
                            $woo_order_id =     sprintf(
                                                '<a target="_blank" href="%s">%s</a>',
                                                $url,
                                                $targetEntity['externalIdentifier']
                                            );
                       }
                            
                    }
                }
                echo $woo_order_id;
                break;
            case 'telegra_order_id':
                $telegra_order_id   =   '';
                if(isset($item['data_received'])){
                    $data_received  = json_decode($item['data_received'], true);
                    if(isset($data_received['targetEntity'])){
                        $targetEntity   =   $data_received['targetEntity'];
                        if(isset($targetEntity['_id']))
                            $telegra_order_id = '<a target="_blank" href="https://affiliate-admin.telegramd.com/orders/' . esc_html($targetEntity['_id']) . '">' . $targetEntity['_id'] . '</a>';
                    }
                }
                echo $telegra_order_id;
                break;
            case 'id':
            case 'request_status':
            case 'request_url':
            case 'request_type':
            case 'request_response':
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
                'edit'      => sprintf('<a href="?page=telemdnow-webhook-logs-edit&action=%s&webhook_log=%s">' . __('View') . '</a>',  'edit', $item['id']),
               
        );

        return sprintf('%1$s %2$s', $item['id'], $this->row_actions($actions));
    }
}
