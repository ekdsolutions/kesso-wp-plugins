<?php
/**
 * Main plugin class
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Kesso_Init class - Singleton pattern
 */
class Kesso_Init {

    /**
     * Single instance of the class
     *
     * @var Kesso_Init
     */
    private static $instance = null;

    /**
     * Admin page handler
     *
     * @var Kesso_Init_Admin_Page
     */
    public $admin_page;

    /**
     * REST API controller
     *
     * @var Kesso_Init_Rest_Controller
     */
    public $rest_controller;

    /**
     * Plugin service
     *
     * @var Kesso_Init_Plugin_Service
     */
    public $plugin_service;

    /**
     * Theme service
     *
     * @var Kesso_Init_Theme_Service
     */
    public $theme_service;

    /**
     * Settings service
     *
     * @var Kesso_Init_Settings_Service
     */
    public $settings_service;

    /**
     * Get the singleton instance
     *
     * @return Kesso_Init
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_services();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Utils
        require_once KESSO_INIT_PATH . 'includes/utils/helpers.php';

        // Admin
        require_once KESSO_INIT_PATH . 'includes/admin/class-kesso-init-activation.php';
        require_once KESSO_INIT_PATH . 'includes/admin/class-kesso-init-admin-page.php';

        // API (don't load services here - they'll be loaded lazily)
        require_once KESSO_INIT_PATH . 'includes/api/class-kesso-init-rest-controller.php';
        require_once KESSO_INIT_PATH . 'includes/api/class-kesso-init-plugins-endpoint.php';
        require_once KESSO_INIT_PATH . 'includes/api/class-kesso-init-theme-endpoint.php';
        require_once KESSO_INIT_PATH . 'includes/api/class-kesso-init-settings-endpoint.php';
        require_once KESSO_INIT_PATH . 'includes/api/class-kesso-init-batch-endpoint.php';
    }

    /**
     * Initialize service classes (lazy loading)
     */
    private function init_services() {
        // Services will be loaded lazily when needed via get_service methods
        $this->plugin_service   = null;
        $this->theme_service    = null;
        $this->settings_service = null;
    }

    /**
     * Get plugin service instance (lazy load)
     *
     * @return Kesso_Init_Plugin_Service
     */
    public function get_plugin_service() {
        if ( null === $this->plugin_service ) {
            require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-plugin-service.php';
            $this->plugin_service = new Kesso_Init_Plugin_Service();
        }
        return $this->plugin_service;
    }

    /**
     * Get theme service instance (lazy load)
     *
     * @return Kesso_Init_Theme_Service
     */
    public function get_theme_service() {
        if ( null === $this->theme_service ) {
            require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-theme-service.php';
            $this->theme_service = new Kesso_Init_Theme_Service();
        }
        return $this->theme_service;
    }

    /**
     * Get settings service instance (lazy load)
     *
     * @return Kesso_Init_Settings_Service
     */
    public function get_settings_service() {
        if ( null === $this->settings_service ) {
            require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-settings-service.php';
            $this->settings_service = new Kesso_Init_Settings_Service();
        }
        return $this->settings_service;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize admin page
        $this->admin_page = new Kesso_Init_Admin_Page();

        // Initialize REST API (services will be loaded lazily)
        $this->rest_controller = new Kesso_Init_Rest_Controller( $this );

        // Handle first-run redirect
        add_action( 'admin_init', array( 'Kesso_Init_Activation', 'maybe_redirect' ) );

        // Add restart wizard link to Tools menu
        add_action( 'admin_menu', array( $this, 'add_restart_wizard_link' ), 99 );
    }

    /**
     * Add restart wizard submenu under Tools
     */
    public function add_restart_wizard_link() {
        add_submenu_page(
            'tools.php',
            __( 'Restart Setup Wizard', 'kesso-init' ),
            __( 'Restart Setup Wizard', 'kesso-init' ),
            'manage_options',
            'kesso-init-restart',
            array( $this, 'handle_restart_wizard' )
        );
    }

    /**
     * Handle restart wizard action
     */
    public function handle_restart_wizard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'kesso-init' ) );
        }

        // Delete the completed flag
        delete_option( 'kesso_init_wizard_completed' );

        // Redirect to wizard
        wp_safe_redirect( admin_url( 'admin.php?page=kesso-init' ) );
        exit;
    }

    /**
     * Get list of recommended plugins
     *
     * @return array
     */
    public function get_plugin_list() {
        return array(
            array(
                'slug'        => 'secure-custom-fields',
                'name'        => 'Secure Custom Fields',
                'description' => 'Flexible custom fields management for WordPress.',
                'source'      => 'wordpress.org',
                'file'        => 'secure-custom-fields/secure-custom-fields.php',
                'category'    => 'Content',
            ),
            array(
                'slug'        => 'polylang',
                'name'        => 'Polylang',
                'description' => 'Multilingual site creation made simple.',
                'source'      => 'wordpress.org',
                'file'        => 'polylang/polylang.php',
                'category'    => 'Content',
            ),
            array(
                'slug'        => 'classic-editor-addon',
                'name'        => 'Classic Editor Addon',
                'description' => 'Enhanced features for the classic editor.',
                'source'      => 'wordpress.org',
                'file'        => 'classic-editor-addon/classic-editor-addon.php',
                'category'    => 'Content',
            ),
            array(
                'slug'        => 'wordfence',
                'name'        => 'Wordfence Security',
                'description' => 'Firewall & malware scanner for WordPress.',
                'source'      => 'wordpress.org',
                'file'        => 'wordfence/wordfence.php',
                'category'    => 'Security',
            ),
            array(
                'slug'        => 'woocommerce',
                'name'        => 'WooCommerce',
                'description' => 'The most customizable eCommerce platform.',
                'source'      => 'wordpress.org',
                'file'        => 'woocommerce/woocommerce.php',
                'category'    => 'Ecommerce',
            ),
            array(
                'slug'        => 'google-site-kit',
                'name'        => 'Site Kit by Google',
                'description' => 'Analytics, Search Console, AdSense and more.',
                'source'      => 'wordpress.org',
                'file'        => 'site-kit-by-google/google-site-kit.php',
                'category'    => 'Tracking',
            ),
            array(
                'slug'        => 'ewww-image-optimizer',
                'name'        => 'EWWW Image Optimizer',
                'description' => 'Optimize images, PDFs, and more.',
                'source'      => 'wordpress.org',
                'file'        => 'ewww-image-optimizer/ewww-image-optimizer.php',
                'category'    => 'Optimize',
            ),
            array(
                'slug'        => 'autoptimize',
                'name'        => 'Autoptimize',
                'description' => 'Optimize your website performance.',
                'source'      => 'wordpress.org',
                'file'        => 'autoptimize/autoptimize.php',
                'category'    => 'Optimize',
            ),
            array(
                'slug'        => 'microsoft-clarity',
                'name'        => 'Microsoft Clarity',
                'description' => 'Free analytics tool with heatmaps and session recordings.',
                'source'      => 'wordpress.org',
                'file'        => 'microsoft-clarity/microsoft-clarity.php',
                'category'    => 'Tracking',
            ),
            array(
                'slug'        => 'litespeed-cache',
                'name'        => 'LiteSpeed Cache',
                'description' => 'High-performance page caching and optimization.',
                'source'      => 'wordpress.org',
                'file'        => 'litespeed-cache/litespeed-cache.php',
                'category'    => 'Optimize',
            ),
            array(
                'slug'        => 'wp-mail-smtp',
                'name'        => 'WP Mail SMTP',
                'description' => 'Improve email deliverability with SMTP configuration.',
                'source'      => 'wordpress.org',
                'file'        => 'wp-mail-smtp/wp_mail_smtp.php',
                'category'    => 'Develop',
            ),
            array(
                'slug'        => 'code-snippets',
                'name'        => 'Code Snippets',
                'description' => 'Run PHP code snippets on your site without editing functions.php.',
                'source'      => 'wordpress.org',
                'file'        => 'code-snippets/code-snippets.php',
                'category'    => 'Develop',
            ),
            array(
                'slug'        => 'bing-webmaster-tools',
                'name'        => 'Bing URL Submissions',
                'description' => 'Automated URL submission to Bing for faster indexing.',
                'source'      => 'wordpress.org',
                'file'        => 'bing-webmaster-tools/bing-webmaster-tools.php',
                'category'    => 'Tracking',
            ),
            array(
                'slug'        => 'updraftplus',
                'name'        => 'UpdraftPlus',
                'description' => 'Backup, restore and migrate your WordPress website.',
                'source'      => 'wordpress.org',
                'file'        => 'updraftplus/updraftplus.php',
                'category'    => 'Security',
            ),
        );
    }

    /**
     * Get available timezones
     *
     * @return array
     */
    public function get_timezone_list() {
        $timezones = timezone_identifiers_list();
        $options   = array();
        $utc       = new DateTimeZone( 'UTC' );
        $now_utc   = new DateTime( 'now', $utc );

        foreach ( $timezones as $timezone ) {
            $label = $timezone;

            try {
                $tz     = new DateTimeZone( $timezone );
                $offset = $tz->getOffset( $now_utc );

                $sign    = ( $offset >= 0 ) ? '+' : '-';
                $abs     = abs( $offset );
                $hours   = floor( $abs / 3600 );
                $minutes = floor( ( $abs % 3600 ) / 60 );

                $offset_label = sprintf( 'UTC%s%02d:%02d', $sign, $hours, $minutes );

                // Try to enrich with country + city.
                $location = $tz->getLocation();
                $country  = '';
                $city     = '';

                if ( is_array( $location ) ) {
                    if ( ! empty( $location['country_code'] ) && is_string( $location['country_code'] ) ) {
                        $country_code = strtoupper( $location['country_code'] );
                        $country      = $country_code;

                        // If intl is available, try to show English country name.
                        if ( class_exists( 'Locale' ) ) {
                            // Locale expects something like en_US, so build one.
                            $candidate = 'en_' . $country_code;
                            $display   = Locale::getDisplayRegion( $candidate, 'en' );
                            if ( is_string( $display ) && $display !== '' && $display !== $candidate ) {
                                $country = $display;
                            }
                        }
                    }
                }

                // City from timezone identifier (best-effort).
                $parts = explode( '/', $timezone );
                if ( count( $parts ) >= 2 ) {
                    $city = str_replace( '_', ' ', end( $parts ) );
                }

                if ( $country && $city ) {
                    $label = sprintf( '%s — %s — %s', $offset_label, $country, $city );
                } elseif ( $city ) {
                    $label = sprintf( '%s — %s', $offset_label, $city );
                } else {
                    $label = sprintf( '%s — %s', $offset_label, str_replace( '_', ' ', $timezone ) );
                }
            } catch ( Throwable $e ) {
                // Fallback label if timezone parsing fails.
                $label = str_replace( '_', ' ', $timezone );
            }

            $options[] = array(
                'value' => $timezone,
                'label' => $label,
            );
        }

        return $options;
    }

    /**
     * Get available date formats
     *
     * @return array
     */
    public function get_date_formats() {
        return array(
            'F j, Y'  => date_i18n( 'F j, Y' ),
            'Y-m-d'   => date_i18n( 'Y-m-d' ),
            'm/d/Y'   => date_i18n( 'm/d/Y' ),
            'd/m/Y'   => date_i18n( 'd/m/Y' ),
        );
    }

    /**
     * Get available time formats
     *
     * @return array
     */
    public function get_time_formats() {
        return array(
            'g:i a' => date_i18n( 'g:i a' ),
            'g:i A' => date_i18n( 'g:i A' ),
            'H:i'   => date_i18n( 'H:i' ),
        );
    }

    /**
     * Get days of the week
     *
     * @return array
     */
    public function get_week_days() {
        global $wp_locale;
        $days = array();

        for ( $i = 0; $i <= 6; $i++ ) {
            $days[] = array(
                'value' => $i,
                'label' => $wp_locale->get_weekday( $i ),
            );
        }

        return $days;
    }

    /**
     * Get list of Kesso plugins from GitHub
     *
     * @return array
     */
    public function get_kesso_plugin_list() {
        return array(
            array(
                'slug'        => 'kesso-access',
                'name'        => '🧀 Kesso Accessibility',
                'description' => 'Accessibility widget and tools for WordPress.',
                'source'      => 'github',
                'file'        => 'kesso-access/kesso-access.php',
                'github_repo' => 'ekdsolutions/kesso-wp-plugins',
                'github_url'  => 'https://github.com/ekdsolutions/kesso-wp-plugins/tree/master/kesso-access',
            ),
            array(
                'slug'        => 'kesso-cookies',
                'name'        => '🧀 Kesso Cookies',
                'description' => 'Cookie consent banner and management.',
                'source'      => 'github',
                'file'        => 'kesso-cookies/kesso-cookies.php',
                'github_repo' => 'ekdsolutions/kesso-wp-plugins',
                'github_url'  => 'https://github.com/ekdsolutions/kesso-wp-plugins/tree/master/kesso-cookies',
            ),
            array(
                'slug'        => 'kesso-woo',
                'name'        => '🧀 Kesso WooCommerce',
                'description' => 'WooCommerce utilities and enhancements.',
                'source'      => 'github',
                'file'        => 'kesso-woo/kesso-woo.php',
                'github_repo' => 'ekdsolutions/kesso-wp-plugins',
                'github_url'  => 'https://github.com/ekdsolutions/kesso-wp-plugins/tree/master/kesso-woo',
            ),
            array(
                'slug'        => 'kesso-msg',
                'name'        => '🧀 Kesso Messages',
                'description' => 'Admin notice collector and manager.',
                'source'      => 'github',
                'file'        => 'kesso-msg/kesso-msg.php',
                'github_repo' => 'ekdsolutions/kesso-wp-plugins',
                'github_url'  => 'https://github.com/ekdsolutions/kesso-wp-plugins/tree/master/kesso-msg',
            ),
        );
    }
}

