<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create custom roles for Brello access control
 */
add_action('init', function() {
    if (!get_role('user_tools')) {
        add_role('user_tools', 'User Tools', [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ]);
    }
    
    if (!get_role('admin_tools')) {
        add_role('admin_tools', 'Admin Tools', [
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => false,
            'manage_options' => true,
        ]);
    }
});

/**
 * Check if user can access Brello menu
 */
function bh_can_access_brello_menu($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if ($user_id == 5) {
        return true;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    // Check primary roles first (fast)
    if (in_array('admin_tools', $user->roles)) {
        return true;
    }
    
    if (in_array('user_tools', $user->roles)) {
        return true; // Allow access, will be filtered by submenus
    }
    
    // Check additional roles if URE is available
    if (function_exists('ure_get_user_additional_roles')) {
        $additional_roles = ure_get_user_additional_roles($user_id);
        if (is_array($additional_roles)) {
            if (in_array('admin_tools', $additional_roles) || in_array('user_tools', $additional_roles)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if user can access specific Brello page
 */
function bh_can_access_brello_page($page_slug, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if ($user_id == 5) {
        return true;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    // Check primary roles first
    if (in_array('admin_tools', $user->roles)) {
        return true;
    }
    
    if (in_array('user_tools', $user->roles)) {
        $user_permissions = get_user_meta($user_id, '_bh_user_tools_permissions', true);
        return is_array($user_permissions) && in_array($page_slug, $user_permissions);
    }
    
    // Check additional roles if URE is available
    if (function_exists('ure_get_user_additional_roles')) {
        $additional_roles = ure_get_user_additional_roles($user_id);
        if (is_array($additional_roles)) {
            if (in_array('admin_tools', $additional_roles)) {
                return true;
            }
            if (in_array('user_tools', $additional_roles)) {
                $user_permissions = get_user_meta($user_id, '_bh_user_tools_permissions', true);
                return is_array($user_permissions) && in_array($page_slug, $user_permissions);
            }
        }
    }
    
    return false;
}

/**
 * Check if user can manage Brello roles (only user ID 5)
 */
function bh_can_manage_brello_roles($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    return ($user_id == 5);
}

/**
 * Hide custom roles from user edit page for non-master users
 */
add_action('admin_head-user-edit.php', function() {
    if (get_current_user_id() != 5) {
        echo '<style>
        option[value="user_tools"], option[value="admin_tools"] {
            display: none !important;
        }
        </style>';
    }
});

/**
 * Hide custom roles from user list page for non-master users
 */
add_action('admin_head-users.php', function() {
    if (get_current_user_id() != 5) {
        echo '<style>
        option[value="user_tools"], option[value="admin_tools"] {
            display: none !important;
        }
        .role-user_tools, .role-admin_tools {
            display: none !important;
        }
        </style>';
    }
});

/**
 * Prevent role assignment if not master user
 */
add_action('set_user_role', function($user_id, $role, $old_roles) {
    if (get_current_user_id() != 5 && in_array($role, ['user_tools', 'admin_tools'])) {
        wp_die('Access denied: Only master user can assign these roles.');
    }
}, 10, 3);

/**
 * Block bulk role changes for custom roles
 */
add_action('admin_init', function() {
    if (get_current_user_id() != 5 && isset($_GET['new_role']) && in_array($_GET['new_role'], ['user_tools', 'admin_tools'])) {
        wp_die('Access denied: Only master user can assign these roles.');
    }
    
    if (get_current_user_id() != 5 && isset($_POST['new_role']) && in_array($_POST['new_role'], ['user_tools', 'admin_tools'])) {
        wp_die('Access denied: Only master user can assign these roles.');
    }
});

/**
 * Filter User Role Editor additional capabilities
 */
add_filter('ure_built_in_wp_roles', function($roles) {
    if (get_current_user_id() != 5) {
        unset($roles['user_tools']);
        unset($roles['admin_tools']);
    }
    return $roles;
});

/**
 * Filter User Role Editor roles list
 */
add_filter('ure_roles_list', function($roles) {
    if (get_current_user_id() != 5) {
        unset($roles['user_tools']);
        unset($roles['admin_tools']);
    }
    return $roles;
});

/**
 * Hide custom roles in User Role Editor additional roles section
 */
add_action('admin_head-users.php', function() {
    if (get_current_user_id() != 5) {
        echo '<script>
        jQuery(document).ready(function($) {
            $("input[value=\"user_tools\"], input[value=\"admin_tools\"]").closest("label").hide();
            $("option[value=\"user_tools\"], option[value=\"admin_tools\"]").remove();
        });
        </script>';
    }
});

/**
 * Block User Role Editor role assignment via AJAX
 */
add_action('wp_ajax_ure_ajax', function() {
    if (get_current_user_id() != 5) {
        $action = isset($_POST['sub_action']) ? $_POST['sub_action'] : '';
        $role = isset($_POST['user_role']) ? $_POST['user_role'] : '';
        
        if (in_array($role, ['user_tools', 'admin_tools'])) {
            wp_die('Access denied: Only master user can assign these roles.');
        }
    }
}, 1);

/**
 * Hide custom roles in User Role Editor user edit page
 */
add_action('admin_head-user-edit.php', function() {
    if (get_current_user_id() != 5) {
        echo '<script>
        jQuery(document).ready(function($) {
            function hideCustomRoles() {
                $("input[value=\"user_tools\"], input[value=\"admin_tools\"]").closest("label").hide();
                $("option[value=\"user_tools\"], option[value=\"admin_tools\"]").remove();
                $("#ure_other_roles input[value=\"user_tools\"], #ure_other_roles input[value=\"admin_tools\"]").closest("label").hide();
            }
            hideCustomRoles();
            setTimeout(hideCustomRoles, 1000);
            $(document).ajaxComplete(function() {
                setTimeout(hideCustomRoles, 100);
            });
        });
        </script>';
    }
});

/**
 * Filter User Role Editor capabilities for additional roles
 */
add_filter('ure_get_user_additional_roles', function($roles, $user_id) {
    if (get_current_user_id() != 5) {
        return array_diff($roles, ['user_tools', 'admin_tools']);
    }
    return $roles;
}, 10, 2);

/**
 * Block User Role Editor role updates
 */
add_action('ure_user_permissions_update', function($user_id, $user_roles) {
    if (get_current_user_id() != 5) {
        $restricted_roles = array_intersect($user_roles, ['user_tools', 'admin_tools']);
        if (!empty($restricted_roles)) {
            wp_die('Access denied: Only master user can assign these roles.');
        }
    }
}, 10, 2);

/**
 * Hide Brello menu if user doesn't have access - TEMPORARILY DISABLED
 */
/*
add_action('admin_menu', function() {
    if (!bh_can_access_brello_menu()) {
        remove_menu_page(PARENT_MENU_SLUG);
    }
}, 999);
*/

/**
 * Filter specific Brello submenus for user_tools role
 */
add_action('admin_head', function() {
    global $submenu;
    
    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();
    $all_roles = $current_user->roles;
    
    // Check additional roles from User Role Editor
    if (function_exists('ure_get_user_additional_roles')) {
        $additional_roles = ure_get_user_additional_roles($user_id);
        if (is_array($additional_roles)) {
            $all_roles = array_merge($all_roles, $additional_roles);
        }
    }
    
    // Only filter for user_tools role (admin_tools sees everything)
    if (in_array('user_tools', $all_roles) && !in_array('admin_tools', $all_roles) && $user_id != 5) {
        $user_permissions = get_user_meta($user_id, '_bh_user_tools_permissions', true);
        
        if (isset($submenu[PARENT_MENU_SLUG]) && is_array($user_permissions)) {
            foreach ($submenu[PARENT_MENU_SLUG] as $key => $menu_item) {
                $page_slug = str_replace(PARENT_MENU_SLUG . '--', '', $menu_item[2]);
                
                // Always allow manage-access for user_tools, filter others
                if ($page_slug !== 'manage-access' && !in_array($page_slug, $user_permissions)) {
                    unset($submenu[PARENT_MENU_SLUG][$key]);
                }
            }
        }
    }
}, 999);

/**
 * Block direct access to Brello pages - TEMPORARILY DISABLED
 */
/*
add_action('admin_init', function() {
    if (!is_admin()) {
        return;
    }
    
    $current_screen = get_current_screen();
    if ($current_screen && strpos($current_screen->id, PARENT_MENU_SLUG) !== false) {
        if (!bh_can_access_brello_menu()) {
            wp_die(
                '<h1>Access Restricted</h1>' .
                '<p>You do not have permission to access Brello administration panel.</p>' .
                '<p>Contact the master administrator for access.</p>',
                'Access Denied',
                ['back_link' => true, 'response' => 403]
            );
        }
    }
    
    if (isset($_GET['page']) && strpos($_GET['page'], PARENT_MENU_SLUG) === 0) {
        $page_slug = str_replace(PARENT_MENU_SLUG . '--', '', $_GET['page']);
        
        if (!bh_can_access_brello_page($page_slug)) {
            $current_user = wp_get_current_user();
            $user_role = !empty($current_user->roles) ? $current_user->roles[0] : 'none';
            
            wp_die(
                '<h1>🔐 Brello Access Restricted</h1>' .
                '<p><strong>You do not have permission to access this Brello function.</strong></p>' .
                '<p>Current role: <code>' . esc_html(ucfirst(str_replace('_', ' ', $user_role))) . '</code></p>' .
                '<p><strong>Access granted to:</strong></p>' .
                '<ul>' .
                '<li>👑 Master user (ID 5)</li>' .
                '<li>🔧 Users with <strong>Admin Tools</strong> role</li>' .
                '<li>🛠️ Users with <strong>User Tools</strong> role and specific permissions</li>' .
                '</ul>' .
                '<p>Contact the master administrator to request access.</p>',
                'Access Denied',
                ['back_link' => true, 'response' => 403]
            );
        }
    }
});
*/

/**
 * Add management page - ONLY for user ID 5
 */
add_action('admin_menu', function() {
    if (bh_can_manage_brello_roles()) {
        /*add_submenu_page(
            'users.php',
            'Brello Access',
            'Brello Access',
            'read',
            'brello-access-management',
            'bh_render_brello_access_page'
        );*/
        
        add_submenu_page(
            PARENT_MENU_SLUG,
            'Manage Access',
            'Manage Access',
            'read',
            'brello-manage-access',
            'bh_render_brello_access_page'
        );
    }
});

/**
 * Render Brello access management page
 */
function bh_render_brello_access_page() {
    global $wpdb;
    
    if (!bh_can_manage_brello_roles()) {
        wp_die(
            '<h1>Access Restricted</h1>' .
            '<p>This function is reserved for the system master user.</p>',
            'Access Denied',
            ['back_link' => true, 'response' => 403]
        );
    }
    
    if (isset($_POST['update_permissions']) && wp_verify_nonce($_POST['_wpnonce'], 'brello_access')) {
        $user_permissions = isset($_POST['user_permissions']) ? $_POST['user_permissions'] : [];
        
        // Get users with user_tools role - efficient approach
        $user_tools_users = get_users(['role' => 'user_tools']);
        
        // Also check for additional roles if URE is available
        if (function_exists('ure_get_user_additional_roles')) {
            $all_users = get_users(['number' => 50, 'exclude' => array_map(function($u) { return $u->ID; }, $user_tools_users)]);
            foreach ($all_users as $user) {
                $additional_roles = ure_get_user_additional_roles($user->ID);
                if (is_array($additional_roles) && in_array('user_tools', $additional_roles)) {
                    $user_tools_users[] = $user;
                }
            }
        }
        
        foreach ($user_tools_users as $user) {
            if ($user->ID != 5) {
                if (isset($user_permissions[$user->ID])) {
                    $permissions = array_map('sanitize_text_field', $user_permissions[$user->ID]);
                    update_user_meta($user->ID, '_bh_user_tools_permissions', $permissions);
                } else {
                    delete_user_meta($user->ID, '_bh_user_tools_permissions');
                }
            }
        }
        
        echo '<div class="notice notice-success"><p>Submenu permissions updated successfully.</p></div>';
    }
    
    // Get users with user_tools role - efficient approach
    $users = get_users(['role' => 'user_tools']);
    
    // Also check for additional roles if URE is available
    if (function_exists('ure_get_user_additional_roles')) {
        $all_users = get_users(['number' => 50, 'exclude' => array_map(function($u) { return $u->ID; }, $users)]);
        foreach ($all_users as $user) {
            $additional_roles = ure_get_user_additional_roles($user->ID);
            if (is_array($additional_roles) && in_array('user_tools', $additional_roles)) {
                $users[] = $user;
            }
        }
    }
    
    echo '<div class="wrap">';
    echo '<h1>🛠️ Brello Submenu Permissions</h1>';
    echo '<p>Manage which submenus can be seen by users with <strong>User Tools</strong> role.</p>';
    
    echo '<div class="notice notice-info">';
    echo '<p><strong>📋 How it works:</strong></p>';
    echo '<ul>';
    echo '<li>🔧 <strong>Admin Tools:</strong> See all submenus automatically</li>';
    echo '<li>👑 <strong>Your user (ID 5):</strong> Complete automatic access</li>';
    echo '<li>🛠️ <strong>User Tools:</strong> Only see submenus you mark here</li>';
    echo '<li>📝 <strong>Assign User Tools role:</strong> Go to Users → Edit user → Change role</li>';
    echo '</ul>';
    echo '</div>';
    
    global $submenu;
    $brello_pages = [];
    if (isset($submenu[PARENT_MENU_SLUG])) {
        foreach ($submenu[PARENT_MENU_SLUG] as $item) {
            $page_slug = str_replace(PARENT_MENU_SLUG . '--', '', $item[2]);
            if ($page_slug !== 'manage-access') {
                $brello_pages[$page_slug] = $item[0];
            }
        }
    }

    if (empty($users)) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>No users with User Tools role</strong></p>';
        echo '<p>To create User Tools users:</p>';
        echo '<ol>';
        echo '<li>Go to <strong>Users → Add New</strong> (or edit existing)</li>';
        echo '<li>Assign <strong>User Tools</strong> role</li>';
        echo '<li>Come back here to assign submenu permissions</li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<form method="post">';
        wp_nonce_field('brello_access');
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 250px;">User Tools User</th>';
        echo '<th>Allowed Submenus</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            $user_permissions = get_user_meta($user->ID, '_bh_user_tools_permissions', true) ?: [];
            
            echo '<tr>';
            echo '<td>';
            echo '<strong>' . esc_html($user->display_name) . '</strong><br>';
            echo '<small>@' . esc_html($user->user_login) . ' (' . esc_html($user->user_email) . ')</small>';
            if ($user->ID == 5) {
                echo '<br><span style="color: #d63638; font-weight: bold;">👑 MASTER USER</span>';
            }
            echo '</td>';
            
            echo '<td>';
            if ($user->ID == 5) {
                echo '<em style="color: #666; font-style: italic;">Complete automatic access</em>';
            } else {
                echo '<div class="permissions-grid">';
                foreach ($brello_pages as $page_key => $page_name) {
                    $checked = in_array($page_key, $user_permissions) ? 'checked' : '';
                    echo '<label style="display: block; margin: 5px 0; font-size: 13px;">';
                    echo '<input type="checkbox" name="user_permissions[' . $user->ID . '][]" value="' . esc_attr($page_key) . '" ' . $checked . '> ';
                    echo '<strong>' . esc_html($page_name) . '</strong>';
                    echo '</label>';
                }
                echo '</div>';
                
                if (empty($brello_pages)) {
                    echo '<em style="color: #666;">No submenus available</em>';
                }
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="update_permissions" class="button button-primary" value="💾 Save Submenu Permissions">';
        echo '</p>';
        echo '</form>';
    }
    
    echo '</div>';
    
    echo '<style>';
    echo '.wp-list-table th, .wp-list-table td { padding: 15px; vertical-align: top; }';
    echo '.permissions-grid label { cursor: pointer; }';
    echo '.permissions-grid input[type="checkbox"] { margin-right: 8px; }';
    echo '</style>';
}