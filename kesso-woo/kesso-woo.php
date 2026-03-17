<?php
/**
 * Plugin Name: Kesso WooCommerce
 * Plugin URI: https://kesso.io
 * Description: A collection of WooCommerce utilities and enhancements to improve your store's functionality.
 * Version: 1.0.0
 * Author: Kesso
 * Author URI: https://kesso.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kesso-woo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'KESSO_WOO_VERSION', '1.0.0' );
define( 'KESSO_WOO_MAIN_FILE', __FILE__ );
define( 'KESSO_WOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KESSO_WOO_URL', plugins_url( '/', __FILE__ ) );
define( 'KESSO_WOO_ASSETS_URL', KESSO_WOO_URL . 'assets/' );

/**
 * Main plugin class
 */
final class Kesso_Woo_Plugin {
	
	/**
	 * Plugin instance
	 *
	 * @var Kesso_Woo_Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Kesso_Woo_Plugin
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
		// Always init admin so the settings page is reachable even without deps
		if ( is_admin() ) {
			require_once KESSO_WOO_PATH . 'includes/admin/class-kesso-woo-admin.php';
			new Kesso_Woo_Admin();
		}

		if ( ! $this->check_dependencies() ) {
			return;
		}

		$this->includes();
		$this->init();
	}

	/**
	 * Check if required plugins are active
	 */
	private function check_dependencies() {
		// Check if Polylang is active
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_polylang_notice' ) );
			return false;
		}

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Display admin notice for missing Polylang
	 */
	public function missing_polylang_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Kesso WooCommerce', 'kesso-woo' ); ?></strong> 
				<?php esc_html_e( 'Some features require Polylang to be installed and activated.', 'kesso-woo' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display admin notice for missing WooCommerce
	 */
	public function missing_woocommerce_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Kesso WooCommerce', 'kesso-woo' ); ?></strong> 
				<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'kesso-woo' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once KESSO_WOO_PATH . 'includes/class-kesso-woo-sync.php';
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Enable WooCommerce products for Polylang translation
		$this->enable_translation();

		// Initialize sync functionality (only if enabled in settings)
		if ( get_option( 'kesso_woo_enabled', 'yes' ) === 'yes' ) {
			new Kesso_Woo_Sync();
		}
	}

	/**
	 * Enable WooCommerce Products for Polylang Translation
	 */
	private function enable_translation() {
		// Enable product post type for Polylang
		add_filter( 'pll_get_post_types', function( $post_types, $is_settings ) {
			// Only apply when NOT in settings page (when actually using Polylang)
			if ( ! $is_settings ) {
				if ( post_type_exists( 'product' ) ) {
					$post_types['product'] = 'product';
				}
				if ( post_type_exists( 'product_variation' ) ) {
					$post_types['product_variation'] = 'product_variation';
				}
			}
			return $post_types;
		}, 10, 2 );
		
		// Enable product taxonomies for Polylang
		add_filter( 'pll_get_taxonomies', function( $taxonomies, $is_settings ) {
			// Only apply when NOT in settings page
			if ( ! $is_settings ) {
				// Product categories and tags
				if ( taxonomy_exists( 'product_cat' ) ) {
					$taxonomies['product_cat'] = 'product_cat';
				}
				if ( taxonomy_exists( 'product_tag' ) ) {
					$taxonomies['product_tag'] = 'product_tag';
				}
				if ( taxonomy_exists( 'product_shipping_class' ) ) {
					$taxonomies['product_shipping_class'] = 'product_shipping_class';
				}
				
				// Product attributes (pa_* taxonomies)
				if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
					$attribute_taxonomies = wc_get_attribute_taxonomies();
					if ( $attribute_taxonomies ) {
						foreach ( $attribute_taxonomies as $tax ) {
							$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
							if ( taxonomy_exists( $taxonomy ) ) {
								$taxonomies[ $taxonomy ] = $taxonomy;
							}
						}
					}
				}
			}
			return $taxonomies;
		}, 10, 2 );
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Something went wrong.', 'kesso-woo' ), '1.0.0' );
	}
}

// Initialize plugin
Kesso_Woo_Plugin::instance();

