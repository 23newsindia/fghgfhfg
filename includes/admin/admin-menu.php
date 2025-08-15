<?php
if (!defined('ABSPATH')) {
    exit;
}

// This file consolidates all admin menu registrations
add_action('admin_menu', 'wns_register_all_admin_menus', 10);

function wns_register_all_admin_menus() {
    // Subscribers submenu
    add_submenu_page(
        'wns-settings',
        __('Subscribers', 'wp-newsletter-subscription'),
        __('Subscribers', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-subscribers',
        'wns_render_subscriber_list_page'
    );

    // Send Newsletter submenu
    add_submenu_page(
        'wns-settings',
        __('Send Newsletter', 'wp-newsletter-subscription'),
        __('Send Newsletter', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-send-newsletter',
        'wns_render_broadcast_page'
    );

    // Email Queue submenu
    add_submenu_page(
        'wns-settings',
        __('Email Queue', 'wp-newsletter-subscription'),
        __('Email Queue', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-email-queue',
        'wns_render_email_queue_page'
    );

    // Import/Export submenu
    add_submenu_page(
        'wns-settings',
        __('Import / Export Subscribers', 'wp-newsletter-subscription'),
        __('Import / Export', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-import-export',
        'wns_render_import_export_page'
    );
}