<?php
if (!defined('ABSPATH')) {
    exit;
}

// Function is called from admin-menu.php, no need to add_action here
function wns_add_broadcast_page() {
    add_submenu_page(
        'wns-settings',
        __('Send Newsletter', 'wp-newsletter-subscription'),
        __('Send Newsletter', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-send-newsletter',
        'wns_render_broadcast_page'
    );
}