<?php
if (!defined('ABSPATH')) {
    exit;
}

// Function is called from admin-menu.php, no need to add_action here
function wns_add_import_export_page() {
    add_submenu_page(
        'wns-settings',
        __('Import / Export Subscribers', 'wp-newsletter-subscription'),
        __('Import / Export', 'wp-newsletter-subscription'),
        'manage_options',
        'wns-import-export',
        'wns_render_import_export_page'
    );
}

function wns_render_import_export_page() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-newsletter-subscription'));
    }

    $message = '';

    // Handle export
    if (isset($_GET['action']) && $_GET['action'] === 'export' && check_admin_referer('wns_export_subscribers')) {
        wns_export_subscribers();
        exit;
    }

    // Handle import
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_subscribers']) && check_admin_referer('wns_import_subscribers')) {
        if (!empty($_FILES['subscriber_csv']['tmp_name'])) {
            $file = $_FILES['subscriber_csv'];
            
            // Enhanced file validation
            $validation_result = wns_validate_csv_file($file);
            if ($validation_result !== true) {
                $message = '<span class="error">' . esc_html($validation_result) . '</span>';
            } else {
                $result = wns_import_subscribers_from_csv($file['tmp_name']);
                if ($result['success']) {
                    $message = sprintf(
                        _n('%d subscriber imported successfully.', '%d subscribers imported successfully.', $result['count'], 'wp-newsletter-subscription'),
                        $result['count']
                    );
                } else {
                    $message = '<span class="error">' . esc_html($result['error']) . '</span>';
                }
            }
        } else {
            $message = '<span class="error">' . esc_html__('Please select a CSV file to import.', 'wp-newsletter-subscription') . '</span>';
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Import / Export Newsletter Subscribers', 'wp-newsletter-subscription'); ?></h1>

        <?php if ($message): ?>
            <div id="message" class="updated fade">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e('Export Subscribers', 'wp-newsletter-subscription'); ?></h2>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wns-import-export&action=export'), 'wns_export_subscribers'); ?>" class="button button-primary">
                <?php esc_html_e('Export All Subscribers', 'wp-newsletter-subscription'); ?>
            </a>
        </p>

        <h2><?php esc_html_e('Import Subscribers', 'wp-newsletter-subscription'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wns_import_subscribers'); ?>
            <input type="file" name="subscriber_csv" accept=".csv" required />
            <p class="description"><?php esc_html_e('CSV must have one column with header "email". Maximum file size: 2MB.', 'wp-newsletter-subscription'); ?></p>
            <br />
            <button type="submit" name="import_subscribers" class="button button-primary"><?php esc_html_e('Import Subscribers', 'wp-newsletter-subscription'); ?></button>
        </form>
    </div>
    <?php
}

function wns_validate_csv_file($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return __('File upload error occurred.', 'wp-newsletter-subscription');
    }

    // Check file size (2MB limit)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        return __('File size exceeds 2MB limit.', 'wp-newsletter-subscription');
    }

    // Check file extension
    $file_info = pathinfo($file['name']);
    if (!isset($file_info['extension']) || strtolower($file_info['extension']) !== 'csv') {
        return __('Only CSV files are allowed.', 'wp-newsletter-subscription');
    }

    // Enhanced MIME type checking
    $allowed_types = array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel');
    $file_type = wp_check_filetype($file['name'], array('csv' => 'text/csv'));
    
    if (!in_array($file_type['type'], $allowed_types)) {
        return __('Invalid file type. Only CSV files are allowed.', 'wp-newsletter-subscription');
    }

    // Additional security: Check file content
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        return __('Unable to read file.', 'wp-newsletter-subscription');
    }

    $first_line = fgets($handle);
    fclose($handle);

    // Basic check for CSV format and prevent code injection
    if (strpos($first_line, 'email') === false) {
        return __('CSV file must contain an "email" column header.', 'wp-newsletter-subscription');
    }

    // Check for potential malicious content
    $dangerous_patterns = array('<?php', '<script', 'javascript:', 'data:', 'vbscript:');
    foreach ($dangerous_patterns as $pattern) {
        if (stripos($first_line, $pattern) !== false) {
            return __('File contains potentially dangerous content.', 'wp-newsletter-subscription');
        }
    }

    return true;
}