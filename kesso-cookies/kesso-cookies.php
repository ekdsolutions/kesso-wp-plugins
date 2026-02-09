<?php
/**
 * Plugin Name: Kesso Cookies
 * Plugin URI: https://kesso.io
 * Description: GDPR-compliant cookie consent banner with script blocking and category management.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kesso-cookies
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'KESSO_COOKIES_VERSION', '1.0.0' );
define( 'KESSO_COOKIES_PATH', plugin_dir_path( __FILE__ ) );
define( 'KESSO_COOKIES_URL', plugin_dir_url( __FILE__ ) );
define( 'KESSO_COOKIES_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function( $class ) {
    // Only handle our namespace
    if ( strpos( $class, 'Kesso_Cookies' ) !== 0 ) {
        return;
    }

    // Convert class name to file path
    $class_file = str_replace( '_', '-', strtolower( $class ) );
    $class_file = 'class-' . $class_file . '.php';

    // Define possible paths
    $paths = array(
        KESSO_COOKIES_PATH . 'includes/',
        KESSO_COOKIES_PATH . 'includes/admin/',
        KESSO_COOKIES_PATH . 'includes/frontend/',
    );

    // Search for the class file
    foreach ( $paths as $path ) {
        $file = $path . $class_file;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
});

/**
 * Main plugin class
 */
class Kesso_Cookies {
    
    /**
     * Single instance of the class
     *
     * @var Kesso_Cookies
     */
    private static $instance = null;

    /**
     * Admin page handler
     *
     * @var Kesso_Cookies_Admin
     */
    public $admin;

    /**
     * Frontend banner handler
     *
     * @var Kesso_Cookies_Banner
     */
    public $banner;

    /**
     * Get the singleton instance
     *
     * @return Kesso_Cookies
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
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Admin
        require_once KESSO_COOKIES_PATH . 'includes/admin/class-kesso-cookies-admin.php';
        
        // Frontend
        require_once KESSO_COOKIES_PATH . 'includes/frontend/class-kesso-cookies-banner.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Initialize admin page
        if ( is_admin() ) {
            $this->admin = new Kesso_Cookies_Admin();
        }

        // Initialize frontend banner
        if ( ! is_admin() ) {
            $this->banner = new Kesso_Cookies_Banner();
        }

        // Add footer link to reopen consent panel
        add_action( 'wp_footer', array( $this, 'add_consent_link' ) );
    }

    /**
     * Check if we're in a page builder context
     *
     * @return bool
     */
    private function is_page_builder() {
        // Bricks Builder
        if ( defined( 'BRICKS_VERSION' ) && function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
            return true;
        }

        // Elementor
        if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
                return true;
            }
        }

        // Beaver Builder
        if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
            return true;
        }

        // Divi Builder
        if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
            return true;
        }

        // Visual Composer
        if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
            return true;
        }

        // Check for common builder query parameters
        if ( isset( $_GET['bricks'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['vc_editable'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Add footer link to reopen consent panel
     */
    public function add_consent_link() {
        // Don't render in page builders
        if ( $this->is_page_builder() ) {
            return;
        }

        $link_text = __( 'Cookie Settings', 'kesso-cookies' );
        $position = get_option( 'kesso_cookies_settings_position', 'bottom-right' );
        $position_class = 'kesso-cookies-settings-position-' . esc_attr( $position );
        ?>
        <a href="#" id="kesso-cookies-settings-link" class="kesso-cookies-settings-link <?php echo esc_attr( $position_class ); ?>" style="display: none;" aria-label="<?php echo esc_attr( $link_text ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="kesso-cookies-settings-icon" aria-hidden="true">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M13.53 2.552l2.667 1.104a1 1 0 0 1 .414 1.53a3 3 0 0 0 3.492 4.604a1 1 0 0 1 1.296 .557l.049 .122a4 4 0 0 1 0 3.062l-.079 .151c-.467 .74 -.785 1.314 -.945 1.7c-.166 .4 -.373 1.097 -.613 2.073l-.047 .144a4 4 0 0 1 -2.166 2.164l-.139 .046c-1.006 .253 -1.705 .461 -2.076 .615c-.412 .17 -.982 .486 -1.696 .942l-.156 .082a4 4 0 0 1 -3.062 0l-.148 -.077c-.759 -.475 -1.333 -.793 -1.704 -.947c-.413 -.171 -1.109 -.378 -2.07 -.612l-.146 -.048a4 4 0 0 1 -2.164 -2.166l-.046 -.138c-.254 -1.009 -.463 -1.709 -.615 -2.078q -.256 -.621 -.942 -1.695l-.082 -.156a4 4 0 0 1 0 -3.062l.084 -.16c.447 -.692 .761 -1.262 .94 -1.692c.147 -.355 .356 -1.057 .615 -2.078l.045 -.138a4 4 0 0 1 2.166 -2.164l.141 -.047c.988 -.245 1.686 -.453 2.074 -.614c.395 -.164 .967 -.48 1.7 -.944l.152 -.08a4 4 0 0 1 3.062 0m-1.531 13.448a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m4 -3a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m-8 -1a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m4 -1a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m-1 -4c-.552 0 -1 .448 -1 1.01a1 1 0 1 0 2 -.01a1 1 0 0 0 -1 -1" />
            </svg>
        </a>
        <?php
    }
}

/**
 * Initialize the plugin
 */
function kesso_cookies_init() {
    return Kesso_Cookies::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'kesso_cookies_init' );
