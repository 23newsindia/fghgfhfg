<?php
if (!defined('ABSPATH')) {
    exit;
}

// Override the theme's download form handler to implement email verification
add_action('wp_ajax_form_download_submit', 'wns_override_foxiz_form_download_submit', 5);
add_action('wp_ajax_nopriv_form_download_submit', 'wns_override_foxiz_form_download_submit', 5);

function wns_override_foxiz_form_download_submit() {
    // Get the email from POST data
    $email = isset($_POST['EMAIL']) ? sanitize_email($_POST['EMAIL']) : '';
    $post_id = isset($_POST['postId']) ? sanitize_text_field($_POST['postId']) : '';
    $block_id = isset($_POST['blockId']) ? sanitize_text_field($_POST['blockId']) : '';
    
    // Validate email exists
    if (empty($email)) {
        wp_send_json([
            'success' => false,
            'message' => esc_html__('Please enter a valid email address.', 'wp-newsletter-subscription'),
        ]);
    }
    
    // Validate email format
    if (!is_email($email)) {
        wp_send_json([
            'success' => false,
            'message' => esc_html__('Please enter a valid email address.', 'wp-newsletter-subscription'),
        ]);
    }
    
    // Validate email domain (only allow Gmail, Hotmail, Yahoo)
    if (!wns_is_allowed_email_domain($email)) {
        wp_send_json([
            'success' => false,
            'message' => esc_html__('Only Gmail, Hotmail, and Yahoo email addresses are allowed for downloads.', 'wp-newsletter-subscription'),
        ]);
    }
    
    // Check for disposable emails
    if (wns_is_disposable_email($email)) {
        wp_send_json([
            'success' => false,
            'message' => esc_html__('Temporary email addresses are not allowed.', 'wp-newsletter-subscription'),
        ]);
    }
    
    // Get the file URL from block data
    $data = foxiz_get_block_attributes('foxiz-elements/download', $post_id, $block_id);
    
    if (empty($data['file'])) {
        wp_send_json([
            'success' => false,
            'message' => esc_html__('Sorry, File not found.', 'wp-newsletter-subscription'),
        ]);
    }
    
    $file_url = $data['file'];
    
    // Check if email verification is required for downloads
    $require_verification = get_option('wns_require_email_verification_for_downloads', false);
    
    // ALWAYS add subscriber to database first
    wns_add_or_update_subscriber($email);
    
    if ($require_verification) {
        // Check the skip verification setting
        $skip_verification_for_verified = get_option('wns_skip_verification_for_verified_users', true);
        
        // Check if user is already a verified subscriber
        $is_verified = wns_is_subscriber_verified($email);
        
        // FIXED LOGIC: If user is verified AND skip setting is enabled, provide direct download
        if ($is_verified && $skip_verification_for_verified) {
            // User is verified and skip verification is enabled - provide direct download link
            // NO EMAIL IS SENT - user gets instant access
            
            // Trigger the subscribe action for other integrations
            do_action('foxiz_subscribe');
            
            // Return the file for direct download
            $success_message = function_exists('foxiz_html__') ? 
                foxiz_html__('Your download will start in a few seconds, If your download does not start, please click here.', 'foxiz-core') :
                esc_html__('Your download will start in a few seconds, If your download does not start, please click here.', 'foxiz-core');
            
            // Log the instant download activity
            wns_log_email_activity($email, 'instant_download_verified_user', 'Verified user got instant download: ' . $file_url);
            
            wp_send_json([
                'success' => true,
                'file' => $file_url,
                'message' => $success_message,
                'verified_user' => true,
                'instant_download' => true
            ]);
        } else {
            // Either user is not verified OR skip verification is disabled - send verification email
            require_once WNS_PLUGIN_DIR . 'includes/download-handler.php';
            
            $token = wns_generate_download_token($email, $file_url, $post_id, $block_id, true);
            
            if ($token) {
                $email_sent = wns_send_download_verification_email($email, $token, $file_url);
                
                if ($email_sent) {
                    if ($is_verified && !$skip_verification_for_verified) {
                        // User is verified but strict mode is enabled
                        wp_send_json([
                            'success' => true,
                            'message' => esc_html__('Please check your email and click the verification link to download the file. Verification required for security.', 'wp-newsletter-subscription'),
                            'email_sent' => true,
                            'verification_required' => true,
                            'strict_mode' => true
                        ]);
                    } else {
                        // User is not verified
                        wp_send_json([
                            'success' => true,
                            'message' => esc_html__('Please check your email and click the verification link to download the file. We\'ve sent you a verification email.', 'wp-newsletter-subscription'),
                            'email_sent' => true,
                            'verification_required' => true
                        ]);
                    }
                } else {
                    wp_send_json([
                        'success' => false,
                        'message' => esc_html__('Failed to send verification email. Please try again.', 'wp-newsletter-subscription'),
                    ]);
                }
            } else {
                wp_send_json([
                    'success' => false,
                    'message' => esc_html__('Failed to generate verification token. Please try again.', 'wp-newsletter-subscription'),
                ]);
            }
        }
    } else {
        // If verification is not required, proceed with normal download
        // Trigger the subscribe action for other integrations
        do_action('foxiz_subscribe');
        
        // Return the file for direct download
        $success_message = function_exists('foxiz_html__') ? 
            foxiz_html__('Your download will start in a few seconds, If your download does not start, please click here.', 'foxiz-core') :
            esc_html__('Your download will start in a few seconds, If your download does not start, please click here.', 'foxiz-core');
        
        wp_send_json([
            'success' => true,
            'file' => $file_url,
            'message' => $success_message,
        ]);
    }
}

// Helper function to add or update subscriber with verification option
function wns_add_or_update_subscriber($email, $force_verify = false) {
    global $wpdb;
    
    // Ensure tables exist
    wns_ensure_tables_exist();
    
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    // Check if email already exists
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `email` = %s", $email));
    
    if ($exists == 0) {
        // Add new subscriber
        $auto_verify = get_option('wns_auto_verify_download_subscribers', true);
        $enable_verification = get_option('wns_enable_verification', false);
        
        // Determine verification status
        if ($force_verify) {
            $verified = 1; // Force verify
        } else {
            $verified = $auto_verify ? 1 : ($enable_verification ? 0 : 1);
        }
        
        $wpdb->insert($table_name, array(
            'email' => $email,
            'verified' => $verified
        ), array('%s', '%d'));
        
        // Send verification email if needed and not force verified
        if (!$force_verify && !$auto_verify && $enable_verification) {
            wns_send_verification_email($email);
        }
        
        // Send welcome email if enabled and verified
        $send_welcome = get_option('wns_send_welcome_to_download_subscribers', false);
        if ($send_welcome && $verified) {
            wns_send_welcome_email($email);
        }
        
        // Log activity
        $activity_type = $force_verify ? 'verified_via_download' : 'subscribed_via_download';
        wns_log_email_activity($email, $activity_type, 'Subscription via download form');
    } else if ($force_verify) {
        // Update existing subscriber to verified if force verify is requested
        $wpdb->update(
            $table_name,
            array('verified' => 1),
            array('email' => $email),
            array('%d'),
            array('%s')
        );
        
        wns_log_email_activity($email, 'verified_via_download', 'Email verified via download verification');
    }
}

// Helper function to check if subscriber is verified
function wns_is_subscriber_verified($email) {
    global $wpdb;
    
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    $verified = $wpdb->get_var($wpdb->prepare(
        "SELECT verified FROM `$table_name` WHERE `email` = %s",
        $email
    ));
    
    return $verified == 1;
}

// Hook into the theme's download form submission for newsletter integration
add_action('foxiz_subscribe', 'wns_handle_theme_download_subscription_enhanced');

function wns_handle_theme_download_subscription_enhanced() {
    // This function is called after the email validation above
    // It ensures the subscriber is added to our newsletter system
    $email = isset($_POST['EMAIL']) ? sanitize_email($_POST['EMAIL']) : '';
    
    if (empty($email) || !is_email($email)) {
        return;
    }
    
    // Additional validation already done in the override function above
    if (!wns_is_allowed_email_domain($email) || wns_is_disposable_email($email)) {
        return;
    }
    
    // Log the subscription activity
    wns_log_email_activity($email, 'download_form_processed', 'Download form processed successfully');
}

// Add admin notice about the new download verification feature
add_action('admin_notices', 'wns_download_verification_notice');

function wns_download_verification_notice() {
    // Only show on our plugin pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'wns-') === false) {
        return;
    }
    
    $require_verification = get_option('wns_require_email_verification_for_downloads', false);
    $skip_for_verified = get_option('wns_skip_verification_for_verified_users', true);
    
    if ($require_verification) {
        if ($skip_for_verified) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . esc_html__('Download Protection Active (Smart Mode):', 'wp-newsletter-subscription') . '</strong> ';
            echo esc_html__('New users must verify email, but verified subscribers get instant download links.', 'wp-newsletter-subscription');
            echo '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Download Protection Active (Strict Mode):', 'wp-newsletter-subscription') . '</strong> ';
            echo esc_html__('ALL users must verify email for every download, even verified subscribers.', 'wp-newsletter-subscription');
            echo '</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__('Download Protection Disabled:', 'wp-newsletter-subscription') . '</strong> ';
        echo esc_html__('Users can download files directly without email verification. Enable "Require Email Verification for Downloads" in settings to prevent spam.', 'wp-newsletter-subscription');
        echo '</p>';
        echo '</div>';
    }
}

// Add settings to control download verification behavior
add_action('admin_init', 'wns_register_download_verification_settings');

function wns_register_download_verification_settings() {
    register_setting('wns_settings_group', 'wns_require_email_verification_for_downloads', array(
        'type' => 'boolean',
        'default' => false,
    ));
    
    register_setting('wns_settings_group', 'wns_download_email_subject', array(
        'type' => 'string',
        'default' => __('Your Download Link is Ready!', 'wp-newsletter-subscription')
    ));
    
    register_setting('wns_settings_group', 'wns_download_email_body', array(
        'type' => 'string',
        'default' => __("Hi there,\n\nThank you for subscribing! Your download is ready.\n\nClick the link below to download your file:\n{download_link}\n\nThis link will expire in 24 hours for security reasons.\n\nBest regards,\nThe Team", 'wp-newsletter-subscription')
    ));
    
    // NEW SETTING: Skip verification for verified users
    register_setting('wns_settings_group', 'wns_skip_verification_for_verified_users', array(
        'type' => 'boolean',
        'default' => true,
    ));
}

// Add success message display for download verification
add_action('wp_footer', 'wns_display_download_verification_messages');

function wns_display_download_verification_messages() {
    if (isset($_GET['download_verified']) && $_GET['download_verified'] === 'success') {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create and show success message
            var message = document.createElement('div');
            message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px 20px; border-radius: 5px; z-index: 9999; font-family: Arial, sans-serif; box-shadow: 0 2px 10px rgba(0,0,0,0.1);';
            message.innerHTML = '✅ Email verified successfully! Check your inbox for the download link.';
            document.body.appendChild(message);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }, 5000);
        });
        </script>
        <?php
    }
}