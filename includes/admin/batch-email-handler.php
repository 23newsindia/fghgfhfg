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

    $batch_size = get_option('wns_email_batch_size', 100);
    $now = current_time('timestamp');

    // Get all pending emails
    $emails = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}newsletter_email_queue
             WHERE send_at <= %s AND sent = 0
             ORDER BY id ASC LIMIT %d",
            date('Y-m-d H:i:s', $now),
            $batch_size
        )
    );

    if (!$emails) {
        return;
    }

    foreach ($emails as $email) {
        $sent = wp_mail($email->recipient, $email->subject, $email->body, $email->headers);

        // Mark as sent
        $wpdb->update(
            "{$wpdb->prefix}newsletter_email_queue",
            array('sent' => 1, 'sent_at' => current_time('mysql')),
            array('id' => $email->id),
            array('%d', '%s'),
            array('%d')
        );
    }
}