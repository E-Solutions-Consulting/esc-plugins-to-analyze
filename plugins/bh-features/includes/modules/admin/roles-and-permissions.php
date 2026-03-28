<?php
/**
 * AH Orders Range Filter
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AH_Roles_And_Permissions' ) ) {

class AH_Roles_And_Permissions {

    public function __construct() {
		add_action( 'init', [$this, 'add_developer_role'] );
		add_action( 'init', [$this, 'manage_capabilities_developer_role'] );
		add_action( 'admin_menu', [$this, 'developer_remove_menu_admin'], 1100 );
    }

    /*
	*	Add Role Developer
	*/
	function add_developer_role () {

	    if ( get_role( 'dev_admin' ) ) {
	        return;
	    }

	    $admin = get_role( 'administrator' );
	    if ( ! $admin ) {
	        return;
	    }

	    $caps = $admin->capabilities;

	    // Create role with admin caps
	    add_role(
	        'dev_admin',
	        'Developer',
	        $caps
	    );

	    $role = get_role( 'dev_admin' );

	    // WooCommerce
	    $remove_caps = [
	        'manage_woocommerce',
	        'view_woocommerce_reports',
	        'edit_shop_orders',
	        'read_shop_orders',
	        'delete_shop_orders',
	        'edit_shop_order',
	        'read_shop_order',
	        'delete_shop_order',
	        'edit_shop_coupons',
	        'edit_products',
	        'publish_products',
	        'delete_products',

	        // Subscriptions
	        'manage_woocommerce_subscriptions',
	        'edit_shop_subscription',
	        'read_shop_subscription',
	        'delete_shop_subscription',

	        // Analytics
	        'view_woocommerce_analytics',
	        'manage_woocommerce_analytics',
	    ];

	    foreach ( $remove_caps as $cap ) {
	        $role->remove_cap( $cap );
	    }
	}

	function manage_capabilities_developer_role() {

	    $role = get_role( 'dev_admin' );
	    if ( ! $role ) {
	        return;
	    }

	    $role->remove_cap( 'manage_user_roles' );

	    $plugin_caps = [
		    'activate_plugins',
		    'update_plugins',
		    'delete_plugins',
		    'install_plugins',
		    'edit_plugins',

			'ure_create_capabilities',
			'ure_create_roles',
			'ure_delete_capabilities',
			'ure_delete_roles',
			'ure_edit_roles',
			'ure_manage_options',
			'ure_reset_roles',
		];

		foreach ( $plugin_caps as $cap ) {
		    $role->remove_cap( $cap );
		}
	}

	function developer_remove_menu_admin(){
		if ( ! is_admin() || ! current_user_can( 'dev_admin' ) ) {
			return ;
		}
		global $menu, $submenu;

		$to_remove_menus	=	[
			'telemdnow',
		];
		$summary=[];
		foreach ($menu as $key => $menuitem) {
			$_menu=$menuitem[2];
			if(in_array($_menu, $to_remove_menus)){
				remove_menu_page($_menu);
				$_menu	.= ' removed';
			}
			$summary[]	=	$_menu;
		}
		
		$to_remove_submenus = [];
		$summary	=	[];
		foreach ( $submenu as $parent_slug => $submenus ) {
		    if ( ! isset( $to_remove_submenus[ $parent_slug ] ) ) {
		        continue;
		    }

		    foreach ( $submenus as $submenu_item ) {
		        $submenu_slug = $submenu_item[2];
		        if ( in_array( $submenu_slug, $to_remove_submenus[ $parent_slug ] ) ) {
		            remove_submenu_page( $parent_slug, $submenu_slug );
		            $submenu_slug .= ' removed';
		        }
		        $summary[$parent_slug]	=	$submenu_slug;
		    }
		}
	}
}

new AH_Roles_And_Permissions();

}

