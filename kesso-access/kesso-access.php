<?php

/**
 * Plugin Name: Kesso Accessibility
 * Plugin URI: https://kesso.io
 * Description: Accessibility widget with font resizing, contrast modes, and keyboard navigation helpers.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kesso-access
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'KESSO_VERSION', '1.0.0' );
define( 'KESSO_MAIN_FILE', __FILE__ );
define( 'KESSO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KESSO_URL', plugins_url( '/', __FILE__ ) );
define( 'KESSO_ASSETS_URL', KESSO_URL . 'public/assets/' );

/**
 * Main plugin class
 */
final class Kesso_Plugin {
	
	/**
	 * Plugin instance
	 *
	 * @var Kesso_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Kesso_Plugin
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
		$this->includes();
		$this->init();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once KESSO_PATH . 'includes/class-widget.php';
		require_once KESSO_PATH . 'admin/class-admin.php';
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Initialize frontend widget
		new Kesso_Widget();

		// Initialize admin
		if ( is_admin() ) {
			new Kesso_Admin();
		}
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Something went wrong.', 'kesso-access' ), '1.0.0' );
	}
}

// Initialize plugin
Kesso_Plugin::instance();
