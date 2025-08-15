<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WNS_Email_Queue_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => __('Email', 'wp-newsletter-subscription'),
            'plural'   => __('Emails', 'wp-newsletter-subscription'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'recipient' => __('Recipient', 'wp-newsletter-subscription'),
            'subject'   => __('Subject', 'wp-newsletter-subscription'),
            'send_at'   => __('Scheduled For', 'wp-newsletter-subscription'),
            'sent_at'   => __('Sent At', 'wp-newsletter-subscription'),
            'status'    => __('Status', 'wp-newsletter-subscription')
        ];
    }

    public function get_sortable_columns() {
        return [
            'recipient' => ['recipient', false],
            'subject'   => ['subject', false],
            'send_at'   => ['send_at', true],
            'sent_at'   => ['sent_at', false],
            'status'    => ['sent', false]
        ];
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'recipient':
                return esc_html($item->recipient);
            case 'subject':
                return esc_html(wp_trim_words($item->subject, 10));
            case 'send_at':
            case 'sent_at':
                return $item->$column_name ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->$column_name))) : '—';
            default:
                return '';
        }
    }

    function column_status($item) {
        return $item->sent ? 
            '<span class="dashicons dashicons-yes" style="color: green;"></span> ' . esc_html__('Sent', 'wp-newsletter-subscription') : 
            '<span class="dashicons dashicons-clock" style="color: orange;"></span> ' . esc_html__('Pending', 'wp-newsletter-subscription');
    }

    function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'wp-newsletter-subscription'),
            'resend' => __('Mark for Resend', 'wp-newsletter-subscription'),
            'delete_all_pending' => __('Delete All Pending', 'wp-newsletter-subscription')
        );
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="email[]" value="%d" />',
            absint($item->id)
        );
    }

    public function prepare_items() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-newsletter-subscription'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'newsletter_email_queue';

        // Verify table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return;
        }

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Handle bulk actions with security checks
        $this->process_bulk_action();

        // Build query with proper sanitization
        $where_conditions = [];
        $where_values = [];

        // Status filter
        if (isset($_REQUEST['status']) && in_array($_REQUEST['status'], ['sent', 'pending'])) {
            $status_filter = sanitize_text_field($_REQUEST['status']);
            $where_conditions[] = "`sent` = %d";
            $where_values[] = ($status_filter === 'sent') ? 1 : 0;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Ordering with whitelist
        $allowed_orderby = ['recipient', 'subject', 'send_at', 'sent_at', 'sent'];
        $orderby = 'send_at';
        if (!empty($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $allowed_orderby)) {
            $orderby = sanitize_key($_REQUEST['orderby']);
        }

        $order = 'DESC';
        if (!empty($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC'])) {
            $order = strtoupper(sanitize_text_field($_REQUEST['order']));
        }

        // Allow different per-page options
        $allowed_per_page = [20, 50, 100, 200, 500];
        $per_page = 20; // default
        if (!empty($_REQUEST['per_page']) && in_array((int)$_REQUEST['per_page'], $allowed_per_page)) {
            $per_page = (int)$_REQUEST['per_page'];
        }
        
        $current_page = $this->get_pagenum();

        // Count total items
        $count_query = "SELECT COUNT(*) FROM `$table_name` $where_clause";
        if (!empty($where_values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));

        $offset = ($current_page - 1) * $per_page;

        // Get items
        $query = "SELECT * FROM `$table_name` $where_clause ORDER BY `$orderby` $order LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);

        if (!empty($where_values)) {
            $this->items = $wpdb->get_results($wpdb->prepare($query, $query_values));
        } else {
            $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table_name` ORDER BY `$orderby` $order LIMIT %d OFFSET %d", $per_page, $offset));
        }
    }

    public function process_bulk_action() {
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        
        // Handle delete all pending action (doesn't need selected items)
        if ($action === 'delete_all_pending') {
            // Verify nonce
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
                wp_die(__('Security check failed.', 'wp-newsletter-subscription'));
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'newsletter_email_queue';
            
            // Get count first for confirmation
            $pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `sent` = %d", 0));
            
            if ($pending_count > 0) {
                $deleted = $wpdb->query($wpdb->prepare("DELETE FROM `$table_name` WHERE `sent` = %d", 0));
                
                if ($deleted !== false) {
                    // Add admin notice
                    add_action('admin_notices', function() use ($pending_count) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>' . sprintf(__('%d pending emails have been deleted from the queue.', 'wp-newsletter-subscription'), $pending_count) . '</strong></p>';
                        echo '</div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible">';
                        echo '<p><strong>' . __('Error deleting pending emails.', 'wp-newsletter-subscription') . '</strong></p>';
                        echo '</div>';
                    });
                }
            }
            return;
        }
        
        // Handle regular bulk actions that need selected items
        if (!isset($_REQUEST['email'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            wp_die(__('Security check failed.', 'wp-newsletter-subscription'));
        }

        $ids = array_map('absint', (array)$_REQUEST['email']);
        $ids = array_filter($ids); // Remove any zero values
        
        if (empty($ids)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'newsletter_email_queue';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        switch ($action) {
            case 'delete':
                $wpdb->query($wpdb->prepare("DELETE FROM `$table_name` WHERE `id` IN ($placeholders)", $ids));
                break;
            case 'resend':
                $wpdb->query($wpdb->prepare("UPDATE `$table_name` SET `sent` = 0, `sent_at` = NULL, `send_at` = NOW() WHERE `id` IN ($placeholders)", $ids));
                break;
        }
    }

    function extra_tablenav($which) {
        if ($which === 'top') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'newsletter_email_queue';
            $pending_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `sent` = %d", 0));
            
            $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            $current_per_page = isset($_REQUEST['per_page']) ? (int)$_REQUEST['per_page'] : 20;
            ?>
            <div class="alignleft actions">
                <label for="filter-by-status" class="screen-reader-text"><?php esc_html_e('Filter by status', 'wp-newsletter-subscription'); ?></label>
                <select name="status" id="filter-by-status">
                    <option value=""><?php esc_html_e('All Statuses', 'wp-newsletter-subscription'); ?></option>
                    <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'wp-newsletter-subscription'); ?></option>
                    <option value="sent" <?php selected($status, 'sent'); ?>><?php esc_html_e('Sent', 'wp-newsletter-subscription'); ?></option>
                </select>
                
                <label for="per-page" style="margin-left: 15px;"><?php esc_html_e('Show:', 'wp-newsletter-subscription'); ?></label>
                <select name="per_page" id="per-page">
                    <option value="20" <?php selected($current_per_page, 20); ?>>20 per page</option>
                    <option value="50" <?php selected($current_per_page, 50); ?>>50 per page</option>
                    <option value="100" <?php selected($current_per_page, 100); ?>>100 per page</option>
                    <option value="200" <?php selected($current_per_page, 200); ?>>200 per page</option>
                    <option value="500" <?php selected($current_per_page, 500); ?>>500 per page</option>
                </select>
                
                <?php submit_button(__('Filter', 'wp-newsletter-subscription'), '', 'filter_action', false); ?>
            </div>
            
            <?php if ($pending_count > 0): ?>
            <div class="alignright actions" style="margin-left: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 15px; border-radius: 4px; display: inline-block;">
                    <strong style="color: #856404;">⏳ <?php echo $pending_count; ?> Pending Emails</strong>
                    <form method="post" style="display: inline-block; margin-left: 10px;" onsubmit="return confirm('<?php echo esc_js(sprintf(__('Are you sure you want to delete all %d pending emails from the queue? This action cannot be undone.', 'wp-newsletter-subscription'), $pending_count)); ?>');">
                        <?php wp_nonce_field('bulk-' . $this->_args['plural']); ?>
                        <input type="hidden" name="action" value="delete_all_pending" />
                        <button type="submit" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;">
                            🗑️ Delete All Pending
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php
        }
    }
}