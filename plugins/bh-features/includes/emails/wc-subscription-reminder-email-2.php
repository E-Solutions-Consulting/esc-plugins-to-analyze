<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

if (!class_exists('WC_Subscription_Reminder_Email_2')) :

class WC_Subscription_Reminder_Email_2 extends WC_Email {
    private $resend;
    public function __construct() {
        $this->id               =   'subscription_payment_reminder';
        $this->title            =   __('Subscription Renewal Reminder – 2 Days Before', 'woocommerce');
        $this->description      =   __('Sends an email reminder 2 days prior to the subscription renewal date.', 'woocommerce');
        $this->customer_email   =   true;

        $this->template_base    =   dirname(__DIR__, 2) . '/templates/';
        $this->template_html    =   'woocommerce/emails/customer-subscription-reminder-2.php';
        //$this->template_plain   =   'woocommerce/emails/plain/customer-subscription-reminder.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'emails/vendor/autoload.php';
        $this->resend = Resend::client('re_E2GJHLUW_AyUvUo9S9pHaMqD1xcV58eXE');

        add_filter('woocommerce_subscriptions_email_previewable', '__return_true');
        parent::__construct();
    }

    public function trigger($subscription_id) {
        if (!$this->is_enabled()) return false;

        // Handle preview mode - FORMA CORRECTA
        if (isset($GLOBALS['wc_subscriptions_email_preview'])) {
            $this->object = $GLOBALS['wc_subscriptions_email_preview']->get_subscription();
            $this->recipient = $GLOBALS['wc_subscriptions_email_preview']->get_recipient();
        } else {
            $this->object = wcs_get_subscription($subscription_id);
            $this->recipient = $this->object->get_billing_email();
        }

        if (!$this->object) return false;

        if($this->object->get_meta('_reminder_email_sent_2')) {
            return false; // Ya se envió este recordatorio
        }

        //$this->recipient    =   'mariana@alliahealth.co,alex.padilla@brellohealth.com,jaime@brellohealth.co,lindsey.bertolacci@alliahealth.co';
        //$this->recipient    =   'jaime+testresend@solutionswebonline.com';

        $next_payment   =   $this->object->get_date('next_payment');
        $formatted_date =   is_a($next_payment, 'WC_DateTime') ? 
                            $next_payment->date_i18n('F j, Y') : 
                            date_i18n('F j, Y', strtotime($next_payment));

        $this->placeholders = array(
            '{next_payment_date}' => $formatted_date,
            '{subscription_total}' => $this->object->get_formatted_order_total(),
            '{subscription_number}' => $this->object->get_order_number()
        );

        $this->subject = $this->format_string($this->get_option('subject'));
        $this->heading = $this->format_string($this->get_option('heading'));

        $this->object->update_meta_data('_reminder_email_sent_2', current_time('mysql'));
        $this->object->save();

        $email_content = $this->get_content();

        if (class_exists('YayMail')) {
            $email_content = $this->get_yaymail_content($this, $subscription_id);
        } else {
            $email_content = $this->style_inline($email_content);
        }

        $result = $this->resend->emails->send([
            'from' => 'Brello Health <info@brellohealth.com>',
            'to' => [$this->get_recipient()],
            'reply_to' => 'info@brellohealth.com',
            'subject' =>$this->get_subject(),
            'html' => $email_content
        ]);
        return $result;
        //return $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }
    public function get_default_subject() {
        return __('⏳ Reminder: Your Brello Plan Renews on {next_payment_date}', 'woocommerce-subscriptions');
    }

    public function get_default_heading() {
        return __('Upcoming renewal on {next_payment_date}', 'woocommerce-subscriptions');
    }

    public function get_content_html() {
        try {
            if (!is_a($this->object, 'WC_Subscription')) {
                throw new Exception('Invalid subscription object');
            }

            $args = array(
                'subscription'  => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'        => $this
            );

            $template = wc_get_template_html(
                $this->template_html,
                $args,
                '',
                $this->template_base
            );

            if (empty($template)) {
                throw new Exception('Template output is empty');
            }
            return $template;

        } catch (Exception $e) {
            return $this->generate_fallback_content();
        }
    }

    private function generate_fallback_content() {
        $subscription = $this->object;
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title><?php echo esc_html($this->get_heading()); ?></title>
        </head>
        <body>
            <?php
            do_action('woocommerce_email_header', $this->get_heading(), $this);                        
            $dates = array(
                'next_payment' => date_i18n(get_option('date_format'), strtotime('+2 days')),
                'last_payment' => date_i18n(get_option('date_format'), strtotime('-1 month')),
            );
            $email=$subscription->get_billing_email();

            $next_payment   =   $dates['next_payment'];
            $formatted_date =   is_a($next_payment, 'WC_DateTime') ? 
                                $next_payment->date_i18n('F j, Y') : 
                                date_i18n('F j, Y', strtotime($next_payment));
            $interval = 3;
            $custom_text = '';
                    if ( $interval == 1 ) {
                        $custom_text = '25 days';
                    } elseif ( $interval == 3 ) {
                        $custom_text = '10 weeks';
                    }
            ?>
            <p><?php printf(__('Hi %s,', 'woocommerce'), $subscription->get_billing_first_name()); ?></p>


            <p><?php printf(
                __('This is a quick reminder that your subscription (<strong>#%1$s</strong>) is scheduled to automatically renew on <strong>%2$s</strong> for <strong>%3$s</strong>. Your plan renews every <strong>%4$s</strong>, which ensures enough time for your provider’s review, order processing, and shipment—so you receive your next refill on time (pending provider approval).', 'woocommerce'),
                $subscription->get_order_number(),
                $formatted_date,
                wc_price(499.99),
                $custom_text
            ); ?></p>
            
            <p><?php printf(
                __('As part of the renewal process, you\'ll be prompted to complete a <strong>Follow-Up Intake Form</strong>. This allows your healthcare provider to review your information and determine if another %1$s plan is appropriate.', 'woocommerce'),
                '3-month'
            ); ?></p>

            <?php do_action('woocommerce_subscriptions_email_order_details', $subscription, $sent_to_admin, $plain_text, $email); ?>

            <p>If you’d like to make any changes before your renewal—such as updating your details, adjusting your plan, or pausing your subscription—you can:</p>
            <ul>
                <li><a href="https://shop.brellohealth.com/my-account">Log into your account</a> to review your current information and make updates as needed.</li>
                <li>Contact our support team at <a href="mailto:info@brellohealth.com">info@brellohealth.com</a> and we’ll be happy to assist.</li>
            </ul>

            <p>Thank you for choosing Brello for your care. 💜</p>
            <br>
            <p>Warmly,<br>
            <strong>The Brello Health Team</strong></p>


            <?php do_action('woocommerce_email_footer', $email); ?>


        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    public function init_form_fields() {
        parent::init_form_fields();
        
        $this->form_fields = array_merge($this->form_fields, array(
            'subject' => array(
                'title' => __('Subject', 'woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('Placeholders available: %s', 'woocommerce'), '{next_payment_date}, {subscription_total}, {subscription_number}'),
                'placeholder' => __('Your subscription will renew on {next_payment_date}', 'woocommerce'),
                'default' => $this->get_default_subject()
            ),
            'heading' => array(
                'title' => __('Header', 'woocommerce'),
                'type' => 'text',
                'default' => $this->get_heading()
            ),
            /*'reminder_days' => array(
                'title' => __('Days Before Next Payment', 'woocommerce'),
                'type' => 'number',
                'default' => $this->get_default_days_before_next_payment(),
                'description' => __('Set the number of days before the next subscription payment when this email should be triggered. You can update this value at any time to adjust when the reminder is sent.', 'woocommerce'),
            )*/
        ));
    }
}

endif;