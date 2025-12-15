<?php if($states) : ?>
    <label for="states"><?php esc_html_e('US States', 'your-textdomain'); ?>
        <select id="states" name="states[]" multiple="multiple" class="wc-enhanced-select" style="width: 100%">
            <option value="">All</option>
            <?php foreach ($states as $code => $name) : ?>
                <option data-state="<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <div class="input-filters">
        <div>
            <label>Start date:</label>
             <?php if($input_datetime) : ?>
            <input type="datetime-local" name="start_date" min="2024-10-01T00:00" max="2030-12-31T23:59" required>
            <?php else : ?>
            <input type="date" name="start_date">
            <?php endif; ?>
        </div>
        <div>
            <label>End date:</label>
            <?php if($input_datetime) : ?>
            <input type="datetime-local" name="end_date" min="2024-01-01T00:00" max="2030-12-31T23:59" required>
            <?php else : ?>
            <input type="date" name="end_date">
            <?php endif; ?>
        </div>
        <?php do_action('input_filters');  ?>
    </div>