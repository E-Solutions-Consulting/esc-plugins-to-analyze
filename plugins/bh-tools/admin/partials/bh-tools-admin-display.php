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

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="bh-tool-tabs">
    <ul class="bh-tool-tab-nav">
        <!-- <li><a href="#bh-tool-tab-4">Subscription Shipping Delay</a></li> -->
        <li><a href="#bh-tool-tab-1">Subscription Export</a></li>
        <li><a href="#bh-tool-tab-2">Subscription Renewal Dates</a></li>
        <li><a href="#bh-tool-tab-3">Another</a></li>
    </ul>
    
    <!-- <div id="bh-tool-tab-4" class="bh-tool-tab-content">
        <h3>Subscription Shipping Delay</h3>
        <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-tab-subscriptions-delay.php'; ?>
        <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-progress-bar.php'; ?>
    </div> -->
    <div id="bh-tool-tab-1" class="bh-tool-tab-content">
        <h3>Subscription Data Export</h3>
        <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-tab-subscriptions.php'; ?>
    </div>

    <div id="bh-tool-tab-2" class="bh-tool-tab-content">
        <h3>Extend Subscription Renewal Dates</h3>
        <div class="description">
            Bulk update subscription payment schedules
        </div>
        <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-tab-subscriptions-renewal.php'; ?>
    </div>
    
    <div id="bh-tool-tab-3" class="bh-tool-tab-content">
        <h3>Extend Subscription Renewal Dates</h3>
        <div class="description">
            Bulk update subscription payment schedules
        </div>

        <?php require_once plugin_dir_path(__FILE__) . 'bh-tools-admin-display-tab-subscriptions-another.php'; ?>
    </div>
</div>
<style>
    .bh-tool-tabs.ui-tabs.ui-widget-content {
        transition: 0.3s box-shadow ease;
        border-radius: 6px;
        max-width: 100%;
        display: flex;
        flex-direction:column;
        flex-wrap: wrap;
        position: relative;
        list-style: none;
        background-color: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
    }
    .bh-tool-tabs .ui-tabs-nav.ui-widget-header {display:flex;flex-direction: row;gap: 5px;margin:0}
    .bh-tool-tabs .ui-tabs-nav > li.ui-tabs-tab a{
        box-shadow: 0 -1px 0 #eee inset;
        border-radius: 6px 6px 0 0;
        cursor: pointer;
        display: block;
        text-decoration: none;
        color: #333;
        flex-grow: 3;
        text-align: center;
        background-color: #f2f2f2;
        -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
                user-select: none;
        text-align: center;
        transition: 0.3s background-color ease, 0.3s box-shadow ease;
        box-sizing: border-box;
        padding: 1rem 3rem;
        box-shadow: inset 0 -2px #d1d3d2;
        color: #74777b;
        -webkit-transition: color 0.3s, box-shadow 0.3s;
        transition: color 0.3s, box-shadow 0.3s;
    }
    .bh-tool-tabs .ui-tabs-nav > li.ui-tabs-tab.ui-state-active > a,
    .bh-tool-tabs .ui-tabs-nav > li.ui-tabs-tab.ui-state-hover > a {
        box-shadow: 0 -1px 0 #fff inset;
        background-color: #fefefe;
        box-shadow: inset 0 -2px #000;
        color: #000;
    }
    .bh-tool-tabs .ui-tabs-panel.ui-corner-bottom.ui-widget-content {
        padding: 1rem 2rem 2rem;
    }
</style>
<style>
    
</style>
<script>
    jQuery(document).ready(function($) {
        $('.bh-tool-tabs').tabs();


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
<!--
<style>
    .toggle-btn {cursor: pointer;color:#666;text-decoration: none;outline:none !important;box-shadow:none !important}
</style>
-->
