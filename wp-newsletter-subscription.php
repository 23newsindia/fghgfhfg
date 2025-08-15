<?php
/**
 * Plugin Name: WP Newsletter Subscription
 * Plugin URI: https://example.com/wp-newsletter-subscription 
 * Description: A simple newsletter subscription system with email verification, import/export, and scheduled mail sending.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://example.com 
 * Text Domain: wp-newsletter-subscription
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Ensure WordPress core functions are available
if (!function_exists('add_action')) {
    exit("WordPress not loaded!");
}

// Define plugin constants
define('WNS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WNS_PLUGIN_URL', plugin_dir_url(__FILE__));
global $wpdb;
define('WNS_TABLE_SUBSCRIBERS', $wpdb->prefix . 'newsletter_subscribers');

// Hook for activation
register_activation_hook(__FILE__, 'wns_activate_plugin');
register_deactivation_hook(__FILE__, 'wns_deactivate_plugin');
register_uninstall_hook(__FILE__, 'wns_uninstall_plugin');

// Include core plugin files
require_once WNS_PLUGIN_DIR . 'includes/install.php';
require_once WNS_PLUGIN_DIR . 'includes/activation.php';

// Include utility functions first (to avoid redeclaration)
require_once WNS_PLUGIN_DIR . 'includes/utils.php';

// Admin includes - Load in proper order
require_once WNS_PLUGIN_DIR . 'includes/admin/settings-page.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/subscriber-list-page.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/class-wns-subscriber-list-table.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/subscriber-list-view.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/import-export-page.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/unverified-notice.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/broadcast-page.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/broadcast-handler.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/broadcast-view.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/email-queue-page.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/class-wns-email-queue-list-table.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/email-queue-view.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/admin-menu.php';
require_once WNS_PLUGIN_DIR . 'includes/admin/post-meta-box.php';

// Frontend includes
require_once WNS_PLUGIN_DIR . 'includes/subscription-form.php';
require_once WNS_PLUGIN_DIR . 'includes/assets.php';
require_once WNS_PLUGIN_DIR . 'includes/verification.php';
require_once WNS_PLUGIN_DIR . 'includes/import-export-functions.php';
require_once WNS_PLUGIN_DIR . 'includes/auto-import-users.php';
require_once WNS_PLUGIN_DIR . 'includes/unsubscribe-handler.php';
require_once WNS_PLUGIN_DIR . 'includes/new-post-notification.php';
require_once WNS_PLUGIN_DIR . 'includes/batch-email-handler.php';
require_once WNS_PLUGIN_DIR . 'includes/cleanup.php';

// Email templates
require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';

// Email deliverability enhancements (NEW)
require_once WNS_PLUGIN_DIR . 'includes/email-deliverability.php';

// Download handler (NEW)
require_once WNS_PLUGIN_DIR . 'includes/download-handler.php';

// Theme integration (UPDATED)
require_once WNS_PLUGIN_DIR . 'includes/theme-integration.php';

// Force table creation on plugin load if they don't exist
add_action('init', 'wns_ensure_tables_exist');

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

// Add filter to prevent shortcode processing during REST API requests
add_filter('do_shortcode_tag', 'wns_filter_shortcodes_during_rest', 10, 4);

function wns_filter_shortcodes_during_rest($output, $tag, $attr, $m) {
    // Skip our shortcodes during REST API requests
    if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX)) {
        if (in_array($tag, array('newsletter_subscribe', 'newsletter_unsubscribe'))) {
            return '';
        }
    }
    return $output;
}