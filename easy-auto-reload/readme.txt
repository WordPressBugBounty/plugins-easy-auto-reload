=== Easy Auto Reload - Auto Refresh ===
Contributors: creativform, ivijanstefan
Donate link: 
Tags: refresh, reload, auto-refresh, cache-clear, performance
Requires at least: 5.4
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 2.0.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Auto-refresh your WordPress pages on user inactivity. Keep sessions alive, nonces fresh, and engagement high with this lightweight plugin.

== Description ==

**Easy Auto Reload** automatically refreshes WordPress pages after a configurable period of user inactivity, helping prevent expired sessions, stale dashboards, WooCommerce timeout issues, and failed nonce requests.

The plugin reloads pages only when visitors become completely idle - never during typing, scrolling, clicking, or interaction.

Built with lightweight vanilla JavaScript and no jQuery dependency, it works quietly in the background to keep sessions active and pages fresh.

Perfect for WooCommerce stores, dashboards, membership systems, admin panels, kiosk displays, and applications with expiring sessions or tokens.

Inspired by Isaac Newton's observations of motion and invisible forces, **Easy Auto Reload** keeps your digital environment in motion - quietly preventing pages and sessions from drifting into silence.

Your website shall not slumber. Not today.

== Features ==

- Automatic refresh after user inactivity
- Smart idle detection
- No reloads during activity
- Helps prevent expired nonces and sessions
- Lightweight vanilla JavaScript
- No jQuery dependency
- Optional WP Admin support
- Custom refresh intervals
- Per-page and per-post controls
- Minimal performance impact

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/easy-auto-reload`, or install via Plugins > Add New.
2. Activate the plugin.
3. Go to **Settings → Easy Auto Reload**.
4. Choose a refresh interval in minutes.
5. Save. You're done.

== Frequently Asked Questions ==

= Does the plugin reload while the user is interacting with the page? =  
No. It detects activity (mouse movement, key presses, scrolling) and only reloads when the user is completely idle.

= Is this plugin good for WooCommerce session expiration? =  
Yes! Especially useful for keeping carts alive and nonce values refreshed during checkout.

= Will this conflict with caching plugins? =  
Not at all. This plugin uses client-side JavaScript and works seamlessly with WP Super Cache, W3 Total Cache, and others.

= Can I disable reload on specific pages? =  
Yes! You can inside "Auto Reload" metabox.

== Changelog ==

= 2.0.6 =
* Security update
* Support update
* Update translations

= 2.0.5 =
* WordPress 7.0 compatibility

= 2.0.4 =
* Changed plugin header from Network: true to Network: false to allow per-site activation in multisite environments

= 2.0.3 =
* Code improvement
* Fixed bugs with nonce
* Fixed permissions

= 2.0.2 =
* Fixed JavaScript errors when clearing cache
* Optimize JavaScript and code minification
* Fixed GUI and optimized PHP code

= 2.0.1 =
* Added possibility to turn off redirection on the whole site
* Added option to turn off redirection on individual pages
* Added the ability to decide that only certain pages will be redirected
* Optimized PHP code

= 2.0.0 =
* Added possibility to set refresh interval for individual pages
* Improved PHP code

= 1.0.10 =
* Support for the WordPress version 6.7

=1.0.9=
* Added support for the WordPress version 6.6
* Added new browsers support

=1.0.8=
* Added support for the WordPress version 6.5
* Fixed plugin security

=1.0.7=
* Added support for the WordPress version 6.4
* Added settings for the lifespan of nonces

=1.0.6=
* Added support for the WordPress version 6.3

=1.0.5=
* Added support for the browsers with no JavaScript
* Improved 

=1.0.4=
* Added support for the WordPress version 6.0

=1.0.3=
* Adding WP admin cache

=1.0.2=
* Fixed plugin initialization
* Added translations
* Fixed PHP bugs

=1.0.1=
* Added browser cache cleaning
* Fixed seconds instead of minutes

=1.0.0=
* First stable version

== Screenshots ==

1. Admin Settings
2. Page and post settings