<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Meta Box for Newsletter Sending Options
 */

// Add meta box to post editor
add_action('add_meta_boxes', 'wns_add_post_newsletter_meta_box');

function wns_add_post_newsletter_meta_box() {
    add_meta_box(
        'wns_newsletter_options',
        __('📧 Newsletter Sending Options', 'wp-newsletter-subscription'),
        'wns_render_post_newsletter_meta_box',
        'post',
        'side',
        'high'
    );
}

function wns_render_post_newsletter_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('wns_post_newsletter_meta', 'wns_post_newsletter_nonce');
    
    // Get current settings
    $auto_send_enabled = get_post_meta($post->ID, '_wns_auto_send_enabled', true);
    $send_to_selected = get_post_meta($post->ID, '_wns_send_to_selected', true);
    $selected_subscribers = get_post_meta($post->ID, '_wns_selected_subscribers', true);
    $already_sent = get_post_meta($post->ID, '_wns_notification_sent', true);
    
    // Get custom email fields
    $custom_email_title = get_post_meta($post->ID, '_wns_custom_email_title', true);
    $custom_email_description = get_post_meta($post->ID, '_wns_custom_email_description', true);
    
    // Default to enabled for new posts
    if ($auto_send_enabled === '') {
        $auto_send_enabled = get_option('wns_enable_new_post_notification', false) ? '1' : '0';
    }
    
    if (!is_array($selected_subscribers)) {
        $selected_subscribers = array();
    }
    
    // Get subscriber count
    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    $total_subscribers = 0;
    
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
        $total_subscribers = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE verified = %d", 1));
    }
    
    ?>
    <div class="wns-newsletter-options">
        <?php if ($already_sent): ?>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                <strong style="color: #0c5460;">✅ Newsletter Already Sent</strong>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #0c5460;">
                    This post has already been sent to subscribers. To send again, you can manually send from the newsletter broadcast page.
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Custom Email Content Section -->
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h4 style="margin: 0 0 15px 0; color: #495057;">📧 Custom Email Content</h4>
            
            <div style="margin-bottom: 15px;">
                <label for="wns_custom_email_title" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    <?php _e('Custom Email Title:', 'wp-newsletter-subscription'); ?>
                </label>
                <input type="text" 
                       id="wns_custom_email_title" 
                       name="wns_custom_email_title" 
                       value="<?php echo esc_attr($custom_email_title); ?>" 
                       placeholder="<?php echo esc_attr(get_the_title($post->ID) ?: 'Post title will be used'); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                    <?php _e('Leave blank to use the post title automatically.', 'wp-newsletter-subscription'); ?>
                </p>
            </div>
            
            <div style="margin-bottom: 10px;">
                <label for="wns_custom_email_description" style="display: block; font-weight: bold; margin-bottom: 5px;">
                    <?php _e('Custom Email Description:', 'wp-newsletter-subscription'); ?>
                </label>
                <?php
                wp_editor($custom_email_description, 'wns_custom_email_description', array(
                    'textarea_name' => 'wns_custom_email_description',
                    'media_buttons' => false,
                    'textarea_rows' => 6,
                    'teeny' => true,
                    'quicktags' => array(
                        'buttons' => 'strong,em,link,ul,ol,li,close'
                    ),
                    'tinymce' => array(
                        'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist,undo,redo',
                        'toolbar2' => '',
                        'height' => 150,
                        'resize' => true,
                        'menubar' => false,
                        'statusbar' => false,
                        'content_style' => 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; }'
                    )
                ));
                ?>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                    <?php _e('HTML editor for rich email content. Press Enter twice for new paragraphs. Leave blank to use post excerpt. Supports: bold, italic, links, lists, blockquotes.', 'wp-newsletter-subscription'); ?>
                </p>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; font-weight: bold;">
                <input type="checkbox" name="wns_auto_send_enabled" value="1" <?php checked($auto_send_enabled, '1'); ?> style="margin-right: 8px;" />
                <?php _e('Send Newsletter on Publish', 'wp-newsletter-subscription'); ?>
            </label>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                <?php _e('Automatically send this post to subscribers when published.', 'wp-newsletter-subscription'); ?>
            </p>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; font-weight: bold;">
                <input type="checkbox" name="wns_send_on_save" value="1" style="margin-right: 8px;" />
                <?php _e('Send Newsletter on Save', 'wp-newsletter-subscription'); ?>
            </label>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                <?php _e('Send newsletter immediately when you save/update this post (even if not published yet).', 'wp-newsletter-subscription'); ?>
            </p>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="font-weight: bold; display: block; margin-bottom: 8px;">
                <?php _e('Send To:', 'wp-newsletter-subscription'); ?>
            </label>
            
            <label style="display: flex; align-items: center; margin-bottom: 8px;">
                <input type="radio" name="wns_send_to_selected" value="0" <?php checked($send_to_selected, '0'); ?> style="margin-right: 8px;" />
                <?php printf(__('All Verified Subscribers (%d)', 'wp-newsletter-subscription'), $total_subscribers); ?>
            </label>
            
            <label style="display: flex; align-items: center;">
                <input type="radio" name="wns_send_to_selected" value="1" <?php checked($send_to_selected, '1'); ?> style="margin-right: 8px;" />
                <?php _e('Selected Subscribers Only', 'wp-newsletter-subscription'); ?>
            </label>
        </div>
        
        <div id="wns-subscriber-selection" style="<?php echo $send_to_selected == '1' ? '' : 'display: none;'; ?>">
            <div style="margin-bottom: 10px;">
                <button type="button" id="wns-select-subscribers-btn" class="button button-secondary" style="width: 100%;">
                    <?php _e('Select Subscribers', 'wp-newsletter-subscription'); ?>
                    <span id="wns-selected-count"><?php echo count($selected_subscribers) > 0 ? '(' . count($selected_subscribers) . ' selected)' : ''; ?></span>
                </button>
            </div>
            
            <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #666;">
                <strong><?php _e('Selected subscribers:', 'wp-newsletter-subscription'); ?></strong>
                <div id="wns-selected-preview">
                    <?php if (count($selected_subscribers) > 0): ?>
                        <?php echo esc_html(implode(', ', array_slice($selected_subscribers, 0, 3))); ?>
                        <?php if (count($selected_subscribers) > 3): ?>
                            <?php printf(__('... and %d more', 'wp-newsletter-subscription'), count($selected_subscribers) - 3); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php _e('None selected', 'wp-newsletter-subscription'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!$already_sent && $post->post_status === 'publish'): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <button type="button" id="wns-send-now-btn" class="button button-primary" style="width: 100%;">
                    <?php _e('📧 Send Newsletter Now', 'wp-newsletter-subscription'); ?>
                </button>
                <p style="margin: 5px 0 0 0; font-size: 11px; color: #666; text-align: center;">
                    <?php _e('Send immediately to selected subscribers', 'wp-newsletter-subscription'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Hidden input to store selected subscribers -->
    <input type="hidden" name="wns_selected_subscribers" id="wns-selected-subscribers-input" value="<?php echo esc_attr(json_encode($selected_subscribers)); ?>" />
    
    <style>
    .wns-newsletter-options label {
        cursor: pointer;
    }
    .wns-newsletter-options input[type="checkbox"],
    .wns-newsletter-options input[type="radio"] {
        cursor: pointer;
    }
    #wns-select-subscribers-btn {
        position: relative;
    }
    #wns-selected-count {
        font-size: 11px;
        color: #666;
    }
    </style>
    
    <script>
    (function($) {
        $(document).ready(function() {
            // Toggle subscriber selection visibility
            $('input[name="wns_send_to_selected"]').change(function() {
                if ($(this).val() === '1') {
                    $('#wns-subscriber-selection').show();
                } else {
                    $('#wns-subscriber-selection').hide();
                }
            });
            
            // Open subscriber selection modal
            $('#wns-select-subscribers-btn').click(function() {
                wns_open_subscriber_modal();
            });
            
            // Send newsletter now
            $('#wns-send-now-btn').click(function() {
                if (confirm('<?php echo esc_js(__('Send newsletter to selected subscribers now?', 'wp-newsletter-subscription')); ?>')) {
                    wns_send_newsletter_now();
                }
            });
        });
        
        // Modal functions
        window.wns_open_subscriber_modal = function() {
            // Create modal HTML
            var modal = $('<div id="wns-subscriber-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;"></div>');
            var content = $('<div style="background: white; width: 90%; max-width: 700px; max-height: 90%; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);"></div>');
            var header = $('<div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa; position: relative;"><button type="button" onclick="wns_close_subscriber_modal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666; padding: 5px; line-height: 1;" title="Close">&times;</button><h3 style="margin: 0 0 15px 0; padding-right: 40px;">Select Subscribers</h3><div style="position: relative;"><input type="text" id="wns-subscriber-search" placeholder="Search subscribers by email..." style="width: 100%; padding: 10px 40px 10px 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;" /><span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666; pointer-events: none;">🔍</span></div></div>');
            var body = $('<div style="padding: 20px; max-height: 500px; overflow-y: auto;"><div class="loading">Loading subscribers...</div></div>');
            var footer = $('<div style="padding: 20px; border-top: 1px solid #ddd; background: #f8f9fa; text-align: right;"><button class="button button-secondary" onclick="wns_close_subscriber_modal()">Cancel</button> <button class="button button-primary" onclick="wns_save_selected_subscribers()">Save Selection</button></div>');
            
            content.append(header, body, footer);
            modal.append(content);
            $('body').append(modal);
            
            // Close modal when clicking outside the content area
            modal.on('click', function(e) {
                if (e.target === this) {
                    wns_close_subscriber_modal();
                }
            });
            
            // Close modal when pressing Escape key
            $(document).on('keydown.wns-modal', function(e) {
                if (e.keyCode === 27) { // Escape key
                    wns_close_subscriber_modal();
                }
            });
            
            // Prevent modal content clicks from closing the modal
            content.on('click', function(e) {
                e.stopPropagation();
            });
            
            // Load subscribers via AJAX
            $.post(ajaxurl, {
                action: 'wns_get_subscribers_for_selection',
                nonce: '<?php echo wp_create_nonce('wns_get_subscribers'); ?>',
                selected: JSON.parse($('#wns-selected-subscribers-input').val() || '[]')
            }, function(response) {
                if (response.success) {
                    body.html(response.data.html);
                    
                    // Initialize search functionality
                    $('#wns-subscriber-search').on('input', function() {
                        var searchTerm = $(this).val().toLowerCase();
                        var visibleCount = 0;
                        
                        $('#wns-subscriber-list .subscriber-item').each(function() {
                            var email = $(this).find('input[type="checkbox"]').val().toLowerCase();
                            if (email.includes(searchTerm)) {
                                $(this).show();
                                visibleCount++;
                            } else {
                                $(this).hide();
                            }
                        });
                        
                        // Update visible count
                        $('#wns-visible-count').text('(' + visibleCount + ' shown)');
                        
                        // Update "Select All" functionality for filtered results
                        $('#wns-select-all-visible').off('change').on('change', function() {
                            var isChecked = $(this).prop('checked');
                            $('#wns-subscriber-list .subscriber-item:visible input[type="checkbox"]').prop('checked', isChecked);
                        });
                    });
                    
                    // Focus on search input
                    setTimeout(function() {
                        $('#wns-subscriber-search').focus();
                    }, 100);
                } else {
                    body.html('<p style="color: red;">Error loading subscribers.</p>');
                }
            });
        };
        
        window.wns_close_subscriber_modal = function() {
            // Remove keydown event listener
            $(document).off('keydown.wns-modal');
            $('#wns-subscriber-modal').remove();
        };
        
        window.wns_save_selected_subscribers = function() {
            var selected = [];
            $('#wns-subscriber-modal #wns-subscriber-list input[type="checkbox"]:checked').each(function() {
                selected.push($(this).val());
            });
            
            $('#wns-selected-subscribers-input').val(JSON.stringify(selected));
            
            // Update preview
            $('#wns-selected-count').text(selected.length > 0 ? '(' + selected.length + ' selected)' : '');
            
            var preview = '';
            if (selected.length > 0) {
                preview = selected.slice(0, 3).join(', ');
                if (selected.length > 3) {
                    preview += '... and ' + (selected.length - 3) + ' more';
                }
            } else {
                preview = 'None selected';
            }
            $('#wns-selected-preview').text(preview);
            
            wns_close_subscriber_modal();
        };
        
        window.wns_send_newsletter_now = function() {
            var button = $('#wns-send-now-btn');
            button.prop('disabled', true).text('Sending...');
            
            $.post(ajaxurl, {
                action: 'wns_send_post_newsletter_now',
                post_id: <?php echo $post->ID; ?>,
                nonce: '<?php echo wp_create_nonce('wns_send_now_' . $post->ID); ?>',
                send_to_selected: $('input[name="wns_send_to_selected"]:checked').val(),
                selected_subscribers: $('#wns-selected-subscribers-input').val()
            }, function(response) {
                if (response.success) {
                    button.text('✅ Sent!').css('background', '#28a745');
                    alert('Newsletter sent successfully!');
                    location.reload();
                } else {
                    button.prop('disabled', false).text('📧 Send Newsletter Now');
                    alert('Error: ' + response.data.message);
                }
            });
        };
        
    })(jQuery);
    </script>
    <?php
}

// Save meta box data
add_action('save_post', 'wns_save_post_newsletter_meta');

function wns_save_post_newsletter_meta($post_id) {
    // Verify nonce
    if (!isset($_POST['wns_post_newsletter_nonce']) || !wp_verify_nonce($_POST['wns_post_newsletter_nonce'], 'wns_post_newsletter_meta')) {
        return;
    }
    
    // Check if user has permission to edit post
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Skip autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Save auto send setting
    $auto_send_enabled = isset($_POST['wns_auto_send_enabled']) ? '1' : '0';
    update_post_meta($post_id, '_wns_auto_send_enabled', $auto_send_enabled);
    
    // Save send to selected setting
    $send_to_selected = isset($_POST['wns_send_to_selected']) ? sanitize_text_field($_POST['wns_send_to_selected']) : '0';
    update_post_meta($post_id, '_wns_send_to_selected', $send_to_selected);
    
    // Save selected subscribers
    $selected_subscribers = isset($_POST['wns_selected_subscribers']) ? json_decode(stripslashes($_POST['wns_selected_subscribers']), true) : array();
    if (is_array($selected_subscribers)) {
        $selected_subscribers = array_map('sanitize_email', $selected_subscribers);
        $selected_subscribers = array_filter($selected_subscribers, 'is_email');
        update_post_meta($post_id, '_wns_selected_subscribers', $selected_subscribers);
    }
    
    // Save custom email fields
    $custom_email_title = isset($_POST['wns_custom_email_title']) ? sanitize_text_field($_POST['wns_custom_email_title']) : '';
    $custom_email_description = isset($_POST['wns_custom_email_description']) ? wp_kses_post($_POST['wns_custom_email_description']) : '';
    
    update_post_meta($post_id, '_wns_custom_email_title', $custom_email_title);
    update_post_meta($post_id, '_wns_custom_email_description', $custom_email_description);
    
    // Handle "Send Newsletter on Save" option
    if (isset($_POST['wns_send_on_save']) && $_POST['wns_send_on_save'] === '1') {
        error_log('WNS Debug: Send on Save triggered for post ID: ' . $post_id);
        
        // RESET the notification sent status when manually sending
        delete_post_meta($post_id, '_wns_notification_sent');
        error_log('WNS Debug: Reset notification sent status for manual send');
        
        // Ensure we have the post object
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            error_log('WNS Debug: Post not published, skipping send');
            return; // Only send for published posts
        }
        
        error_log('WNS Debug: Calling manual send with send_to_selected: ' . $send_to_selected . ', subscribers: ' . print_r($selected_subscribers, true));
        
        // Send newsletter immediately
        $result = wns_send_post_newsletter_manual($post_id, $send_to_selected, $selected_subscribers);
        
        error_log('WNS Debug: Manual send result: ' . print_r($result, true));
        
        if ($result['success']) {
            // Mark as sent
            update_post_meta($post_id, '_wns_notification_sent', true);
            
            // Add admin notice
            set_transient('wns_admin_notice_' . get_current_user_id(), array(
                'type' => 'success',
                'message' => 'Newsletter Sent! ' . $result['message']
            ), 30);
            
            error_log('WNS Debug: Newsletter marked as sent and admin notice set');
        } else {
            // Add error notice
            set_transient('wns_admin_notice_' . get_current_user_id(), array(
                'type' => 'error',
                'message' => 'Newsletter Error: ' . $result['message']
            ), 30);
            
            error_log('WNS Debug: Newsletter send failed: ' . $result['message']);
        }
    }
}

// Display admin notices from transients
add_action('admin_notices', 'wns_display_post_meta_notices');

function wns_display_post_meta_notices() {
    $notice = get_transient('wns_admin_notice_' . get_current_user_id());
    if ($notice) {
        delete_transient('wns_admin_notice_' . get_current_user_id());
        $class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible">';
        echo '<p><strong>' . esc_html($notice['message']) . '</strong></p>';
        echo '</div>';
    }
}

// AJAX handler to get subscribers for selection
add_action('wp_ajax_wns_get_subscribers_for_selection', 'wns_ajax_get_subscribers_for_selection');

function wns_ajax_get_subscribers_for_selection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wns_get_subscribers')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('edit_posts')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $table_name = WNS_TABLE_SUBSCRIBERS;
    
    // Get all verified subscribers
    $subscribers = $wpdb->get_results($wpdb->prepare(
        "SELECT email FROM `$table_name` WHERE verified = %d ORDER BY email ASC",
        1
    ));
    
    $selected = isset($_POST['selected']) ? (array) $_POST['selected'] : array();
    
    if (empty($subscribers)) {
        wp_send_json_success(array(
            'html' => '<p>No verified subscribers found.</p>'
        ));
        return;
    }
    
    $html = '<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
        <label style="font-weight: bold;">
            <input type="checkbox" id="wns-select-all-visible" style="margin-right: 8px;" />
            Select All Visible <span id="wns-visible-count">(' . count($subscribers) . ' shown)</span>
        </label>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
            Total subscribers: ' . count($subscribers) . ' | Selected: <span id="wns-current-selected">' . count($selected) . '</span>
        </div>
    </div>';
    
    $html .= '<div id="wns-subscriber-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">';
    
    foreach ($subscribers as $subscriber) {
        $checked = in_array($subscriber->email, $selected) ? 'checked' : '';
        $html .= '<label class="subscriber-item" style="display: block; padding: 8px 0; cursor: pointer; border-bottom: 1px solid #eee; margin-bottom: 5px;">
            <input type="checkbox" value="' . esc_attr($subscriber->email) . '" ' . $checked . ' style="margin-right: 8px;" />
            <span style="font-family: monospace; font-size: 13px;">' . esc_html($subscriber->email) . '</span>
        </label>';
    }
    
    $html .= '</div>';
    
    $html .= '<script>
        jQuery(function($) {
            // Real-time update of selected count
            // Update selected count when checkboxes change
            function updateSelectedCount() {
                var selectedCount = $("#wns-subscriber-list input[type=\"checkbox\"]:checked").length;
                $("#wns-current-selected").text(selectedCount);
                
                // Also update the "Select All" checkbox state
                var totalVisible = $("#wns-subscriber-list .subscriber-item:visible").length;
                var selectedVisible = $("#wns-subscriber-list .subscriber-item:visible input[type=\"checkbox\"]:checked").length;
                
                if (selectedVisible === 0) {
                    $("#wns-select-all-visible").prop("checked", false).prop("indeterminate", false);
                } else if (selectedVisible === totalVisible) {
                    $("#wns-select-all-visible").prop("checked", true).prop("indeterminate", false);
                } else {
                    $("#wns-select-all-visible").prop("checked", false).prop("indeterminate", true);
                }
            }
            
            // Initial count update
            updateSelectedCount();
            
            // Update count when individual checkboxes change
            $(document).on("change", "#wns-subscriber-list input[type=\"checkbox\"]", function() {
                updateSelectedCount();
            });
            
            // Select all visible functionality
            $("#wns-select-all-visible").change(function() {
                var isChecked = $(this).prop("checked");
                $("#wns-subscriber-list .subscriber-item:visible input[type=\"checkbox\"]").prop("checked", isChecked);
                updateSelectedCount();
            });
            
            // Update search functionality to maintain selection state
            $("#wns-subscriber-search").on("input", function() {
                var searchTerm = $(this).val().toLowerCase();
                var visibleCount = 0;
                
                $("#wns-subscriber-list .subscriber-item").each(function() {
                    var email = $(this).find("input[type=\"checkbox\"]").val().toLowerCase();
                    if (email.includes(searchTerm)) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });
                
                // Update visible count
                $("#wns-visible-count").text("(" + visibleCount + " shown)");
                
                // Update selected count and select all state
                updateSelectedCount();
            });
        });
    </script>';
    
    wp_send_json_success(array('html' => $html));
}

// AJAX handler to send newsletter now
add_action('wp_ajax_wns_send_post_newsletter_now', 'wns_ajax_send_post_newsletter_now');

function wns_ajax_send_post_newsletter_now() {
    $post_id = intval($_POST['post_id']);
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wns_send_now_' . $post_id)) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        wp_send_json_error(array('message' => 'Post not found or not published'));
    }
    
    // Check if already sent
    if (get_post_meta($post_id, '_wns_notification_sent', true)) {
        wp_send_json_error(array('message' => 'Newsletter already sent for this post'));
    }
    
    $send_to_selected = sanitize_text_field($_POST['send_to_selected']);
    $selected_subscribers = json_decode(stripslashes($_POST['selected_subscribers']), true);
    
    if (!is_array($selected_subscribers)) {
        $selected_subscribers = array();
    }
    
    // Send the newsletter
    $result = wns_send_post_newsletter_manual($post_id, $send_to_selected, $selected_subscribers);
    
    if ($result['success']) {
        // Mark as sent
        update_post_meta($post_id, '_wns_notification_sent', true);
        wp_send_json_success(array('message' => $result['message']));
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}