<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add unsubscribe link to all outgoing emails
add_filter('wp_mail', 'wns_add_unsubscribe_link_to_email');

function wns_add_unsubscribe_link_to_email($args) {
    // Skip if it's not a newsletter email
    if (strpos($args['message'], '{unsubscribe_link}') === false) {
        return $args;
    }

    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;

    // Try to find recipient in subscriber list
    $email = is_array($args['to']) ? reset($args['to']) : $args['to'];
    $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table_name` WHERE `email` = %s", $email));

    if (!$subscriber) {
        return $args;
    }

    $unsubscribe_page = get_option('wns_unsubscribe_page_id');
    if ($unsubscribe_page && get_post_status($unsubscribe_page) === 'publish') {
        $unsubscribe_url = get_permalink($unsubscribe_page);
    } else {
        $unsubscribe_url = home_url('/unsubscribe/');
    }

    $args['message'] = str_replace('{unsubscribe_link}', $unsubscribe_url, $args['message']);

    return $args;
}