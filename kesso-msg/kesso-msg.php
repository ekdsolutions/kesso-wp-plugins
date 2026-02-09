<?php
/**
 * Plugin Name: Kesso Messages
 * Plugin URI: https://kesso.io
 * Description: Centralized admin notifications with a notification bell icon in the admin bar. Collects and displays all admin notices in a clean, minimizable popup.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kesso-msg
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'KESSO_MSG_VERSION', '1.0.0' );
define( 'KESSO_MSG_PATH', plugin_dir_path( __FILE__ ) );
define( 'KESSO_MSG_URL', plugin_dir_url( __FILE__ ) );
define( 'KESSO_MSG_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function( $class ) {
    // Only handle our namespace
    if ( strpos( $class, 'Kesso_Msg' ) !== 0 ) {
        return;
    }

    // Convert class name to file path
    $class_file = str_replace( '_', '-', strtolower( $class ) );
    $class_file = 'class-' . $class_file . '.php';

    // Define possible paths
    $paths = array(
        KESSO_MSG_PATH . 'includes/',
        KESSO_MSG_PATH . 'includes/admin/',
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
class Kesso_Msg {
    
    /**
     * Single instance of the class
     *
     * @var Kesso_Msg
     */
    private static $instance = null;

    /**
     * Admin page handler
     *
     * @var Kesso_Msg_Admin
     */
    public $admin;

    /**
     * Admin bar handler
     *
     * @var Kesso_Msg_Admin_Bar
     */
    public $admin_bar;

    /**
     * Notice collector
     *
     * @var Kesso_Msg_Notice_Collector
     */
    public $notice_collector;

    /**
     * Get single instance
     *
     * @return Kesso_Msg
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new Kesso_Msg_Admin();
            $this->admin_bar = new Kesso_Msg_Admin_Bar();
            $this->notice_collector = new Kesso_Msg_Notice_Collector();
        }
    }
}

/**
 * Initialize plugin
 */
function kesso_msg() {
    return Kesso_Msg::instance();
}

// Initialize plugin
kesso_msg();

