<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Bh_Features_Emails {
    private $common;
    const BATCH_SIZE = 100;
    const TRANSIENT_KEY = 'bh_reminders_processing';

    public function __construct($common) {
        $this->common = $common;
        add_filter('woocommerce_email_classes', function($emails) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/wc-subscription-reminder-email-2.php';
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/emails/wc-subscription-reminder-email-7.php';
            $emails['WC_Subscription_Reminder_Email_2'] = new WC_Subscription_Reminder_Email_2();
            $emails['WC_Subscription_Reminder_Email_7'] = new WC_Subscription_Reminder_Email_7();
            return $emails;
        });
        add_filter('woocommerce_locate_template', function($template, $template_name, $template_path) {
            $plugin_path = trailingslashit(dirname(__DIR__, 1)) . 'templates/';
            if (file_exists($plugin_path . $template_name)) {
                return $plugin_path . $template_name;
            }
            
            return $template;
        }, 10, 3);
        $this->init();
    }

    public function init() {
        add_action('admin_init', [$this, 'send_subscription_reminders']);
        
        add_action('init', [$this, 'schedule_crons']);
        add_action('bh_cron_reminder_7_days', [$this, 'handle_cron_7_days']);
        add_action('bh_cron_reminder_2_days', [$this, 'handle_cron_2_days']);
    }

    /**
     * Schedule staggered cron events
     */
    public function schedule_crons() {
        if (!wp_next_scheduled('bh_cron_reminder_2_days')) {
            wp_schedule_event(strtotime('05:30:00 UTC'), 'daily', 'bh_cron_reminder_2_days');
        }
        if (!wp_next_scheduled('bh_cron_reminder_7_days')) {
            wp_schedule_event(strtotime('06:00:00 UTC'), 'daily', 'bh_cron_reminder_7_days');
        }
    }

    /**
     * Run 2-day reminders
     */
    public function handle_cron_2_days() {
        $this->process_subscription_reminders(2);
    }

    /**
     * Run 7-day reminders
     */
    public function handle_cron_7_days() {
        $this->process_subscription_reminders(7);
    }
    /**
     * Manual 
     * */
    public function send_subscription_reminders() {
        if (!isset($_GET['bh_action']) || $_GET['bh_action'] !== 'send_subscription_reminders') {
            return;
        }

        if (!isset($_GET['days']) || !in_array($_GET['days'], ['2', '7'])) {
            die('Invalid days parameter. Use 2 or 7.');
        }

        $days = intval($_GET['days']);
        $this->process_subscription_reminders($days);
    }

    public function process_subscription_reminders($days) {
        try {
            if (php_sapi_name() !== 'cli' && !wp_doing_cron()) {
                if (ob_get_level()) ob_end_clean();
                header('Content-Type: text/html; charset=utf-8');
                header('Cache-Control: no-cache');
                ini_set('output_buffering', 'off');
                ini_set('zlib.output_compression', false);
            }
            
            echo "Starting reminder process for $days days before payment\n";
            echo "==========================================\n";
            flush();

            $class_name = 'WC_Subscription_Reminder_Email_' . $days;
            $file_path = plugin_dir_path(dirname(__FILE__)) . 'includes/emails/wc-subscription-reminder-email-' . $days . '.php';
            
            if (!class_exists($class_name)) {
                require_once $file_path;
            }

            $email = WC()->mailer()->emails[$class_name];
            if (!$email || !$email->is_enabled())
                die('Email class not found or not enabled');

            $batch_size = isset($_GET['batch']) ? intval($_GET['batch']) : 50;
            $sleep_between_batches = 1; // segundos
            $emails_per_second = 8; // conservador (80% del límite)
            
            $total_processed = 0;
            $total_success = 0;
            $total_errors = 0;
            $batch_count = 0;
            $bh_log = [];

            $emails_sent_this_second = 0;
            $current_second = time();

            echo "Configuration: Batch size = $batch_size, Emails/sec = $emails_per_second\n";
            flush();

            do {
                $batch_count++;
                $offset = ($batch_count - 1) * $batch_size;
                
                echo "<h5>Processing batch #$batch_count (offset: $offset)...</h5>";
                echo '<ol>';
                flush();
                
                $subscriptions = $this->get_due_subscriptions($days, $batch_size, $offset);
                
                if (empty($subscriptions)) {
                    echo "No more subscriptions found.\n";
                    flush();
                    break;
                }

                $batch_success = 0;
                $batch_errors = 0;
                
                foreach ($subscriptions as $subscription) {
                    echo '<li>';
                    $now = time();
                    if ($now !== $current_second) {
                        $current_second = $now;
                        $emails_sent_this_second = 0;
                    }
                    
                    if ($emails_sent_this_second >= $emails_per_second) {
                        echo "⏳ Rate limit control: waiting 1 second...\n";
                        flush();
                        sleep(1);
                        $current_second = time();
                        $emails_sent_this_second = 0;
                    }
                    
                    $max_retries = 2;
                    $retry_count = 0;
                    $sent_successfully = false;
                    
                    while ($retry_count <= $max_retries && !$sent_successfully) {
                        try {
                            $subscription_id = $subscription->id;
                            $start_time = microtime(true);

                            echo "#$subscription_id: Sending to " . $subscription->billing_email . "... ";
                            flush();

                            $return = $email->trigger($subscription_id);
                            // bh_plugins_log([$subscription->billing_email, $return], 'bh_plugins_reminder_email_' . $days . '_resend_response');
                            $process_time = round(microtime(true) - $start_time, 4);
                            echo "✓ Sent in {$process_time}s\n";
                            flush();
                            
                            $batch_success++;
                            $emails_sent_this_second++;
                            $sent_successfully = true;
                            
                            usleep(125000); // 125ms = 8 emails/segundo
                            
                        } catch (Exception $e) {
                            $retry_count++;
                            $error_message = $e->getMessage();
                            
                            if (strpos($error_message, '429') !== false) {
                                echo "🚨 Rate limit exceeded, waiting 5 seconds...\n";
                                flush();
                                sleep(5);
                                $current_second = time();
                                $emails_sent_this_second = 0;
                                
                            } elseif (strpos($error_message, '403') !== false) {
                                echo "❌ ERROR: Invalid Resend API Key - STOPPING PROCESS\n";
                                flush();
                                die('Invalid Resend API Key');
                                
                            } elseif (strpos($error_message, '422') !== false) {
                                echo "⚠️ Invalid email, skipping...\n";
                                flush();
                                break;
                                
                            } elseif (strpos($error_message, '500') !== false && $retry_count <= $max_retries) {
                                echo "🔧 Server error, retry $retry_count/$max_retries in 2s...\n";
                                flush();
                                sleep(2);
                                
                            } else {
                                echo "❌ Unrecoverable error: " . $error_message . "\n";
                                flush();
                                $batch_errors++;
                                break;
                            }
                            
                            if ($retry_count > $max_retries) {
                                echo "❌ Max retries reached, skipping email...\n";
                                flush();
                                $batch_errors++;
                            }
                        }
                    }
                    
                    echo '</li>';
                    flush();
                }
                
                echo '</ol>';
                flush();
                
                $total_processed += count($subscriptions);
                $total_success += $batch_success;
                $total_errors += $batch_errors;
                
                echo "Batch #$batch_count completed: " . count($subscriptions) . " processed, $batch_success success, $batch_errors errors\n";
                echo "Progress: $total_processed total emails processed\n";
                flush();
                
                if (!empty($subscriptions) && count($subscriptions) === $batch_size) {
                    echo "Pausing $sleep_between_batches seconds before next batch...\n";
                    flush();
                    sleep($sleep_between_batches);
                }
                
            } while (!empty($subscriptions) && count($subscriptions) === $batch_size);
            
            echo "\n==========================================\n";
            echo "PROCESS COMPLETED\n";
            echo "Total batches: $batch_count\n";
            echo "Total subscriptions: $total_processed\n";
            echo "Successfully sent: $total_success\n";
            echo "Errors: $total_errors\n";
            echo "==========================================\n";
            flush();

            $this->save_final_log($days, [
                'total_batches' => $batch_count,
                'total_processed' => $total_processed,
                'total_success' => $total_success,
                'total_errors' => $total_errors,
                'execution_time' => round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2),
                'log' => $bh_log
            ]);

            echo "Reminder process complete. Check logs for details.\n";
            
            $total_time = round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2);
            echo "Total execution time: {$total_time}s\n";
            echo "Average: " . round($total_processed / $total_time, 2) . " emails/second\n";
            
            die('Process completed successfully.');

        } catch (\Throwable $th) {
            echo "Error: " . $th->getMessage() . "\n";
            flush();
        }
    }

    private function get_due_subscriptions($days, $limit = 100, $offset = 0) {
        global $wpdb;
        $target_date        =   date('Y-m-d', strtotime("+{$days} days"));
        $state_placeholders =   implode(',', array_fill(0, count($this->common->restricted_states), '%s'));

        $query = $wpdb->prepare(
            "SELECT o.id, o.billing_email, oa.state, oa.address_type
            FROM {$wpdb->prefix}wc_orders o 
            INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id 
            INNER JOIN {$wpdb->prefix}wc_order_addresses oa ON oa.order_id = o.id 
            WHERE o.type = 'shop_subscription' 
            AND o.status = 'wc-active' 
            AND om.meta_key = '_schedule_next_payment' 
            AND om.meta_value NOT IN ('0', '') 
            AND oa.state   NOT IN ($state_placeholders) 
            AND oa.address_type = 'shipping' 
            AND DATE(om.meta_value) = %s
            ORDER BY o.id ASC
            LIMIT %d OFFSET %d",
            array_merge($this->common->restricted_states, [$target_date, $limit, $offset])
        );
        // _print('Query: ' . $query);
        $subscriptions = $wpdb->get_results($query);
        return $subscriptions;
    }

    private function save_final_log($days, $summary) {
        $logger = wc_get_logger();
        $logger->info('Reminder process completed', array(
            'source' => 'bh-reminders',
            'days' => $days,
            'summary' => $summary
        ));
    }

}
