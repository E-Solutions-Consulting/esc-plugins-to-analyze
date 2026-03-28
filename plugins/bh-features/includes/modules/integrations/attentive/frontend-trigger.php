<?php
/**
 * Attentive Integration - Frontend Trigger
 * 
 * Loads Attentive SDK and triggers sign-up unit on footer form submission.
 * All settings are dynamic and configurable from Admin page.
 * 
 * @package BH_Features
 * @subpackage Integrations/Attentive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BH_Attentive_Frontend_Trigger {

    /**
     * Cached settings
     */
    private static $settings = null;

    /**
     * Get settings
     */
    private static function get_settings() {
        if ( self::$settings === null ) {
            self::$settings = BH_Attentive_Config::get_settings();
        }
        return self::$settings;
    }

    /**
     * Initialize frontend trigger
     */
    public static function init() {
        $settings = self::get_settings();
        
        // Only load if integration is enabled
        if ( $settings['enabled'] !== 'yes' ) {
            return;
        }
        
        // Only load if frontend trigger is enabled
        if ( $settings['frontend_trigger_enabled'] !== 'yes' ) {
            return;
        }

        // Load Attentive SDK in header
        add_action( 'wp_head', array( __CLASS__, 'load_attentive_sdk' ), 5 );
        
        // Load footer trigger script
        add_action( 'wp_footer', array( __CLASS__, 'load_footer_trigger_script' ), 99 );
    }

    /**
     * Load Attentive SDK/Tag in header
     */
    public static function load_attentive_sdk() {
        $settings = self::get_settings();
        
        // Check if we should load on this page
        if ( ! self::should_load_on_current_page( $settings ) ) {
            return;
        }
        
        $account = ! empty( $settings['attentive_account'] ) ? $settings['attentive_account'] : 'hellowellness';
        ?>
        <!-- Attentive SDK - BH Features -->
        <script>
        (function(w,d,t,s,c){
            w[c]=w[c]||{};
            w[c].trigger=w[c].trigger||function(){(w[c].q=w[c].q||[]).push(arguments)};
            var e=d.createElement(t);
            e.async=1;
            e.src=s;
            var x=d.getElementsByTagName(t)[0];
            x.parentNode.insertBefore(e,x);
        })(window,document,'script','https://cdn.attn.tv/<?php echo esc_js( $account ); ?>/dtag.js','__attentive');
        </script>
        <?php
    }
    
    /**
     * Check if trigger should load on current page
     */
    private static function should_load_on_current_page( $settings ) {
        $trigger_pages = $settings['trigger_pages'] ?? 'all';
        
        // If set to all pages, always load
        if ( $trigger_pages === 'all' ) {
            return true;
        }
        
        // If set to blog_only, check if current page is a blog page
        if ( $trigger_pages === 'blog_only' ) {
            // Check various blog-related conditions
            return is_home() || is_single() || is_category() || is_tag() || is_archive() || is_search();
        }
        
        return true;
    }

    /**
     * Load footer form trigger script
     */
    public static function load_footer_trigger_script() {
        $settings = self::get_settings();
        
        // Check if we should load on this page
        if ( ! self::should_load_on_current_page( $settings ) ) {
            return;
        }
        
        // Get settings with fallbacks
        $form_selector = ! empty( $settings['footer_form_selector'] ) 
            ? $settings['footer_form_selector'] 
            : '#ap3w-embeddable-form-68ae26e106cf18412df4d314';
            
        $mobile_creative = ! empty( $settings['mobile_creative_id'] ) 
            ? $settings['mobile_creative_id'] 
            : '1257205';
            
        $desktop_creative = ! empty( $settings['desktop_creative_id'] ) 
            ? $settings['desktop_creative_id'] 
            : '1257193';
        ?>
        <!-- Attentive Footer Trigger - BH Features -->
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var formSelector = "<?php echo esc_js( $form_selector ); ?>";
            var form = document.querySelector(formSelector);
            
            if (!form) {
                // Try alternative selector
                form = document.querySelector(".subscribe_form form");
            }
            
            if (!form) {
                console.log("BH Attentive: Footer form not found. Selector:", formSelector);
                return;
            }

            form.addEventListener("submit", function(e) {
                e.preventDefault();
                
                var emailInput = form.querySelector("input[type='email']");
                if (!emailInput) return;
                
                var email = emailInput.value.trim();
                if (!email || !email.includes("@")) {
                    console.warn("BH Attentive: Invalid email:", email);
                    return;
                }

                // Show thank you message immediately
                // Extract form ID from selector and find containers
                var formId = formSelector.replace('#ap3w-embeddable-form-', '');
                var formContainer = document.getElementById(formId + "-form");
                var thankYouContainer = document.getElementById(formId + "-thank-you");
                
                if (formContainer) formContainer.style.display = "none";
                if (thankYouContainer) thankYouContainer.style.display = "flex";

                // Determine creative ID based on screen width (mobile vs desktop)
                var creativeId = (window.innerWidth >= 760) 
                    ? "<?php echo esc_js( $desktop_creative ); ?>" 
                    : "<?php echo esc_js( $mobile_creative ); ?>";

                // Trigger Attentive Sign-up Unit with retry
                function tryTrigger(attempts) {
                    if (window.__attentive && typeof window.__attentive.trigger === "function") {
                        window.__attentive.trigger(null, null, email, creativeId);
                        console.log("BH Attentive: Signup triggered for:", email, "Creative:", creativeId);
                    } else if (attempts > 0) {
                        setTimeout(function() { tryTrigger(attempts - 1); }, 500);
                    } else {
                        console.warn("BH Attentive: SDK not available after retries.");
                    }
                }
                
                tryTrigger(10);
            });
            
            console.log("BH Attentive: Footer trigger initialized.");
        });
        </script>
        <?php
    }
}

// Initialize
add_action( 'init', array( 'BH_Attentive_Frontend_Trigger', 'init' ) );
