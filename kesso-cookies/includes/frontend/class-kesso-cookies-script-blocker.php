<?php
/**
 * Server-side script blocker.
 *
 * Buffers the rendered front-end HTML and rewrites tracking <script> tags to a
 * dormant state (type="text/plain") BEFORE the browser can execute them. The
 * front-end JS then re-activates only the categories the visitor has consented
 * to. Because the page is rewritten identically for every visitor and the
 * consent decision is read client-side, this remains compatible with full-page
 * caching.
 *
 * Two things get blocked:
 *   1. Any <script> the site author tags with data-kesso-category="analytics|marketing".
 *   2. Known third-party trackers (Google Analytics/GTM, Meta Pixel, Hotjar, …)
 *      detected by URL/inline signature — only when the auto-detect toggle is on.
 *
 * @package Kesso_Cookies
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rewrites tracking scripts to a consent-gated dormant state.
 */
class Kesso_Cookies_Script_Blocker {

    /**
     * Whether known-tracker auto-detection is enabled for this request.
     *
     * @var bool
     */
    private $auto_detect = true;

    /**
     * Constructor.
     */
    public function __construct() {
        // Start as early as possible on the main front-end request so the
        // buffer wraps the whole document (priority 1).
        add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 1 );
    }

    /**
     * Open the output buffer when appropriate.
     */
    public function maybe_start_buffer() {
        if ( ! $this->should_run() ) {
            return;
        }

        $this->auto_detect = (bool) Kesso_Cookies_Defaults::get( 'kesso_cookies_block_known_trackers' );
        ob_start( array( $this, 'process_html' ) );
    }

    /**
     * Decide whether blocking should run for the current request.
     *
     * @return bool
     */
    private function should_run() {
        // Master switch.
        if ( ! Kesso_Cookies_Defaults::get( 'kesso_cookies_blocking_enabled' ) ) {
            return false;
        }

        // Never touch non-page requests.
        if ( is_admin() || is_feed() || is_embed() || is_preview() || is_robots() ) {
            return false;
        }
        if ( wp_doing_ajax() ) {
            return false;
        }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return false;
        }

        // Skip AMP — it has its own sanitiser and forbids arbitrary scripts.
        if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
            return false;
        }

        // Skip page-builder editors/previews.
        if ( Kesso_Cookies_Helpers::is_page_builder() ) {
            return false;
        }

        /**
         * Final escape hatch for site owners (e.g. to exclude a specific URL).
         *
         * @param bool $run Whether to run the blocker.
         */
        return (bool) apply_filters( 'kesso_cookies_run_script_blocker', true );
    }

    /**
     * Output-buffer callback: rewrite blocked scripts in the page HTML.
     *
     * @param string $html Buffered HTML.
     * @return string
     */
    public function process_html( $html ) {
        if ( ! is_string( $html ) || '' === $html ) {
            return $html;
        }

        // Only process what looks like a full HTML document.
        if ( false === stripos( $html, '<head' ) && false === stripos( $html, '</html>' ) ) {
            return $html;
        }

        $signatures = $this->get_signatures();

        $result = preg_replace_callback(
            '#<script\b([^>]*)>(.*?)</script>#is',
            function ( $matches ) use ( $signatures ) {
                return $this->maybe_block_script( $matches[0], $matches[1], $matches[2], $signatures );
            },
            $html
        );

        // preg_replace_callback returns null on failure (e.g. backtrack limit
        // on a huge page) — fall back to the original, unmodified HTML.
        return ( null === $result ) ? $html : $result;
    }

    /**
     * Inspect a single <script> tag and rewrite it if it should be blocked.
     *
     * @param string $full       The full original tag.
     * @param string $attrs      The raw attribute string.
     * @param string $content    Inline script body.
     * @param array  $signatures Category => patterns map.
     * @return string
     */
    private function maybe_block_script( $full, $attrs, $content, $signatures ) {
        // Already processed.
        if ( false !== stripos( $attrs, 'data-kesso-blocked' ) ) {
            return $full;
        }

        // Only touch executable JavaScript — leave JSON-LD, templates, etc. alone.
        if ( preg_match( '/(?<![\w-])type\s*=\s*([\'"])(.*?)\1/i', $attrs, $tm ) ) {
            $type = strtolower( trim( $tm[2] ) );
            $executable = in_array( $type, array( 'text/javascript', 'application/javascript', 'module', '' ), true );
            if ( ! $executable ) {
                return $full;
            }
        }

        // Never block the plugin's own assets.
        if ( false !== stripos( $attrs, 'kesso-cookies' ) || false !== stripos( $content, 'kessoCookies' ) ) {
            return $full;
        }

        // 1) Explicit author tag wins.
        $category = '';
        if ( preg_match( '/\bdata-kesso-category\s*=\s*([\'"])(.*?)\1/i', $attrs, $cm ) ) {
            $category = strtolower( trim( $cm[2] ) );
        }

        // 2) Known-tracker auto-detection.
        if ( '' === $category && $this->auto_detect ) {
            $src = '';
            if ( preg_match( '/(?<![\w-])src\s*=\s*([\'"])(.*?)\1/i', $attrs, $sm ) ) {
                $src = $sm[2];
            }
            $haystack = $src . ' ' . $content;
            foreach ( $signatures as $cat => $patterns ) {
                foreach ( $patterns as $needle ) {
                    if ( false !== stripos( $haystack, $needle ) ) {
                        $category = $cat;
                        break 2;
                    }
                }
            }
        }

        // Only analytics/marketing are gated; essential & unknown pass through.
        if ( ! in_array( $category, array( 'analytics', 'marketing' ), true ) ) {
            return $full;
        }

        return $this->rewrite_blocked( $attrs, $content, $category );
    }

    /**
     * Produce the dormant version of a blocked script.
     *
     * @param string $attrs    Original attribute string.
     * @param string $content  Inline body.
     * @param string $category Resolved category.
     * @return string
     */
    private function rewrite_blocked( $attrs, $content, $category ) {
        // Move src out of the way so the browser does not fetch it.
        $new_attrs = preg_replace( '/(?<![\w-])src\s*=\s*([\'"])(.*?)\1/i', 'data-kesso-src=$1$2$1', $attrs, 1 );
        // Strip any existing type / category attributes (re-added canonically).
        $new_attrs = preg_replace( '/(?<![\w-])type\s*=\s*([\'"]).*?\1/i', '', $new_attrs );
        $new_attrs = preg_replace( '/\bdata-kesso-category\s*=\s*([\'"]).*?\1/i', '', $new_attrs );
        $new_attrs = trim( preg_replace( '/\s+/', ' ', (string) $new_attrs ) );

        $prefix = '<script type="text/plain" data-kesso-blocked="1" data-kesso-category="' . esc_attr( $category ) . '"';
        $prefix .= ( '' !== $new_attrs ) ? ' ' . $new_attrs . '>' : '>';

        return $prefix . $content . '</script>';
    }

    /**
     * Tracker detection signatures, keyed by category.
     *
     * @return array
     */
    private function get_signatures() {
        $defaults = array(
            'analytics' => array(
                'googletagmanager.com/gtag',
                'googletagmanager.com/gtm.js',
                'google-analytics.com/analytics.js',
                'google-analytics.com/ga.js',
                'gtag(',
                "ga('create'",
                "ga('send'",
                '_gaq.push',
                'dataLayer.push',
                'static.hotjar.com',
                '(function(h,o,t,j,a,r)',
                'clarity.ms/tag',
                'window.clarity',
                'matomo.js',
                'piwik.js',
                'plausible.io/js',
                'cdn.segment.com/analytics',
            ),
            'marketing'  => array(
                'connect.facebook.net',
                'fbevents.js',
                'fbq(',
                '_fbq',
                'doubleclick.net',
                'googleadservices.com',
                'googlesyndication.com',
                'ads-twitter.com',
                'snap.licdn.com',
                'analytics.tiktok.com',
                'ttq.load',
                'bat.bing.com',
                's.pinimg.com',
                'pintrk(',
            ),
        );

        /**
         * Filter the tracker signatures used for auto-detection.
         *
         * @param array $defaults Category => array of case-insensitive substrings.
         */
        return apply_filters( 'kesso_cookies_tracker_signatures', $defaults );
    }
}
