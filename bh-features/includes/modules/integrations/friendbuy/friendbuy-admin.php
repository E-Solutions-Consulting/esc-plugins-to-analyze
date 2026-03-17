<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FriendBuy_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_referrals_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
    }

    public function add_referrals_admin_page() {
        // add_menu_page(
        //     'Referral Management',
        //     'Referral Rewards',
        //     'manage_options',
        //     'referral-rewards',
        //     [$this, 'render_referrals_admin_page'],
        //     'dashicons-money-alt',
        //     30
        // );

        add_submenu_page(
            PARENT_MENU_SLUG,
            'Brello Bestie',
            'Brello Bestie',
            'manage_options',
            PARENT_MENU_SLUG . '--brellobestie',
            [$this, 'render_referrals_admin_page'],
            'dashicons-money-alt',
        );
    }

    public function render_referrals_admin_page() {
        ?>
        <div class="wrap">
            <h1>Brello Bestie - Friendbuy Integration</h1>
        <?php

        $this->ah_friendbuy_beta_page();

        global $wpdb;
        $table_name = $wpdb->prefix . 'referral_rewards';
        
        // Handle bulk delete
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && 
            isset($_POST['bulk_delete_nonce']) && 
            wp_verify_nonce($_POST['bulk_delete_nonce'], 'bulk_delete_rewards') &&
            !empty($_POST['bulk_delete']) && 
            is_array($_POST['bulk_delete'])) {
            
            $ids = array_map('intval', $_POST['bulk_delete']);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE id IN($placeholders)",
                $ids
            ));
            
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(_n('%d reward deleted.', '%d rewards deleted.', count($ids), 'friendbuy'), 
                 count($ids)) . '</p></div>';
        }
        
        // Handle actions
        if (isset($_GET['action']) && isset($_GET['reward_id']) && current_user_can('manage_options')) {
            $reward_id = intval($_GET['reward_id']);
            
            // Handle mark as used
            if ($_GET['action'] === 'mark_used') {
                $wpdb->update(
                    $table_name,
                    [
                        'used' => 1, 
                        'status' => 'used', 
                        'used_amount' => $wpdb->get_var("SELECT amount FROM $table_name WHERE id = $reward_id")
                    ],
                    ['id' => $reward_id]
                );
                echo '<div class="notice notice-success"><p>Reward marked as used.</p></div>';
            }
            
            // Handle delete
            if ($_GET['action'] === 'delete' && isset($_GET['_wpnonce']) && 
                wp_verify_nonce($_GET['_wpnonce'], 'delete_reward_' . $reward_id)) {
                $wpdb->delete(
                    $table_name,
                    ['id' => $reward_id],
                    ['%d']
                );
                echo '<div class="notice notice-success"><p>Reward deleted successfully.</p></div>';
            }
        }

        // Handle edit form submission
        if (isset($_POST['update_reward_date']) && current_user_can('manage_options')) {
            $reward_id = intval($_POST['reward_id']);
            $new_date = sanitize_text_field($_POST['new_date']);
            
            if (strtotime($new_date)) {
                $wpdb->update(
                    $table_name,
                    [
                        'created_on' => $new_date, 
                        'expires_at' => date('Y-m-d H:i:s', strtotime($new_date . ' +1 year'))
                    ],
                    ['id' => $reward_id]
                );
                echo '<div class="notice notice-success"><p>Reward date updated successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Invalid date format. Please use YYYY-MM-DD.</p></div>';
            }
        }

        // Get all referrals
        $referrals = $wpdb->get_results("
            SELECT r.*, r.id as reward_id,u.user_email, u.display_name,
                CASE 
                    WHEN r.used = 1 THEN 2
                    WHEN r.status = 'partially_used' THEN 1
                    WHEN r.expires_at < NOW() THEN 1
                    ELSE 0
                END as status_order
            FROM $table_name r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            ORDER BY status_order, r.created_on DESC
        ");
        ?>
        
            <h2>Referral Rewards Management</h2>
            <form method="post" action="">
                <?php wp_nonce_field('bulk_delete_rewards', 'bulk_delete_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1">Bulk Actions</option>
                            <option value="bulk_delete">Delete</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Apply">
                    </div>
                    <br class="clear">
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Friend Email</th>
                            <th>Created On</th>
                            <th>Expires</th>
                            <?php /* <th>Actions</th> */ ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $referral): 
                            $is_expired = $referral->expires_at && strtotime($referral->expires_at) < current_time('timestamp');
                            $status_class = $referral->used ? 'used' : ($is_expired ? 'expired' : 'active');
                            $delete_nonce = wp_create_nonce('delete_reward_' . $referral->id);
                        ?>
                        <?php
                            // First calculate the status
                            $status = 'Active';
                            $icon = 'yes';
                            $class = 'active';

                            if ($referral->used) {
                                $status = 'Used';
                                $icon = 'yes';
                                $class = 'used';
                            } elseif ($referral->status === 'partially_used') {
                                $status = 'Partially Used';
                                $icon = 'minus';
                                $class = 'partially-used';
                            } elseif ($referral->expires_at && strtotime($referral->expires_at) < time()) {
                                $status = 'Expired';
                                $icon = 'warning';
                                $class = 'expired';
                            }
                        ?>
                        <tr class="referral-<?php echo $class; ?> status-<?php echo strtolower($status); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="bulk_delete[]" value="<?php echo $referral->id; ?>">
                            </th>
                            <td><?php echo $referral->reward_id ?></td>
                            <td><?php echo esc_html($referral->display_name ?: 'User #' . $referral->user_id); ?></td>
                            <td><?php echo esc_html($referral->user_email); ?></td>
                            <td>
                                
                            <?php 
                            if ($referral->status === 'partially_used') {
                                $remaining = $referral->amount - $referral->used_amount;
                                echo '$' . number_format($remaining, 2) . ' of $' . number_format($referral->amount, 2);
                            } else {
                                echo '$' . number_format($referral->amount, 2);
                            }
                            ?>
                            </td>
                            
                            <td class="status-<?php echo strtolower($status); ?>">
                                <span class="dashicons dashicons-<?php echo $icon; ?>"></span>
                                <?php 
                                echo $status; 
                                if ($referral->status === 'partially_used') {
                                    $remaining = $referral->amount - $referral->used_amount;
                                    echo ' ($' . number_format($remaining, 2) . ' remaining)';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($referral->friend_email); ?></td>
                            <td class="editable-date" 
                                data-reward-id="<?php echo $referral->id; ?>"
                                data-original-date="<?php echo esc_attr($referral->created_on); ?>">
                                <?php echo date('M j, Y', strtotime($referral->created_on)); ?>
                            </td>
                            <td>
                                <?php 
                                if ($referral->expires_at) {
                                    echo date('M j, Y', strtotime($referral->expires_at));
                                    if ($is_expired) {
                                        echo ' <span class="expired-badge">(Expired)</span>';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <?php /*
                            <td>
                                <div class="row-actions">
                                    <?php if (!$referral->used): ?>
                                        <span class="edit">
                                            <a href="#" class="edit-date" 
                                               data-id="<?php echo $referral->id; ?>"
                                               data-date="<?php echo esc_attr($referral->created_on); ?>">
                                                Edit Date
                                            </a> |
                                        </span>
                                        <?php if (!$referral->used && $referral->status !== 'partially_used'): ?>
                                            <span class="mark-used">
                                                <a href="?page=referral-rewards&action=mark_used&reward_id=<?php echo $referral->id; ?>" 
                                                onclick="return confirm('Mark this reward as used?')">
                                                    Mark Used
                                                </a> |
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <span class="delete">
                                        <a href="?page=referral-rewards&action=delete&reward_id=<?php echo $referral->id; ?>&_wpnonce=<?php echo $delete_nonce; ?>" 
                                           class="delete-reward" 
                                           onclick="return confirm('Are you sure you want to delete this reward? This action cannot be undone.')">
                                            Delete
                                        </a>
                                    </span>
                                </div>
                            </td>
                            */ ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Edit Modal -->
            <div id="editRewardModal" style="display:none; padding: 20px; background: #fff; max-width: 500px; margin: 0 auto;">
                <h2>Edit Reward Date</h2>
                <form method="post" action="">
                    <input type="hidden" name="reward_id" id="edit_reward_id" value="">
                    <p>
                        <label for="new_date">New Created Date:</label>
                        <input type="date" name="new_date" id="new_date" class="regular-text" required>
                    </p>
                    <p>
                        <input type="submit" name="update_reward_date" class="button button-primary" value="Update Date">
                        <button type="button" id="cancelEdit" class="button">Cancel</button>
                    </p>
                </form>
            </div>
            
            <style>
                .dashicons-yes { color: #46b450; }
                .dashicons-no { color: #dc3232; }
                .dashicons-warning { color: #ffb900; }
                .dashicons-yes:before, 
                .dashicons-no:before, 
                .dashicons-warning:before { 
                    font-size: 20px; 
                    vertical-align: middle;
                }
                .expired-badge {
                    color: #dc3232;
                    font-size: 0.9em;
                    font-weight: normal;
                }
                .referral-expired td {
                    opacity: 0.7;
                }
                .row-actions {
                    font-size: 12px;
                    color: #646970;
                    margin-top: 5px;
                }
                .row-actions .delete a {
                    color: #b32d2e;
                }
                .row-actions .delete a:hover {
                    color: #f86368;
                }
                .row-actions a {
                    text-decoration: none;
                }
                .row-actions .edit a {
                    color: #2271b1;
                }
                .row-actions .mark-used a {
                    color: #00a32a;
                }
                tr:hover .row-actions {
                    visibility: visible;
                }
                .row-actions {
                    visibility: hidden;
                }
                .referral-used { color: #a7aaad; }
                .referral-expired { color: #d63638; }
                .referral-active { color: #00a32a; }
                .dashicons { vertical-align: middle; }
                
                .referral-used td {
                    color: #a7aaad !important;
                }
                .status-used {
                    color: #a7aaad !important;
                }
                .referral-expired td {
                    color: #d63638 !important;
                    opacity: 1 !important;
                }
                .referral-active td {
                    color: #1d2327 !important;
                }
                .dashicons {
                    vertical-align: middle;
                    margin-right: 5px;
                }
                /* Ensure the entire row is grey for used referrals */
                tr.referral-used {
                    background-color: #f6f7f7 !important;
                }
                /* Fix the action links visibility */
                tr:hover .row-actions,
                tr.referral-used:hover .row-actions {
                    visibility: visible;
                }
                /* Style the action links for used referrals */
                tr.referral-used .row-actions a {
                    color: #a7aaad !important;
                }
                tr.referral-used .row-actions .delete a {
                    color: #d63638 !important;
                }
                .referral-partially-used {
                    background-color: #fff8e5 !important;
                }
                .status-partially_used {
                    color: #dba617 !important;
                }
                .dashicons-minus {
                    color: #dba617 !important;
                }
                .status-partially_used {
                    color: #dba617 !important;
                    font-weight: 600;
                }
                .referral-partially-used {
                    background-color: #fff8e5 !important;
                }
                .dashicons-minus {
                    color: #dba617 !important;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#editRewardModal').dialog({
                    autoOpen: false,
                    modal: true,
                    width: 'auto',
                    dialogClass: 'wp-dialog',
                    close: function() {
                        $(this).dialog('close');
                    }
                });

                // Open modal
                $('.edit-date').on('click', function(e) {
                    e.preventDefault();
                    var rewardId = $(this).data('id');
                    var createdOn = $(this).data('date');
                    $('#edit_reward_id').val(rewardId);
                    $('#new_date').val(createdOn.split(' ')[0]);
                    modal.dialog('open');
                });

                // Close modal
                $('#cancelEdit').on('click', function() {
                    modal.dialog('close');
                });

                // Close on ESC
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && modal.dialog('isOpen')) {
                        modal.dialog('close');
                    }
                });

                // Delete confirmation
                $('.delete-reward').on('click', function(e) {
                    if (!confirm('Are you sure you want to delete this reward? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    function ah_friendbuy_beta_page() {

        if ( isset( $_POST['ah_friendbuy_beta_emails'] ) ) {

            check_admin_referer( 'ah_friendbuy_beta_save' );

            $emails = array_filter(
                array_map(
                    'sanitize_email',
                    explode( "\n", $_POST['ah_friendbuy_beta_emails'] )
                )
            );

            update_option( 'ah_friendbuy_beta_emails', $emails );

            echo '<div class="updated"><p>Saved.</p></div>';
        }

        $saved_emails = get_option( 'ah_friendbuy_beta_emails', [] );
        ?>
        
            <h2>Beta Users</h2>

            <form method="post">
                <?php wp_nonce_field( 'ah_friendbuy_beta_save' ); ?>

                <textarea
                    name="ah_friendbuy_beta_emails"
                    rows="10"
                    style="width:30%;"
                ><?php echo esc_textarea( implode( "\n", $saved_emails ) ); ?></textarea>

                <p>Enter one email per line.</p>

                <p>
                    <button class="button button-primary">Save</button>
                </p>
            </form>

            <hr>

        <?php
    }

}

// Initialize the admin class
new FriendBuy_Admin();