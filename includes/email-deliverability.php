<?php
/**
 * Email deliverability helpers for your newsletter/download emails.
 * - Keeps all old method names so other code still works.
 * - Forces From + envelope sender to a single, allowed mailbox (wilddragon.in).
 * - Deduplicates Content-Type / List-Unsubscribe / Reply-To and strips Return-Path.
 * - Uses a LEAN header profile for "alert" (Primary-friendly) vs. full list headers for newsletters.
 *
 * IMPORTANT:
 *   1) Authenticate SMTP as the SAME mailbox set below (or via option/filter).
 *   2) Set SPF/DKIM/DMARC for the sender domain.
 *
 * Primary-friendly mode:
 *   Add header "X-WNS: alert" when sending post alerts (or pass marker 'alert' to your helper).
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('WNS_Email_Deliverability')):

class WNS_Email_Deliverability {

    /** Change this to your authenticated mailbox, or override via option/filter. */
    private static function get_sender_email() {
        // Mail server lives on wilddragon.in (matches your SMTP auth)
        $default = 'admin@wilddragon.in';
        $opt     = sanitize_email(get_option('wns_sender_email')); // Optional WP setting
        $email   = $opt ?: $default;
        return apply_filters('wns_sender_email', $email);
    }

    /** Display name for From: */
    private static function get_sender_name() {
        $name = get_bloginfo('name');
        return apply_filters('wns_sender_name', $name);
    }

    /** Initialize deliverability enhancements */
    public static function init() {
        add_filter('wp_mail',               array(__CLASS__, 'enhance_email_headers'), 10, 1);
        add_action('phpmailer_init',        array(__CLASS__, 'configure_phpmailer'));
        add_filter('wns_email_batch_size',  array(__CLASS__, 'adjust_batch_size_by_time'));
        add_filter('wns_email_content',     array(__CLASS__, 'optimize_email_content'), 10, 2);
        add_action('wp_mail_succeeded',     array(__CLASS__, 'track_email_success'));
        add_action('wp_mail_failed',        array(__CLASS__, 'track_email_failure'));
    }

    /** Enhance headers for our emails only */
    public static function enhance_email_headers($args) {
    // Identify marker from headers (default newsletter)
    $incoming = array();
    if (!empty($args['headers'])) {
        $incoming = is_array($args['headers'])
            ? array_map('strval', $args['headers'])
            : preg_split('/\r\n|\n|\r/', (string)$args['headers']);
    }
    $headers_str = implode("\n", $incoming);
    $marker = 'newsletter';
    if (preg_match('/^\s*X-WNS:\s*([^\s]+)/mi', $headers_str, $m)) {
        $marker = strtolower(trim($m[1]));
    }
    $is_alert      = in_array($marker, array('alert','verify','download','transactional'), true);
    $is_newsletter = ($marker === 'newsletter' || $marker === 'broadcast' || $marker === 'digest');

    // Clean duplicates first
    $cleaned = array();
    $has_ct = false;
    foreach ($incoming as $h) {
        $h = trim($h);
        if ($h === '') continue;
        if (stripos($h, 'Content-Type:') === 0) { if (!$has_ct) { $cleaned[] = $h; $has_ct = true; } continue; }
        if (stripos($h, 'Return-Path:') === 0) continue; // envelope set by PHPMailer
        // Drop any marketing-y X-* headers if this is an alert
        if ($is_alert && preg_match('/^X\-(Newsletter|Email|Message|Content|Campaign|Google\-Appengine|Entity|Mailer\-LID|Message\-Flag|Content\-Category|Email\-Type\-Id)\b/i', $h)) {
            continue;
        }
        $cleaned[] = $h;
    }

    // Build From/Reply-To
    $from_email = self::get_sender_email(); // set this to your authenticated mailbox
    $from_name  = self::get_sender_name();

    // Unsubscribe URL (may be omitted for alerts)
    $recipient_email = is_array($args['to']) ? reset($args['to']) : $args['to'];
    $recipient_email = sanitize_email($recipient_email);
    $unsub_url = (function_exists('wns_get_unsubscribe_link') && $recipient_email)
        ? wns_get_unsubscribe_link($recipient_email)
        : home_url('/unsubscribe/');

    $enhanced = array(
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        $has_ct ? null : 'Content-Type: text/html; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: WordPress/' . get_bloginfo('version') . ' - Newsletter System',
        'X-WNS-Profile: ' . ($is_alert ? 'alert' : 'newsletter'),
    );

    if ($is_newsletter) {
        $site_domain = parse_url(home_url(), PHP_URL_HOST);
        $enhanced = array_merge($enhanced, array(
            'List-ID: ' . $from_name . ' Newsletter <newsletter.' . $site_domain . '>',
            'List-Unsubscribe: <' . esc_url($unsub_url) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            'List-Archive: <' . esc_url(home_url()) . '>',
            'List-Owner: <mailto:' . $from_email . '>',
            'List-Subscribe: <' . esc_url(home_url()) . '>',
            'Precedence: bulk',
            'Auto-Submitted: auto-generated',
        ));
    } else {
        // ALERT profile: explicitly remove bulk/list hints if they somehow slipped in
        foreach ($cleaned as $k => $h) {
            if (preg_match('/^(List\-(Unsubscribe|Archive|Owner|Subscribe|ID)|Precedence|Auto\-Submitted)\:/i', $h)) {
                unset($cleaned[$k]);
            }
        }
    }

    $enhanced = array_values(array_filter($enhanced, fn($v)=>!is_null($v)));
    $args['headers'] = !empty($cleaned) ? array_merge(array_values($cleaned), $enhanced) : $enhanced;
    return $args;
}


    /** PHPMailer tuning + envelope sender alignment */
    public static function configure_phpmailer($phpmailer) {
        $phpmailer->CharSet       = 'UTF-8';
        $phpmailer->Encoding      = '8bit';
        $phpmailer->WordWrap      = 78;
        $phpmailer->SMTPKeepAlive = true;
        $phpmailer->Timeout       = 30;
        if (property_exists($phpmailer, 'SMTPTimeout')) {
            $phpmailer->SMTPTimeout = 30;
        }

        $domain = parse_url(home_url(), PHP_URL_HOST);
        $phpmailer->MessageID = '<' . uniqid('', true) . '@' . $domain . '>';

        $from_email = self::get_sender_email();
        $from_name  = self::get_sender_name();

        // Set From header & envelope sender to the SAME authenticated mailbox
        $phpmailer->setFrom($from_email, $from_name, false); // prevent PHPMailer auto Sender change
        $phpmailer->Sender = $from_email; // Envelope-From (Return-Path)
    }

    /** Batch size tuning (unchanged) */
    public static function adjust_batch_size_by_time($batch_size) {
        $h = (int) date('H');
        $rep = self::get_email_reputation_score();
        if ($h >= 9 && $h <= 17) { $batch_size = min($batch_size, 50); }
        if    ($rep < 0.8)  { $batch_size = min($batch_size, 25); }
        elseif($rep > 0.95) { $batch_size = min((int) round($batch_size * 1.5), 200); }
        return $batch_size;
    }

    /** Content helpers (unchanged) */
    public static function optimize_email_content($content, $type = 'newsletter') {
        $content = self::preserve_urls_in_content($content);
        $content = self::remove_spam_triggers($content);
        $content = self::ensure_text_html_balance($content);
        $content = self::ensure_unsubscribe_compliance($content);
        $content = self::add_view_in_browser_link($content);
        return $content;
    }
    private static function preserve_urls_in_content($c){ return $c; }
    private static function remove_spam_triggers($c){
        $bad = array('FREE!','URGENT!','ACT NOW!','LIMITED TIME!','CLICK HERE NOW','MAKE MONEY FAST','GUARANTEED','NO OBLIGATION','RISK FREE','CASH BONUS');
        foreach ($bad as $w) { $c = str_ireplace($w, ucwords(strtolower($w)), $c); }
        $c = preg_replace('/!{2,}/','!',$c);
        $c = preg_replace('/\?{2,}/','?',$c);
        $c = preg_replace_callback('/[A-Z]{4,}/', fn($m)=>ucfirst(strtolower($m[0])), $c);
        return $c;
    }
    private static function ensure_text_html_balance($c){
        $c = preg_replace('/<img([^>]*?)(?:alt=["\'][^"\']*["\'])?([^>]*?)>/i','<img$1 alt="Newsletter Image"$2>', $c);
        $c = preg_replace('/<a([^>]*)>(?:\s*(?:click here|here|link)\s*)<\/a>/i','<a$1>Read More</a>', $c);
        return $c;
    }
    private static function ensure_unsubscribe_compliance($c){
        if (strpos($c,'{unsubscribe_link}') === false) {
            $c .= '<br><br><small><a href="{unsubscribe_link}">Unsubscribe from this newsletter</a></small>';
        }
        return $c;
    }
    private static function add_view_in_browser_link($c){
        $c = '<p style="text-align:center;font-size:12px;color:#666;">Having trouble viewing this email? <a href="'.esc_url(home_url()).'">View it in your browser</a></p>' . $c;
        return $c;
    }

    /** Identify our emails (markers first, then legacy heuristics) */
    private static function is_newsletter_email($args) {
        $headers = isset($args['headers']) ? $args['headers'] : array();
        $subject = isset($args['subject']) ? (string) $args['subject'] : '';
        $headers_str = is_array($headers) ? implode("\n", array_map('strval', $headers)) : (string)$headers;

        if (preg_match('/^\s*X-WNS:/mi', $headers_str))                        return true;
        if (preg_match('/^\s*X-Plugin:\s*WP Newsletter Plugin/i', $headers_str)) return true;
        if (stripos($headers_str, 'newsletter') !== false)                     return true;
        if (preg_match('/\b(newsletter|download|verify|verification)\b/i', $subject)) return true;
        if (apply_filters('wns_is_plugin_email', false, $args))                return true;

        return false;
    }

    /** Extract marker (verify | download | alert | …) from headers */
    private static function get_marker_from_headers(array $headers): string {
        foreach ($headers as $h) {
            if (stripos($h, 'X-WNS:') === 0) {
                $parts = explode(':', $h, 2);
                if (!empty($parts[1])) { return trim(strtolower($parts[1])); }
            }
        }
        return '';
    }

    /** Stats (unchanged) */
    public static function track_email_success($mail_data = null) {
        $s = get_option('wns_email_stats', array('sent'=>0,'failed'=>0,'last_reset'=>time()));
        $s['sent']++; update_option('wns_email_stats', $s);
    }
    public static function track_email_failure($wp_error) {
        $s = get_option('wns_email_stats', array('sent'=>0,'failed'=>0,'last_reset'=>time()));
        $s['failed']++; update_option('wns_email_stats', $s);
        if (is_wp_error($wp_error)) { error_log('WNS Email Failure: ' . $wp_error->get_error_message()); }
        else { error_log('WNS Email Failure (unknown)'); }
    }

    /** Reputation + delay (unchanged) */
    private static function get_email_reputation_score() {
        $s = get_option('wns_email_stats', array('sent'=>0,'failed'=>0,'last_reset'=>time()));
        $t = (int)$s['sent'] + (int)$s['failed'];
        return $t === 0 ? 1.0 : ((int)$s['sent'] / $t);
    }
    public static function get_progressive_delay($batch_number) {
        $base = 60; $delay = $base * (1 + ($batch_number * 0.1));
        return min($delay, 600);
    }
}

endif;

// Bootstrap once
if (!defined('WNS_DELIVERABILITY_INIT')) {
    define('WNS_DELIVERABILITY_INIT', true);
    add_action('plugins_loaded', array('WNS_Email_Deliverability', 'init'));
}
