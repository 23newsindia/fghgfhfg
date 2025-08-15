<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility functions shared across the plugin
 */

if (!function_exists('wns_get_unsubscribe_link')) {
    function wns_get_unsubscribe_link($email = '') {
        $unsubscribe_page = get_option('wns_unsubscribe_page_id');
        if ($unsubscribe_page && get_post_status($unsubscribe_page) === 'publish') {
            $unsubscribe_url = get_permalink($unsubscribe_page);
            if ($email) {
                $unsubscribe_url = add_query_arg('email', urlencode($email), $unsubscribe_url);
            }
            return $unsubscribe_url;
        } else {
            return home_url('/unsubscribe/');
        }
    }
}

if (!function_exists('wns_get_standard_email_headers')) {
    function wns_get_standard_email_headers($recipient_email = '') {
        $site_name = get_bloginfo('name');
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $admin_email = get_option('admin_email');
        $unsubscribe_link = wns_get_unsubscribe_link($recipient_email);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
            'Reply-To: ' . $admin_email,
            'Return-Path: ' . $admin_email,
            'Sender: ' . $admin_email,
            
            // List management headers (RFC 2369) - Critical for Gmail
            'List-ID: ' . $site_name . ' Newsletter <newsletter.' . $site_domain . '>',
            'List-Unsubscribe: <' . $unsubscribe_link . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            'List-Archive: <' . home_url() . '>',
            'List-Owner: <mailto:' . $admin_email . '>',
            'List-Subscribe: <' . home_url() . '>',
            
            // Email client optimization
            'X-Mailer: WordPress/' . get_bloginfo('version') . ' - WP Newsletter Plugin',
            'X-Priority: 3',
            'X-MSMail-Priority: Normal',
            'Importance: Normal',
            
            // Bulk email identification
            'Precedence: bulk',
            'Auto-Submitted: auto-generated',
            
            // Gmail-specific headers for better inbox delivery
            'X-Google-Original-From: ' . $admin_email,
            'X-Auto-Response-Suppress: All',
            'X-Entity-ID: <newsletter@' . $site_domain . '>',
            'X-Google-Appengine-App-Id: educational-newsletter',
            'X-Email-Type-Id: educational-content',
            'X-Newsletter-Type: educational',
            
            // Campaign tracking
            'X-Campaign-ID: educational-newsletter-' . date('Y-m-d'),
            'X-Mailer-LID: ' . md5($site_domain . date('Y-m-d')),
            
            // Anti-spam headers
            'X-Spam-Status: No',
            'X-Authenticated-Sender: ' . $admin_email,
            'X-Content-Category: educational',
            'X-Message-Flag: educational-content',
            
            // Security headers
            'X-Content-Type-Options: nosniff'
        );
        
        return $headers;
    }
}

if (!function_exists('wns_is_allowed_email_domain')) {
    function wns_is_allowed_email_domain($email) {
        // Extract domain from email
        $domain = substr(strrchr($email, "@"), 1);
        if (!$domain) {
            return false;
        }
        
        $domain = strtolower($domain);
        
        // List of allowed domains - only Gmail, Hotmail, and Yahoo
        $allowed_domains = array(
            // Gmail domains
            'gmail.com',
            'googlemail.com',
            
            // Hotmail/Outlook domains
            'hotmail.com',
            'hotmail.co.uk',
            'hotmail.fr',
            'hotmail.de',
            'hotmail.it',
            'hotmail.es',
            'hotmail.ca',
            'hotmail.com.au',
            'outlook.com',
            'outlook.co.uk',
            'outlook.fr',
            'outlook.de',
            'outlook.it',
            'outlook.es',
            'outlook.ca',
            'outlook.com.au',
            'live.com',
            'live.co.uk',
            'live.fr',
            'live.de',
            'live.it',
            'live.ca',
            'msn.com',
            
            // Yahoo domains
            'yahoo.com',
            'yahoo.co.uk',
            'yahoo.fr',
            'yahoo.de',
            'yahoo.it',
            'yahoo.es',
            'yahoo.ca',
            'yahoo.com.au',
            'yahoo.co.in',
            'yahoo.com.br',
            'ymail.com',
            'rocketmail.com'
        );
        
        return in_array($domain, $allowed_domains);
    }
}

if (!function_exists('wns_is_disposable_email')) {
    function wns_is_disposable_email($email) {
        $disposable_domains = array(
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com',
            'yopmail.com', 'temp-mail.org', 'throwaway.email', 'getnada.com',
            'sharklasers.com', 'grr.la', 'guerrillamailblock.com', 'pokemail.net',
            'spam4.me', 'bccto.me', 'chacuo.net', 'dispostable.com', 'emailondeck.com',
            'goolemail.com', 'xyz.com', 'example.com', 'test.com', 'fake.com',
            'dummy.com', 'invalid.com', 'notreal.com', 'fakemail.com', 'tempmail.com'
        );
        
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), $disposable_domains);
    }
}

if (!function_exists('wns_get_client_ip')) {
    function wns_get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1'; // Fallback
    }
}

if (!function_exists('wns_validate_email_deliverability')) {
    function wns_validate_email_deliverability($email) {
        // Basic email validation
        if (!is_email($email)) {
            error_log('WNS Debug: Invalid email format: ' . $email);
            return false;
        }
        
        // Check email length
        if (strlen($email) > 254) {
            error_log('WNS Debug: Email too long: ' . $email);
            return false;
        }
        
        // Check for disposable emails
        if (wns_is_disposable_email($email)) {
            error_log('WNS Debug: Disposable email detected: ' . $email);
            return false;
        }
        
        // NEW: Check if domain is allowed (only Gmail, Hotmail, Yahoo)
        if (!wns_is_allowed_email_domain($email)) {
            error_log('WNS Debug: Email domain not allowed: ' . $email);
            return false;
        }
        
        // Additional domain validation
        $domain = substr(strrchr($email, "@"), 1);
        if (!$domain || strlen($domain) < 3) {
            error_log('WNS Debug: Invalid domain: ' . $domain . ' for email: ' . $email);
            return false;
        }
        
        return true;
    }
}

if (!function_exists('wns_log_email_activity')) {
    function wns_log_email_activity($email, $action, $details = '') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                'WNS Email Activity - Email: %s, Action: %s, Details: %s, Time: %s',
                $email,
                $action,
                $details,
                current_time('mysql')
            );
            error_log($log_message);
        }
    }
}

// Centralized function to ensure tables exist - ONLY DECLARED ONCE
if (!function_exists('wns_ensure_tables_exist')) {
    function wns_ensure_tables_exist() {
        // Don't run during REST API requests to avoid interference
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        
        global $wpdb;
        
        $subscriber_table = $wpdb->prefix . 'newsletter_subscribers';
        $queue_table = $wpdb->prefix . 'newsletter_email_queue';
        
        // Check if tables exist
        $subscriber_exists = $wpdb->get_var("SHOW TABLES LIKE '$subscriber_table'") == $subscriber_table;
        $queue_exists = $wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table;
        
        if (!$subscriber_exists || !$queue_exists) {
            wns_install_subscriber_table();
        }
    }
}