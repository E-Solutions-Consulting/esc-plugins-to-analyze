<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Tools
 * @subpackage Bh_Tools/admin/partials
 */
wp_enqueue_script('jquery-ui-tabs');
wp_enqueue_style( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css' );
wp_enqueue_script( 'hb-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true );
?>
    <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-tab-subscriptions.php'; ?>


<script>
    jQuery(document).ready(function($) {
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtnAdvancedFilter = document.getElementById('toggleAdvancedFilter');
            const advancedFilterSection = document.getElementById('advancedFilterSection');
            let isAdvancedVisible = false;
            toggleBtnAdvancedFilter.addEventListener('click', function() {
                isAdvancedVisible = !isAdvancedVisible;
                
                if (isAdvancedVisible) {
                advancedFilterSection.style.display = 'flex';
                toggleBtnAdvancedFilter.innerHTML = '⚙️ Advanced Options ▲';
                } else {
                advancedFilterSection.style.display = 'none';
                toggleBtnAdvancedFilter.innerHTML = '⚙️ Advanced Options ▼';
                }
            });
        });
    });
</script>
