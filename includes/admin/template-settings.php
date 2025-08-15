<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'wns_register_template_settings');

function wns_register_template_settings() {
    register_setting('wns_settings_group', 'wns_template_subscribe_subject', array(
        'type' => 'string',
        'default' => __('Welcome to Our Newsletter!', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_subscribe_body', array(
        'type' => 'string',
        'default' => __("Thank you for subscribing to our newsletter!\n\nClick the link below to verify your email:\n\n{verify_link}\n\nTo unsubscribe at any time: {unsubscribe_link}", 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_unsubscribe_subject', array(
        'type' => 'string',
        'default' => __('You Have Been Unsubscribed', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_unsubscribe_body', array(
        'type' => 'string',
        'default' => __("You have successfully unsubscribed from our newsletter. We're sorry to see you go!\n\nIf this was a mistake, you can resubscribe here: {unsubscribe_link}", 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_subject', array(
        'type' => 'string',
        'default' => __('New Blog Post: {post_title}', 'wp-newsletter-subscription')
    ));

    register_setting('wns_settings_group', 'wns_template_new_post_body', array(
        'type' => 'string',
        'default' => __("Hi there,\n\nWe've just published a new blog post that you might enjoy:\n\n{post_title}\n{post_excerpt}\n\nRead more: {post_url}\n\nThanks,\nThe Team\n\n{unsubscribe_link}", 'wp-newsletter-subscription')
    ));
}