<?php
if (!defined('ABSPATH')) { exit; }

/**
 * New Post Notification (queue-aware, custom-body aware)
 * - Auto-sends on publish if enabled (global or per-post)
 * - Respects "Send To selected subscribers" meta
 * - Subjects via option: wns_template_new_post_subject (default {post_title})
 * - Body:
 *     - profile "newsletter": responsive post template (date, avatar row, CTA)
 *       and inserts 📧 Custom Email Content if provided
 *     - profile "alert": minimal, plain-ish body to lean Primary
 * - Headers via wns_build_plugin_headers($email, 'newsletter'|'alert') if present,
 *   otherwise wns_get_standard_email_headers() + X-WNS marker
 */

/* ------------------------- Small helpers ------------------------- */

if (!function_exists('wnsn_get_meta_any')) {
    function wnsn_get_meta_any($post_id, $keys) {
        foreach ((array)$keys as $k) {
            $v = get_post_meta($post_id, $k, true);
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return '';
    }
}

if (!function_exists('wnsn_get_broadcast_profile')) {
    function wnsn_get_broadcast_profile($post_id) {
        // Per-post override (metabox): _wns_send_profile = 'alert' | 'newsletter'
        $val = get_post_meta($post_id, '_wns_send_profile', true);
        if ($val === 'alert' || $val === 'newsletter') return $val;

        // Global default
        $global = get_option('wns_default_broadcast_profile', 'newsletter');
        return ($global === 'alert') ? 'alert' : 'newsletter';
    }
}

if (!function_exists('wnsn_build_headers')) {
    function wnsn_build_headers($email, $profile = 'newsletter') {
        $marker = ($profile === 'alert') ? 'alert' : 'newsletter';

        if (function_exists('wns_build_plugin_headers')) {
            // Your deliverability helper adds Return-Path/From/Reply-To/List-* safely
            return wns_build_plugin_headers($email, $marker);
        }

        if (function_exists('wns_get_standard_email_headers')) {
            $h = wns_get_standard_email_headers($email);
            if (is_array($h)) {
                $h[] = 'X-WNS: ' . $marker;
                return $h;
            }
            return rtrim((string)$h) . "\r\nX-WNS: " . $marker;
        }

        // Very simple fallback
        return array('Content-Type: text/html; charset=UTF-8', 'X-WNS: ' . $marker);
    }
}


/* ------------------- Body renderers (no duplicates) ------------------- */

/**
 * ALERT profile: short, transactional-looking body to lean Primary.
 */
if (!function_exists('wnsn_render_alert_body')) {
    function wnsn_render_alert_body($post_id, $subject) {
        require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';

        $post = get_post($post_id);
        if (!$post) return '';

        $post_url = get_permalink($post_id);
        $title    = get_the_title($post_id);

        // Keep it very minimal
        $body = '<p><a href="' . esc_url($post_url) . '">' . esc_html($title) . '</a></p>';

        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates','get_newsletter_template')) {
            return WNS_Email_Templates::get_newsletter_template($subject, $body);
        }
        return $body;
    }
}

/**
 * NEWSLETTER profile: full responsive "new post" layout.
 * Injects the "📧 Custom Email Content" when provided (from your post metabox).
 */
if (!function_exists('wnsn_render_newsletter_body')) {
    function wnsn_render_newsletter_body($post_id, $subject, $custom_body_html = '') {
        require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';

        $post = get_post($post_id);
        if (!$post) return '';

        // If explicit HTML not passed, read from likely meta keys your UI saves
        if (trim($custom_body_html) === '') {
            $custom_body_html = wnsn_get_meta_any($post_id, array(
                '_wns_custom_email_body',
                '_wns_custom_email_description', // your UI label says “Custom Email Description”
                '_wns_custom_email_html',
                'newsletter_custom_email_body',
            ));
        }

        // Preferred: use the full post template with avatar/date/CTA.
        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates', 'get_new_post_template')) {
            $args = array();
            if ($custom_body_html !== '') {
                // Template should accept this and replace excerpt block
                $args['custom_html'] = $custom_body_html;
            }

            // Handle older template signature (1 arg) gracefully
            try {
                return WNS_Email_Templates::get_new_post_template($post, $args);
            } catch (\ArgumentCountError $e) {
                if ($custom_body_html !== '' && method_exists('WNS_Email_Templates','get_newsletter_template')) {
                    return WNS_Email_Templates::get_newsletter_template($subject, $custom_body_html);
                }
                return WNS_Email_Templates::get_new_post_template($post);
            }
        }

        // Fallback: basic newsletter wrapper with either custom HTML or short intro
        $fallback = $custom_body_html !== ''
            ? $custom_body_html
            : (has_excerpt($post_id) ? get_the_excerpt($post_id)
                                     : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 30));

        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates','get_newsletter_template')) {
            return WNS_Email_Templates::get_newsletter_template($subject, $fallback);
        }
        return $fallback;
    }
}


/* --------------- Auto-send on publish (once) --------------- */

add_action('transition_post_status', 'wns_send_new_post_notifications', 10, 3);
if (!function_exists('wns_send_new_post_notifications')) {
    function wns_send_new_post_notifications($new_status, $old_status, $post) {
        if ($post->post_type !== 'post') return;

        // Only when it becomes published (not on updates to already-published)
        if ($new_status !== 'publish' || $old_status === 'publish') return;

        // Skip autosaves/revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post->ID)) return;

        // Per-post toggle overrides global
        $post_auto_send = get_post_meta($post->ID, '_wns_auto_send_enabled', true);
        $global_enabled = get_option('wns_enable_new_post_notification', false);
        $should_send    = ($post_auto_send !== '') ? ($post_auto_send === '1') : $global_enabled;
        if (!$should_send) return;

        // Prevent duplicate scheduling
        if (get_post_meta($post->ID, '_wns_notification_sent', true)) return;
        update_post_meta($post->ID, '_wns_notification_sent', true);

        if (!wp_next_scheduled('wns_cron_send_post_notification', array($post->ID))) {
            wp_schedule_single_event(time(), 'wns_cron_send_post_notification', array($post->ID));
        }
    }
}


/* --------------- Cron handler: build and queue --------------- */

add_action('wns_cron_send_post_notification', 'wns_cron_handler_send_post_notification', 10, 1);
if (!function_exists('wns_cron_handler_send_post_notification')) {
    function wns_cron_handler_send_post_notification($post_id) {
        global $wpdb;

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') return;

        // Ensure tables your plugin uses exist
        if (function_exists('wns_ensure_tables_exist')) {
            wns_ensure_tables_exist();
        }

        // Subscribers table constant must be defined by the plugin
        if (!defined('WNS_TABLE_SUBSCRIBERS')) return;
        $table_name = WNS_TABLE_SUBSCRIBERS;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) return;

        /* Recipient scope: all verified OR per-selection */
        $send_to_selected     = get_post_meta($post_id, '_wns_send_to_selected', true);
        $selected_subscribers = get_post_meta($post_id, '_wns_selected_subscribers', true);

        if ($send_to_selected === '1' && is_array($selected_subscribers) && !empty($selected_subscribers)) {
            $placeholders = implode(',', array_fill(0, count($selected_subscribers), '%s'));
            $query = "SELECT email FROM `$table_name` WHERE verified = %d AND email IN ($placeholders)";
            $params = array_merge(array(1), $selected_subscribers);
            $subscribers = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $subscribers = $wpdb->get_results($wpdb->prepare("SELECT email FROM `$table_name` WHERE verified = %d", 1));
        }
        if (!$subscribers || $wpdb->last_error) { return; }

        /* Subject: honor Custom Email Title if provided */
        $custom_title = wnsn_get_meta_any($post_id, array(
            '_wns_custom_email_title',
            'wns_custom_email_title',
            'newsletter_custom_email_title',
        ));
        $subject_tpl = get_option('wns_template_new_post_subject', '{post_title}');
        $subject     = str_replace('{post_title}', sanitize_text_field($custom_title ? $custom_title : $post->post_title), $subject_tpl);

        /* Body: choose profile + inject custom HTML if present */
        $profile     = wnsn_get_broadcast_profile($post_id); // 'newsletter' | 'alert'
        $custom_body = wnsn_get_meta_any($post_id, array(
            '_wns_custom_email_body',
            '_wns_custom_email_description',
            'wns_custom_email_body',
            'wns_custom_email_description',
            'newsletter_custom_email_body',
        ));
        $email_html  = ($profile === 'alert')
            ? wnsn_render_alert_body($post_id, $subject)
            : wnsn_render_newsletter_body($post_id, $subject, $custom_body);

        /* Queue or send now (if queue table missing) */
        $queue_table = $wpdb->prefix . 'newsletter_email_queue';
        $use_queue   = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table)) == $queue_table);

        $send_after  = current_time('mysql');
        $enqueued    = 0;

        foreach ($subscribers as $s) {
            if (!is_email($s->email)) continue;

            $personal = str_replace(
                array('{unsubscribe_link}','{recipient_email}'),
                array(
                    function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($s->email) : home_url('/unsubscribe/'),
                    esc_html($s->email)
                ),
                $email_html
            );

            $headers = wnsn_build_headers($s->email, $profile);

            if ($use_queue) {
                $ok = $wpdb->insert($queue_table, array(
                    'recipient' => sanitize_email($s->email),
                    'subject'   => sanitize_text_field($subject),
                    'body'      => $personal,
                    'headers'   => maybe_serialize($headers),
                    'send_at'   => $send_after,
                    'sent'      => 0,
                ), array('%s','%s','%s','%s','%s','%d'));

                if ($ok) {
                    $enqueued++;
                    if (function_exists('wns_log_email_activity')) {
                        wns_log_email_activity($s->email, 'new_post_queued', 'Queued: ' . $post->post_title);
                    }
                }
            } else {
                // Rare fallback: send immediately
                if (wp_mail($s->email, $subject, $personal, $headers)) {
                    $enqueued++;
                }
            }
        }

        if ($enqueued > 0) {
            // Allow immediate processing if your queue worker checks this transient
            delete_transient('wns_last_batch_time');
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time(), 'wns_cron_process_email_queue');
            }
            error_log("WNS: queued/sent {$enqueued} new-post emails ({$profile}) for: {$post->post_title}");
        }
    }
}


/* --------------- Manual sender (UI button) --------------- */

if (!function_exists('wns_send_post_newsletter_manual')) {
    function wns_send_post_newsletter_manual($post_id, $send_to_selected = '0', $selected_subscribers = array()) {
        global $wpdb;

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return array('success' => false, 'message' => 'Post not found or not published');
        }

        if (function_exists('wns_ensure_tables_exist')) {
            wns_ensure_tables_exist();
        }
        if (!defined('WNS_TABLE_SUBSCRIBERS')) {
            return array('success' => false, 'message' => 'Subscriber table not defined');
        }
        $table_name = WNS_TABLE_SUBSCRIBERS;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return array('success' => false, 'message' => 'Subscriber table not found');
        }

        if ($send_to_selected === '1' && is_array($selected_subscribers) && !empty($selected_subscribers)) {
            $selected_subscribers = array_values(array_filter($selected_subscribers, 'is_email'));
            if (empty($selected_subscribers)) {
                return array('success' => false, 'message' => 'No valid email addresses in selection');
            }
            $placeholders = implode(',', array_fill(0, count($selected_subscribers), '%s'));
            $query = "SELECT email FROM `$table_name` WHERE verified = %d AND email IN ($placeholders)";
            $params = array_merge(array(1), $selected_subscribers);
            $subscribers = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $subscribers = $wpdb->get_results($wpdb->prepare("SELECT email FROM `$table_name` WHERE verified = %d", 1));
        }
        if (empty($subscribers)) {
            return array('success' => false, 'message' => ($send_to_selected === '1' ? 'No verified subscribers found in your selection' : 'No verified subscribers found'));
        }

        // Subject
        $custom_title = wnsn_get_meta_any($post_id, array(
            '_wns_custom_email_title',
            'wns_custom_email_title',
            'newsletter_custom_email_title',
        ));
        $subject_tpl = get_option('wns_template_new_post_subject', '{post_title}');
        $subject     = str_replace('{post_title}', sanitize_text_field($custom_title ? $custom_title : $post->post_title), $subject_tpl);

        // Body (profile + custom body)
        $profile     = wnsn_get_broadcast_profile($post_id);
        $custom_body = wnsn_get_meta_any($post_id, array(
            '_wns_custom_email_body',
            '_wns_custom_email_description',
            'wns_custom_email_body',
            'wns_custom_email_description',
            'newsletter_custom_email_body',
        ));
        $email_html  = ($profile === 'alert')
            ? wnsn_render_alert_body($post_id, $subject)
            : wnsn_render_newsletter_body($post_id, $subject, $custom_body);

        // Queue preferred
        $queue_table = $wpdb->prefix . 'newsletter_email_queue';
        $use_queue   = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table)) == $queue_table);

        $send_after  = current_time('mysql');
        $success     = 0;

        foreach ($subscribers as $s) {
            if (!is_email($s->email)) continue;

            $personal = str_replace(
                array('{unsubscribe_link}','{recipient_email}'),
                array(
                    function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($s->email) : home_url('/unsubscribe/'),
                    esc_html($s->email)
                ),
                $email_html
            );
            $headers = wnsn_build_headers($s->email, $profile);

            if ($use_queue) {
                $ok = $wpdb->insert($queue_table, array(
                    'recipient' => sanitize_email($s->email),
                    'subject'   => sanitize_text_field($subject),
                    'body'      => $personal,
                    'headers'   => maybe_serialize($headers),
                    'send_at'   => $send_after,
                    'sent'      => 0
                ), array('%s','%s','%s','%s','%s','%d'));
                if ($ok) { $success++; if (function_exists('wns_log_email_activity')) { wns_log_email_activity($s->email, 'manual_post_newsletter', 'Queued manual: '.$post->post_title); } }
            } else {
                if (wp_mail($s->email, $subject, $personal, $headers)) { $success++; }
            }
        }

        if ($success > 0) {
            delete_transient('wns_last_batch_time');
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(time(), 'wns_cron_process_email_queue');
            }
            return array('success' => true, 'message' => "Newsletter queued for {$success} " . ($send_to_selected === '1' ? 'selected subscribers' : 'subscribers'));
        }
        return array('success' => false, 'message' => 'Failed to queue newsletter emails');
    }
}
