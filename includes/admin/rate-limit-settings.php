<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'wns_register_rate_limit_settings');

function wns_register_rate_limit_settings() {
    register_setting('wns_settings_group', 'wns_email_batch_size', array(
        'type' => 'integer',
        'default' => 100,
    ));

    register_setting('wns_settings_group', 'wns_email_send_interval_minutes', array(
        'type' => 'integer',
        'default' => 5,
    ));
}