<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Table constant fallback.
 */
if (!defined('WNS_TABLE_SUBSCRIBERS')) {
    global $wpdb;
    define('WNS_TABLE_SUBSCRIBERS', $wpdb->prefix . 'newsletter_subscribers');
}

/**
 * Build minimal base headers; deliverability class will enhance.
 * $marker is used by WNS_Email_Deliverability::is_newsletter_email().
 */
// Put this version in the file where wns_build_plugin_headers lives (you had it in verification.php).
function wns_build_plugin_headers($email, $marker = 'verify') {
    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Always identify our plugin + marker
    $headers[] = 'X-WNS: ' . $marker; // alert|verify|download|newsletter
    $headers[] = 'X-Plugin: WP Newsletter Plugin';

    // Only add list headers for actual newsletters (Promotions profile)
    if ($marker === 'newsletter') {
        $unsub_url = function_exists('wns_get_unsubscribe_link')
            ? wns_get_unsubscribe_link($email)
            : home_url('/unsubscribe/');
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $headers[] = 'List-ID: ' . get_bloginfo('name') . ' Newsletter <newsletter.' . $site_domain . '>';
        $headers[] = 'List-Unsubscribe: <' . esc_url($unsub_url) . '>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        $headers[] = 'List-Archive: <' . esc_url(home_url()) . '>';
        $headers[] = 'List-Owner: <mailto:' . sanitize_email(apply_filters('wns_sender_email', 'admin@aistudynow.com')) . '>';
        $headers[] = 'List-Subscribe: <' . esc_url(home_url()) . '>';

        // Bulk hints are fine for newsletters
        $headers[] = 'Precedence: bulk';
        $headers[] = 'Auto-Submitted: auto-generated';
    }

    // DO NOT add Return-Path here (PHPMailer will set envelope Sender)

    return $headers;
}


/**
 * Handle verification link clicks: ?verify_email=...&token=...
 */
add_action('init', 'wns_check_verification_request');
function wns_check_verification_request() {
    if (empty($_GET['verify_email']) || empty($_GET['token'])) {
        return;
    }

    $email = sanitize_email(wp_unslash($_GET['verify_email']));
    $token = sanitize_text_field(wp_unslash($_GET['token']));

    // Basic validation
    if (!is_email($email) || empty($token) || strlen($token) !== 64) {
        wp_safe_redirect(add_query_arg('verified', 'invalid', home_url()));
        exit;
    }

    // Rate limit attempts
    if (!wns_check_verification_rate_limit($email)) {
        wp_safe_redirect(add_query_arg('verified', 'rate_limited', home_url()));
        exit;
    }

    if (wns_verify_email_token($email, $token)) {
        wns_mark_email_as_verified($email);
        wp_safe_redirect(add_query_arg('verified', 'success', home_url()));
        exit;
    }

    wp_safe_redirect(add_query_arg('verified', 'invalid', home_url()));
    exit;
}

/**
 * Max 3 verification attempts per hour per email.
 */
function wns_check_verification_rate_limit($email) {
    $key = 'wns_verify_rate_' . md5(strtolower($email));
    $attempts = get_transient($key);

    if ($attempts === false) {
        set_transient($key, 1, HOUR_IN_SECONDS);
        return true;
    }
    if ((int)$attempts >= 3) {
        return false;
    }
    set_transient($key, (int)$attempts + 1, HOUR_IN_SECONDS);
    return true;
}

/**
 * Create a 64-char HMAC token; store in transient for 24h.
 */
function wns_generate_verification_token($email) {
    $salt      = defined('AUTH_SALT') ? AUTH_SALT : wp_salt();
    $timestamp = time();
    $data      = strtolower($email) . '|' . $timestamp . '|' . wp_get_session_token();

    $token = hash_hmac('sha256', $data, $salt); // 64 hex chars
    set_transient('wns_verify_token_' . md5(strtolower($email)), array(
        'token'   => $token,
        'expires' => $timestamp + DAY_IN_SECONDS,
    ), DAY_IN_SECONDS);

    return $token;
}

/**
 * Validate token for an existing, not-yet-verified subscriber.
 */
function wns_verify_email_token($email, $token) {
    global $wpdb;

    if (!is_email($email) || empty($token) || strlen($token) !== 64) {
        return false;
    }

    $table = WNS_TABLE_SUBSCRIBERS;

    // Ensure table exists
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) { return false; }

    // Must be present and not already verified
    $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE `email` = %s", $email));
    if ($wpdb->last_error) {
        error_log('WNS verification DB error: ' . $wpdb->last_error);
        return false;
    }
    if (!$subscriber || (int)$subscriber->verified === 1) {
        return false;
    }

    // Check stored token
    $stored = get_transient('wns_verify_token_' . md5(strtolower($email)));
    if (!$stored || !is_array($stored)) { return false; }
    if (!hash_equals($stored['token'], $token)) { return false; }
    if (time() > (int)$stored['expires']) {
        delete_transient('wns_verify_token_' . md5(strtolower($email)));
        return false;
    }

    // One-time use
    delete_transient('wns_verify_token_' . md5(strtolower($email)));
    return true;
}

/**
 * Mark subscriber as verified in DB.
 */
function wns_mark_email_as_verified($email) {
    global $wpdb;
    $table = WNS_TABLE_SUBSCRIBERS;

    $result = $wpdb->update(
        $table,
        array('verified' => 1),
        array('email' => $email),
        array('%d'),
        array('%s')
    );

    if ($wpdb->last_error) {
        error_log('WNS mark_verified DB error: ' . $wpdb->last_error);
        return false;
    }
    return $result !== false;
}

/**
 * Build the verification email HTML body using existing templates.
 * Avoids calling a non-existent method (get_verification_template).
 */
function wns_build_verification_body($verify_link) {
    require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';

    // If you later add this method to email-templates.php, we'll use it
    if (method_exists('WNS_Email_Templates', 'get_verification_template')) {
        return WNS_Email_Templates::get_verification_template($verify_link);
    }

    // Works with your current templates (you already have get_verify_template)
    if (method_exists('WNS_Email_Templates', 'get_verify_template')) {
        $headline = __('Confirm your subscription', 'wp-newsletter-subscription');
        $message  = __('Tap the button below to confirm your subscription.', 'wp-newsletter-subscription');
        return WNS_Email_Templates::get_verify_template(
            $headline,
            $message,
            $verify_link,
            __('Verify email', 'wp-newsletter-subscription')
        );
    }

    // Last-resort fallback
    $subject = __('Confirm your subscription', 'wp-newsletter-subscription');
    $msg  = '<p>' . esc_html__('Tap the link to verify:', 'wp-newsletter-subscription') . '</p>';
    $msg .= '<p><a href="' . esc_url($verify_link) . '">' . esc_html__('Verify email', 'wp-newsletter-subscription') . '</a></p>';

    if (method_exists('WNS_Email_Templates', 'get_newsletter_template')) {
        return WNS_Email_Templates::get_newsletter_template($subject, $msg);
    }
    return $msg;
}

/**
 * Send verification email (queue first; fallback to direct).
 */
function wns_send_verification_email($email) {
    if (!is_email($email)) { return false; }

    // Debounce: 5 minutes
    $tkey = 'wns_verify_email_sent_' . md5(strtolower($email));
    if (get_transient($tkey)) { return true; }

    global $wpdb;
    $queue_table = $wpdb->prefix . 'newsletter_email_queue';

    // If queue table is missing, send now.
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table));
    if ($exists !== $queue_table) {
        return wns_send_verification_email_direct($email);
    }

    $token = wns_generate_verification_token($email);
    $verify_link = add_query_arg(array(
        'verify_email' => urlencode($email),
        'token'        => $token,
    ), home_url());

    // Subject + Body
    $subject = sanitize_text_field(get_option(
        'wns_template_subscribe_subject',
        __('Confirm Your Subscription', 'wp-newsletter-subscription')
    ));
    $body = wns_build_verification_body($verify_link);

    // Unsubscribe replacement
    $unsubscribe_link = function_exists('wns_get_unsubscribe_link')
        ? wns_get_unsubscribe_link($email)
        : home_url('/unsubscribe/');
    $body = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $body);

    // Base headers with X-WNS marker
    $headers = wns_build_plugin_headers($email, 'verify');

    // Store in queue
    $ok = $wpdb->insert($queue_table, array(
        'recipient' => sanitize_email($email),
        'subject'   => $subject,
        'body'      => $body,
        'headers'   => maybe_serialize($headers),
        'send_at'   => current_time('mysql'),
        'sent'      => 0,
    ), array('%s','%s','%s','%s','%s','%d'));

    if ($ok) {
        set_transient($tkey, true, 5 * MINUTE_IN_SECONDS);
        return true;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WNS Debug: failed to insert verification email into queue for ' . $email);
    }
    return false;
}

/**
 * Direct send fallback (no queue).
 */
function wns_send_verification_email_direct($email) {
    if (!is_email($email)) { return false; }

    $token = wns_generate_verification_token($email);
    $verify_link = add_query_arg(array(
        'verify_email' => urlencode($email),
        'token'        => $token,
    ), home_url());

    $subject = sanitize_text_field(get_option(
        'wns_template_subscribe_subject',
        __('Confirm Your Subscription', 'wp-newsletter-subscription')
    ));
    $body = wns_build_verification_body($verify_link);

    $unsubscribe_link = function_exists('wns_get_unsubscribe_link')
        ? wns_get_unsubscribe_link($email)
        : home_url('/unsubscribe/');
    $body = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $body);

    // Mark as ours so deliverability class enhances From/Sender
    $headers = wns_build_plugin_headers($email, 'verify');

    $sent = wp_mail($email, $subject, $body, $headers);

    if (!$sent && defined('WP_DEBUG') && WP_DEBUG) {
        if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer'])) {
            error_log('WNS verify mail failed: ' . $GLOBALS['phpmailer']->ErrorInfo);
        } else {
            error_log('WNS verify mail failed: unknown');
        }
    }

    if ($sent) {
        set_transient('wns_verify_email_sent_' . md5(strtolower($email)), true, 5 * MINUTE_IN_SECONDS);
    }
    return $sent;
}
