<?php
if (!defined('ABSPATH')) {
    exit;
}

// Function is called from admin-menu.php, no need to add_action here
function wns_add_subscriber_list_page() {
    add_submenu_page(
        'wns-settings',
        __('Subscribers', 'wp-newsletter-subscription'),
        __('Subscribers', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-subscribers',
        'wns_render_subscriber_list_page'
    );
}