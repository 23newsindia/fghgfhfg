<?php
if (!defined('ABSPATH')) {
    exit;
}

function wns_export_subscribers() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-newsletter-subscription'));
    }

    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    // Verify table exists with prepared statement
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        wp_die(__('Subscriber table not found.', 'wp-newsletter-subscription'));
    }

    $subscribers = $wpdb->get_results($wpdb->prepare("SELECT email FROM `$table_name` ORDER BY created_at DESC"));

    if ($wpdb->last_error) {
        error_log('WNS Plugin Error in export: ' . $wpdb->last_error);
        wp_die(__('Database error occurred during export.', 'wp-newsletter-subscription'));
    }

    $filename = 'newsletter-subscribers-' . date('Y-m-d') . '.csv';
    
    // Security headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, array('email'));

    foreach ($subscribers as $subscriber) {
        // Validate email before export
        if (is_email($subscriber->email)) {
            fputcsv($output, array(sanitize_email($subscriber->email)));
        }
    }

    fclose($output);
    exit;
}

function wns_import_subscribers_from_csv($file_path) {
    // Security check
    if (!current_user_can('manage_options')) {
        return array('success' => false, 'error' => __('Insufficient permissions.', 'wp-newsletter-subscription'));
    }

    // Additional file security checks
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return array('success' => false, 'error' => __('File not accessible.', 'wp-newsletter-subscription'));
    }

    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return array('success' => false, 'error' => __('Failed to open CSV file.', 'wp-newsletter-subscription'));
    }

    $count = 0;
    $errors = 0;
    $domain_errors = 0;
    $line_number = 0;
    $headers = fgetcsv($handle);
    $line_number++;

    if (!is_array($headers) || !in_array('email', array_map('strtolower', $headers))) {
        fclose($handle);
        return array('success' => false, 'error' => __('Invalid CSV format. Missing "email" column.', 'wp-newsletter-subscription'));
    }

    // Find email column index
    $email_index = array_search('email', array_map('strtolower', $headers));
    if ($email_index === false) {
        fclose($handle);
        return array('success' => false, 'error' => __('Email column not found.', 'wp-newsletter-subscription'));
    }

    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    // Verify table exists with prepared statement
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        fclose($handle);
        return array('success' => false, 'error' => __('Subscriber table not found.', 'wp-newsletter-subscription'));
    }

    while (($data = fgetcsv($handle)) !== false) {
        $line_number++;
        
        // Skip empty lines
        if (empty($data) || !isset($data[$email_index])) {
            continue;
        }

        $email = trim($data[$email_index]);
        
        // Enhanced validation with domain restriction
        if (!wns_validate_email_deliverability($email)) {
            $errors++;
            // Check specifically for domain issues
            if (is_email($email) && !wns_is_allowed_email_domain($email)) {
                $domain_errors++;
            }
            continue;
        }

        // Sanitize email
        $email = sanitize_email($email);

        // Check if already exists
        $exists = wns_email_exists_in_subscribers($email);
        if (!$exists) {
            $result = wns_add_subscriber_to_db($email);
            if ($result) {
                $count++;
            } else {
                $errors++;
            }
        }

        // Prevent memory issues with large files
        if ($line_number > 10000) {
            break;
        }
    }

    fclose($handle);

    $message = '';
    if ($domain_errors > 0) {
        $message = sprintf(__('%d subscribers imported successfully. %d emails were rejected (only Gmail, Hotmail, and Yahoo are allowed). %d other errors encountered.', 'wp-newsletter-subscription'), $count, $domain_errors, ($errors - $domain_errors));
    } elseif ($errors > 0) {
        $message = sprintf(__('%d subscribers imported successfully. %d errors encountered.', 'wp-newsletter-subscription'), $count, $errors);
    } else {
        $message = sprintf(__('%d subscribers imported successfully.', 'wp-newsletter-subscription'), $count);
    }

    return array('success' => true, 'count' => $count, 'message' => $message);
}

function wns_email_exists_in_subscribers($email) {
    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `email` = %s", $email));
    
    if ($wpdb->last_error) {
        error_log('WNS Plugin Error in email check: ' . $wpdb->last_error);
        return false;
    }
    
    return $count > 0;
}

function wns_add_subscriber_to_db($email) {
    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    $verified = get_option('wns_enable_verification', false) ? 0 : 1;

    $result = $wpdb->insert($table_name, array(
        'email'     => sanitize_email($email),
        'verified'  => $verified
    ), array('%s', '%d'));

    if ($wpdb->last_error) {
        error_log('WNS Plugin Error in subscriber insert: ' . $wpdb->last_error);
        return false;
    }

    return $result !== false;
}