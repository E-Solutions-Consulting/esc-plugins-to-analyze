<?php

class Telemdnow_Logs_Table extends WP_List_Table {
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

        $table = $wpdb->prefix . 'telemdnow_logs';

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
            case 'id':
            case 'request_status':
            case 'request_url':
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
