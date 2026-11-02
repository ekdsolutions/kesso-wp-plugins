=== Kesso Cookies ===
Contributors: kesso
Tags: cookies, gdpr, consent, privacy, compliance
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR-first cookie consent banner with script blocking and category management.

== Description ==

Kesso Cookies is a WordPress plugin that provides a consent-based cookie consent solution. It includes:

* Cookie consent banner with customizable text
* Support for cookie categories (Essential, Analytics, Marketing)
* Automatic script blocking (default-deny) until consent is granted
* Auto-detection of common trackers (Google Analytics/GTM, Meta Pixel, Hotjar, Clarity, LinkedIn, TikTok, etc.)
* Manual gating of any script via a data-kesso-category attribute
* Third-party cookies cleared when consent is withdrawn
* Customizable consent panel with full keyboard support (focus trap + Escape)
* Persistent consent management
* Footer link to reopen consent settings
* Fully accessible and RTL compatible

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kesso-cookies` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the cookie banner text in Settings > Cookie Consent

== Frequently Asked Questions ==

= Is this plugin GDPR compliant? =

The plugin is designed to enforce GDPR-compliant behavior by default: with Script Blocking enabled it holds analytics and marketing scripts until the visitor consents. However, compliance also depends on how you configure and use the plugin, as well as your website's specific requirements. Always consult with a legal professional for full compliance verification.

= How does script blocking work? =

Go to Settings > Cookie Consent > Script Blocking. With blocking enabled, the plugin rewrites tracking scripts to a dormant state before the browser runs them, then re-activates only the categories the visitor consents to.

There are three ways a script gets blocked:

1. **Auto-detection** (recommended) — leave "Auto-detect Known Trackers" on and common trackers (Google Analytics/GTM, Meta Pixel, Hotjar, Microsoft Clarity, LinkedIn, TikTok, Bing, Pinterest, etc.) are blocked automatically.

2. **Manual tagging** — add a data-kesso-category attribute to any script tag:

```html
<script data-kesso-category="analytics" src="https://example.com/tracker.js"></script>
<script data-kesso-category="marketing">/* inline pixel code */</script>
```

3. **JavaScript API** — register scripts programmatically:

```javascript
window.kessoCookies.registerScript({
  category: "analytics",
  src: "https://www.googletagmanager.com/gtag/js?id=XXXX"
});
```

You can extend or override the auto-detection list with the `kesso_cookies_tracker_signatures` PHP filter, and exclude a request entirely with `kesso_cookies_run_script_blocker`.

= Can I customize the banner appearance? =

Yes, you can customize all text content through the admin settings page. The plugin uses CSS that can be overridden with custom CSS if needed.

== Changelog ==

= 1.1.0 =
* Added real server-side script blocking (default-deny) via output buffering — analytics/marketing scripts are now held until consent
* Added auto-detection of common third-party trackers, plus manual gating via the data-kesso-category attribute
* Added Script Blocking settings (enable blocking, auto-detect toggle)
* Third-party cookies are now cleared when consent is withdrawn
* Accessibility: customize panel now has a focus trap, Escape-to-close, and focus restoration; the bottom banner no longer falsely claims aria-modal
* Hardened custom-CSS sanitization (blocks @import, remote url(), expression())
* Banner now hidden by default to remove the flash/CLS for returning visitors
* Stale consent cookies are cleared on consent-version changes; early script registrations are no longer dropped
* Added uninstall cleanup for all plugin options
* Unified option defaults so placeholders, defaults, and "Reset to Defaults" always agree

= 1.0.0 =
* Initial release
* Consent-based cookie consent banner
* Script blocking functionality
* Cookie category management
* Customizable text and labels

