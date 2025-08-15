<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'wns_add_main_menu_page');

function wns_add_main_menu_page() {
    // Add main menu page
    add_menu_page(
        __('WP Newsletter', 'wp-newsletter-subscription'),
        __('WP Newsletter', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-settings',
        'wns_render_settings_page',
        'dashicons-email-alt',
        30
    );

    // Add submenu pages
    add_submenu_page(
        'wns-settings',
        __('Newsletter Settings', 'wp-newsletter-subscription'),
        __('Settings', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-settings',
        'wns_render_settings_page'
    );
}

add_action('admin_init', 'wns_register_settings');

function wns_register_settings() {
    register_setting('wns_settings_group', 'wns_enable_verification', array(
        'type' => 'boolean',
        'default' => false,
    ));

    register_setting('wns_settings_group', 'wns_template_subscribe_subject', array(
        'type' => 'string',
        'default' => __('Welcome to Our Newsletter!', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_subscribe_body', array(
        'type' => 'string',
        'default' => __("Thank you for subscribing to our newsletter!\n\nClick the link below to verify your email:\n\n{verify_link}\n\nTo unsubscribe at any time: {unsubscribe_link}", 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_unsubscribe_subject', array(
        'type' => 'string',
        'default' => __('You Have Been Unsubscribed', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_unsubscribe_body', array(
        'type' => 'string',
        'default' => __("You have successfully unsubscribed from our newsletter. We're sorry to see you go!\n\nIf this was a mistake, you can resubscribe here: {unsubscribe_link}", 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_enable_new_post_notification', array(
        'type' => 'boolean',
        'default' => false,
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_subject', array(
        'type' => 'string',
        'default' => __('📚 New Tutorial: {post_title}', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_body', array(
        'type' => 'string',
        'default' => __("Hi there,\n\nWe've just published a new blog post that you might enjoy:\n\n{post_title}\n{post_excerpt}\n\nRead more: {post_url}\n\nThanks,\nThe Team\n\n{unsubscribe_link}", 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_email_batch_size', array(
        'type' => 'integer',
        'default' => 100,
    ));

    register_setting('wns_settings_group', 'wns_email_send_interval_minutes', array(
        'type' => 'integer',
        'default' => 5,
    ));

    register_setting('wns_settings_group', 'wns_unsubscribe_page_id', array(
        'type' => 'integer',
        'default' => 0,
    ));

    // Theme integration settings
    register_setting('wns_settings_group', 'wns_auto_verify_download_subscribers', array(
        'type' => 'boolean',
        'default' => true,
    ));

    register_setting('wns_settings_group', 'wns_send_welcome_to_download_subscribers', array(
        'type' => 'boolean',
        'default' => false,
    ));

    // Download verification settings
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

    // NEW SETTING: Skip verification for already verified users
    register_setting('wns_settings_group', 'wns_skip_verification_for_verified_users', array(
        'type' => 'boolean',
        'default' => true,
    ));

    // Social media settings for email templates
    register_setting('wns_settings_group', 'wns_facebook_url', array(
        'type' => 'string',
        'default' => '',
    ));

    register_setting('wns_settings_group', 'wns_twitter_url', array(
        'type' => 'string',
        'default' => '',
    ));

    register_setting('wns_settings_group', 'wns_instagram_url', array(
        'type' => 'string',
        'default' => '',
    ));

    register_setting('wns_settings_group', 'wns_linkedin_url', array(
        'type' => 'string',
        'default' => '',
    ));
}

function wns_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('WP Newsletter Subscription Settings', 'wp-newsletter-subscription'); ?></h1>
        
        <?php
        // Show current status of download verification
        $require_verification = get_option('wns_require_email_verification_for_downloads', false);
        $skip_for_verified = get_option('wns_skip_verification_for_verified_users', true);
        
        if ($require_verification) {
            if ($skip_for_verified) {
                echo '<div class="notice notice-success"><p><strong>✅ Download Email Verification is ENABLED</strong> - New users must verify email, but verified subscribers get instant download links.</p></div>';
            } else {
                echo '<div class="notice notice-info"><p><strong>🔒 Download Email Verification is ENABLED (Strict Mode)</strong> - ALL users must verify email for every download, even if already verified.</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning"><p><strong>⚠️ Download Email Verification is DISABLED</strong> - Users can download files directly without verification.</p></div>';
        }
        ?>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('wns_settings_group');
            do_settings_sections('wns_settings_group');
            ?>

            <!-- Download Protection Section - MOVED TO TOP -->
            <h2 style="color: #d63638;"><?php _e('🛡️ Download Protection (Anti-Spam)', 'wp-newsletter-subscription'); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Require Email Verification for Downloads', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wns_require_email_verification_for_downloads" value="1" <?php checked(1, get_option('wns_require_email_verification_for_downloads', false)); ?> />
                            <strong><?php _e('Enable Email Verification for Downloads', 'wp-newsletter-subscription'); ?></strong>
                        </label>
                        <p class="description" style="color: #d63638; font-weight: bold;">
                            <?php _e('🚨 IMPORTANT: When enabled, unverified users must verify their email before downloading files. This prevents spam and ensures valid email addresses.', 'wp-newsletter-subscription'); ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Skip Verification for Already Verified Users', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wns_skip_verification_for_verified_users" value="1" <?php checked(1, get_option('wns_skip_verification_for_verified_users', true)); ?> />
                            <strong><?php _e('Allow verified subscribers to download immediately', 'wp-newsletter-subscription'); ?></strong>
                        </label>
                        <p class="description">
                            <?php _e('✅ RECOMMENDED: When enabled, users who are already verified subscribers will receive download links immediately without additional verification. When disabled, ALL users must verify for every download.', 'wp-newsletter-subscription'); ?>
                        </p>
                        <div style="background: #e8f5e8; border: 1px solid #c3e6cb; padding: 15px; margin-top: 10px; border-radius: 4px;">
                            <strong>How this works:</strong>
                            <ul style="margin: 10px 0;">
                                <li>✅ <strong>Enabled (Recommended):</strong> Verified subscribers get instant download links, new users verify once</li>
                                <li>🔒 <strong>Disabled (Strict Mode):</strong> Everyone must verify email for every download, even verified subscribers</li>
                            </ul>
                            <p style="margin: 10px 0 0 0; color: #155724;">
                                <strong>💡 Tip:</strong> Keep this enabled for better user experience. Verified subscribers won't receive repeated verification emails.
                            </p>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Download Email Subject', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <input type="text" name="wns_download_email_subject" value="<?php echo esc_attr(get_option('wns_download_email_subject', __('Your Download Link is Ready!', 'wp-newsletter-subscription'))); ?>" class="regular-text" />
                        <p class="description"><?php _e('Subject line for emails containing download links.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Download Email Message', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <textarea name="wns_download_email_body" rows="6" class="large-text"><?php echo esc_textarea(get_option('wns_download_email_body', __("Hi there,\n\nThank you for subscribing! Your download is ready.\n\nClick the link below to download your file:\n{download_link}\n\nThis link will expire in 24 hours for security reasons.\n\nBest regards,\nThe Team", 'wp-newsletter-subscription'))); ?></textarea>
                        <p class="description"><?php _e('Use {download_link} placeholder for the actual download link. HTML is allowed.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Email Verification Section -->
            <h2><?php _e('Email Verification', 'wp-newsletter-subscription'); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Enable Email Verification', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label><input type="checkbox" name="wns_enable_verification" value="1" <?php checked(1, get_option('wns_enable_verification', false)); ?> /> <?php _e('Verify new subscriptions via email.', 'wp-newsletter-subscription'); ?></label>
                    </td>
                </tr>
            </table>

            <!-- Email Templates Section -->
            <h2><?php _e('Email Templates', 'wp-newsletter-subscription'); ?></h2>
            <p class="description"><?php _e('Note: All emails now use professional HTML templates automatically. The content below will be wrapped in a beautiful, responsive design.', 'wp-newsletter-subscription'); ?></p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Subscribe Email Subject', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="text" name="wns_template_subscribe_subject" value="<?php echo esc_attr(get_option('wns_template_subscribe_subject', __('Welcome to Our Newsletter!', 'wp-newsletter-subscription'))); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Unsubscribe Email Subject', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="text" name="wns_template_unsubscribe_subject" value="<?php echo esc_attr(get_option('wns_template_unsubscribe_subject', __('You Have Been Unsubscribed', 'wp-newsletter-subscription'))); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <!-- New Post Notifications Section -->
            <h2><?php _e('New Post Notifications', 'wp-newsletter-subscription'); ?></h2>
            <p class="description" style="background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin-bottom: 20px;">
                <strong>💡 New Feature:</strong> You can now control newsletter sending on a per-post basis! When editing a post, look for the "📧 Newsletter Sending Options" meta box in the sidebar to:
                <br>• Enable/disable auto-sending for that specific post
                <br>• Choose to send to all subscribers or selected subscribers only
                <br>• Send newsletters immediately with the "Send Now" button
            </p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Enable New Post Notifications', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label><input type="checkbox" name="wns_enable_new_post_notification" value="1" <?php checked(1, get_option('wns_enable_new_post_notification', false)); ?> /> <?php _e('Send email to all subscribers when a new post is published.', 'wp-newsletter-subscription'); ?></label>
                        <p class="description">
                            <?php _e('This is the default setting for new posts. You can override this setting for individual posts using the meta box in the post editor.', 'wp-newsletter-subscription'); ?>
                            <br><?php _e('New post emails will include the featured image, title, excerpt, and a beautiful "Read More" button.', 'wp-newsletter-subscription'); ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('New Post Email Subject', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <input type="text" name="wns_template_new_post_subject" value="<?php echo esc_attr(get_option('wns_template_new_post_subject', __('📚 New Tutorial: {post_title}', 'wp-newsletter-subscription'))); ?>" class="regular-text" />
                        <p class="description"><?php _e('Use {post_title} placeholder. You can also customize title per post in the post editor.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Social Media Links Section -->
            <h2><?php _e('Social Media Links', 'wp-newsletter-subscription'); ?></h2>
            <p class="description"><?php _e('These links will appear in the footer of all newsletter emails.', 'wp-newsletter-subscription'); ?></p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Facebook URL', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="url" name="wns_facebook_url" value="<?php echo esc_attr(get_option('wns_facebook_url', '')); ?>" class="regular-text" placeholder="https://facebook.com/yourpage" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Twitter URL', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="url" name="wns_twitter_url" value="<?php echo esc_attr(get_option('wns_twitter_url', '')); ?>" class="regular-text" placeholder="https://twitter.com/youraccount" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Instagram URL', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="url" name="wns_instagram_url" value="<?php echo esc_attr(get_option('wns_instagram_url', '')); ?>" class="regular-text" placeholder="https://instagram.com/youraccount" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('LinkedIn URL', 'wp-newsletter-subscription'); ?></th>
                    <td><input type="url" name="wns_linkedin_url" value="<?php echo esc_attr(get_option('wns_linkedin_url', '')); ?>" class="regular-text" placeholder="https://linkedin.com/company/yourcompany" /></td>
                </tr>
            </table>

            <!-- Email Sending Limits Section -->
            <h2><?php _e('Email Sending Limits', 'wp-newsletter-subscription'); ?></h2>
            <p class="description" style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                <strong>⚠️ Important for Gmail Deliverability:</strong> Lower batch sizes and longer intervals improve inbox delivery rates. 
                For 3000+ subscribers, use batch size 25-50 with 5-10 minute intervals.
            </p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Emails Per Batch', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <input type="number" name="wns_email_batch_size" value="<?php echo esc_attr(get_option('wns_email_batch_size', 50)); ?>" min="10" max="200" />
                        <p class="description">
                            <?php _e('How many emails to send at once. Recommended: 25-50 for better deliverability.', 'wp-newsletter-subscription'); ?>
                            <br><strong>Current setting will send to all 3000 subscribers in approximately <?php echo ceil(3000 / get_option('wns_email_batch_size', 50)) * get_option('wns_email_send_interval_minutes', 5); ?> minutes.</strong>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Send Interval (minutes)', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <input type="number" name="wns_email_send_interval_minutes" value="<?php echo esc_attr(get_option('wns_email_send_interval_minutes', 5)); ?>" min="2" max="60" />
                        <p class="description">
                            <?php _e('Time to wait between each batch of emails. Recommended: 5-10 minutes for large lists.', 'wp-newsletter-subscription'); ?>
                            <br><em>Longer intervals = better deliverability but slower sending.</em>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Email Reputation Score', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <?php
                        $stats = get_option('wns_email_stats', array('sent' => 0, 'failed' => 0));
                        $total = $stats['sent'] + $stats['failed'];
                        $success_rate = $total > 0 ? ($stats['sent'] / $total) * 100 : 100;
                        $color = $success_rate >= 95 ? 'green' : ($success_rate >= 80 ? 'orange' : 'red');
                        ?>
                        <span style="color: <?php echo $color; ?>; font-weight: bold; font-size: 16px;">
                            <?php echo number_format($success_rate, 1); ?>%
                        </span>
                        <p class="description">
                            Success rate: <?php echo $stats['sent']; ?> sent, <?php echo $stats['failed']; ?> failed.
                            <br>High success rates (95%+) improve future deliverability.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- Unsubscribe Settings Section -->
            <h2><?php _e('Unsubscribe Settings', 'wp-newsletter-subscription'); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Unsubscribe Page', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <?php
                        $pages = get_pages();
                        $selected = get_option('wns_unsubscribe_page_id', 0);
                        ?>
                        <select name="wns_unsubscribe_page_id">
                            <option value="0"><?php _e('-- Select a Page --', 'wp-newsletter-subscription'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select a page that contains the [newsletter_unsubscribe] shortcode.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Theme Integration Section -->
            <h2><?php _e('Theme Integration', 'wp-newsletter-subscription'); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Download Form Integration', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <p class="description">
                            <?php if (has_action('foxiz_subscribe', 'wns_handle_theme_download_subscription_enhanced')): ?>
                                <span style="color: green; font-weight: bold;">✓ <?php _e('Active', 'wp-newsletter-subscription'); ?></span> - <?php _e('Download form emails are automatically added to newsletter subscribers', 'wp-newsletter-subscription'); ?>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">✗ <?php _e('Not detected', 'wp-newsletter-subscription'); ?></span> - <?php _e('Theme download form integration not found', 'wp-newsletter-subscription'); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Auto-verify Download Subscribers', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wns_auto_verify_download_subscribers" value="1" <?php checked(1, get_option('wns_auto_verify_download_subscribers', true)); ?> />
                            <?php _e('Automatically verify emails from download forms (recommended)', 'wp-newsletter-subscription'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, emails from download forms will be automatically verified and can receive newsletters immediately. When disabled, they must verify via email first.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Send Welcome Email to Download Subscribers', 'wp-newsletter-subscription'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wns_send_welcome_to_download_subscribers" value="1" <?php checked(1, get_option('wns_send_welcome_to_download_subscribers', false)); ?> />
                            <?php _e('Send welcome email to new download subscribers', 'wp-newsletter-subscription'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, users who subscribe via download forms will receive a welcome email.', 'wp-newsletter-subscription'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Settings', 'wp-newsletter-subscription'), 'primary', 'submit', true, array('style' => 'font-size: 16px; padding: 10px 20px;')); ?>
        </form>

        <!-- Instructions Section -->
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin-top: 30px; border-radius: 8px;">
            <h3 style="color: #495057; margin-top: 0;">📋 Download Verification Workflow:</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                    <h4 style="color: #28a745; margin-top: 0;">✅ Verified Subscribers (Skip Verification = ON)</h4>
                    <ol style="color: #155724; line-height: 1.6; margin: 0;">
                        <li>User enters email in download form</li>
                        <li>System checks: Email is verified ✓</li>
                        <li>Download link sent to email immediately</li>
                        <li>User clicks link and downloads file</li>
                    </ol>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <h4 style="color: #856404; margin-top: 0;">⚠️ Unverified Users</h4>
                    <ol style="color: #856404; line-height: 1.6; margin: 0;">
                        <li>User enters email in download form</li>
                        <li>System checks: Email not verified ⚠️</li>
                        <li>Verification email sent first</li>
                        <li>User verifies email via link</li>
                        <li>Download link sent after verification</li>
                    </ol>
                </div>
            </div>

            <div style="background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin-top: 20px;">
                <h4 style="color: #721c24; margin-top: 0;">🔒 Strict Mode (Skip Verification = OFF)</h4>
                <p style="color: #721c24; margin: 0;">When "Skip Verification for Already Verified Users" is disabled, ALL users (even verified subscribers) must verify their email for every download. This provides maximum security but may annoy returning users.</p>
            </div>
            
            <h4 style="color: #28a745; margin-bottom: 10px;">✅ Benefits of This System:</h4>
            <ul style="color: #6c757d; line-height: 1.6;">
                <li>🛡️ <strong>Prevents spam:</strong> Users can't use fake emails like "123@gmail.com"</li>
                <li>📧 <strong>Valid emails only:</strong> Ensures you get real, deliverable email addresses</li>
                <li>📈 <strong>Builds subscriber list:</strong> All downloaders become verified newsletter subscribers</li>
                <li>🔒 <strong>Security:</strong> Download links expire after 24 hours</li>
                <li>⚡ <strong>Smart workflow:</strong> Verified users get instant access, new users verify once</li>
                <li>😊 <strong>Better UX:</strong> Verified subscribers don't get repeated verification emails</li>
            </ul>
        </div>
    </div>
    <?php
}