<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('user_register', 'wns_auto_add_registered_user_to_subscribers');

function wns_auto_add_registered_user_to_subscribers($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
        return;
    }

    if (!wns_email_exists_in_subscribers($user->user_email)) {
        wns_add_subscriber_to_db($user->user_email);
    }
}