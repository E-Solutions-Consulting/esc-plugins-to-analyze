<?php
namespace YayMail\Models;

use YayMail\SupportedPlugins;

/**
 * Migration Model
 *
 * @method static MigrationModel get_instance()
 */
class AddonModel {

    public static function get_all() {
        $data = [
            'YayMailAddonConditionalLogic'                 => [
                'plugin_name'  => 'YayMail Conditional Logic',
                'link_upgrade' => 'https://yaycommerce.com/yaymail-addons/conditional-logic-addon-for-yaymail/',
                'image'        => 'https://images.wpbrandy.com/uploads/conditional-logic.png',
                'description'  => 'Send WooCommerce custom emails per product. Display custom email content based on conditional logic like <b>all/any & is/is not.</b>',
                'plugin_slug'  => 'yaymail-conditional-logic',
                'categories'   => [ 'marketing', 'others' ],
            ],
            'YayMailAddonWcSubscription'                   => [
                'plugin_name'            => 'WooCommerce Subscriptions',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-subscription.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'ENR_Email_Customer_Auto_Renewal_Reminder',
                        'ENR_Email_Customer_Expiry_Reminder',
                        'ENR_Email_Customer_Manual_Renewal_Reminder',
                        'ENR_Email_Customer_Processing_Shipping_Fulfilment_Order',
                        'ENR_Email_Customer_Shipping_Frequency_Notification',
                        'ENR_Email_Customer_Subscription_Price_Updated',
                        'ENR_Email_Customer_Trial_Ending_Reminder',
                        'WCS_Email_Completed_Renewal_Order',
                        'WCS_Email_Cancelled_Subscription',
                        'WCS_Email_Completed_Switch_Order',
                        'WCS_Email_Customer_Payment_Retry',
                        'WCS_Email_Customer_Renewal_Invoice',
                        'WCS_Email_Expired_Subscription',
                        'WCS_Email_New_Renewal_Order',
                        'WCS_Email_New_Switch_Order',
                        'WCS_Email_Customer_On_Hold_Renewal_Order',
                        'WCS_Email_On_Hold_Subscription',
                        'WCS_Email_Payment_Retry',
                        'WCS_Email_Processing_Renewal_Order',
                        'WCS_Email_Customer_Notification_Auto_Renewal',
                        'WCS_Email_Customer_Notification_Auto_Trial_Expiration',
                        'WCS_Email_Customer_Notification_Manual_Renewal',
                        'WCS_Email_Customer_Notification_Manual_Trial_Expiration',
                        'WCS_Email_Customer_Notification_Subscription_Expiration',
                    ]
                ),
                'slug_name'              => 'woocommerce-subscriptions',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-subscriptions',
                'is_3rd_party_installed' => class_exists( 'WC_Subscriptions' ),
                'plugin_slug'            => 'yaymail-premium-addon-for-woocommerce-subscription',
                'description'            => 'Customize <strong>WooCommerce Subscriptions</strong> emails with <strong>YayMail</strong> email builder.',
                'categories'             => [ 'subscription' ],
            ],
            'YayMailAddonYITHWishlist'                     => [
                // yith_wishlist_constructor
                'plugin_name'            => 'YITH WooCommerce Wishlist',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-wishlist.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'yith_wcwl_back_in_stock',
                        'estimate_mail',
                        'yith_wcwl_on_sale_item',
                        'yith_wcwl_promotion_mail',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-wishlist-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-wishlist',
                'is_3rd_party_installed' => function_exists( 'yith_wishlist_constructor' ),
                'description'            => 'Use YayMail to customize your <strong>YITH WooCommerce Wishlist</strong>&nbsp;email templates.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woocommerce-wishlist-premium',
                'categories'             => [ 'marketing', 'others' ],
            ],
            'YayMailAddonSUMOSubscriptions'                => [
                'plugin_name'            => 'SUMO Subscription',
                'template_ids'           => self::get_template_ids(
                    [
                        'SUMOSubs_Subscription_Auto_Renewal_Reminder_Email',
                        'SUMOSubs_Subscription_Auto_Renewal_Success_Email',
                        'SUMOSubs_Subscription_Cancelled_Email',
                        'SUMOSubs_Subscription_Cancel_Request_Revoked_Email',
                        'SUMOSubs_Subscription_Cancel_Request_Submitted_Email',
                        'SUMOSubs_Subscription_Order_Completed_Email',
                        'SUMOSubs_Subscription_Expired_Email',
                        'SUMOSubs_Subscription_Expiry_Reminder_Email',
                        'SUMOSubs_Subscription_Invoice_Email',
                        'SUMOSubs_Subscription_New_Order_Email',
                        'SUMOSubs_Subscription_New_Order_Old_Subscribers_Email',
                        'SUMOSubs_Subscription_Paused_Email',
                        'SUMOSubs_Subscription_Overdue_Automatic_Email',
                        'SUMOSubs_Subscription_Overdue_Manual_Email',
                        'SUMOSubs_Subscription_Pending_Authorization_Email',
                        'SUMOSubs_Subscription_Order_Processing_Email',
                        'SUMOSubs_Subscription_Suspended_Automatic_Email',
                        'SUMOSubs_Subscription_Suspended_Manual_Email',
                        'SUMOSubs_Subscription_Turnoff_Auto_Payments_Success_Email',
                    ]
                ),
                'slug_name'              => 'sumosubscriptions',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-sumo-subscription',
                'is_3rd_party_installed' => class_exists( 'SUMOSubscriptions' ),
                'description'            => 'Use YayMail to customize the email templates of <strong>SUMO Subscriptions - WooCommerce Subscription System</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-sumo-subscriptions',
                'categories'             => [ 'subscription' ],
            ],
            'YayMailAddonYITHWooSubscription'              => [
                'plugin_name'            => 'YITH WooCommerce Subscription Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-subscription.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WC_Customer_Subscription_Before_Expired',
                        'YITH_WC_Customer_Subscription_Cancelled',
                        'YITH_WC_Customer_Subscription_Expired',
                        'YITH_WC_Customer_Subscription_Paused',
                        'YITH_WC_Customer_Subscription_Payment_Done',
                        'YITH_WC_Customer_Subscription_Payment_Failed',
                        'YITH_WC_Customer_Subscription_Request_Payment',
                        'YITH_WC_Customer_Subscription_Renew_Reminder',
                        'YITH_WC_Customer_Subscription_Resumed',
                        'YITH_WC_Subscription_Status',
                        'YITH_WC_Customer_Subscription_Suspended',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-subscription-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-subscription',
                'is_3rd_party_installed' => function_exists( 'YITH_WC_Subscription' ),
                'description'            => 'HTML email design made simple. Now you can fully customize <strong>YITH WooCommerce Subscription</strong> emails with <strong>YayMail</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-yith-subscription',
                'categories'             => [ 'subscription' ],
            ],
            'YayMailAddonWcB2B'                            => [
                'plugin_name'            => 'WooCommerce B2B',
                'template_ids'           => self::get_template_ids(
                    [
                        'WCB2B_Email_Customer_OnQuote_Order',
                        'WCB2B_Email_Customer_Quoted_Order',
                        'WCB2B_Email_Customer_Status_Notification',
                        'WCB2B_Email_New_Quote',
                    ]
                ),
                'slug_name'              => 'woocommerce-b2b',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-b2b',
                'is_3rd_party_installed' => class_exists( 'WooCommerceB2B' ),
                'description'            => 'Customize your wholesale email templates and grow your business with <strong>WooCommerce B2B</strong>!',
                'plugin_slug'            => 'yaymail-addon-for-wc-b2b',
                'categories'             => [ 'wholesale' ],
            ],
            'YayMailYithVendor'                            => [
                'plugin_name'            => 'YITH WooCommerce Multi Vendor Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-multi-vendor.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WC_Email_Cancelled_Order',
                        'YITH_WC_Email_Commissions_Paid',
                        'YITH_WC_Email_Commissions_Unpaid',
                        'YITH_WC_Email_New_Order',
                        'YITH_WC_Email_New_Staff_Member',
                        'YITH_WC_Email_New_Vendor_Registration',
                        'YITH_WC_Email_Product_Set_In_Pending_Review',
                        'YITH_WC_Email_Vendor_Commissions_Bulk_Action',
                        'YITH_WC_Email_Vendor_Commissions_Paid',
                        'YITH_WC_Email_Vendor_New_Account',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-multi-vendor-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-multi-vendor',
                'is_3rd_party_installed' => function_exists( 'YITH_Vendors' ),
                'description'            => 'Fully customize extra email templates sent by <strong>YITH WooCommerce Multi Vendor</strong>&nbsp;using the&nbsp;<strong>YayMail</strong>&nbsp;email designer.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woocommerce-multi-vendor',
            ],
            'YayMailAddonGermanized'                       => [
                'plugin_name'            => 'Germanized Pro',
                'template_ids'           => self::get_template_ids(
                    [
                        'storeabill_documentadmin',
                        'storeabill_vendiderogermanizedprostoreabillpackingslipemail',
                        'WC_GZD_Email_Customer_Cancelled_Order',
                        'WC_GZD_Email_Customer_Guest_Return_Shipment_Request',
                        'WC_GZD_Email_Customer_New_Account_Activation',
                        'WC_GZD_Email_Customer_Paid_For_Order',
                        'storeabill_cancellationinvoice',
                        'storeabill_document',
                        'storeabill_simpleinvoice',
                        'WC_GZD_Email_Customer_Return_Shipment',
                        'WC_GZD_Email_Customer_Return_Shipment_Delivered',
                        'WC_GZD_Email_Customer_Revocation',
                        'WC_GZD_Email_Customer_Shipment',
                        'WC_GZD_Email_New_Return_Shipment_Request',
                        'oss_woocommerce_deliverythresholdemailnotification',
                        'WC_GZD_Email_Customer_SEPA_Direct_Debit_Mandate',
                        'WC_STC_Email_Customer_Guest_Return_Shipment_Request',
                        'WC_STC_Email_Customer_Return_Shipment',
                        'WC_STC_Email_Customer_Return_Shipment_Delivered',
                        'WC_STC_Email_Customer_Shipment',
                        'WC_STC_Email_New_Return_Shipment_Request',

                    ]
                ),
                'slug_name'              => 'woocommerce-germanized',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-germanized',
                'is_3rd_party_installed' => class_exists( 'WooCommerce_Germanized' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your WooCommerce <strong>Germanized</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-germanized',
                'categories'             => [ 'shipment', 'order-status', 'others' ],
            ],
            'YayMailAddonWcBookings'                       => [
                'plugin_name'            => 'WooCommerce Bookings',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-booking.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Email_Admin_Booking_Cancelled',
                        'WC_Email_Booking_Cancelled',
                        'WC_Email_Booking_Confirmed',
                        'WC_Email_Booking_Notification',
                        'WC_Email_Booking_Pending_Confirmation',
                        'WC_Email_Booking_Reminder',
                        'WC_Email_New_Booking',
                    ]
                ),
                'slug_name'              => 'woocommerce-bookings',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-bookings',
                'is_3rd_party_installed' => class_exists( 'WC_Bookings' ),
                'description'            => 'Start customizing <strong>WooCommerce Bookings</strong> email templates with <strong>YayMail - WooCommerce Email Customizer</strong> today!',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-bookings',
                'categories'             => [ 'booking', 'subscription' ],
            ],
            'YayMailAddonWcWaitlist'                       => [
                'plugin_name'            => 'WooCommerce Waitlist',
                'template_ids'           => self::get_template_ids(
                    [
                        'Pie_WCWL_Waitlist_Joined_Email',
                        'Pie_WCWL_Waitlist_Left_Email',
                        'Pie_WCWL_Waitlist_Mailout',
                        'Pie_WCWL_Waitlist_Signup_Email',
                    ]
                ),
                'slug_name'              => 'woocommerce-waitlist',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-waitlist',
                'is_3rd_party_installed' => class_exists( 'WooCommerce_Waitlist_Plugin' ),
                'description'            => 'Beautify <strong>WooCommerce Waitlist</strong> emails to lead to a better brand impression. Easy to use and set up using <strong>YayMail Builder</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-woo-waitlist',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonQuotesForWooCommerce'             => [
                'plugin_name'            => 'Quotes for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'QWC_Request_New_Quote',
                        'QWC_Request_Sent',
                        'QWC_Send_Quote',
                    ]
                ),
                'slug_name'              => 'quotes-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-quotes-for-woocommerce',
                'is_3rd_party_installed' => class_exists( 'Quotes_WC' ),
                'description'            => 'Use YayMail to customize your <strong>Quotes for WooCommerce</strong> email templates (by Pinal Shah).',
                'plugin_slug'            => 'yaymail-addon-for-quotes-for-woocommerce',
                'categories'             => [ 'wholesale' ],
            ],
            'YayMailAddonYITHPreOrder'                     => [
                'plugin_name'            => 'YITH Pre-Order',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-pre-order.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_Pre_Order_New_Pre_Order_Email',
                        'YITH_Pre_Order_Out_Of_Stock_Email',
                        'YITH_Pre_Order_Payment_Reminder_Email',
                        'YITH_Pre_Order_Cancelled_Email',
                        'YITH_Pre_Order_Completed_Email',
                        'YITH_Pre_Order_Confirmed_Email',
                        'YITH_Pre_Order_Release_Date_Changed_Email',
                        'YITH_Pre_Order_Release_Date_Reminder_Email',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-pre-order-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-pre-order',
                'is_3rd_party_installed' => function_exists( 'yith_ywpo_init' ),
                'description'            => 'Use YayMail to customize your <strong>YITH WooCommerce Pre-Order</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-yith-pre-order',
                'categories'             => [ 'order-status', 'others' ],
            ],
            'YayMailAddonWCAppointments'                   => [
                'plugin_name'            => 'WooCommerce Appointments',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Email_Admin_Appointment_Cancelled',
                        'WC_Email_Admin_Appointment_Rescheduled',
                        'WC_Email_Admin_New_Appointment',
                        'WC_Email_Appointment_Cancelled',
                        'WC_Email_Appointment_Confirmed',
                        'WC_Email_Appointment_Follow_Up',
                        'WC_Email_Appointment_Reminder',
                    ]
                ),
                'slug_name'              => 'woocommerce-appointments',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-appointments',
                'is_3rd_party_installed' => class_exists( 'WC_Appointments' ),
                'description'            => 'Customize your <strong>WooCommerce Appointments</strong> emails with the <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-appointments',
                'categories'             => [ 'booking' ],
            ],
            'YayMailAddonSGWcOrderApproval'                => [
                'plugin_name'            => 'SG WooCommerce Order Approval',
                'template_ids'           => self::get_template_ids(
                    [
                        'Sgitsoa_WC_Admin_Order_New',
                        'WC_Admin_Order_New',
                        'Sgitsoa_WC_Customer_Order_Approved',
                        'WC_Customer_Order_Approved',
                        'Sgitsoa_WC_Customer_Order_New',
                        'WC_Customer_Order_New',
                        'Sgitsoa_WC_Customer_Order_Rejected',
                        'WC_Customer_Order_Rejected',
                    ]
                ),
                'slug_name'              => 'sg-order-approval-woocommerce-pro',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-sg-woocommerce-order-approval',
                'is_3rd_party_installed' => class_exists( 'Sgitsoa_Order_Approval_Woocommerce_Pro' ) || class_exists( 'Sg_Order_Approval_Woocommerce' ),
                'description'            => 'Customize SG WooCommerce <strong>Order Approval</strong> emails in <strong>YayMail</strong> - a drag and drop email builder for WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-sg-order-approval',
                'categories'             => [ 'order-status' ],
            ],
            // 'YayMailAddonWFU'                              => [
            // 'plugin_name'            => 'WooCommerce Follow Up',
            // 'template_ids'           => self::get_follow_up_email_ids(),
            // 'slug_name'              => 'woocommerce-follow-up-emails',
            // 'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-follow-up-emails',
            // 'is_3rd_party_installed' => function_exists( 'FUE' ),
            // 'plugin_slug'            => 'yaymail-addon-for-woocommerce-follow-ups',
            // ],
            'YayMailAddonOrderDeliveryDatePro'             => [
                'plugin_name'            => 'Order Delivery Date Pro',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-order-delivery.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'ORDDD_Email_Admin_Delivery_Reminder',
                        'ORDDD_Email_Update_Date',
                        'ORDDD_Email_Delivery_Reminder',
                        'ORDDD_Lite_Email_Update_Date',
                    ]
                ),
                'slug_name'              => 'order-delivery-date',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-order-delivery-date',
                'is_3rd_party_installed' => class_exists( 'order_delivery_date' ) || class_exists( 'order_delivery_date_lite' ),
                'categories'             => [ 'order-status' ],
                'description'            => 'Customize your <strong>Order Delivery Date</strong> emails with the <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-order-delivery-date',
            ],
            'YayMailAddonOrderCancellationEmailToCustomer' => [
                'plugin_name'            => 'Order Cancellation Email to Customer',
                'image'                  => 'https://images.wpbrandy.com/uploads/order-cancellation-email-to-customer.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Email_Cancelled_customer_Order',
                    ]
                ),
                'slug_name'              => 'order-cancellation-email-to-customer',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-order-cancellation-email-to-customer',
                'is_3rd_party_installed' => class_exists( 'KA_Custom_WC_Email' ),
                'description'            => 'Use YayMail to customize your <strong>Order Cancellation Email to Customer</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-order-cancel-customer',
                'categories'             => [ 'order-status' ],
            ],
            'YayMailAddonWcSmartCoupons'                   => [
                'plugin_name'            => 'WooCommerce Smart Coupons',
                'image'                  => 'https://images.wpbrandy.com/uploads/smart-coupons.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_SC_Combined_Email_Coupon',
                        'WC_SC_Acknowledgement_Email',
                        'WC_SC_Email_Coupon',
                        'WC_SC_Expiry_Reminder_Email',
                    ]
                ),
                'slug_name'              => 'woocommerce-smart-coupons',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-smart-coupons',
                'is_3rd_party_installed' => class_exists( 'WC_Smart_Coupons' ),
                'description'            => 'Are you using <strong>Smart Coupons</strong> by StoreApps? Start sending discounts, coupons, credits, gift cards, product giveaways, and promotions via customized emails.',
                'plugin_slug'            => 'yaymail-addon-for-smart-coupons',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonDokan'                            => [
                'plugin_name'            => 'Dokan',
                'template_ids'           => self::get_template_ids(
                    [
                        'Announcement',
                        'CanceledRefundVendor',
                        'ContactSeller',
                        'ConversationNotification',
                        'DokanEmailBookingCancelled',
                        'Dokan_Email_Booking_New',
                        'Dokan_Email_Wholesale_Register',
                        'Dokan_Follow_Store_Email',
                        'Dokan_Follow_Store_Vendor_Email',
                        'Dokan_New_Support_Ticket',
                        'DokanNewSupportTicketForAdmin',
                        'Dokan_Product_Enquiry_Email',
                        'DokanReplyToAdminSupportTicket',
                        'Dokan_Reply_To_Store_Support_Ticket',
                        'Dokan_Reply_To_User_Support_Ticket',
                        'Dokan_Report_Abuse_Admin_Email',
                        'Dokan_Rma_Send_Warranty_Request',
                        'Dokan_Send_Coupon_Email',
                        'Dokan_Staff_New_Order',
                        'Dokan_Staff_Password_Update',
                        'InvoiceAuthentication',
                        'InvoiceEmail',
                        'Dokan_Email_New_Product',
                        'Dokan_Email_New_Product_Pending',
                        'Dokan_Email_New_Seller',
                        'Dokan_Email_New_Store_Review',
                        'Dokan_Email_Product_Published',
                        'Dokan_Email_Refund_Request',
                        'Dokan_Email_Refund_Vendor',
                        'Dokan_Vendor_Verification_Request_Submission',
                        'Dokan_Email_Reverse_Withdrawal_Invoice',
                        'Dokan_Email_Shipping_Status',
                        'Dokan_Vendor_Verification_Status_Update',
                        'Dokan_Email_Admin_Update_Order_Delivery_Time',
                        'Dokan_Email_Updated_Product',
                        'Dokan_Email_Update_Request_Quote',
                        'Dokan_Email_Vendor_Update_Order_Delivery_Time',
                        'Dokan_Email_Completed_Order',
                        'Dokan_Email_Vendor_Disable',
                        'Dokan_Email_Vendor_Enable',
                        'Dokan_Email_New_Order',
                        'Dokan_Email_Vendor_Product_Review',
                        'Dokan_Email_Vendor_Withdraw_Request',
                        'Dokan_Email_Withdraw_Approved',
                        'Dokan_Email_Withdraw_Cancelled',
                    ]
                ),
                'slug_name'              => [ 'dokan-lite', 'dokan-pro' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-dokan',
                'is_3rd_party_installed' => class_exists( 'WeDevs_Dokan' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>Dokan</strong> WooCommerce Multivendor Marketplace email templates.',
                'plugin_slug'            => 'yaymail-addon-for-dokan',
                'categories'             => [ 'multivendor' ],
            ],
            'YayMailAddonGermanMarket'                     => [
                'plugin_name'            => 'Woocommerce German Market',
                'template_ids'           => [
                    'wgm_confirm_order_email',
                    'wgm_double_opt_in_customer_registration',
                    'wgm_sepa',
                ],
                'slug_name'              => 'woocommerce-german-market',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-german-market',
                'is_3rd_party_installed' => class_exists( 'Woocommerce_German_Market' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>WooCommerce German Market</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-german-market',
                'categories'             => [ 'shipment', 'order-status', 'payments', 'others' ],
            ],
            'YayMailAddonB2BWholesaleSuite'                => [
                'plugin_name'            => 'B2B & Wholesale Suite',
                'template_ids'           => self::get_template_ids(
                    [
                        'B2bwhs_Your_Account_Approved_Email',
                        'B2bwhs_New_Customer_Email',
                        'B2bwhs_New_Customer_Requires_Approval_Email',
                        'B2bwhs_New_Message_Email',
                        'B2bwhs_New_Quote_Email',
                    ]
                ),
                'slug_name'              => 'b2b-wholesale-suite',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-b2b-wholesale-suite',
                'is_3rd_party_installed' => class_exists( 'B2bwhs' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>B2B &amp; Wholesale Suite</strong> by WebWizards&nbsp;email templates.',
                'plugin_slug'            => 'yaymail-addon-for-b2b-wholesale-suite',
                'categories'             => [ 'wholesale' ],
            ],
            'YayMailAddonWcDeposits'                       => [
                'plugin_name'            => 'WooCommerce Deposits',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-deposits.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Deposits_Email_Customer_Deposit_Paid',
                        'WC_Deposits_Email_Full_Payment',
                        'WC_Deposits_Email_Customer_Partial_Payment_Paid',
                        'WC_Deposits_Email_Partial_Payment',
                        'WC_Deposits_Email_Customer_Remaining_Reminder',
                    ]
                ),
                'slug_name'              => 'woocommerce-deposits',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-deposits',
                'is_3rd_party_installed' => class_exists( '\Webtomizer\WCDP\WC_Deposits' ),
                'description'            => 'Customize WooCommerce <strong>Deposits</strong> emails in <strong>YayMail</strong> – a drag and drop email builder for WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-deposits',
                'categories'             => [ 'payments' ],
            ],
            'YayMailAddonYITHWooBookingAndAppointment'     => [
                'plugin_name'            => 'YITH Booking and Appointment for WooCommerce Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-booking-and-appointment.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WCBK_Email_Booking_Status',
                        'YITH_WCBK_Email_Admin_New_Booking',
                        'YITH_WCBK_Email_Customer_Booking_Note',
                        'YITH_WCBK_Email_Customer_Booking_Notification_After_End',
                        'YITH_WCBK_Email_Customer_Booking_Notification_After_Start',
                        'YITH_WCBK_Email_Customer_Booking_Notification_Before_End',
                        'YITH_WCBK_Email_Customer_Booking_Notification_Before_Start',
                        'YITH_WCBK_Email_Customer_Cancelled_Booking',
                        'YITH_WCBK_Email_Customer_Completed_Booking',
                        'YITH_WCBK_Email_Customer_Confirmed_Booking',
                        'YITH_WCBK_Email_Customer_New_Booking',
                        'YITH_WCBK_Email_Customer_Paid_Booking',
                        'YITHEmailCustomerUnconfirmedBooking',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-booking-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-booking-and-appointment-for-woocommerce',
                'is_3rd_party_installed' => function_exists( 'yith_wcbk_init' ),
                'categories'             => [ 'booking' ],
                'plugin_slug'            => 'yaymail-addon-for-yith-booking-appointment',
            ],
            'YayMailAddonPointsRewards'                    => [
                'plugin_name'            => 'Points and Rewards for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'wps_wpr_email_notification',
                    ]
                ),
                'slug_name'              => 'points-and-rewards-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-points-and-rewards-for-woocommerce',
                'is_3rd_party_installed' => class_exists( 'Points_Rewards_For_Woocommerce' ),
                'description'            => 'Use YayMail to customize your <strong>Points and Rewards for WooCommerce</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-points-rewards',
                'categories'             => [ 'payments', 'marketing' ],
            ],
            'YayMailAddonWcGiftCards'                      => [
                'plugin_name'            => 'WooCommerce Gift Cards',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-gift-cards.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_GC_Email_Gift_Card_Received',
                        'WC_GC_Email_Gift_Card_Send_To_Buyer',
                        'WC_GC_Email_Expiration_Reminder',
                    ]
                ),
                'slug_name'              => 'woocommerce-gift-cards',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-gift-cards',
                'is_3rd_party_installed' => class_exists( 'WC_Gift_Cards' ),
                'description'            => 'Get this <strong>YayMail Addon</strong> to beautify the <strong>WooCommerce</strong> <strong>Gift Cards</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-gift-cards',
                'categories'             => [ 'marketing', 'others' ],
            ],
            'YayMailAddonPWGC'                             => [
                'plugin_name'            => 'PW WooCommerce Gift Cards',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-gift-cards.png',
                'template_ids'           => [ 'pwgc_email' ],
                'slug_name'              => 'pw-woocommerce-gift-cards',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-gift-cards',
                'is_3rd_party_installed' => class_exists( 'PW_Gift_Cards' ),
                'description'            => 'Customize <strong>PW WooCommerce Gift Cards</strong> emails in YayMail – a drag and drop email builder for WooCommerce. ',
                'plugin_slug'            => 'yaymail-addon-for-pw-woocommerce-gift-cards',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonYITHWooGiftCards'                 => [
                'plugin_name'            => 'YITH WooCommerce Gift Cards',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-gift-cards.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'ywgc-email-delivered-gift-card',
                        'ywgc-email-send-gift-card',
                        'ywgc-email-notify-customer',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-gift-cards-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-gift-cards',
                'is_3rd_party_installed' => function_exists( 'YITH_YWGC' ),
                'description'            => 'Edit email content and add attractive visuals to <strong>YITH gift cards</strong> emails and delivered gift card notifications.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woocommerce-gift-card',
                'categories'             => [ 'order-status', 'marketing' ],
            ],
            'YayMailAddonYITHWooMembership'                => [
                'plugin_name'            => 'YITH WooCommerce Membership',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-membership.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WCMBS_Cancelled_Mail',
                        'YITH_WCMBS_Expired_Mail',
                        'YITH_WCMBS_Expiring_Mail',
                        'YITH_WCMBS_Welcome_Mail',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-membership-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-woocommerce-membership',
                'is_3rd_party_installed' => function_exists( 'yith_wcmbs_pr_init' ),
                'description'            => 'Beautify <strong>YITH WooCommerce Membership</strong> emails to lead to a better brand impression. Easy to use and set up using YayMail Builder.',
                'plugin_slug'            => 'yaymail-addon-yith-woocommerce-membership-premium',
                'categories'             => [ 'membership' ],
            ],
            'YayMailAddonOrderDeliveryWc'                  => [
                'plugin_name'            => 'WooCommerce Order Delivery',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-order-delivery.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_OD_Email_Order_Delivery_Note',
                        'WC_OD_Email_Subscription_Delivery_Note',
                    ]
                ),
                'slug_name'              => 'woocommerce-order-delivery',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-order-delivery-for-woocommerce',
                'is_3rd_party_installed' => class_exists( 'WC_Order_Delivery' ),
                'description'            => 'Use YayMail to customize your <strong>WooCommerce Order Delivery Date Pro</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-order-delivery',

            ],
            'YayMailAddonWcSimpleAuctions'                 => [
                'plugin_name'            => 'WooCommerce Simple Auction',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-simple-auctions.png',
                'template_ids'           => [
                    'bid_note',
                    'auction_buy_now',
                    'auction_closing_soon',
                    'auction_fail',
                    'auction_finished',
                    'auction_relist',
                    'auction_relist_user',
                    'remind_to_pay',
                    'auction_win',
                    'customer_bid_note',
                    'Reserve_fail',
                    'outbid_note',
                ],
                'slug_name'              => 'woocommerce-simple-auctions',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-simple-auction',
                'is_3rd_party_installed' => class_exists( 'WooCommerce_simple_auction' ),
                'description'            => 'Simply install this YayMail Addon to design <strong>WooCommerce Simple Auctions</strong> emails in a drag and drop email builder.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-simple-auction',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonWCVendors'                        => [
                'plugin_name'            => 'WooCommerce Vendors Marketplace',
                'template_ids'           => self::get_template_ids(
                    [
                        'WCVendors_Admin_Notify_Application',
                        'WCVendors_Admin_Notify_Approved',
                        'WCVendors_Admin_Notify_Product',
                        'WCVendors_Admin_Notify_Shipped',
                        'WC_Email_Approve_Vendor',
                        'WC_Vendors_Pro_Email_Customer_Mark_Received',
                        'WCVendors_Customer_Notify_Shipped',
                        'WC_Email_Notify_Admin',
                        'WC_Email_Notify_Vendor',
                        'WCVendors_Vendor_Notify_Application',
                        'WCVendors_Vendor_Notify_Approved',
                        'WCVendors_Vendor_Notify_Cancelled_Order',
                        'WCVendors_Vendor_Notify_Denied',
                        'WCVendors_Vendor_Notify_Order',
                        'WC_Email_Notify_Shipped',
                        'WC_Vendors_Pro_Email_Vendor_Contact_Widget',
                    ]
                ),
                'slug_name'              => [ 'wc-vendors', 'wc-vendors-pro' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-wcvendors',
                'is_3rd_party_installed' => class_exists( 'WC_Vendors' ),
                'description'            => 'Fully customize extra email templates sent by <strong>WC Vendors Marketplace</strong> using the <strong>YayMail</strong> email designer.',
                'plugin_slug'            => 'yaymail-addon-for-wc-vendors',
                'categories'             => [ 'multivendor' ],
            ],
            'YayMailAddonWcPreOrders'                      => [
                'plugin_name'            => 'WooCommerce Pre-Orders',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-pre-orders.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Pre_Orders_Email_Admin_Pre_Order_Cancelled',
                        'WC_Pre_Orders_Email_New_Pre_Order',
                        'WC_Pre_Orders_Email_Pre_Order_Available',
                        'WC_Pre_Orders_Email_Pre_Order_Cancelled',
                        'WC_Pre_Orders_Email_Pre_Order_Date_Changed',
                        'WC_Pre_Orders_Email_Pre_Ordered',
                    ]
                ),
                'slug_name'              => 'woocommerce-pre-orders',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-pre-order',
                'is_3rd_party_installed' => class_exists( 'WC_Pre_Orders' ),
                'description'            => 'Are you selling pre-order products using <strong>WooCommerce Pre-Orders</strong>? Download this <strong>YayMail Addon</strong> to customize the email notifications today!',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-pre-orders',
                'categories'             => [ 'order-status', 'payments', 'marketing', 'others' ],

            ],
            'YayMailWooSplitOrders'                        => [
                'plugin_name'            => 'WooCommerce Split Orders',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-split-orders.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'Customer_Order_Split',
                    ]
                ),
                'slug_name'              => 'split-orders',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-split-order',
                'is_3rd_party_installed' => function_exists( 'vibe_split_orders' ),
                'description'            => 'Use YayMail to customize your <strong>Split Orders by Vibe Agency</strong> WooCommerce email templates.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-split-orders',
                'categories'             => [ 'shipment', 'order-status', 'others' ],
            ],
            'YayMailAddonWPCrowdfunding'                   => [
                'plugin_name'            => 'WP Crowdfunding Pro',
                'template_ids'           => self::get_template_ids(
                    [
                        'WPCF_Campaign_Accept',
                        'WPCF_Campaign_Submit',
                        'WPCF_Campaign_Update',
                        'WPCF_New_Backed',
                        'WPCF_New_User',
                        'WPCF_Target_Reached',
                        'WPCF_Withdraw_Request',
                    ]
                ),
                'slug_name'              => 'wp-crowdfunding-pro',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-wp-crowdfunding',
                'is_3rd_party_installed' => class_exists( '\WPCF_PRO\Init' ) || class_exists( '\WPCF\Crowdfunding' ),
                'description'            => 'Fully customize extra email templates created by <strong>WP Crowdfunding</strong> using the YayMail email designer.',
                'plugin_slug'            => 'yaymail-addon-for-wp-crowdfunding',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonWcPIP'                            => [
                'plugin_name'            => 'WC Print Invoices/Packing Lists',
                'template_ids'           => self::get_template_ids(
                    [
                        'pip_email_invoice',
                        'pip_email_packing_list',
                        'pip_email_pick_list',
                    ]
                ),
                'slug_name'              => 'woocommerce-pip',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-print-invoices-packing-lists',
                'is_3rd_party_installed' => class_exists( 'WC_PIP' ),
                'description'            => 'Customize <strong>WooCommerce Print Invoices &amp; Packing Lists</strong> emails with <strong>YayMail</strong> – WooCommerce Email Customizer.',
                'plugin_slug'            => 'yaymail-addon-for-print-invoices-packing-lists',
                'categories'             => [ 'payments', 'others' ],
            ],
            'YayMailAddonLicenseManagerWc'                 => [
                'plugin_name'            => 'License Manager for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'LMFWC_Customer_Deliver_License_Keys',
                    ]
                ),
                'slug_name'              => 'license-manager-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-license-manager',
                'is_3rd_party_installed' => class_exists( 'LicenseManagerForWooCommerce\\Main' ),
                'description'            => 'Use YayMail to customize email templates sent by <strong>License Manager for WooCommerce</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-license-manager-for-wc',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonWcAccountFunds'                   => [
                'plugin_name'            => 'Account Funds',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Account_Funds_Email_Account_Funds_Increase',
                    ]
                ),
                'slug_name'              => 'woocommerce-account-funds',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-account-funds',
                'is_3rd_party_installed' => class_exists( 'WC_Account_Funds' ),
                'description'            => 'Use YayMail to customize your <strong>Account Funds</strong> email notifications sent by Themesquad.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-account-funds',
                'categories'             => [ 'payments', 'marketing' ],
            ],
            'YayMailAddonAutomateWoo'                      => [
                'plugin_name'            => 'AutomateWoo',
                'image'                  => 'https://images.wpbrandy.com/uploads/automatewoo.png',
                'template_ids'           => self::get_automatewoo_template_ids(),
                'slug_name'              => 'automatewoo',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-automatewoo',
                'is_3rd_party_installed' => class_exists( 'AutomateWoo_Loader' ),
                'description'            => 'Fully customize extra email templates created by <strong>AutomateWoo</strong> workflows using the <strong>YayMail</strong> email designer.',
                'plugin_slug'            => 'yaymail-addon-for-automatewoo',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonSMFW'                             => [
                'plugin_name'            => 'ShopMagic',
                'template_ids'           => self::get_shopmagic_template_ids(),
                'slug_name'              => 'shopmagic-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-shopmagic',
                'is_3rd_party_installed' => class_exists( 'WPDesk\ShopMagic\Workflow\Workflow' ),
                'description'            => 'Want to boost your conversion rate? Customize follow-up emails &amp; marketing automation with ShopMagic &amp; YayMail Addon!',
                'plugin_slug'            => 'yaymail-addon-for-shopmagic',
                'categories'             => [ 'booking', 'order-status', 'marketing' ],
            ],
            'YayMailAddonWcStripePaymentGateway'           => [
                'plugin_name'            => 'WooCommerce Stripe Payment Gateway',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-stripe-payment-gateway.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Stripe_Email_Failed_Authentication_Retry',
                        'WC_Stripe_Email_Failed_Preorder_Authentication',
                        'WC_Stripe_Email_Failed_Renewal_Authentication',
                    ]
                ),
                'slug_name'              => 'woocommerce-gateway-stripe',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-stripe-payment-gateway',
                'is_3rd_party_installed' => class_exists( 'WC_Stripe' ),
                'description'            => 'Use <strong>YayMail</strong> to customize email templates sent by <strong>WooCommerce Stripe Payment Gateway</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-stripe-gateway',
                'categories'             => [ 'payments' ],
            ],
            'YayMailAddonYithStripePremium'                => [
                'plugin_name'            => 'YITH WooCommerce Stripe Premium',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WCStripe_Expiring_Card_Email',
                        'YITH_WCStripe_Renew_Needs_Action_Email',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-stripe-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-stripe',
                'is_3rd_party_installed' => function_exists( 'YITH_WCStripe' ),
                'description'            => 'Use YayMail to customize your <strong>YITH WooCommerce Stripe</strong> email notifications.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woocommerce-stripe',
                'categories'             => [ 'payments' ],
            ],
            'YayMailAddonWcfmMarketplace'                  => [
                'plugin_name'            => 'WooCommerce Multivendor Marketplace',
                'template_ids'           => self::get_wcfmvm_template_ids(),
                'slug_name'              => [ 'wc-multivendor-marketplace', 'wc-frontend-manager', 'wc-multivendor-membership' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-wcfm-marketplace',
                'is_3rd_party_installed' => class_exists( 'WCFMmp' ),
                'description'            => 'Customize WCFM Multivendor Marketplace emails in YayMail – a drag and drop email builder for WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-wcfm-marketplace',
                'categories'             => [ 'multivendor' ],
            ],
            'YayMailAddonWcMemberships'                    => [
                'plugin_name'            => 'WooCommerce Memberships',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-memberships.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Memberships_User_Membership_Activated_Email',
                        'WC_Memberships_User_Membership_Ended_Email',
                        'WC_Memberships_User_Membership_Ending_Soon_Email',
                        'WC_Memberships_User_Membership_Note_Email',
                        'WC_Memberships_User_Membership_Renewal_Reminder_Email',
                        'wc_memberships_for_teams_team_invitation',
                        'wc_memberships_for_teams_team_membership_ended',
                        'wc_memberships_for_teams_team_membership_ending_soon',
                        'wc_memberships_for_teams_team_membership_renewal_reminder',
                    ]
                ),
                'slug_name'              => [ 'woocommerce-memberships', 'woocommerce-memberships-for-teams' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-woocommerce-memberships',
                'is_3rd_party_installed' => class_exists( 'WC_Memberships' ),
                'description'            => 'Customize your <strong>WooCommerce Memberships</strong> emails with the <strong>YayMail</strong> email builder. Sell more services and gain more members.',
                'plugin_slug'            => 'yaymail-addon-for-woo-memberships',
                'categories'             => [ 'membership' ],
            ],
            'YayMailAddonWcTrackShip'                      => [
                'plugin_name'            => 'TrackShip for WooCommerce',
                'template_ids'           => [
                    'trackship_available_for_pickup',
                    'trackship_delivered',
                    'trackship_exception',
                    'trackship_failure',
                    'trackship_in_transit',
                    'trackship_on_hold',
                    'trackship_out_for_delivery',
                    'trackship_pickup_reminder',
                    'trackship_return_to_sender',

                ],
                'slug_name'              => 'trackship-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-trackship-for-woocommerce',
                'is_3rd_party_installed' => class_exists( 'Trackship_For_Woocommerce' ),
                'description'            => 'Customize your <strong>TrackShip</strong> post-shipping workflow\'s email templates with YayMail!',
                'plugin_slug'            => 'yaymail-addon-for-trackship-woocommerce',
                'categories'             => [ 'order-status', 'others' ],
            ],
            // // TODO: Hold
            // 'AliDropship_Woo_Plugin'                       => [
            // 'plugin_name'            => 'AliDropship Woo Plugin',
            // 'image'                  => 'https://images.wpbrandy.com/uploads/alidropship.png',
            // 'template_ids'           => [
            // 'adsw_order_shipped_notification',
            // 'adsw_order_tracking_changed_notification',
            // 'adsw_update_notification',
            // ],
            // 'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-alidropship-woo-plugin',
            // 'is_3rd_party_installed' => false,
            // 'description'            => 'Customize your email templates and grow your business with <strong>AliDropship</strong> - WooCommerce Dropshipping Plugin!',
            // 'plugin_slug'            => 'yaymail-addon-alidropship-woo-plugin',
            // 'categories'             => [ 'shipment', 'marketing' ],
            // ],
            'YayMailYITHWooReviewDiscountsPremium'         => [
                'plugin_name'            => 'YITH WooCommerce Review For Discounts Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-review-discounts.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YWRFD_Coupon_Mail',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-review-for-discounts-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-yith-review-for-discounts',
                'is_3rd_party_installed' => function_exists( 'YITH_WRFD' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>YITH WooCommerce Review for Discounts</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-review-for-discounts-premium',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonSUMOPaymentPlans'                 => [
                'plugin_name'            => 'SUMO Payment Plans',
                'template_ids'           => self::get_template_ids(
                    [
                        'SUMO_PP_Deposit_Balance_Payment_Auto_Charge_Reminder_Email',
                        'SUMO_PP_Deposit_Balance_Payment_Completed_Email',
                        'SUMO_PP_Deposit_Balance_Payment_Invoice_Email',
                        'SUMO_PP_Deposit_Balance_Payment_Overdue_Email',
                        'SUMO_PP_Payment_Awaiting_Cancel_Email',
                        'SUMO_PP_Payment_Cancelled_Email',
                        'SUMO_PP_Payment_Pending_Auth_Email',
                        'SUMO_PP_Payment_Plan_Auto_Charge_Reminder_Email',
                        'SUMO_PP_Payment_Plan_Completed_Email',
                        'SUMO_PP_Payment_Plan_Invoice_Email',
                        'SUMO_PP_Payment_Plan_Overdue_Email',
                        'SUMO_PP_Payment_Plan_Success_Email',
                        'SUMO_PP_Payment_Schedule_Email',
                    ]
                ),
                'slug_name'              => 'sumopaymentplans',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-premium-addon-for-sumo-payment-plans',
                'is_3rd_party_installed' => class_exists( 'SUMOPaymentPlans' ),
                'description'            => 'Fully customize extra email templates sent by <strong>SUMO WooCommerce Payment Plans</strong> – Deposits, Down Payments, Installments, Variable Payments using the YayMail email designer.',
                'plugin_slug'            => 'yaymail-addon-for-sumo-payment-plans',
                'categories'             => [ 'payments' ],
            ],
            'YayMailAddonWcTeraWallet'                     => [
                'plugin_name'            => 'TeraWallet',
                'template_ids'           => self::get_template_ids(
                    [
                        'Woo_Wallet_Email_Low_Wallet_Balance',
                        'Woo_Wallet_Email_New_Transaction',
                        'WOO_Wallet_Withdrawal_Approved',
                        'WOO_Wallet_Withdrawal_Reject',
                        'WOO_Wallet_Withdrawal_Request',
                    ]
                ),
                'slug_name'              => 'woo-wallet',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-terawallet',
                'is_3rd_party_installed' => class_exists( 'WooWallet' ) || class_exists( 'Woo_Wallet' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>TeraWallet</strong> WooCommerce email templates.',
                'plugin_slug'            => 'yaymail-addon-for-terawallet',
                'categories'             => [ 'payments', 'others' ],
            ],
            // TODO
            // 'CustomFieldsforWooCommerce'                   => [
            // 'plugin_name'  => 'Custom Fields for WooCommerce by Addify',
            // 'template_ids' => [
            // 'af_email_admin_register_new_user',
            // 'af_email_approve_user_account',
            // 'af_email_declined_user_account',
            // 'af_email_register_new_account',
            // ],
            // 'link_upgrade' => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-custom-fields-by-addify',
            // 'description'  => 'Use <strong>YayMail</strong> to customize your <strong>Custom Fields</strong> by Addify email templates.',
            // 'plugin_slug'  => 'yaymail-addon-for-custom-fields-addify',
            // 'categories'   => [ 'others' ],
            // ],
            'YayMailAddonMultiLocationInventory'           => [
                'plugin_name'            => 'WooCommerce MultiLocation Inventory & Order Routing',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Wh_New_Order_Email',
                        'WC_Wh_Reassign_Order_Email',
                    ]
                ),
                'slug_name'              => 'myworks-warehouse-routing',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-multi-warehouse-order-routing',
                'is_3rd_party_installed' => class_exists( 'MW_WHDependencies' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>WooCommerce Multi Warehouse &amp; Order Routing</strong> email templates by woocommercewarehouses.com',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-multilocation-inventory-order-routing',
                'categories'             => [ 'shipment', 'order-status' ],
            ],
            'YayMailAddonMultiVendorX'                     => [
                'plugin_name'            => 'MultiVendorX - The Ultimate WooCommerce Multivendor Marketplace Solution',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Email_Admin_Added_New_Product_to_Vendor',
                        'WC_Email_Admin_Change_Order_Status',
                        'WC_Email_Admin_New_Question',
                        'WC_Email_Admin_New_Vendor_Account',
                        'WC_Email_Admin_Widthdrawal_Request',
                        'WC_Email_Approved_New_Vendor_Account',
                        'WC_Email_Customer_Refund_Request',
                        'WC_Email_Customer_Answer',
                        'WC_Email_Vendor_New_Coupon_Added',
                        'WC_Email_Plugin_Deactivated_Mail',
                        'WC_Email_Rejected_New_Vendor_Account',
                        'WC_Email_Send_Report_Abuse',
                        'WC_Email_Send_Site_Information',
                        'WC_Email_Suspend_Vendor_Account',
                        'WC_Email_Admin_Vendor_Account_Deactivation_Request_Mail',
                        'WC_Email_Vendor_Account_Deactive_Request_Reject_Mail',
                        'WC_Email_Vendor_Account_Deletion_Mail',
                        'WC_Email_Vendor_DirectBank_Commission_Transactions',
                        'WC_Email_Vendor_Cancelled_Order',
                        'WC_Email_Vendor_Direct_Bank',
                        'WC_Email_Vendor_Contact_Widget',
                        'WC_Email_Vendor_Followed',
                        'WC_Email_Vendor_Followed_Customer',
                        'WC_Email_Vendor_New_Account',
                        'WC_Email_Vendor_New_Announcement',
                        'WC_Email_Vendor_New_Coupon_Added_To_Customer',
                        'WC_Email_Vendor_New_Order',
                        'WC_Email_Vendor_New_Product_Added',
                        'WC_Email_Vendor_New_Question',
                        'WC_Email_Notify_Shipped',
                        'WC_Email_Vendor_Orders_Stats_Report',
                        'WC_Email_Vendor_Product_Approved',
                        'WC_Email_Vendor_Product_Rejected',
                        'WC_Email_Vendor_Review',
                        'WC_Email_Vendor_Commission_Transactions',
                    ]
                ),
                'slug_name'              => [ 'dc-woocommerce-multi-vendor', 'mvx-pro' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-wc-marketplace',
                'is_3rd_party_installed' => class_exists( 'WC_Dependencies_Product_Vendor' ),
                'description'            => 'Use YayMail to customize your <strong>MultiVendorX</strong> email templates of MultiVendor Marketplace Solution For WooCommerce (formerly known as WCMp by WC Marketplace).',
                'plugin_slug'            => 'yaymail-addon-for-multivendor-marketplace-solution',
                'categories'             => [ 'multivendor' ],
            ],
            'YayMailAddonAffiliateWc'                      => [
                'plugin_name'            => 'Affiliate For WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'AFWC_Email_Affiliate_Pending_Request',
                        'AFWC_Email_Affiliate_Summary_Reports',
                        'AFWC_Email_Automatic_Payouts_Reminder',
                        'AFWC_Email_Commission_Paid',
                        'AFWC_Email_New_Conversion_Received',
                        'AFWC_Email_New_Registration_Received',
                        'AFWC_Email_Welcome_Affiliate',
                    ]
                ),
                'slug_name'              => 'affiliate-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-affiliate-for-woocommerce',
                'is_3rd_party_installed' => class_exists( 'Affiliate_For_WooCommerce' ),
                'description'            => 'Customize affiliate email templates to run a successful affiliate program with <strong>YayMail</strong> for WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-affiliate-for-woocommerce',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonWooProductVendors'                => [
                'plugin_name'            => 'WooCommerce Product Vendors',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-product-vendors.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Product_Vendors_Approval',
                        'WC_Product_Vendors_Cancelled_Order_Email_To_Vendor',
                        'WC_Product_Vendors_New_Renewal_Email_To_Vendor',
                        'WC_Product_Vendors_Order_Email_To_Vendor',
                        'WC_Product_Vendors_Order_Fulfill_Status_To_Admin',
                        'WC_Product_Vendors_Order_Note_To_Customer',
                        'WC_Product_Vendors_Product_Added_Notice',
                        'WC_Product_Vendors_Registration_Email_To_Admin',
                        'WC_Product_Vendors_Registration_Email_To_Vendor',
                    ]
                ),
                'slug_name'              => 'woocommerce-product-vendors',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-product-vendors',
                'is_3rd_party_installed' => class_exists( 'WC_Product_Vendors' ),
                'description'            => 'Use YayMail to customize your <strong>WooCommerce Product Vendors</strong>&nbsp;email templates.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-product-vendors',
                'categories'             => [ 'multivendor' ],
            ],
            'YayMailAddonBISN'                             => [
                'plugin_name'            => 'WooCommerce Back In Stock Notifications',
                'image'                  => 'https://images.wpbrandy.com/uploads/back-in-stock-notifications.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_BIS_Email_Notification_Confirm',
                        'WC_BIS_Email_Notification_Received',
                        'WC_BIS_Email_Notification_Verify',
                    ]
                ),
                'slug_name'              => 'woocommerce-back-in-stock-notifications',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-back-in-stock-notifications',
                'is_3rd_party_installed' => class_exists( 'WC_Back_In_Stock' ),
                'description'            => 'Use&nbsp;<strong>YayMail</strong> to customize the email templates sent by <strong>Back In Stock Notifications</strong> (by WooCommerce).',
                'plugin_slug'            => 'yaymail-addon-for-woo-back-in-stock-notifications',
                'categories'             => [ 'order-status', 'others' ],
            ],
            'YayMailAddonWcReturnWarranty'                 => [
                'plugin_name'            => 'WooCommerce Return and Warranty Pro',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-returns-and-warranty-requests.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WCRW_Send_Coupon_Email',
                        'WCRW_Send_Message_Email',
                        'WCRW_Cancel_Order_Request',
                        'WCRW_Create_Request_Admin',
                        'WCRW_Create_Request_Customer',
                        'WCRW_Update_Request',
                    ]
                ),
                'slug_name'              => [ 'wc-return-warranty', 'wc-return-warranty-pro' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-return-and-warranty',
                'is_3rd_party_installed' => class_exists( 'WC_Return_Warranty' ) || class_exists( 'WC_Return_Warranty_Pro' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>WooCommerce Return and Warranty</strong> email templates (by chilidevs).',
                'plugin_slug'            => 'yaymail-addon-for-woo-return-warranty-pro',
                'categories'             => [ 'order-status', 'payments', 'others' ],
            ],
            'YayMailAddonB2BKing'                          => [
                'plugin_name'            => 'B2BKing',
                'image'                  => 'https://images.wpbrandy.com/uploads/b2bking.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'B2bking_New_Customer_Email',
                        'B2bking_New_Customer_Requires_Approval_Email',
                        'B2bking_New_Message_Email',
                        'B2bking_New_Offer_Email',
                        'B2bking_Your_Account_Approved_Email',
                    ]
                ),
                'slug_name'              => 'b2bking',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-b2bking',
                'is_3rd_party_installed' => class_exists( 'B2bking' ),
                'description'            => 'Use&nbsp;<strong>YayMail</strong> to customize your <strong>B2BKing</strong> (by WebWizards)&nbsp;email templates.',
                'plugin_slug'            => 'yaymail-addon-for-b2bking',
                'categories'             => [ 'wholesale' ],
            ],
            // Hold
            // 'Domina_Shipping'                              => [
            // 'plugin_name'            => 'Domina Shipping',
            // 'template_ids'           => [
            // 'Domina_Email_Tracking',
            // ],
            // 'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-domina-shipping',
            // 'is_3rd_party_installed' => class_exists( 'Domina' ),
            // 'description'            => 'Email Customizer for Domina Shipping of Departamentos y Ciudades de Colombia para WooCommerce.',
            // 'plugin_slug'            => 'yaymail-addon-domina-shipping',
            // ],
            'YayMailAddonYITHWooDeliveryDate'              => [
                'plugin_name'            => 'YITH WooCommerce Delivery Date Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-delivery-date.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_Delivery_Date_Advise_Customer_Email',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-delivery-date-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-delivery-date',
                'is_3rd_party_installed' => function_exists( 'yith_delivery_date_init_plugin' ),
                'description'            => 'Edit email content and add attractive visuals to <strong>YITH WooCommerce Delivery Date</strong> emails with YayMail - WooCommerce Email Customizer.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-delivery-date',
            ],
            'YayMailAddonYITHAdvancedRefundSystem'         => [
                'plugin_name'            => 'YITH Advanced Refund System for WooCommerce Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-advanced-refund-system.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_ARS_Coupon_User_Email',
                        'YITH_ARS_New_Message_Admin_Email',
                        'YITH_ARS_New_Message_User_Email',
                        'YITH_ARS_New_Request_Admin_Email',
                        'YITH_ARS_New_Request_User_Email',
                        'YITH_ARS_Approved_User_Email',
                        'YITH_ARS_On_Hold_User_Email',
                        'YITH_ARS_Processing_User_Email',
                        'YITH_ARS_Rejected_User_Email',
                    ]
                ),
                'slug_name'              => 'yith-advanced-refund-system-for-woocommerce.premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-advanced-refund-system',
                'is_3rd_party_installed' => function_exists( 'yith_ywars_init' ),
                'description'            => 'Use YayMail to customize your <strong>YITH Advanced Refund System for WooCommerce</strong> email templates.',
                'plugin_slug'            => 'yaymail-addon-for-yith-advanced-refund-system-for-woocommerce',
                'categories'             => [ 'order-status', 'payments', 'others' ],
            ],
            'YayMailAddonYITHWooAffiliates'                => [
                'plugin_name'            => 'YITH WooCommerce Affiliates Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-affiliates.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WCAF_admin_affiliate_banned_Email',
                        'YITH_WCAF_admin_affiliate_status_changed_Email',
                        'YITH_WCAF_admin_commission_status_changed_Email',
                        'YITH_WCAF_admin_new_affiliate_Email',
                        'YITH_WCAF_admin_paid_commission_Email',
                        'YITH_WCAF_affiliate_banned_Email',
                        'YITH_WCAF_affiliate_disabled_Email',
                        'YITH_WCAF_affiliate_enabled_Email',
                        'YITH_WCAF_new_affiliate_Email',
                        'YITH_WCAF_new_affiliate_commission_Email',
                        'YITH_WCAF_new_affiliate_coupon_Email',
                        'YITH_WCAF_new_affiliate_payment_Email',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-affiliates-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-affiliates',
                'is_3rd_party_installed' => function_exists( 'yith_affiliates_constructor' ),
                'description'            => 'Use <strong>YayMail</strong> to customize your <strong>YITH WooCommerce Affiliates</strong> email templates and grow your store!',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-affiliates',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonYITHWooAuctions'                  => [
                'plugin_name'            => 'YITH Auctions for WooCommerce Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-auctions.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WCACT_Email_Auction_No_Winner',
                        'YITH_WCACT_Email_Auction_Rescheduled_Admin',
                        'YITH_WCACT_Email_Auction_Winner',
                        'YITH_WCACT_Email_Auction_Winner_Reminder',
                        'YITH_WCACT_Email_Better_Bid',
                        'YITH_WCACT_Email_Closed_Buy_Now',
                        'YITH_WCACT_Email_Delete_Bid',
                        'YITH_WCACT_Email_Delete_Bid_Admin',
                        'YITH_WCACT_Email_End_Auction',
                        'YITH_WCACT_Email_New_Bid',
                        'YITH_WCACT_Email_Not_Reached_Reserve_Price',
                        'YITH_WCACT_Email_Not_Reached_Reserve_Price_Max_Bidder',
                        'YITH_WCACT_Email_Successfully_Bid',
                        'YITH_WCACT_Email_Successfully_Bid_Admin',
                        'YITH_WCACT_Email_Successfully_Follow',
                        'YITH_WCACT_Email_Winner_Admin',
                        'YITH_WCACT_Email_Without_Bid',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-auctions-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-auctions-for-woocommerce',
                'is_3rd_party_installed' => function_exists( 'yith_wcact_init_premium' ),
                'description'            => 'Are you using <strong>YITH Auctions for WooCommerce</strong>? Buy this YayMail Addon to customize those auctions emails.',
                'plugin_slug'            => 'yaymail-addon-for-yith-auctions-for-woo',
                'categories'             => [ 'payments', 'marketing' ],
            ],
            'YayMailAddonRMAReturnRefundExchange'          => [
                'plugin_name'            => 'RMA Return Refund & Exchange for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'wps_rma_cancel_request_email',
                        'wps_rma_exchange_request_accept_email',
                        'wps_rma_exchange_request_cancel_email',
                        'wps_rma_exchange_request_email',
                        'wps_rma_order_messages_email',
                        'wps_rma_refund_email',
                        'wps_rma_refund_request_accept_email',
                        'wps_rma_refund_request_cancel_email',
                        'wps_rma_refund_request_email',
                        'wps_rma_returnship_email',
                    ]
                ),
                'slug_name'              => [ 'woo-refund-and-exchange-lite', 'woocommerce-rma-for-return-refund-and-exchange' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-rma-return-refund-and-exchange-for-woocommerce',
                'is_3rd_party_installed' => function_exists( 'define_woo_refund_and_exchange_lite_constants' ),
                'description'            => 'Use YayMail to customize extra email templates sent by <strong>RMA Return Refund and Exchange for WooCommerce</strong> (by WPSwings).',
                'plugin_slug'            => 'yaymail-addon-for-rma-return-refund-exchange-for-woo',
                'categories'             => [ 'order-status', 'marketing', 'others' ],
            ],
            'YayMailAddonYITHWooPointsRewards'             => [
                'plugin_name'            => 'YITH WooCommerce Points and Rewards Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-points-and-rewards.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_YWPAR_Expiration',
                        'YITH_YWPAR_Update_Points',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-points-and-rewards-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-points-and-rewards',
                'is_3rd_party_installed' => function_exists( 'yith_ywpar_premium_constructor' ),
                'description'            => 'Fully customize extra email templates developed by <strong>YITH WooCommerce Points and Rewards</strong> using the <strong>YayMail</strong> email customizer.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-points-rewards-premium',
            ],
            'YayMailAddonWCPDFProductVouchers'             => [
                'plugin_name'            => 'WooCommerce PDF Product Vouchers',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_PDF_Product_Vouchers_Email_Voucher_Purchaser',
                        'WC_PDF_Product_Vouchers_Email_Voucher_Recipient',
                    ]
                ),
                'slug_name'              => 'woocommerce-pdf-product-vouchers',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-pdf-product-vouchers',
                'is_3rd_party_installed' => class_exists( 'WC_PDF_Product_Vouchers' ),
                'description'            => 'Fully customize extra email templates sent by<strong> WooCommerce PDF Product Vouchers</strong>&nbsp;using&nbsp;<strong>YayMail</strong>&nbsp;email designer.',
                'plugin_slug'            => 'yaymail-addon-for-woo-pdf-product-vouchers',
            ],
            'YayMailAddonYITHWooRequestAQuote'             => [
                'plugin_name'            => 'YITH WooCommerce Request A Quote Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-request-quote.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_YWRAQ_Quote_Status',
                        'YITH_YWRAQ_Send_Quote',
                        'YITH_YWRAQ_Send_Quote_Reminder',
                        'YITH_YWRAQ_Send_Quote_Reminder_Accept',
                        'YITH_YWRAQ_Send_Email_Request_Quote',
                        'YITH_YWRAQ_Send_Email_Request_Quote_Customer',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-request-a-quote-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-request-a-quote',
                'is_3rd_party_installed' => function_exists( 'yith_ywraq_premium_constructor' ),
                'description'            => 'Fully customize extra email templates created by<strong> YITH WooCommerce Request a Quote</strong> using <strong>YayMail</strong> email designer.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-request-a-quote',
                'categories'             => [ 'wholesale', 'others' ],
            ],
            'YayMailAddonWooSellServices'                  => [
                'plugin_name'            => 'Woo Sell Services',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Order_Accepted_Email',
                        'WC_Order_Rejected_Email',
                        'WC_Requirement_Received_Email',
                        'WC_Order_Ready_Email',
                        'WC_Requirement_Order_Email',
                        'WC_Order_Conversation_Email',
                    ]
                ),
                'slug_name'              => 'woo-sell-services',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woo-sell-services',
                'is_3rd_party_installed' => class_exists( 'Woo_Sell_Services_Main' ),
                'description'            => 'Customize your <strong>Woo Sell Services</strong> email templates with the YayMail email designer.',
                'plugin_slug'            => 'yaymail-addon-for-woo-sell-services',
                'categories'             => [ 'booking', 'subscription', 'multivendor' ],
            ],
            'YayMailAddonYITHWooRecoverAbandonedCart'      => [
                'plugin_name'            => 'YITH WooCommerce Recover Abandoned Cart Premium',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-recover-abandoned-cart.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_YWRAC_Send_Email',
                        'YITH_YWRAC_Send_Email_Recovered_Cart',
                    ]
                ),
                'slug_name'              => 'yith-woocommerce-recover-abandoned-cart-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-recover-abandoned-cart',
                'is_3rd_party_installed' => function_exists( 'yith_ywrac_premium_constructor' ),
                'description'            => 'Use <strong>YayMail</strong> designer to customize your extra email templates created by <strong>YITH WooCommerce Recover Abandoned Cart</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woo-recover-abandoned-cart',
            ],
            'YayMailAddonYITHWooCouponEmailSystem'         => [
                'plugin_name'            => 'YITH WooCommerce Coupon Email System Premium',
                'template_ids'           => [
                    'YWCES_birthday',
                    'YWCES_first_purchase',
                    'YWCES_last_purchase',
                    'YWCES_product_purchasing',
                    'YWCES_purchases',
                    'YWCES_register',
                    'YWCES_spending',
                    'yith-coupon-email-system',
                ],
                'slug_name'              => 'yith-woocommerce-coupon-email-system-premium',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-woocommerce-coupon-email-system',
                'is_3rd_party_installed' => function_exists( 'ywces_init' ),
                'description'            => 'Are you using <strong>YITH WooCommerce Coupon Email System</strong>? Buy this YayMail Addon to customize those coupon notification emails.',
                'plugin_slug'            => 'yaymail-addon-for-yith-woocommerce-coupon-email-system-premium',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonYITHWooEasyLoginAndRegisterPopup' => [
                'plugin_name'            => 'YITH Easy Login & Register Popup For WooCommerce',
                'image'                  => 'https://images.wpbrandy.com/uploads/yith-easy-login.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'YITH_WELRP_Customer_Authentication_Code',
                    ]
                ),
                'slug_name'              => 'yith-easy-login-register-popup-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-yith-easy-login-register-popup',
                'is_3rd_party_installed' => function_exists( 'yith_welrp_init' ),
                'description'            => 'Customize extra emails and enhance your sales funnel of <strong>YITH Easy Login &amp; Register Popup</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-yith-easy-login-register-popup',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonColissimoShippingMethod'          => [
                'plugin_name'            => 'Colissimo shipping methods for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'LpcInwardLabelGenerationEmail',
                        'LpcOutwardLabelGenerationEmail',
                    ]
                ),
                'slug_name'              => 'colissimo-shipping-methods-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-colissimo-shipping-methods',
                'is_3rd_party_installed' => class_exists( 'LpcInit' ),
                'description'            => 'Customize WooCommerce <strong>Colissimo</strong> emails in <strong>YayMail</strong> – a drag and drop email builder for WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-colissimo-shipping-methods-for-woo',
                'categories'             => [ 'shipment' ],
            ],
            'YayMailAddonParcelPanelOrderTrackingWc'       => [
                'plugin_name'            => 'Parcel Panel Order Tracking for WooCommerce',
                'template_ids'           => self::get_template_ids(
                    [
                        'WC_Email_Customer_Partial_Shipped_Order',
                        'WC_Email_Customer_PP_Delivered',
                        'WC_Email_Customer_PP_Exception',
                        'WC_Email_Customer_PP_Failed_Attempt',
                        'WC_Email_Customer_PP_In_Transit',
                        'WC_Email_Customer_PP_Out_For_Delivery',
                    ]
                ),
                'slug_name'              => 'parcelpanel',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-parcelpanel-order-tracking-for-woocommerce/',
                'is_3rd_party_installed' => class_exists( 'ParcelPanel\ParcelPanel' ),
                'description'            => 'Personalize WooCommerce shipment tracking emails to your customers using <strong>YayMail - WooCommerce Email Customizer</strong>!',
                'plugin_slug'            => 'yaymail-addon-for-parcel-panel-order-tracking',
                'categories'             => [ 'shipment' ],
            ],
            'YayMailAddonWcCartAbandonmentRecovery'        => [
                'plugin_name'            => 'WooCommerce Cart Abandonment Recovery',
                'template_ids'           => self::get_wc_cart_abandonment_recovery_template_ids(),
                'slug_name'              => 'woo-cart-abandonment-recovery',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woo-cart-abandonment-recovery-cartflows',
                'is_3rd_party_installed' => class_exists( 'CARTFLOWS_CA_Loader' ),
                'description'            => 'Easily showcase your branding and <strong>personalize email templates</strong> of WooCommerce Cart Abandonment Recovery by CartFlows',
                'plugin_slug'            => 'yaymail-addon-for-wc-cart-abandonment-recovery',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonB2BMarket'                        => [
                'plugin_name'            => 'B2B Market',
                'template_ids'           => [
                    'new_customer_registration_admin_customer_approval',
                    'new_customer_registration_pending_approval',
                    'new_customer_registration_user_approved',
                    'new_customer_registration_user_denied',
                    'double_opt_in_customer_registration',
                ],
                'slug_name'              => 'b2b-market',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-b2b-market-marketpress',
                'is_3rd_party_installed' => class_exists( 'BM' ),
                'description'            => 'Easily add custom header/footer and <strong>personalize email templates</strong> sent by WooCommerce B2B Market (MarketPress).',
                'plugin_slug'            => 'yaymail-addon-for-b2b-market',
                'categories'             => [ 'wholesale' ],
            ],
            'YayMailAddonWholesaleX'                       => [
                'plugin_name'            => 'WholesaleX',
                'template_ids'           => self::get_template_ids(
                    [
                        'WholesaleX_Admin_New_User_Awating_Approval_Notification_Email',
                        'WholesaleX_Admin_New_User_Notification_Email',
                        'WholesaleX_New_User_Approved_Email',
                        'WholesaleX_New_User_Auto_Approved_Email',
                        'WholesaleX_New_User_Pending_For_Approval_Email',
                        'WholesaleX_New_User_Rejected_Email',
                        'WholesaleX_New_User_Verification_Email',
                        'WholesaleX_New_User_Verified_Email',
                        'WholesaleX_User_Profile_Update_Notification_Email',
                        'WholesaleX_New_Subaccount_Create_Email',
                        'WholesaleX_Subaccount_Order_Approval_Required_Email',
                        'WholesaleX_Subaccount_Order_Approved_Email',
                        'WholesaleX_Subaccount_Order_Pending_Email',
                        'WholesaleX_Subaccount_Order_Placed_Email',
                        'WholesaleX_Subaccount_Order_Reject_Email',
                    ]
                ),
                'slug_name'              => 'wholesalex',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-wholesalex',
                'is_3rd_party_installed' => function_exists( 'wholesalex_run' ),
                'description'            => 'Customize WooCommerce B2B store order emails from <strong>WholesaleX</strong>&nbsp;with YayMail - WooCommerce Email Customizer.',
                'plugin_slug'            => 'yaymail-addon-for-wholesalex',
                'categories'             => [ 'wholesale' ],
            ],
            'YayMailAddonWcBookingsAppointments'           => [
                'plugin_name'            => 'WooCommerce Bookings And Appointments by PluginHive',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-booking.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'Ph_WC_Email_Booking_Cancelled_For_Admin',
                        'Ph_WC_Email_Booking_Waiting_For_Approval',
                        'Ph_WC_Email_Booking_Cancelled',
                        'Ph_WC_Email_Booking_Confirmation',
                        'Ph_WC_Email_Booking_followup',
                        'Ph_WC_Email_Booking_reminder',
                        'Ph_WC_Email_Booking_Requires_Confirmation',
                    ]
                ),
                'slug_name'              => 'ph-bookings-appointments-woocommerce-premium-3.4.2',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-booking-and-appointments-pluginhive',
                'is_3rd_party_installed' => class_exists( 'phive_booking_initialze_premium' ),
                'description'            => 'Customize new booking emails, booking confirmations, and email reminders sent by <strong>WooCommerce Bookings And Appointments by PluginHive</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-booking-and-appointments-pluginhive',
                'categories'             => [ 'booking' ],
            ],
            'YayMailAddonWcContactShippingQuote'           => [
                'plugin_name'            => 'WooCommerce Contact for Shipping Quote',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-contact-for-shipping-quote.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'WCCSQ_Email_Customer_Shipping_Quote_Available',
                        'WCCSQ_Email_Shipping_Quote_Requested',
                    ]
                ),
                'slug_name'              => 'woocommerce-contact-for-shipping-quote',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-contact-for-shipping-quote',
                'is_3rd_party_installed' => class_exists( '\WooCommerce_Contact_for_Shipping_Quote\WooCommerce_Contact_For_Shipping_Quote' ),
                'description'            => 'Customize shipping quote request notifications. Enhance your branding through emails of <strong>WooCommerce Contact for Shipping Quote</strong>.',
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-contact-for-shipping-quote',
                'categories'             => [ 'shipment', 'wholesale' ],
            ],
            'YayMailAddonDepositsPartialPayment'           => [
                'plugin_name'            => 'Deposits & Partial Payments for WooCommerce',
                'image'                  => 'https://images.wpbrandy.com/uploads/woo-deposits.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'AWCDP_Email_Deposit_Paid',
                        'AWCDP_Email_Full_Payment',
                        'AWCDP_Email_Partial_Paid',
                        'AWCDP_Email_Partial_Payment',
                        'AWCDP_Email_Payment_Reminder',
                    ]
                ),
                'slug_name'              => 'deposits-partial-payments-for-woocommerce-pro',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woocommerce-deposits-partial-payments-acowebs/',
                'is_3rd_party_installed' => class_exists( 'AWCDP_Deposits' ),
                'categories'             => [ 'payments' ],
                'plugin_slug'            => 'yaymail-addon-for-woocommerce-deposits-partial-payments-acowebs',
            ],
            'YayMailAddonMarketKing'                       => [
                'plugin_name'            => 'MarketKing Core',
                'template_ids'           => self::get_template_ids(
                    [
                        'Marketking_New_Announcement_Email',
                        'Marketking_New_Message_Email',
                        'Marketking_New_Payout_Email',
                        'Marketking_New_Product_Requires_Approval_Email',
                        'Marketking_New_Rating_Email',
                        'Marketking_New_Refund_Email',
                        'Marketking_New_Vendor_Requires_Approval_Email',
                        'Marketking_New_Verification_Email',
                        'Marketking_Product_Has_Been_Approved_Email',
                        'Marketking_Your_Account_Approved_Email',
                    ]
                ),
                'slug_name'              => [ 'marketking-multivendor-marketplace-for-woocommerce', 'marketking-pro' ],
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-marketking-multivendor-marketplace-plugin',
                'is_3rd_party_installed' => class_exists( 'Marketkingcore' ),
                'description'            => 'Customize MarketKing emails and enhance your multi-vendor marketplace in WooCommerce.',
                'plugin_slug'            => 'yaymail-addon-for-marketking-multivendor-marketplace-plugin',
            ],
            'YayMailAddonAddifyRegistrationFields'         => [
                'plugin_name'            => 'Custom User Registration Fields for WooCommerce',
                'image'                  => 'https://images.wpbrandy.com/uploads/custom-user-registration-fields-addify.png',
                'template_ids'           => self::get_template_ids(
                    [
                        'afreg_admin_email_new_user',
                        'afreg_admin_email_update_user',
                        'afreg_approved_user_email_user',
                        'afreg_disapproved_user_email_user',
                        'afreg_pending_user_email_user',
                        'afreg_user_email_new_user',
                    ]
                ),
                'slug_name'              => 'user-registration-plugin-for-woocommerce',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-custom-user-registration-fields-for-woocommerce-by-addify/',
                'is_3rd_party_installed' => class_exists( 'Addify_Registration_Fields_Addon' ),
                'description'            => 'Customize <strong>Custom User Registration Fields for WooCommerce</strong> emails with <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-custom-user-registration-fields-addify',
                'categories'             => [ 'others' ],
            ],
            'YayMailAddonSalesman'                         => [
                'plugin_name'            => 'Salesman (by MarketPress)',
                'template_ids'           => [],
                'slug_name'              => 'salesman',
                'link_upgrade'           => '',
                'is_3rd_party_installed' => class_exists( 'MarketPress_Salesman' ),
                'description'            => 'Customize <strong>Salesman</strong> emails with <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-salesman-by-marketpress',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonForBlocksy'                       => [
                'plugin_name'            => 'YayMail Addon For Blocksy',
                'template_ids'           => self::get_template_ids(
                    [
                        'BackInStockEmail',
                        'ConfirmSubscriptionEmail',
                        'SubscriptionConfirmedEmail',
                    ]
                ),
                'slug_name'              => 'blocksy',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-blocksy/',
                'is_3rd_party_installed' => class_exists( 'Blocksy\Plugin' ),
                'description'            => 'Customize <strong>Blocksy</strong> emails with <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-blocksy',
                'categories'             => [ 'marketing' ],
            ],
            'YayMailAddonForWoodmart'                      => [
                'plugin_name'            => 'YayMail Addon For WoodMart',
                'template_ids'           => self::get_template_ids(
                    [
                        'XTS_Email_Wishlist_Back_In_Stock',
                        'XTS_Email_Wishlist_On_Sale_Products',
                        'XTS_Email_Wishlist_Promotional',
                    ]
                ),
                'slug_name'              => 'woodmart',
                'link_upgrade'           => 'https://yaycommerce.com/yaymail-addons/yaymail-addon-for-woodmart/',
                'is_3rd_party_installed' => defined( 'WOODMART_THEME_DIR' ),
                'description'            => 'Customize <strong>WoodMart</strong> emails with <strong>YayMail</strong> email builder.',
                'plugin_slug'            => 'yaymail-addon-for-woodmart',
                'categories'             => [ 'marketing' ],
            ],
        ];

        $data = apply_filters( 'yaymail_addon_supported_plugins', $data );

        foreach ( array_keys( $data ) as $namespace ) {
            $data[ $namespace ]['installation_status']              = [];
            $data[ $namespace ]['installation_status']['is_active'] = function_exists( $namespace . '\init' ) || function_exists( $namespace . '\addon_init' );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        foreach ( $data as $namespace => $addon ) {
            $data[ $namespace ]['installation_status']['is_installed'] = false;
            if ( isset( $addon['plugin_slug'] ) ) {
                $plugin_status = \install_plugin_install_status(
                    [
                        'slug'    => $addon['plugin_slug'],
                        'version' => '',
                    ]
                );
                $data[ $namespace ]['installation_status']['is_installed'] = $plugin_status['file'] !== false;
                $data[ $namespace ]['installation_status']['plugin_file']  = $plugin_status['file'];
            }
        }
        return $data;
    }

    public static function get_3rd_party_addons() {
        return array_filter(
            self::get_all(),
            function( $addon ) {
                return isset( $addon['is_3rd_party_installed'] );
            }
        );
    }

    public static function get_template_ids( array $template_names ): array {
        if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Emails' ) ) {
            return [];
        }

        return array_filter(
            array_map(
                function ( $template_name ) {
                    return \WC_Emails::instance()->get_emails()[ $template_name ]->id ?? null;
                },
                $template_names
            )
        );
    }

    public static function get_follow_up_email_ids() {
        if ( ! is_callable( 'fue_get_emails' ) ) {
            return [];
        }

        $follow_ups_emails = \fue_get_emails( 'any', [ 'fue-active' ] );
        $follow_ups_emails = array_filter(
            $follow_ups_emails,
            function ( $email ) {
                return $email->status === 'fue-active';
            }
        );

        if ( empty( $follow_ups_emails ) ) {
            return [];
        }

        return array_map(
            function ( $fue_email ) {
                return 'follow_up_email_' . $fue_email->id;
            },
            $follow_ups_emails
        );
    }

    public static function get_automatewoo_template_ids() {
        if ( ! class_exists( 'AutomateWoo\Workflow_Query' ) ) {
            return [];
        }

        $query = new \AutomateWoo\Workflow_Query();
        $query->set_return( 'ids' );
        $ids = $query->get_results();

        if ( empty( $ids ) ) {
            return [];
        }

        $workflows = [];

        foreach ( $ids as $id ) {
            $workflow = \AutomateWoo\Workflows\Factory::get( $id );
            if ( $workflow ) {
                $workflows[] = $workflow;
            }
        }

        $template_ids = [];

        foreach ( $workflows as $workflow ) {
            $actions = $workflow->get_actions();
            foreach ( $actions as $action_index => $action ) {
                $workflow_id = $workflow->get_id();
                $name        = 'AutomateWoo_' . $workflow_id;
                if ( $action_index !== null ) {
                    $name .= '_action_' . $action_index;
                }
                $template_ids[] = $name;
            }
        }

        return $template_ids;
    }

    public static function get_shopmagic_template_ids() {
        if ( ! class_exists( 'YayMailAddonSMFW\Emails\EmailsCreation' ) ) {
            return [];
        }

        $emails = \YayMailAddonSMFW\Emails\EmailsCreation::get_instance()->get_emails();

        if ( empty( $emails ) ) {
            return [];
        }

        return array_filter(
            array_map(
                function ( $email ) {
                    return $email->get_id();
                },
                $emails
            )
        );
    }

    public static function get_wcfmvm_template_ids() {
        if ( ! function_exists( 'get_wcfmvm_emails' ) ) {
            return [];
        }
        $emails = get_wcfmvm_emails();
        return array_filter( array_keys( $emails ) );
    }

    public static function get_wc_cart_abandonment_recovery_template_ids() {
        if ( ! class_exists( 'YayMailAddonWcCartAbandonmentRecovery\EmailCreation' ) ) {
            return [];
        }
        $emails = \YayMailAddonWcCartAbandonmentRecovery\EmailCreation::get_instance()->get_emails();

        if ( empty( $emails ) ) {
            return [];
        }

        return array_filter(
            array_map(
                function ( $email ) {
                    return $email->get_id();
                },
                $emails
            )
        );
    }
}
