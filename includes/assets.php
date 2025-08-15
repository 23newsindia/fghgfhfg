<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'wns_enqueue_frontend_assets');

function wns_enqueue_frontend_assets() {
    wp_enqueue_style('wns-style', WNS_PLUGIN_URL . 'assets/css/style.css', array(), '1.0.0');
    
    // Enqueue download form validation script
    wp_enqueue_script('wns-download-validation', WNS_PLUGIN_URL . 'assets/js/download-form-validation.js', array(), '1.0.0', true);
}