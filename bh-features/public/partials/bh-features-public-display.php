<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://solutionswebonline.com
 * @since      1.0.0
 *
 * @package    Bh_Features
 * @subpackage Bh_Features/public/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div id="pause-subscription-section">
    <label>
        <input 
            type="checkbox" 
            id="pause-subscription-checkbox" 
            name="pause_subscription" 
            data-subscription-id="<?php echo esc_attr($subscription_id) ?>" 
            <?php echo ($is_paused ? 'checked' : '') ?>
            >
        <span>Pause subscription</span>
    </label>

<?php
if($is_paused){
    echo '</div>';
    return ;
}
?>

    <div id="pause-info" style="display:none;">
        <span class="dashicons dashicons-info"></span>
        The new date will only be saved after you click the "Update" button.
    </div>
    <div id="pause-box" style="display:none;">
        <label>How long?</label>
        <select id="pause-months-select" data-subscription-id="' . esc_attr($subscription_id) . '">
        <?php
            for ($i = 1; $i <= 12; $i++) {
                echo '<option value="' . $i . '" ' . selected($by_default_months_paused, $i, false) .  '>' . $i . ' Month' . ($i>1? 's':'') . '</option>';
            }
        ?>
        </select>
        <div id="original_dates"></div>
        <div class="buttons">
            <button id="confirm-pause" class="button button-primary">Confirm</button>
            <button id="cancel-pause" class="button">Cancel</button>
        </div>
    </div>
</div>