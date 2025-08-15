<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'wns_setup_batch_email_cron');

function wns_setup_batch_email_cron() {
    if (!wp_next_scheduled('wns_cron_process_email_queue')) {
        wp_schedule_event(time(), 'every_minute', 'wns_cron_process_email_queue');
    }
}

// Register custom cron interval
add_filter('cron_schedules', 'wns_add_custom_cron_intervals');

function wns_add_custom_cron_intervals($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display' => __('Once Every Minute')
    );
    return $schedules;
}

add_action('wns_cron_process_email_queue', 'wns_process_email_queue');

function wns_process_email_queue() {
    global $wpdb;

    // Ensure tables exist before processing
    wns_ensure_tables_exist();

    // Get dynamic batch size based on deliverability factors
    $base_batch_size = get_option('wns_email_batch_size', 50); // Reduced default
    $batch_size = apply_filters('wns_email_batch_size', $base_batch_size);
    
    // Implement progressive sending - slower during peak hours
    $current_hour = (int) date('H');
    if ($current_hour >= 9 && $current_hour <= 17) {
        $batch_size = min($batch_size, 25); // Reduce during business hours
    }
    
    $now = current_time('timestamp');
    $queue_table = $wpdb->prefix . 'newsletter_email_queue';

    // Check if table exists before querying with prepared statement
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table)) != $queue_table) {
        error_log('WNS Debug: Email queue table does not exist');
        return; // Table doesn't exist, skip processing
    }

    // Check for pending emails first before applying rate limiting
    $pending_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `$queue_table` WHERE send_at <= %s AND sent = %d",
            date('Y-m-d H:i:s', $now),
            0
        )
    );
    
    if ($pending_count == 0) {
        error_log('WNS Debug: No pending emails found in queue');
        return; // No emails to process
    }
    
    error_log('WNS Debug: Found ' . $pending_count . ' pending emails in queue');
    
    // Add delay between batches to avoid overwhelming mail servers (but allow immediate processing for new emails)
    $last_batch_time = get_transient('wns_last_batch_time');
    $min_interval = get_option('wns_email_send_interval_minutes', 5) * 60; // Convert to seconds
    
    // Only apply rate limiting if we have processed emails recently AND there are many pending emails
    if ($last_batch_time && (time() - $last_batch_time) < $min_interval && $pending_count > $batch_size) {
        error_log('WNS Debug: Rate limiting applied - too soon since last batch (' . (time() - $last_batch_time) . ' seconds ago), skipping large batch');
        return; // Too soon since last batch for large batches
    }

    // Get all pending emails with prepared statement
    $emails = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `$queue_table`
             WHERE send_at <= %s AND sent = %d
             ORDER BY id ASC LIMIT %d",
            date('Y-m-d H:i:s', $now),
            0,
            $batch_size
        )
    );

    if (!$emails || $wpdb->last_error) {
        if ($wpdb->last_error) {
            error_log('WNS Plugin Error in email queue processing: ' . $wpdb->last_error);
        } else {
            error_log('WNS Debug: No pending emails found in queue');
        }
        return;
    }

    error_log('WNS Debug: Processing ' . count($emails) . ' emails from queue');
    
    $successful_sends = 0;
    $failed_sends = 0;
    
    foreach ($emails as $email) {
        // Validate email before sending
        if (!is_email($email->recipient)) {
            // Mark invalid emails as sent to prevent retry
            $wpdb->update(
                $queue_table,
                array('sent' => 1, 'sent_at' => current_time('mysql')),
                array('id' => $email->id),
                array('%d', '%s'),
                array('%d')
            );
            $failed_sends++;
            error_log('WNS Debug: Invalid email address: ' . $email->recipient);
            continue;
        }

        // Process headers - use standard headers if none provided
        $headers = maybe_unserialize($email->headers);
        if (empty($headers)) {
            $headers = wns_get_standard_email_headers($email->recipient);
        }

        // Ensure unsubscribe link is processed
        $email_body = $email->body;
        if (strpos($email_body, '{unsubscribe_link}') !== false) {
            $unsubscribe_link = wns_get_unsubscribe_link($email->recipient);
            $email_body = str_replace('{unsubscribe_link}', $unsubscribe_link, $email_body);
        }

        // Apply deliverability optimizations - but preserve URL case
        // Skip content optimization that might change URL case
        // $email_body = apply_filters('wns_email_content', $email_body, 'newsletter');
        
        // Add small delay between individual emails to avoid rate limiting
        if ($successful_sends > 0 && $successful_sends % 10 === 0) {
            sleep(2); // 2 second pause every 10 emails
        }

        error_log('WNS Debug: Attempting to send email to: ' . $email->recipient . ' with subject: ' . $email->subject);
        
        // Send email with enhanced error handling
        $sent = wp_mail(
            sanitize_email($email->recipient), 
            sanitize_text_field($email->subject), 
            wp_kses_post($email_body), 
            $headers
        );

        // Log email activity
        if ($sent) {
            wns_log_email_activity($email->recipient, 'sent_via_queue', 'Email sent successfully via queue');
            $successful_sends++;
            error_log('WNS Debug: Email sent successfully to: ' . $email->recipient);
        } else {
            wns_log_email_activity($email->recipient, 'failed_via_queue', 'Email failed to send via queue');
            $failed_sends++;
            error_log('WNS Debug: Email failed to send to: ' . $email->recipient);
        }

        // Mark as sent regardless of success to prevent infinite retries
        $wpdb->update(
            $queue_table,
            array('sent' => 1, 'sent_at' => current_time('mysql')),
            array('id' => $email->id),
            array('%d', '%s'),
            array('%d')
        );
    }
    
    // Record batch completion time
    set_transient('wns_last_batch_time', time(), HOUR_IN_SECONDS);
    
    // Log batch statistics
    if ($successful_sends > 0 || $failed_sends > 0) {
        error_log("WNS Batch Complete: {$successful_sends} sent, {$failed_sends} failed");
    }
    
    // Adjust future batch sizes based on success rate
    if (($successful_sends + $failed_sends) > 0) {
        $success_rate = $successful_sends / ($successful_sends + $failed_sends);
        if ($success_rate < 0.8) {
            // Reduce batch size if success rate is low
            $new_batch_size = max(10, $base_batch_size * 0.8);
            update_option('wns_email_batch_size', $new_batch_size);
        }
    }
}