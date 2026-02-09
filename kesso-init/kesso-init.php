<?php
/**
 * Plugin Name: Kesso Initialization
 * Plugin URI: https://kesso.io
 * Description: A unified setup wizard for new WordPress sites - install plugins, page builders, child themes, and configure settings all from one page.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kesso-init
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'KESSO_INIT_VERSION', '1.0.0' );
define( 'KESSO_INIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'KESSO_INIT_URL', plugin_dir_url( __FILE__ ) );
define( 'KESSO_INIT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function( $class ) {
    // Only handle our namespace
    if ( strpos( $class, 'Kesso_Init' ) !== 0 ) {
        return;
    }

    // Convert class name to file path
    $class_file = str_replace( '_', '-', strtolower( $class ) );
    $class_file = 'class-' . $class_file . '.php';

    // Define possible paths
    $paths = array(
        KESSO_INIT_PATH . 'includes/',
        KESSO_INIT_PATH . 'includes/admin/',
        KESSO_INIT_PATH . 'includes/api/',
        KESSO_INIT_PATH . 'includes/services/',
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
 * Plugin activation hook
 */
function kesso_init_activate() {
    require_once KESSO_INIT_PATH . 'includes/admin/class-kesso-init-activation.php';
    Kesso_Init_Activation::activate();
}
register_activation_hook( __FILE__, 'kesso_init_activate' );

/**
 * Plugin deactivation hook
 */
function kesso_init_deactivate() {
    require_once KESSO_INIT_PATH . 'includes/admin/class-kesso-init-activation.php';
    Kesso_Init_Activation::deactivate();
}
register_deactivation_hook( __FILE__, 'kesso_init_deactivate' );

/**
 * Initialize the plugin
 */
function kesso_init_run() {
    require_once KESSO_INIT_PATH . 'includes/class-kesso-init.php';
    return Kesso_Init::get_instance();
}
add_action( 'plugins_loaded', 'kesso_init_run' );

