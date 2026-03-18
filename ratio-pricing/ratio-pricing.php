<?php
/**
 * Plugin Name: Ratio Pricing
 * Plugin URI: https://kesso.io
 * Description: Dynamic pricing for WooCommerce artwork products based on size selections from ACF or SCF fields and print-ratio CPT.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ratio-pricing
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'RATIO_PRICING_VERSION', '1.0.0' );
define( 'RATIO_PRICING_MAIN_FILE', __FILE__ );
define( 'RATIO_PRICING_PATH', plugin_dir_path( __FILE__ ) );
define( 'RATIO_PRICING_URL', plugins_url( '/', __FILE__ ) );
define( 'RATIO_PRICING_ASSETS_URL', RATIO_PRICING_URL . 'assets/' );

/**
 * Main plugin class
 */
final class Ratio_Pricing_Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Ratio_Pricing_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Ratio_Pricing_Plugin
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
		$this->check_dependencies();
		$this->includes();
	
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Check if required plugins are active
	 */
	private function check_dependencies() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
		}

		// Check if ACF or SCF is active (at least one required)
		if ( ! $this->has_fields_provider() ) {
			add_action( 'admin_notices', array( $this, 'missing_fields_notice' ) );
		}
	}

	/**
	 * Whether ACF or SCF is available (must be called after includes)
	 *
	 * @return bool
	 */
	private function has_fields_provider() {
		if ( ! class_exists( 'Ratio_Pricing_Fields' ) ) {
			return false;
		}
		return Ratio_Pricing_Fields::has_provider();
	}

	/**
	 * Display admin notice for missing WooCommerce
	 */
	public function missing_woocommerce_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Ratio Pricing', 'ratio-pricing' ); ?></strong> 
				<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'ratio-pricing' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice when neither ACF nor SCF is installed
	 */
	public function missing_fields_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Ratio Pricing', 'ratio-pricing' ); ?></strong>
				<?php esc_html_e( 'requires either Advanced Custom Fields (ACF) or Smart Custom Fields (SCF) to be installed and activated for dynamic pricing to work.', 'ratio-pricing' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once RATIO_PRICING_PATH . 'includes/class-ratio-pricing-fields.php';
		require_once RATIO_PRICING_PATH . 'includes/class-ratio-pricing.php';

		if ( is_admin() ) {
			require_once RATIO_PRICING_PATH . 'includes/admin/class-ratio-pricing-admin.php';
		}

		require_once RATIO_PRICING_PATH . 'includes/frontend/class-ratio-pricing-product.php';
		require_once RATIO_PRICING_PATH . 'includes/frontend/class-ratio-pricing-price.php';
		require_once RATIO_PRICING_PATH . 'includes/frontend/class-ratio-pricing-bricks-tags.php';
		require_once RATIO_PRICING_PATH . 'includes/cart/class-ratio-pricing-cart.php';
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Admin always loads
		if ( is_admin() ) {
			new Ratio_Pricing_Admin();
		}
	
		// Frontend UI should load whenever WooCommerce is active (Bricks-safe)
		if ( class_exists( 'WooCommerce' ) && ! is_admin() ) {
			new Ratio_Pricing_Product();
			new Ratio_Pricing_Price();
			new Ratio_Pricing_Bricks_Tags();
		}
	
		// Core/cart logic only when fields provider exists
		if ( class_exists( 'WooCommerce' ) && $this->has_fields_provider() ) {
			Ratio_Pricing::instance();
			new Ratio_Pricing_Cart();
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
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Something went wrong.', 'ratio-pricing' ), '1.0.0' );
	}
}

// Initialize plugin
Ratio_Pricing_Plugin::instance();

