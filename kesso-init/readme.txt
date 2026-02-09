=== Kesso Init ===
Contributors: kesso
Tags: setup, wizard, installation, configuration
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified setup wizard for new WordPress sites - install plugins, page builders, child themes, and configure settings all from one page.

== Description ==

Kesso Init provides a comprehensive setup wizard that allows you to configure your entire WordPress site in one go. The wizard helps you:

* Install recommended plugins with a single click
* Install and configure page builders (Elementor or Bricks)
* Create and activate child themes automatically
* Configure general site settings (title, tagline, language, timezone, etc.)
* Upload and set a site favicon

All operations are performed via WordPress REST API with proper nonce authentication and progress reporting.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kesso-init` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. You will be automatically redirected to the Setup Wizard page.
4. Follow the wizard to configure your site.

== Frequently Asked Questions ==

= Can I run the wizard again? =

Yes, you can restart the wizard from Tools > Restart Setup Wizard in the WordPress admin menu.

= What happens if a plugin is already installed? =

The wizard will detect installed plugins and skip them, showing an "Installed" indicator. You can still activate them if they're not already active.

= Can I use Bricks Builder? =

Yes, the wizard supports Bricks Builder. You'll need to upload the Bricks theme ZIP file when selecting Bricks as your page builder.

== Changelog ==

= 1.0.0 =
* Initial release
* Plugin installation and activation
* Theme installation (Elementor/Hello Elementor, Bricks)
* Child theme creation and activation
* General settings configuration
* Site favicon upload
* REST API endpoints for all operations
* Progress reporting and error handling

== Upgrade Notice ==

= 1.0.0 =
Initial release of Kesso Init.

