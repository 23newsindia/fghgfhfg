<?php
if (!defined('ABSPATH')) { exit; }

// Register AJAX + non-AJAX handlers (idempotent)
if ( ! has_action('wp_ajax_form_download_submit', 'wns_handle_form_download_submit') ) {
    add_action('wp_ajax_form_download_submit', 'wns_handle_form_download_submit');
}
if ( ! has_action('wp_ajax_nopriv_form_download_submit', 'wns_handle_form_download_submit') ) {
    add_action('wp_ajax_nopriv_form_download_submit', 'wns_handle_form_download_submit');
}
if ( ! has_action('template_redirect', 'wns_handle_form_download_submit') ) {
    add_action('template_redirect', 'wns_handle_form_download_submit');
}


/**
 * Download Handler for Email Verification System (stable)
 * - Compatible with your existing utils.php and templates
 * - No duplicate function definitions
 * - Better file URL resolving
 * - JSON response for AJAX, redirect for classic POST
 */

/* -------------------------------------------------------
 * TABLE CREATION
 * ----------------------------------------------------- */
if (!function_exists('wns_create_download_tokens_table')) {
    add_action('init', 'wns_create_download_tokens_table');
    function wns_create_download_tokens_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'newsletter_download_tokens';

        // Only create if missing
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                token varchar(64) NOT NULL,
                file_url text NOT NULL,
                post_id bigint(20) NOT NULL,
                block_id varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime NOT NULL,
                used tinyint(1) DEFAULT 0,
                verified tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY email (email),
                KEY token (token),
                KEY expires_at (expires_at)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}

/* -------------------------------------------------------
 * FILE URL RESOLVER (robust)
 * ----------------------------------------------------- */
if (!function_exists('wns_resolve_file_url')) {
    function wns_resolve_file_url($post_id, $block_id) {
        // A) Direct form pass-through
        if (!empty($_POST['fileUrl'])) {
            $u = esc_url_raw(wp_unslash($_POST['fileUrl']));
            if ($u && filter_var($u, FILTER_VALIDATE_URL)) return $u;
        }

        // B) Common meta keys
        $meta_keys = array(
            'wns_download_url_' . $block_id,
            'wns_download_url',
            'download_url',
            'rb_download_url',
            'rb_file_url',
            'foxiz_download_url',
            'foxiz_file_url',
            'file_url',
        );
        foreach ($meta_keys as $k) {
            $v = get_post_meta($post_id, $k, true);
            if ($v && filter_var($v, FILTER_VALIDATE_URL)) return esc_url_raw($v);
        }

        // C) Attachment IDs
        $id_keys = array(
            'wns_download_attachment_id',
            'download_attachment_id',
            'rb_download_attachment_id',
            'foxiz_download_attachment_id',
        );
        foreach ($id_keys as $k) {
            $aid = (int) get_post_meta($post_id, $k, true);
            if ($aid) {
                $url = wp_get_attachment_url($aid);
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) return esc_url_raw($url);
            }
        }

        // D) Parse Gutenberg block JSON/comments in post content
        $content = get_post_field('post_content', $post_id);
        if ($content) {
            // Match block JSON with download-ish names
            if (preg_match_all('/<!--\s+wp:([a-z0-9\-\/]+download)[\s\n]+(\{.*?\})\s+-->/is', $content, $m, PREG_SET_ORDER)) {
                foreach ($m as $blk) {
                    $json = json_decode($blk[2], true);
                    if (!is_array($json)) { continue; }
                    // Match by blockId when present
                    if (!empty($json['blockId']) && $block_id && $json['blockId'] !== $block_id) {
                        continue;
                    }
                    foreach (array('fileUrl','url','href','downloadUrl') as $attr) {
                        if (!empty($json[$attr]) && filter_var($json[$attr], FILTER_VALIDATE_URL)) {
                            return esc_url_raw($json[$attr]);
                        }
                    }
                }
            }
            // Look near the block id for data-* or href with file extension
            if ($block_id) {
                if (preg_match('/'.preg_quote($block_id,'/').'.{0,2000}?data-(?:file|file-url|url|download)=\"([^\"]+)\"/is', $content, $m1)) {
                    $u = $m1[1];
                    if ($u && filter_var($u, FILTER_VALIDATE_URL)) return esc_url_raw($u);
                }
                if (preg_match('/'.preg_quote($block_id,'/').'.{0,2000}?href=\"([^\"]+\.(?:zip|rar|7z|pdf|mp4|mov|gif|png|jpe?g|webp|json|safetensors|ckpt))\"/is', $content, $m2)) {
                    $u = $m2[1];
                    if ($u && filter_var($u, FILTER_VALIDATE_URL)) return esc_url_raw($u);
                }
            }
            // Generic “download” anchor
            if (preg_match('/class=\"[^\"]*(?:download|rb-download)[^\"]*\"[^>]*href=\"([^\"]+)\"/i', $content, $m3)) {
                $u = $m3[1];
                if ($u && filter_var($u, FILTER_VALIDATE_URL)) return esc_url_raw($u);
            }
        }

        // E) Allow theme/plugins to provide it
        $filtered = apply_filters('wns_resolve_download_url', '', $post_id, $block_id);
        if ($filtered && filter_var($filtered, FILTER_VALIDATE_URL)) return esc_url_raw($filtered);

        return '';
    }
}

/* -------------------------------------------------------
 * TOKEN GENERATION
 * ----------------------------------------------------- */
if (!function_exists('wns_generate_download_token')) {
    function wns_generate_download_token($email, $file_url, $post_id, $block_id, $requires_verification = false) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'newsletter_download_tokens';

        $token      = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', time() + (24 * HOUR_IN_SECONDS)); // 24h

        $ok = $wpdb->insert(
            $table_name,
            array(
                'email'     => sanitize_email($email),
                'token'     => $token,
                'file_url'  => esc_url_raw($file_url),
                'post_id'   => (int) $post_id,
                'block_id'  => sanitize_text_field($block_id),
                'expires_at'=> $expires_at,
                'used'      => 0,
                'verified'  => $requires_verification ? 0 : 1,
            ),
            array('%s','%s','%s','%d','%s','%s','%d','%d')
        );

        return $ok ? $token : false;
    }
}







/* -------------------------------------------------------
 * EMAIL SENDING (uses your templates + headers)
 * ----------------------------------------------------- */
if (!function_exists('wns_send_download_email')) {
    function wns_send_download_email($email, $file_url, $post_id, $block_id) {
        // ✅ avoid duplicate emails if user double-submits within ~60s
        $lock_key = 'wns_dl_lock_' . md5(strtolower($email) . '|' . (int) $post_id . '|' . $block_id);
        if (get_transient($lock_key)) {
            return true; // already processed recently
        }
        set_transient($lock_key, 1, 60);

        $require_verification    = (bool) get_option('wns_require_email_verification_for_downloads', false);
        $skip_for_verified_users = (bool) get_option('wns_skip_verification_for_verified_users', true);
        $is_verified             = function_exists('wns_is_subscriber_verified') ? wns_is_subscriber_verified($email) : false;

        // If verification is required BUT subscriber is already verified AND we skip verification,
        // do not send any email here (front-end still shows success)
       if ($require_verification && $is_verified && $skip_for_verified_users) {
    return 'skipped';
}

        $token = wns_generate_download_token($email, $file_url, $post_id, $block_id, $require_verification);
        if (!$token) { return false; }

        if ($require_verification && !$is_verified) {
            // Send verification email only
            return wns_send_download_verification_email($email, $token, $file_url);
        }

        // Send the download email now
        $download_link = add_query_arg(array(
            'wns_download' => '1',
            'token'        => $token,
            'email'        => urlencode($email),
        ), home_url());

        $subject = get_option('wns_download_email_subject', __('Your Download Link is Ready!', 'wp-newsletter-subscription'));
        $body    = get_option(
            'wns_download_email_body',
            __("Hi there,\n\nThank you for subscribing! Your download is ready.\n\nClick the link below to download your file:\n{download_link}\n\nThis link will expire in 24 hours for security reasons.\n\nBest regards,\nThe Team", 'wp-newsletter-subscription')
        );
        $body = str_replace('{download_link}', $download_link, $body);

        require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';
        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates', 'get_download_template')) {
            $email_content = WNS_Email_Templates::get_download_template($subject, nl2br(esc_html($body)), $download_link);
            $email_content = str_replace('{recipient_email}', esc_html($email), $email_content);

$unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
// keep your existing unsubscribe replacement (or replace it with the line below)
$email_content = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

            
        } elseif (class_exists('WNS_Email_Templates')) {
            $email_content = WNS_Email_Templates::get_newsletter_template($subject, nl2br(esc_html($body)));
        } else {
            $email_content = wpautop(esc_html($body));
        }

        $unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
        $email_content    = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

        $headers = function_exists('wns_build_plugin_headers')
    ? wns_build_plugin_headers($email, 'alert')
    : ( function_exists('wns_get_standard_email_headers')
          ? wns_get_standard_email_headers($email)
          : array('Content-Type: text/html; charset=UTF-8') );

// If the helper isn’t available, add our marker manually
if (!function_exists('wns_build_plugin_headers')) {
    $headers[] = 'X-WNS: alert';
}

return wp_mail($email, $subject, $email_content, $headers);

    }
}


if (!function_exists('wns_send_download_verification_email')) {




    function wns_send_download_verification_email($email, $download_token, $file_url) {
        $verify_link = add_query_arg(array(
            'wns_verify_download' => '1',
            'token'               => $download_token,
            'email'               => urlencode($email),
        ), home_url());

        $file_name = basename(parse_url($file_url, PHP_URL_PATH));

        require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';
        $subject = __('Verify Your Email to Download File', 'wp-newsletter-subscription');

        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates', 'get_download_verification_template')) {
            $email_content = WNS_Email_Templates::get_download_verification_template($verify_link, $file_name);
            $email_content = str_replace('{recipient_email}', esc_html($email), $email_content);

$unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
$email_content = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

            
            
            
        } elseif (class_exists('WNS_Email_Templates')) {
            // Simple fallback with the generic template
            $msg = 'Please verify your email to download <strong>' . esc_html($file_name) . '</strong>.<br><br>'
                 . '<a href="' . esc_url($verify_link) . '">Verify &amp; get the download link</a>';
            $email_content = WNS_Email_Templates::get_newsletter_template($subject, $msg);
        } else {
            $email_content = '<p><a href="' . esc_url($verify_link) . '">Verify &amp; get the download link</a></p>';
        }

        $unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
        $email_content    = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

        $headers = function_exists('wns_build_plugin_headers')
    ? wns_build_plugin_headers($email, 'alert') // lean transactional profile
    : ( function_exists('wns_get_standard_email_headers')
          ? wns_get_standard_email_headers($email)
          : array('Content-Type: text/html; charset=UTF-8') );

if (!function_exists('wns_build_plugin_headers')) {
    $headers[] = 'X-WNS: alert';
}

return wp_mail($email, $subject, $email_content, $headers);

    }
}

/* -------------------------------------------------------
 * VERIFY HANDLER (?wns_verify_download=1&token=..&email=..)
 * ----------------------------------------------------- */
if (!function_exists('wns_mark_download_token_verified')) {
    function wns_mark_download_token_verified($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'newsletter_download_tokens';
        $wpdb->update($table, array('verified' => 1), array('token' => sanitize_text_field($token)), array('%d'), array('%s'));
    }
}

add_action('init', 'wns_handle_download_verification');

if (!function_exists('wns_handle_download_verification')) {
    function wns_handle_download_verification() {
        if (empty($_GET['wns_verify_download']) || empty($_GET['token']) || empty($_GET['email'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['token']));
        $email = sanitize_email(wp_unslash($_GET['email']));

        if (!$token || !$email) {
            wp_die(__('Invalid verification request.', 'wp-newsletter-subscription'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'newsletter_download_tokens';

        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s AND email = %s AND used = 0 AND expires_at > NOW()",
            $token, $email
        ));

        if (!$token_data) {
            wp_die(__('Verification link has expired or is invalid.', 'wp-newsletter-subscription'));
        }
        
        if ((int)$token_data->verified === 1) {
    wp_redirect(add_query_arg('download_verified', 'success', home_url()));
    exit;
}

        /* ─────────────────────────────────────────────────────────────
           NEW: verify mail lock — prevents double “download ready” email
           when user double-clicks the verification link
        ───────────────────────────────────────────────────────────── */
        $verify_mail_lock = 'wns_v_mail_' . md5($token);
        if (get_transient($verify_mail_lock)) {
            // Already processed very recently; just show success
            wp_redirect(add_query_arg('download_verified', 'success', home_url()));
            exit;
        }
        set_transient($verify_mail_lock, 1, 120);
        /* ───────────────────────────────────────────────────────────── */

        // Add/update subscriber as verified (only if function exists elsewhere)
        if (function_exists('wns_add_or_update_subscriber')) {
            wns_add_or_update_subscriber($email, true);
        }

        // Mark token as verified
        wns_mark_download_token_verified($token);

        // Build final download link email (one email)
        $download_link = add_query_arg(array(
            'wns_download' => '1',
            'token'        => $token,
            'email'        => urlencode($email),
        ), home_url());

        $subject = get_option('wns_download_email_subject', __('Your Download Link is Ready!', 'wp-newsletter-subscription'));
        $body    = get_option(
            'wns_download_email_body',
            __("Hi there,\n\nThank you for verifying your email! Your download is ready.\n\nClick the link below to download your file:\n{download_link}\n\nThis link will expire in 24 hours for security reasons.\n\nBest regards,\nThe Team", 'wp-newsletter-subscription')
        );
        $body = str_replace('{download_link}', $download_link, $body);

        require_once WNS_PLUGIN_DIR . 'includes/email-templates.php';
        if (class_exists('WNS_Email_Templates') && method_exists('WNS_Email_Templates', 'get_download_template')) {
            $email_content = WNS_Email_Templates::get_download_template($subject, nl2br(esc_html($body)), $download_link);
            $email_content = str_replace('{recipient_email}', esc_html($email), $email_content);

$unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
$email_content = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

            
            
            
        } elseif (class_exists('WNS_Email_Templates')) {
            $email_content = WNS_Email_Templates::get_newsletter_template($subject, nl2br(esc_html($body)));
        } else {
            $email_content = wpautop(esc_html($body));
        }

        $unsubscribe_link = function_exists('wns_get_unsubscribe_link') ? wns_get_unsubscribe_link($email) : home_url('/unsubscribe/');
        $email_content    = str_replace('{unsubscribe_link}', esc_url($unsubscribe_link), $email_content);

        $headers = function_exists('wns_build_plugin_headers')
    ? wns_build_plugin_headers($email, 'alert')
    : ( function_exists('wns_get_standard_email_headers')
          ? wns_get_standard_email_headers($email)
          : array('Content-Type: text/html; charset=UTF-8') );

if (!function_exists('wns_build_plugin_headers')) {
    $headers[] = 'X-WNS: alert';
}

wp_mail($email, $subject, $email_content, $headers);


        if (function_exists('wns_log_email_activity')) {
            wns_log_email_activity($email, 'download_verified', 'Email verified for download: ' . $token_data->file_url);
        }

        wp_redirect(add_query_arg('download_verified', 'success', home_url()));
        exit;
    }
}


/* -------------------------------------------------------
 * SECURE DOWNLOAD (?wns_download=1&token=..&email=..)
 * ----------------------------------------------------- */
add_action('init', 'wns_handle_secure_download');
if (!function_exists('wns_handle_secure_download')) {
    function wns_handle_secure_download() {
        if (empty($_GET['wns_download']) || empty($_GET['token']) || empty($_GET['email'])) {
            return;
        }
        $token = sanitize_text_field(wp_unslash($_GET['token']));
        $email = sanitize_email(wp_unslash($_GET['email']));
        if (!$token || !$email) {
            wp_die(__('Invalid download request.', 'wp-newsletter-subscription'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'newsletter_download_tokens';

        $require_verification = (bool) get_option('wns_require_email_verification_for_downloads', false);
        $condition            = $require_verification ? 'AND verified = 1' : '';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s AND email = %s AND used = 0 AND expires_at > NOW() $condition",
            $token, $email
        ));

        if (!$row) {
            if ($require_verification) {
                wp_die(__('Download link has expired, is invalid, or email verification is required.', 'wp-newsletter-subscription'));
            }
            wp_die(__('Download link has expired or is invalid.', 'wp-newsletter-subscription'));
        }

        // Mark as used
        $wpdb->update($table, array('used' => 1), array('id' => $row->id), array('%d'), array('%d'));

        if (function_exists('wns_log_email_activity')) {
            wns_log_email_activity($email, 'file_downloaded', 'File downloaded via secure link: ' . $row->file_url);
        }

        wp_redirect($row->file_url);
        exit;
    }
}

/* -------------------------------------------------------
 * FORM SUBMIT HANDLER (classic POST or AJAX)
 * Your form posts: name="EMAIL", "postId", "blockId", "action=form_download_submit"
 * ----------------------------------------------------- */
if ( ! function_exists('wns_handle_form_download_submit') ) {
    function wns_handle_form_download_submit() {
        if (empty($_REQUEST['action']) || $_REQUEST['action'] !== 'form_download_submit') {
            return;
        }

        try {
            nocache_headers();

            $email    = isset($_POST['EMAIL'])  ? sanitize_email(wp_unslash($_POST['EMAIL'])) : '';
            $post_id  = isset($_POST['postId']) ? (int) $_POST['postId'] : 0;
            $block_id = isset($_POST['blockId'])? sanitize_text_field(wp_unslash($_POST['blockId'])) : '';

            if (function_exists('wns_validate_email_deliverability')) {
                if (!wns_validate_email_deliverability($email)) {
                    return wns_output_submit_response(false, 'Invalid or unsupported email address.');
                }
            } elseif (!is_email($email)) {
                return wns_output_submit_response(false, 'Invalid email address.');
            }

            $file_url = wns_resolve_file_url($post_id, $block_id);
            if (!$file_url) {
                return wns_output_submit_response(false, 'Download is not available yet.');
            }

            $sent = wns_send_download_email($email, $file_url, $post_id, $block_id);

            if (function_exists('wns_add_or_update_subscriber')) {
                // Don’t force verification here; the flow handles it.
                wns_add_or_update_subscriber($email, null);
            }

            if ($sent === 'skipped') {
                // Verified user + “skip verification” enabled → no email on purpose
                return wns_output_submit_response(true, "You’re verified! You can now download instantly from aistudynow.com (no email needed).");
            }

            if ($sent === false) {
                // wp_mail failed (or token creation failed)
                return wns_output_submit_response(false, 'We couldn’t send the email right now. Please try again in a moment.');
            }

            // Normal case: an email was sent (verify or direct download)
            return wns_output_submit_response(true, "Please check your email for the link.\nIf you’re verifying for the first time, click the verification email first.");

        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WNS form_submit error: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            }
            return wns_output_submit_response(false, 'Server error. Please try again in a moment.');
        }
    }
}


/**
 * Output helper: JSON for AJAX, redirect for classic POST
 */
if (!function_exists('wns_output_submit_response')) {
    function wns_output_submit_response($success, $message) {
        $is_ajax =
            (function_exists('wp_doing_ajax') && wp_doing_ajax()) ||
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_POST['ajax']) && $_POST['ajax']);

        if ($is_ajax) {
            // admin-ajax.php response
            wp_send_json(array(
                'success' => (bool) $success,
                'message' => $message,
            ));
        } else {
            // Classic POST → redirect back with query args
            $ref = !empty($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : home_url('/');
            $ref = add_query_arg(array(
                'download_submitted' => $success ? '1' : '0',
                'download_msg'       => rawurlencode($message),
            ), $ref);
            wp_safe_redirect($ref);
            exit;
        }
    }
}


/* -------------------------------------------------------
 * CRON: CLEANUP EXPIRED TOKENS
 * ----------------------------------------------------- */
if (!function_exists('wns_schedule_token_cleanup')) {
    add_action('wp', 'wns_schedule_token_cleanup');
    function wns_schedule_token_cleanup() {
        if (!wp_next_scheduled('wns_cleanup_expired_tokens')) {
            wp_schedule_event(time(), 'daily', 'wns_cleanup_expired_tokens');
        }
    }
}
if (!function_exists('wns_cleanup_expired_download_tokens')) {
    add_action('wns_cleanup_expired_tokens', 'wns_cleanup_expired_download_tokens');
    function wns_cleanup_expired_download_tokens() {
        global $wpdb;
        $table = $wpdb->prefix . 'newsletter_download_tokens';
        // Tokens older than 48h
        $wpdb->query("DELETE FROM $table WHERE expires_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    }
}
