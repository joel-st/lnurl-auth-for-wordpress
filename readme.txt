=== LNURL Auth For WordPress ===

Contributors: joelmelon
Donate link: https://lnurl-auth-for-wordpress.joelstuedle.ch/
Tags: LNURL, Authentication, Login, Bitcoin, Lightning
Requires at least: 6.0
Requires PHP: 8.0.15
Tested up to: 6.7
Stable tag: 1.0.14
License: DBAD 1.1 or later
License URI: https://dbad-license.org/

This plugin provides LNURL Auth for WordPress. Login to WordPress with Bitcoin Lightning ⚡️

== Features ==

With **LNURL Auth for WordPress** LNURL Auth is now available for WordPress. Add LNURL Auth to the WordPress login form or use the shortcode `[lnurl_auth]` to use LNURL anywhere on your site.

* Custom Colors
* Custom Callback URL
* Custom Redirect URL
* Node Bann- & Allowlist
* Enable/Disable User Registrations
* Naming Options for New Users
* Set Role(s) for New Users
* Toggle Login Options

*If the setting to create users is enabled in the plugin settings, the plugin will automatically generate new users. Be wise, with great power comes great responsibility.*

== Demo ==

A demo WordPress installation with LNURL Auth is available [here](https://lnurl-auth-for-wordpress.joelstuedle.ch/).

== Installation ==

1. Upload the `lnurl-auth` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Check out the plugins settings page

== LNURL Auth ==

Overall, LNURL Auth login is a convenient and secure way for users of the Lightning Network to log into third-party services using their Lightning accounts. It allows users to easily and securely log into services without needing to create separate accounts or handle sensitive information. This process typically involves the user scanning a LNURL with their Lightning wallet app.

The LNURL auth QR code or URL contains a unique and time-sensitive text string. This text string is sent to the visitor's wallet. The visitor's wallet signs this text string with the private key and sends back the response with the signed text string. This signature can then be validated to confirm the visitor's identity.

Read the specs here: https://github.com/lnurl/luds/blob/luds/04.md

== Vendors ==

* eza/lnurl-php: https://github.com/eza/lnurl-php
* endroid/qr-code https://github.com/endroid/qr-code

== Screenshots ==

1. LNURL Auth on a WordPress login page.
2. LNURL Auth settings.

== Changelog ==

= 1.0.14 =
* Compatibility ckeck. Adapt internationalization improvements in 6.7 – load textdomain on `init` and fix `get_plugin_data`.

= 1.0.13 =
* Set error reporting to `false` inside auth callback function, so we do not have any php warnings/errors in our json response.

= 1.0.12 =
* Add `allowdynamicproperties`, remove `error_logs` and change all callback responses to `wp_json_encode`.

= 1.0.11 =
* Respect redirect_to query parameter on wp-login.php.

= 1.0.1 =
* Provide user object to wp_login action.

= 1.0.0 =
* Initial version.

== License ==

Use this code freely, widely and for free. Provision of this code provides and implies no guarantee.
Please respect the GPL v3 licence, which is available via http://www.gnu.org/licenses/gpl-3.0.html
