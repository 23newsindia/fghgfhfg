<?php
if (!defined('ABSPATH')) {
    exit;
}

function wns_install_subscriber_table() {
    global $wpdb;

    $subscriber_table = $wpdb->prefix . 'newsletter_subscribers';
    $queue_table = $wpdb->prefix . 'newsletter_email_queue';

    $charset_collate = $wpdb->get_charset_collate();

    // Subscribers table
    $sql_subscribers = "CREATE TABLE IF NOT EXISTS `$subscriber_table` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(255) NOT NULL,
        `verified` TINYINT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        KEY `email_verified` (`email`, `verified`)
    ) $charset_collate;";

    // Email queue table
    $sql_queue = "CREATE TABLE IF NOT EXISTS `$queue_table` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` TEXT NOT NULL,
        `body` LONGTEXT NOT NULL,
        `headers` TEXT,
        `send_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sent` TINYINT NOT NULL DEFAULT 0,
        `sent_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `send_at_sent` (`send_at`, `sent`)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    $result1 = dbDelta($sql_subscribers);
    $result2 = dbDelta($sql_queue);
    
    // Log results for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('WNS Plugin: Subscriber table creation result: ' . print_r($result1, true));
        error_log('WNS Plugin: Queue table creation result: ' . print_r($result2, true));
    }
    
    // Set database version
    update_option('wns_db_version', '1.0.0');
}

// Function to manually create tables (for debugging)
function wns_force_create_tables() {
    global $wpdb;
    
    $subscriber_table = $wpdb->prefix . 'newsletter_subscribers';
    $queue_table = $wpdb->prefix . 'newsletter_email_queue';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Drop existing tables if they exist
    $wpdb->query("DROP TABLE IF EXISTS `$subscriber_table`");
    $wpdb->query("DROP TABLE IF EXISTS `$queue_table`");
    
    // Create subscribers table
    $sql_subscribers = "CREATE TABLE `$subscriber_table` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(255) NOT NULL,
        `verified` TINYINT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        KEY `email_verified` (`email`, `verified`)
    ) $charset_collate;";
    
    $result1 = $wpdb->query($sql_subscribers);
    
    // Create queue table
    $sql_queue = "CREATE TABLE `$queue_table` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` TEXT NOT NULL,
        `body` LONGTEXT NOT NULL,
        `headers` TEXT,
        `send_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `sent` TINYINT NOT NULL DEFAULT 0,
        `sent_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `send_at_sent` (`send_at`, `sent`)
    ) $charset_collate;";
    
    $result2 = $wpdb->query($sql_queue);
    
    return array(
        'subscriber_table' => $result1 !== false,
        'queue_table' => $result2 !== false
    );
}