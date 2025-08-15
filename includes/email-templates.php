<?php
if (!defined('ABSPATH')) { exit; }

class WNS_Email_Templates
{
    /**
     * Keep raw URL case while still sanitizing content.
     */
    public static function preserve_url_case_in_content($content) {
        $urls = [];
        $processed = preg_replace_callback('/https?:\/\/[^\s"]+/', function ($m) use (&$urls) {
            $ph = '__URL__' . count($urls) . '__';
            $urls[$ph] = $m[0];
            return $ph;
        }, $content);

        $processed = wpautop(wp_kses_post($processed));
        foreach ($urls as $ph => $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $processed = str_replace($ph, $url, $processed);
            }
        }
        return $processed;
    }
    
    
    
    /**
 * Sanitize rich editor HTML for emails (allow safe inline styles/images/links).
 */
private static function sanitize_custom_email_html($html) {
    if ($html === '' || $html === null) return '';

    // Keep original URL casing, then sanitize
    $html = self::preserve_url_case_in_content($html);

    $allowed = array(
        'p' => array('style' => true, 'align' => true),
        'br' => array(),
        'span' => array('style' => true),
        'strong' => array(), 'b' => array(),
        'em' => array(), 'i' => array(), 'u' => array(),
        'h1' => array('style' => true), 'h2' => array('style' => true),
        'h3' => array('style' => true), 'h4' => array('style' => true),
        'ul' => array('style' => true), 'ol' => array('style' => true),
        'li' => array('style' => true),
        'blockquote' => array('style' => true),
        'a' => array('href' => true, 'title' => true, 'target' => true, 'rel' => true, 'style' => true),
        'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true),
        'table' => array('style' => true, 'role' => true, 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'width' => true, 'align' => true),
        'thead' => array(), 'tbody' => array(), 'tr' => array('style' => true, 'align' => true),
        'th' => array('style' => true, 'align' => true, 'width' => true),
        'td' => array('style' => true, 'align' => true, 'width' => true, 'colspan' => true, 'rowspan' => true),
        'hr' => array('style' => true),
        'div' => array('style' => true, 'align' => true),
    );

    $allowed = apply_filters('wns_allowed_email_tags', $allowed);
    return wp_kses($html, $allowed);
}


    /**
     * Minimal wrapper (no <head>/<style>), all inline styles.
     * 100% width container with 788px max for desktop; works nicely on mobile.
     */
    public static function get_email_wrapper($content_html, $title = '') {
        $site_name = get_bloginfo('name');
        $site_url  = home_url();
        $year      = date_i18n('Y');
        $preheader = $title ? $title . ' — ' . date_i18n('M j, Y') : $site_name;

        $preheader_html = '<div style="opacity:0;color:transparent;height:0;width:0;max-height:0;max-width:0;overflow:hidden;font-size:1px;line-height:1px">'
                        . esc_html($preheader) . '</div>';

        return '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFE">
  <tr>
    <td align="center" style="padding:0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:788px;background-color:#FFFFFE">
        <tr>
          <td style="padding:24px 14px">
            ' . $preheader_html . '
            ' . $content_html . '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e8e8e8;margin-top:32px">
              <tr>
                <td style="padding:16px 14px">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td width="50%" style="color:#666;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:12px;line-height:1.4;padding:4px 0">
                        &copy; ' . esc_html($year) . ' ' . esc_html($site_name) . '<br>
                        <a href="' . esc_url($site_url . '/privacy') . '" style="color:#666;text-decoration:underline">Privacy policy</a> |
                        <a href="' . esc_url($site_url . '/terms') . '" style="color:#666;text-decoration:underline">Terms of use</a><br>
                        ' . esc_html(parse_url($site_url, PHP_URL_HOST)) . '
                      </td>
                      <td width="50%" align="right" style="color:#666;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:12px;line-height:1.4;padding:4px 0">
                        Email sent to {recipient_email}<br>
                        <a href="{unsubscribe_link}" style="color:#666;text-decoration:underline">Unsubscribe</a> |
                        <a href="' . esc_url($site_url) . '" style="color:#666;text-decoration:underline">Visit Website</a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>';
    }

    /**
     * Simple “newsletter/freeform” layout (title + date + body).
     */
    public static function get_newsletter_template($subject, $content) {
        $title_style = "margin:0;color:#000;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:32px;font-weight:700;line-height:1.3;word-break:break-word;hyphens:auto;";
        $date_style  = "color:#999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.4;";
        $body_style  = "color:#00000e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;";

        $content_html = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td style="padding:0 0 8px 0">
      <h1 style="' . $title_style . '">' . esc_html($subject) . '</h1>
    </td>
  </tr>
  <tr>
    <td style="padding:0 0 20px 0">
      <div style="' . $date_style . '">' . date_i18n('d M Y') . '</div>
    </td>
  </tr>
</table>
<div style="' . $body_style . '">' . self::preserve_url_case_in_content($content) . '</div>';

        return self::get_email_wrapper($content_html, $subject);
    }

    /**
     * Patreon-style “new post” layout
     * (title/date first; avatar + name + button on the SAME row).
     */
   /**
 * Patreon-style “new post” layout with optional custom HTML body.
 * Usage: get_new_post_template($post, ['custom_html' => '...']);
 */
public static function get_new_post_template($post, $args = array()) {
    $post_title = get_the_title($post->ID);
    $post_url   = get_permalink($post->ID);
    $author_id  = $post->post_author;
    $author     = get_the_author_meta('display_name', $author_id);
    $author_url = get_author_posts_url($author_id);
    $avatar     = get_avatar_url($author_id, ['size' => 84]);
    $thumb_url  = get_the_post_thumbnail_url($post->ID, 'large');

    $custom_html = isset($args['custom_html']) ? (string) $args['custom_html'] : '';

    $title_style   = 'margin:0;color:#000;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:28px;font-weight:700;letter-spacing:-0.2px;line-height:1.3;text-decoration:none;word-break:break-word';
    $date_style    = 'color:#999;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:16px;line-height:1.4';
    $btn_style     = 'background:transparent;border:1px solid #CCC;border-radius:9999px;color:#000;display:inline-block;white-space:nowrap;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:14px;font-weight:700;letter-spacing:0.2px;line-height:19.6px;padding:9px 18px;text-decoration:none';
    $body_style    = 'color:#00000e;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:18px;line-height:1.4';
    $cta_title     = 'color:#000;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:22px;font-weight:600;letter-spacing:-0.3px;line-height:1.3';
    $cta_text      = 'color:#666;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:16px;line-height:1.4';
    $cta_btn_style = 'background:#000;border-radius:50px;color:#fff;display:inline-block;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:16px;font-weight:700;letter-spacing:0.2px;line-height:16px;padding:14px 26px;text-decoration:none';

    $excerpt_html = has_excerpt($post->ID)
        ? wpautop(esc_html(get_the_excerpt($post->ID)))
        : '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($post->post_content), 30)) . '</p>';

    $img_html = '';
    if ($thumb_url) {
        $img_html = '<img src="' . esc_url($thumb_url) . '" alt="' . esc_attr($post_title) . '" width="100%" style="width:100%;max-width:760px;height:auto;border-radius:8px;margin:20px 0;display:block">';
    }

    // If custom HTML provided, use it instead of excerpt; keep featured image
    $body_inner = $img_html . (
        $custom_html !== ''
            ? self::sanitize_custom_email_html($custom_html)
            : self::preserve_url_case_in_content($excerpt_html)
    );

    $content = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px">
  <tr>
    <td style="width:75%;vertical-align:top">
      <h1 style="' . $title_style . '"><a href="' . esc_url($post_url) . '" style="color:#000;text-decoration:none">' . esc_html($post_title) . '</a></h1>
      <div style="' . $date_style . '">' . date_i18n('d M Y', strtotime($post->post_date)) . '</div>
    </td>
  </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e8e8e8;border-bottom:1px solid #e8e8e8;padding:20px 0;margin:24px 0">
  <tr>
    <td style="width:42px;vertical-align:top">
      <img src="' . esc_url($avatar) . '" alt="' . esc_attr($author) . '" style="width:42px;height:42px;border-radius:4px;display:block">
    </td>
    <td style="padding-left:12px;vertical-align:middle">
      <a href="' . esc_url($author_url) . '" style="color:#000;text-decoration:none;font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;font-size:16px;font-weight:500">' . esc_html($author) . '</a>
    </td>
    <td style="vertical-align:middle;text-align:right;white-space:nowrap">
      <a href="' . esc_url($post_url) . '" style="' . $btn_style . '">Read Article</a>
    </td>
  </tr>
</table>

<div style="' . $body_style . '">' . $body_inner . '
  <div style="text-align:center;margin:30px 0">
    <a href="' . esc_url($post_url) . '" style="color:#666;text-decoration:underline;font-size:16px">Read full article →</a>
  </div>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F5F5F5;border-radius:8px;padding:24px 20px;margin:32px 0;text-align:left">
  <tr>
    <td style="vertical-align:middle">
      <div style="' . $cta_title . '">Did you enjoy this article?</div>
      <div style="' . $cta_text . '">Share this post with someone who might find it interesting.</div>
    </td>
    <td style="width:200px;vertical-align:middle;text-align:right">
      <a href="' . esc_url($post_url) . '" style="' . $cta_btn_style . '">Share Article</a>
    </td>
  </tr>
</table>';

    return self::get_email_wrapper($content, $post_title);
}


    /**
     * REQUIRED BY HANDLER:
     * Download email (after verification or instant access).
     * Handler signature: get_download_template($subject, $body, $download_link)
     */
    public static function get_download_template($subject, $body, $download_link) {
        $title_style = "margin:0;color:#000;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:24px;font-weight:700;line-height:1.3;";
        $text_style  = "color:#00000e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;";
        $btn_style   = "background:#000;border-radius:50px;color:#fff;display:inline-block;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;letter-spacing:0.2px;line-height:1;padding:14px 26px;text-decoration:none;";

        $content_html = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td style="padding:0 0 8px 0">
      <h1 style="' . $title_style . '">' . esc_html($subject) . '</h1>
    </td>
  </tr>
  <tr>
    <td style="padding:0 0 16px 0">
      <div style="' . $text_style . '">' . self::preserve_url_case_in_content($body) . '</div>
    </td>
  </tr>
  <tr>
    <td style="padding:12px 0 0 0">
      <a href="' . esc_url($download_link) . '" style="' . $btn_style . '">Download now</a>
      <div style="margin-top:10px;' . $text_style . '">Or paste this link in your browser:<br>' . esc_html($download_link) . '</div>
    </td>
  </tr>
</table>';

        return self::get_email_wrapper($content_html, $subject);
    }

    /**
     * REQUIRED BY HANDLER:
     * Verification email for download flow.
     * Handler signature: get_download_verification_template($verify_link, $file_name)
     */
    public static function get_download_verification_template($verify_link, $file_name) {
        $subject = 'Confirm your email to download ' . $file_name;
        $title_style = "margin:0;color:#000;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:24px;font-weight:700;line-height:1.3;";
        $text_style  = "color:#00000e;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;";
        $btn_style   = "background:#000;border-radius:50px;color:#fff;display:inline-block;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;letter-spacing:0.2px;line-height:1;padding:14px 26px;text-decoration:none;";

        $content_html = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td style="padding:0 0 8px 0">
      <h1 style="' . $title_style . '">Please verify your email</h1>
    </td>
  </tr>
  <tr>
    <td style="padding:0 0 16px 0">
      <div style="' . $text_style . '">Tap the button below to verify your email. Once verified, you’ll be able to <strong>download any workflow instantly</strong> from aistudynow.com.</div>
    </td>
  </tr>
  <tr>
    <td style="padding:12px 0 0 0">
      <a href="' . esc_url($verify_link) . '" style="' . $btn_style . '">Verify email</a>
      <div style="margin-top:10px;' . $text_style . '">Or paste this link in your browser:<br>' . esc_html($verify_link) . '</div>
    </td>
  </tr>
</table>';

        return self::get_email_wrapper($content_html, $subject);
    }

    /**
     * Optional helper you can use elsewhere if needed.
     */
    public static function get_verify_template($headline, $message_html, $button_url = '', $button_label = 'Browse Workflows') {
        $body = '<p>' . $message_html . '</p>';
        if (!empty($button_url)) {
            $body .= '<div style="margin-top:20px"><a href="' . esc_url($button_url) . '" style="background:#000;border-radius:50px;color:#fff;display:inline-block;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;letter-spacing:0.2px;line-height:1;padding:14px 26px;text-decoration:none">' . esc_html($button_label) . '</a></div>';
        }
        return self::get_newsletter_template($headline, $body);
    }
}
