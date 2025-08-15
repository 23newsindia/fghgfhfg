<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('newsletter_subscribe', 'wns_render_subscription_form');

function wns_render_subscription_form($atts) {
    // Don't render during REST API requests or admin AJAX calls
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return '';
    }
    
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return '';
    }
    
    // Don't render in admin context unless specifically requested
    if (is_admin() && !wp_doing_ajax()) {
        return '';
    }

    $atts = shortcode_atts(array(
        'show_unsubscribe' => false,
        'style' => 'default', // default, compact, card-style
        'layout' => 'inline', // inline, stacked
        'placeholder' => __('Enter your email address', 'wp-newsletter-subscription'),
        'button_text' => __('Subscribe', 'wp-newsletter-subscription'),
        'show_icon' => false,
    ), $atts, 'newsletter_subscribe');

    // Sanitize attributes
    $atts['style'] = sanitize_html_class($atts['style']);
    $atts['layout'] = in_array($atts['layout'], ['inline', 'stacked']) ? $atts['layout'] : 'inline';
    $atts['placeholder'] = sanitize_text_field($atts['placeholder']);
    $atts['button_text'] = sanitize_text_field($atts['button_text']);
    $atts['show_icon'] = (bool) $atts['show_icon'];

    ob_start();

    $message_displayed = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wns_subscribe_email'])) {
        $email = sanitize_email($_POST['wns_subscribe_email']);
        $result = wns_handle_subscription($email);

        if ($result === true) {
            echo '<div class="wns-message success">';
            echo '<strong>' . esc_html__('Success!', 'wp-newsletter-subscription') . '</strong> ';
            echo esc_html__('Thank you for subscribing! You\'ll receive our latest updates.', 'wp-newsletter-subscription');
            echo '</div>';
            $message_displayed = true;
        } else {
            echo '<div class="wns-message error">';
            echo '<strong>' . esc_html__('Error:', 'wp-newsletter-subscription') . '</strong> ';
            echo esc_html($result);
            echo '</div>';
        }
    }

    // Build CSS classes
    $form_classes = array('wns-subscribe-form');
    if ($atts['style'] !== 'default') {
        $form_classes[] = sanitize_html_class($atts['style']);
    }
    if ($atts['show_icon']) {
        $form_classes[] = 'form-icon';
    }

    $form_class = implode(' ', $form_classes);
    $layout_class = $atts['layout'] === 'inline' ? 'wns-subscribe-form-inline' : 'wns-subscribe-form-stacked';

    ?>
    <div class="<?php echo esc_attr($form_class); ?>">
        <?php if (!$message_displayed): ?>
            <form method="post" class="<?php echo esc_attr($layout_class); ?>" id="wns-subscribe-form">
                <?php wp_nonce_field('wns_subscribe_nonce', 'wns_nonce'); ?>
                
                <div class="wns-input-wrapper" style="flex: 1;">
                    <input 
                        type="email" 
                        name="wns_subscribe_email" 
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                        required 
                        maxlength="254"
                        aria-label="<?php esc_attr_e('Email address', 'wp-newsletter-subscription'); ?>"
                        autocomplete="email"
                    />
                </div>
                
                <button 
                    type="submit" 
                    class="wns-subscribe-btn"
                    aria-label="<?php echo esc_attr($atts['button_text']); ?>"
                >
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
            </form>
            
            <p class="wns-email-notice" style="font-size: 12px; color: #666; margin-top: 10px; text-align: center;">
                <?php esc_html_e('Only Gmail, Hotmail, and Yahoo email addresses are accepted.', 'wp-newsletter-subscription'); ?>
            </p>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('wns-subscribe-form');
                if (form) {
                    const button = form.querySelector('.wns-subscribe-btn');
                    const emailInput = form.querySelector('input[type="email"]');
                    
                    // Add real-time email validation
                    emailInput.addEventListener('input', function() {
                        const email = this.value.toLowerCase();
                        const allowedDomains = [
                            'gmail.com', 'googlemail.com',
                            'hotmail.com', 'hotmail.co.uk', 'hotmail.fr', 'hotmail.de', 'hotmail.it', 'hotmail.es', 'hotmail.ca', 'hotmail.com.au',
                            'outlook.com', 'outlook.co.uk', 'outlook.fr', 'outlook.de', 'outlook.it', 'outlook.es', 'outlook.ca', 'outlook.com.au',
                            'live.com', 'live.co.uk', 'live.fr', 'live.de', 'live.it', 'live.ca', 'msn.com',
                            'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.it', 'yahoo.es', 'yahoo.ca', 'yahoo.com.au', 'yahoo.co.in', 'yahoo.com.br',
                            'ymail.com', 'rocketmail.com'
                        ];
                        
                        if (email.includes('@')) {
                            const domain = email.split('@')[1];
                            if (domain && !allowedDomains.includes(domain)) {
                                this.setCustomValidity('<?php echo esc_js(__('Please use a Gmail, Hotmail, or Yahoo email address.', 'wp-newsletter-subscription')); ?>');
                            } else {
                                this.setCustomValidity('');
                            }
                        }
                    });
                    
                    form.addEventListener('submit', function() {
                        if (button) {
                            button.classList.add('loading');
                            button.textContent = '<?php echo esc_js(__('Subscribing...', 'wp-newsletter-subscription')); ?>';
                            button.disabled = true;
                        }
                    });
                }
            });
            </script>
        <?php else: ?>
            <div style="text-align: center; padding: 20px 0;">
                <p style="margin: 0; color: var(--meta-fcolor, #666);">
                    <?php esc_html_e('Want to subscribe again?', 'wp-newsletter-subscription'); ?>
                    <a href="<?php echo esc_url(remove_query_arg(array('wns_subscribe_email', 'wns_nonce'))); ?>" 
                       style="color: var(--g-color, #007cba); text-decoration: underline;">
                        <?php esc_html_e('Click here', 'wp-newsletter-subscription'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function wns_handle_subscription($email) {
    // Don't process during REST API requests
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    // Verify nonce for security
    if (!wp_verify_nonce($_POST['wns_nonce'], 'wns_subscribe_nonce')) {
        return __('Security check failed. Please try again.', 'wp-newsletter-subscription');
    }

    // Rate limiting check
    if (!wns_check_rate_limit()) {
        return __('Too many subscription attempts. Please try again later.', 'wp-newsletter-subscription');
    }

    global $wpdb;

    // Enhanced email validation with domain restriction
    if (!wns_validate_email_deliverability($email)) {
        return __('Please enter a valid Gmail, Hotmail, or Yahoo email address.', 'wp-newsletter-subscription');
    }

    // Ensure tables exist
    wns_ensure_tables_exist();

    $table_name = WNS_TABLE_SUBSCRIBERS;

    // Check if already exists
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `email` = %s", $email));
    
    if ($wpdb->last_error) {
        error_log('WNS Plugin Error: ' . $wpdb->last_error);
        return __('A database error occurred. Please contact the administrator.', 'wp-newsletter-subscription');
    }
    
    if ($exists > 0) {
        return __('This email is already subscribed to our newsletter.', 'wp-newsletter-subscription');
    }

    $enable_verification = get_option('wns_enable_verification', false);
    $verified = $enable_verification ? 0 : 1;

    $inserted = $wpdb->insert($table_name, array(
        'email'     => $email,
        'verified'  => $verified
    ), array('%s', '%d'));

    if (!$inserted) {
        error_log('WNS Plugin Error: Failed to insert subscriber - ' . $wpdb->last_error);
        return __('An error occurred while processing your subscription. Please try again later.', 'wp-newsletter-subscription');
    }

    // Log subscription activity
    wns_log_email_activity($email, 'subscribed', 'New subscription via form');

    if ($enable_verification) {
        $sent = wns_send_verification_email($email);
        if (!$sent) {
            return __('Subscription successful, but we couldn\'t send the verification email. Please contact us.', 'wp-newsletter-subscription');
        }
        return __('A verification email has been sent to your inbox. Please check your email and click the verification link.', 'wp-newsletter-subscription');
    } else {
        // Send welcome email for auto-verified subscribers
        wns_send_welcome_email($email);
    }

    return true;
}

function wns_send_welcome_email($email) {
    require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';
    
    $subject = __('Welcome to Our Newsletter!', 'wp-newsletter-subscription');
    $email_content = WNS_Email_Templates::get_welcome_template($email);
    
    // Add unsubscribe link
    $unsubscribe_link = wns_get_unsubscribe_link($email);
    $email_content = str_replace('{unsubscribe_link}', $unsubscribe_link, $email_content);
    
    $headers = wns_get_standard_email_headers($email);
    
    wp_mail($email, $subject, $email_content, $headers);
}

function wns_check_rate_limit() {
    $ip = wns_get_client_ip();
    $transient_key = 'wns_rate_limit_' . md5($ip);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, HOUR_IN_SECONDS);
        return true;
    }
    
    if ($attempts >= 5) { // Max 5 attempts per hour
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
    return true;
}