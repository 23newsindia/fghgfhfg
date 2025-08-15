<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'wns_register_unsubscribe_page_setting');

function wns_register_unsubscribe_page_setting() {
    register_setting('wns_settings_group', 'wns_unsubscribe_page_id', array(
        'type' => 'integer',
        'default' => 0,
    ));
}