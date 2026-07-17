<?php
namespace YayMail;

use YayMail\Models\AddonModel;
use YayMail\Utils\SingletonTrait;
/**
 * YayMail SupportedPlugins
 *
 * @method static SupportedPlugins get_instance()
 */
class SupportedPlugins {

    use SingletonTrait;

    private $wc_emails               = [];
    private $addon_supported_plugins = [];
    private $pro_supported_plugins   = [];

    private function __construct() {
        $this->addon_supported_plugins = AddonModel::get_3rd_party_addons();
        $this->init_pro_plugins_list();
    }


    public function get_template_ids_from_core() {
        return AddonModel::get_template_ids(
            [
                'WC_Email_Cancelled_Order',
                'WC_Email_Customer_Cancelled_Order',
                'WC_Email_Customer_Completed_Order',
                'WC_Email_Customer_Invoice',
                'WC_Email_Customer_New_Account',
                'WC_Email_Customer_Note',
                'WC_Email_Customer_On_Hold_Order',
                'WC_Email_Customer_Processing_Order',
                'WC_Email_Customer_Refunded_Order',
                'WC_Email_Customer_Reset_Password',
                'WC_Email_Failed_Order',
                'WC_Email_Customer_Failed_Order',
                'WC_Email_New_Order',
            ]
        );
    }


    /**
     * Determines the source of support for a given template ID.
     *
     * This method checks if the template ID is supported by the core, an addon, or neither.
     *
     * @param string $template_id The template ID to check.
     * @return string Returns 'already_supported', 'addon_needed' if supported by an addon, 'pro_needed' if supported by pro, or 'not_supported'.
     */
    private function get_support_status( string $template_id ): string {
        // If the YayMail template data exists, it means the template is supported and ready to be edited
        if ( ! empty( $this->get_yaymail_template_data( $template_id ) ) ) {
            return 'already_supported';
        }

        /**
         * Check addons
         */
        $template_ids_from_addons = [];
        foreach ( $this->get_addon_supported_plugins() as $third_party ) {
            if ( ! empty( $third_party['template_ids'] ) && ! empty( $third_party['is_3rd_party_installed'] ) ) {
                $template_ids_from_addons = array_merge( $template_ids_from_addons, $third_party['template_ids'] );
            }
        }
        if ( in_array( $template_id, $template_ids_from_addons, true ) ) {
            return 'addon_needed';
        }

        /**
         * Check Pro
         */
        $template_ids_from_pro = [];
        foreach ( $this->get_pro_supported_plugins() as $third_party ) {
            if ( ! empty( $third_party['template_ids'] ) ) {
                $template_ids_from_pro = array_merge( $template_ids_from_pro, $third_party['template_ids'] );
            }
        }
        if ( in_array( $template_id, $template_ids_from_pro, true ) ) {
            return 'pro_needed';
        }

        return 'not_supported';
    }

    /**
     * Get the plugin name based on a specific template ID.
     *
     * @param string $template_id The template ID to search for.
     * @return array|null The addon info if the template ID is found, or null if not found.
     */
    private function get_addon_info( string $template_id ): ?array {

        foreach ( $this->addon_supported_plugins as $addon ) {
            // Check if 'template_ids' exists and contains the specified template ID
            if ( isset( $addon['template_ids'] ) && in_array( $template_id, $addon['template_ids'], true ) ) {
                return $addon;
            }
        }

        return null;
    }

    /**
     * Retrieves support information for a given template.
     *
     * @param string $template_id Template id.
     *
     * @return array An associative array containing:
     *               - 'support_status' (string): 'already_supported', 'addon_needed' if supported by an addon, 'pro_needed' if supported by pro, or 'not_supported'.
     *               - 'addon_info' (array|null): array (object) that has 3 fields: {plugin_name: string, template_ids: array of strings, link_upgrade: string}
     */
    public function get_support_info( string $template_id ): array {
        $support_status = $this->get_support_status( $template_id );
        $addon_info     = $this->get_addon_info( $template_id );

        return [
            'status' => $support_status,
            'addon'  => $addon_info,
        ];
    }

    public function get_yaymail_template_data( $template_id ) {
        $yaymail_emails = \yaymail_get_emails();
        return current( array_filter( $yaymail_emails, fn( $email ) => $email->get_id() === $template_id ) );
    }

    private function init_pro_plugins_list() {

        if ( class_exists( 'FooEvents' ) ) {
            $this->pro_supported_plugins['foo_events'] = [
                'plugin_name' => 'FooEvents for WooCommerce',
            ];
        }

        if ( class_exists( 'Bright_Plugins_COSW' ) ) {
            $template_ids   = [];
            $arg            = [
                'numberposts' => -1,
                'post_type'   => 'order_status',
            ];
            $order_statuses = get_posts( $arg );

            if ( $order_statuses ) {
                foreach ( $order_statuses as $order_status ) {
                    $slug = get_post_meta( $order_status->ID, 'status_slug', true );
                    if ( ! empty( $slug ) ) {
                        $slug = 'bvos_custom_' . $slug;
                    }
                    if ( isset( $this->wc_emails[ $slug ] ) ) {
                        $template_ids[] = $slug;
                    }
                }
            }

            $this->pro_supported_plugins['cosm_by_bright_plugins'] = [
                'plugin_name'  => 'Custom Order Status Manager for WooCommerce by Bright Plugins',
                'template_ids' => $template_ids,
            ];
        }//end if

        if ( class_exists( 'WC_Order_Status_Manager_Loader' ) ) {
            $emails = wc_order_status_manager()->get_emails_instance()->get_emails();

            $template_ids = [];
            foreach ( $emails as $email ) {

                $email_id = 'wc_order_status_email_' . esc_attr( $email->ID );

                if ( isset( $wc_emails[ $email_id ] ) ) {
                    $template_ids[] = $wc_emails[ $email_id ];
                }
            }
            $this->pro_supported_plugins['wodm_by_skyverge'] = [
                'plugin_name'  => 'WooCommerce Order Status Manager by SkyVerge',
                'template_ids' => $template_ids,
            ];
        }

        if ( class_exists( 'Zorem_Woocommerce_Advanced_Shipment_Tracking' )
        || ( class_exists( 'Ast_Pro' ) ) ) {
            $this->pro_supported_plugins['ast_by_zorem'] = [
                'plugin_name'  => 'Advanced Shipment Tracking by Zorem',
                'template_ids' => AddonModel::get_template_ids(
                    [ 'WC_Email_Customer_Partial_Shipped_Order' ]
                ),
            ];
        }

        if ( function_exists( 'yith_ywot_premium_init' ) || function_exists( 'yith_ywot_init' ) ) {
            $this->pro_supported_plugins['ywot'] = [
                'plugin_name' => 'YITH WooCommerce Order & Shipment Tracking free/premium',
            ];
        }

        if ( class_exists( 'Woocommerce_Local_Pickup' ) ) {
            $this->pro_supported_plugins['advanced_local_pickup'] = [
                'plugin_name' => 'Advanced Local Pickup for WooCommerce',
            ];
        }

        if ( class_exists( 'CWG_Instock_API' ) ) {
            $this->pro_supported_plugins['back_in_stock_notifier'] = [
                'plugin_name'  => 'Back In Stock Notifier for WooCommerce',
                'template_ids' => [
                    'notifier_instock_mail',
                    'notifier_subscribe_mail',
                ],
            ];
        }
        if ( class_exists( 'ACF' ) ) {
            $this->pro_supported_plugins['acf'] = [
                'plugin_name' => 'Advanced Custom Fields ACF by WP Engine',
            ];
        }

        if ( class_exists( 'WC_Connect_Loader' ) ) {
            $this->pro_supported_plugins['wc_shipping_tax'] = [
                'plugin_name' => 'WC Shipping & Tax',
            ];
        }

        if ( class_exists( 'WC_Software' ) ) {
            $this->pro_supported_plugins['wc_software_addon'] = [
                'plugin_name' => 'Software Addon by WooCommerce',
            ];
        }

        if ( class_exists( 'WC_Shipment_Tracking' ) ) {
            $this->pro_supported_plugins['wc_shipment_tracking'] = [
                'plugin_name' => 'Shipment Tracking by WooCommerce',
            ];
        }

        if ( class_exists( 'PH_Shipment_Tracking_Common' ) ) {
            $this->pro_supported_plugins['pluginhive_shipment_tracking'] = [
                'plugin_name' => 'Shipment Tracking by PluginHive',
            ];
        }

        if ( class_exists( '\OneTeamSoftware\WooCommerce\Shipping\Plugin' ) ) {
            $this->pro_supported_plugins['chitchats_shipping'] = [
                'plugin_name' => 'Chitchats Shipping Pro',
            ];
        }

        if ( class_exists( 'Alg_WC_Custom_Order_Statuses' ) ) {
            $this->pro_supported_plugins['cos_tychesoftwares'] = [
                'plugin_name' => 'Custom Order Status by TycheSoftwares',
            ];
        }

        if ( class_exists( 'WOOCOS_Email_Manager' ) ) {
            $template_ids          = [];
            $custom_order_statuses = json_decode( get_option( 'woocos_custom_order_statuses' ) );
            if ( $custom_order_statuses ) {
                foreach ( $custom_order_statuses as $order_status ) {
                    $slug = $order_status->slug;

                    if ( isset( $this->wc_emails[ $slug ] ) ) {
                        $template_ids[] = $this->wc_emails[ $slug ];
                    }
                }
            }
            $this->pro_supported_plugins['cos_nuggethone'] = [
                'plugin_name'  => 'Custom Order Status by Nuggethone',
                'template_ids' => $template_ids,
            ];
        }

        if ( class_exists( 'WC_Admin_Custom_Order_Fields' ) ) {
            $this->pro_supported_plugins['cof_skyverge'] = [
                'plugin_name' => 'WooCommerce Admin Custom Order Fields by SkyVerge',
            ];
        }

        if ( class_exists( 'WC_Checkout_Field_Editor' ) ) {
            $this->pro_supported_plugins['wc_checkout_field_editor'] = [
                'plugin_name' => 'WooCommerce Checkout Field Editor',
            ];
        }

        if ( function_exists( 'AWCFE' ) ) {
            $this->pro_supported_plugins['checkout-field-editor-and-manager-for-woocommerce'] = [
                'plugin_name' => 'Checkout Field Editor and Manager for WooCommerce',
            ];
        }

        if ( class_exists( 'TrackingMore' ) ) {
            $this->pro_supported_plugins['trackingmore'] = [
                'plugin_name' => 'TrackingMore Order Tracking For WooCommerce',
            ];
        }

        if ( class_exists( 'GTranslate' ) ) {
            $this->pro_supported_plugins['gtranslate'] = [
                'plugin_name' => 'GTranslate',
            ];
        }

        if ( class_exists( '\Loco_api_WordPressTranslations' ) ) {
            $this->pro_supported_plugins['loco'] = [
                'plugin_name' => 'Loco',
            ];
        }

        if ( class_exists( 'Polylang' ) ) {
            $this->pro_supported_plugins['polylang'] = [
                'plugin_name' => 'Polylang',
            ];
        }

        if ( class_exists( 'TRP_Translate_Press' ) ) {
            $this->pro_supported_plugins['translatepress'] = [
                'plugin_name' => 'TranslatePress',
            ];
        }

        if ( function_exists( 'weglot_get_service' ) ) {
            $this->pro_supported_plugins['weglot'] = [
                'plugin_name' => 'Weglot',
            ];
        }

        if ( class_exists( 'SitePress' ) ) {
            $this->pro_supported_plugins['wpml'] = [
                'plugin_name' => 'WPML',
            ];
        }

        // if ( class_exists( 'AST_PRO_Install' ) ) {
        // $this->pro_supported_plugins['ast_pro'] = [
        // 'plugin_name' => 'Advanced Shipment Tracking Pro',
        // ];
        // }

        // if ( class_exists( 'PH_Shipment_Tracking_API_Manager' ) ) {
        // $this->pro_supported_plugins['ph_shipment_tracking'] = [
        // 'plugin_name' => 'PH Shipment Tracking',
        // ];
        // }

        if ( class_exists( 'EventON' ) ) {
            $this->pro_supported_plugins['event_on'] = [
                'plugin_name' => 'EventON',
            ];
        }

        if ( function_exists( 'woocontracts_maile_ekle' ) ) {
            $this->pro_supported_plugins['woo_contracts_maile'] = [
                'plugin_name' => 'WC EmailSozlesmeler',
            ];
        }

        if ( class_exists( 'WooCommerce_Show_Attributes' ) ) {
            $this->pro_supported_plugins['wc_show_attributes'] = [
                'plugin_name' => 'WC Show Attributes',
            ];
        }

        if ( class_exists( 'THWCFD' ) ) {
            $this->pro_supported_plugins['cfd_themehigh'] = [
                'plugin_name' => 'Checkout Field Editor by Themehigh',
            ];
        }
        if ( class_exists( 'Flexible_Checkout_Fields_Plugin' ) ) {
            $this->pro_supported_plugins['flexible_checkout_fields'] = [
                'plugin_name' => 'Flexible Checkout Fields for WooCommerce',
            ];
        }
        if ( class_exists( '\Woocommerce\Pagarme\Core' ) ) {
            $this->pro_supported_plugins['pagarme'] = [
                'plugin_name' => 'Pagarme',
            ];
        }

        if ( class_exists( '\Payplug\PayplugWoocommerce' ) ) {
            $this->pro_supported_plugins['payplug'] = [
                'plugin_name' => 'Payplug',
            ];
        }
    }

    public function get_addon_supported_plugins() {
        return $this->addon_supported_plugins;
    }
    public function get_pro_supported_plugins() {
        return $this->pro_supported_plugins;
    }

    public function get_all_addon_supported_template_ids() {
        $template_ids = [];
        foreach ( $this->addon_supported_plugins as $addon_namespace => $addon ) {
            $template_ids = array_merge( $template_ids, $this->get_addon_supported_template_ids( $addon_namespace ) );
        }
        return $template_ids;
    }

    public function get_addon_supported_template_ids( string $addon_namespace ): array {
        return $this->addon_supported_plugins[ $addon_namespace ]['template_ids'] ?? [];
    }

    public function get_slug_name_supported_plugins(): array {
        return array_map(
            function( $addon ) {
                return [
                    'plugin_name' => $addon['plugin_name'] ?? '',
                    'slug_name'   => $addon['slug_name'] ?? '',
                ];
            },
            $this->addon_supported_plugins
        );
    }
}
