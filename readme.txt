=== LNURL auth for WordPress ===

Contributors: joelmelon
Donate link: https://lnurl-auth-for-wordpress.joelstuedle.ch/
Tags: LNURL, Authentication, Login, Bitcoin, Lightning
Requires at least: 6.0
Requires PHP: 8.0.15
Tested up to: 6.1.1
Stable tag: trunk
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin provides LNURL auth for WordPress. Login to WordPress with Bitcoin Lightning ⚡️.

== Features ==

With **LNURL Auth for WordPress** LNURL Auth is now available for WordPress. Add LNURL Auth to the WordPress login form or use the shortcode `[lnurl_auth]` to use LNURL anywhere on your site.

* Custom Colors
* Custom Callback URL
* Custom Redirect URL
* Node Bann- & Allowlist
* Naming Options For New Users
* Set Role(s) For New Users
* Toggle Login Options

*If the setting to create users is enabled in the plugin settings, the plugin will automatically generate new users. Be wise, with great power comes great responsibility.*

== Installation ==

1. Upload the `lnurl-auth` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Check out the plugins settings page

== LNURL Auth ==

LNURL auth login is a specific type of LNURL authentication process that is used to log a user into a third-party service using their Lightning account. This process typically involves the user scanning a LNURL with their Lightning wallet app.

Overall, LNURL auth login is a convenient and secure way for users of the Lightning Network to log into third-party services using their Lightning accounts. It allows users to easily and securely log into services without needing to create separate accounts or handle sensitive information, and it allows third-party services to securely access user accounts without needing to implement their own authentication processes.

Read the specs here: https://github.com/lnurl/luds/blob/luds/04.md 

== Vendors ==
* eza/lnurl-php: https://github.com/eza/lnurl-php
* endroid/qr-code https://github.com/endroid/qr-code

== Changelog ==

= 1.0.0 =
* Initial version.

== License ==

Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.
Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html
