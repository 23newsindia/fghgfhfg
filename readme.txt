=== Custom Newsletter ===
Contributors: yourusername
Tags: newsletter, email subscription, wp mail smtp, custom newsletter
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.1
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html 

A lightweight and customizable newsletter plugin that integrates with WP Mail SMTP.

== Description ==

Custom Newsletter allows you to:
- Collect subscribers via shortcode form
- Send automatic emails when new posts are published
- Import/export subscribers via CSV
- Auto-import registered users
- Manage all subscribers from the admin panel
- Use WP Mail SMTP for reliable delivery

== Installation ==

1. Upload the `custom-newsletter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Newsletter to configure options
4. Use the `[newsletter_subscribe]` shortcode to display the form

== Frequently Asked Questions ==

= Does it work with WP Mail SMTP? =
Yes! This plugin uses `wp_mail()` internally, so it works seamlessly with WP Mail SMTP or any other email delivery plugin.

= Can I send emails on post types other than posts? =
Yes! In the settings, select which post types should trigger an email.

= How do I import existing users? =
Go to Newsletter > Import / Export and enable "Auto-import Registered Users".

== Changelog ==

= 1.0.1 =
* Added subscriber list table UI
* Added bulk actions and inline management
* Improved activation checks
* Minor bug fixes

= 1.0.0 =
* Initial release