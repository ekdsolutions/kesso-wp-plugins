=== Kesso Cookies ===
Contributors: kesso
Tags: cookies, gdpr, consent, privacy, compliance
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR-first cookie consent banner with script blocking and category management.

== Description ==

Kesso Cookies is a WordPress plugin that provides a consent-based cookie consent solution. It includes:

* Cookie consent banner with customizable text
* Support for cookie categories (Essential, Analytics, Marketing)
* Script blocking until consent is granted
* Customizable consent panel
* Persistent consent management
* Footer link to reopen consent settings
* Fully accessible and RTL compatible

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kesso-cookies` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the cookie banner text in Settings > Cookie Consent

== Frequently Asked Questions ==

= Is this plugin GDPR compliant? =

The plugin is designed to enforce GDPR-compliant behavior by default. However, compliance also depends on how you configure and use the plugin, as well as your website's specific requirements. Always consult with a legal professional for full compliance verification.

= How do I register scripts to be blocked? =

Use the JavaScript API:

```javascript
window.kessoCookies.registerScript({
  category: "analytics",
  src: "https://www.googletagmanager.com/gtag/js?id=XXXX"
});
```

= Can I customize the banner appearance? =

Yes, you can customize all text content through the admin settings page. The plugin uses CSS that can be overridden with custom CSS if needed.

== Changelog ==

= 1.0.0 =
* Initial release
* Consent-based cookie consent banner
* Script blocking functionality
* Cookie category management
* Customizable text and labels

