<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'wns_register_post_notification_settings');

function wns_register_post_notification_settings() {
    register_setting('wns_settings_group', 'wns_enable_new_post_notification', array(
        'type' => 'boolean',
        'default' => false,
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_subject', array(
        'type' => 'string',
        'default' => __('New Blog Post: {post_title}', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_body', array(
        'type' => 'string',
        'default' => __("Hi there,\n\nWe've just published a new blog post that you might enjoy:\n\n{post_title}\n{post_excerpt}\n\nRead more: {post_url}\n\nThanks,\nThe Team", 'wp-newsletter-subscription')
    ));
}