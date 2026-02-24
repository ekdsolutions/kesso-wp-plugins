<?php
/**
 * Product Page Hooks Class
 *
 * Handles size and texture selectors on product pages
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles product page frontend functionality
 */
class Ratio_Pricing_Product {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * Does not rely on is_singular('product') so Bricks templates work.
	 * Enqueues when we can resolve a product ID and it is a print product.
	 */
	public function enqueue_assets() {
		$product_id = $this->get_single_product_id();
		if ( ! $product_id ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return;
		}

		$ratio_pricing = Ratio_Pricing::instance();
		if ( ! $ratio_pricing->is_print_product( $product_id ) ) {
			return;
		}

		$sizes = array();
		$ratio = $ratio_pricing->get_product_ratio( $product_id );
		if ( $ratio ) {
			$ratio_sizes = $ratio_pricing->get_ratio_sizes( $ratio->ID );
			$settings   = $ratio_pricing->get_settings();
			foreach ( $ratio_sizes as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$size  = isset( $item[ $settings['field_size'] ] ) ? $item[ $settings['field_size'] ] : '';
				$price = isset( $item[ $settings['field_price'] ] ) ? floatval( $item[ $settings['field_price'] ] ) : 0;
				if ( '' !== $size ) {
					$sizes[ $size ] = $price;
				}
			}
		}

		$settings = $ratio_pricing->get_settings();

		$size_ui   = isset( $settings['size_ui'] ) && is_array( $settings['size_ui'] ) ? $settings['size_ui'] : array();
		$default_ui = array( 'placeholder' => 'Choose a size...', 'format' => '{{size}} - {{price}} {{currency}}' );
		if ( isset( $size_ui['default'] ) && is_array( $size_ui['default'] ) ) {
			$default_ui = array(
				'placeholder' => isset( $size_ui['default']['placeholder'] ) ? $size_ui['default']['placeholder'] : $default_ui['placeholder'],
				'format'      => isset( $size_ui['default']['format'] ) ? $size_ui['default']['format'] : $default_ui['format'],
			);
		}
		$lang = ( function_exists( 'pll_current_language' ) && pll_current_language() ) ? pll_current_language() : 'default';
		$placeholder = $default_ui['placeholder'];
		$format = $default_ui['format'];
		if ( $lang !== 'default' && isset( $size_ui[ $lang ] ) && is_array( $size_ui[ $lang ] ) ) {
			if ( ! empty( $size_ui[ $lang ]['placeholder'] ) ) {
				$placeholder = $size_ui[ $lang ]['placeholder'];
			}
			if ( ! empty( trim( $size_ui[ $lang ]['format'] ?? '' ) ) ) {
				$format = $size_ui[ $lang ]['format'];
			}
		}

		$price_decimals = 2;
		$decimal_sep    = '.';
		$thousand_sep   = ',';
		$currency_pos   = 'left';
		$currency_sym   = '₪';
		if ( function_exists( 'wc_get_price_decimals' ) ) {
			$price_decimals = (int) wc_get_price_decimals();
		}
		if ( function_exists( 'wc_get_price_decimal_separator' ) ) {
			$decimal_sep = wc_get_price_decimal_separator();
		}
		if ( function_exists( 'wc_get_price_thousand_separator' ) ) {
			$thousand_sep = wc_get_price_thousand_separator();
		}
		$currency_pos = get_option( 'woocommerce_currency_pos', 'left' );
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$currency_sym = get_woocommerce_currency_symbol();
		}

		wp_enqueue_style(
			'ratio-pricing-frontend',
			RATIO_PRICING_ASSETS_URL . 'css/frontend.css',
			array(),
			RATIO_PRICING_VERSION
		);

		wp_enqueue_script(
			'ratio-pricing-frontend',
			RATIO_PRICING_ASSETS_URL . 'js/frontend.js',
			array( 'jquery' ),
			RATIO_PRICING_VERSION,
			true
		);

		wp_localize_script(
			'ratio-pricing-frontend',
			'ratioPricingConfig',
			array(
				'sizes'               => $sizes,
				'texturePercentage'   => isset( $settings['texture_percentage'] ) ? floatval( $settings['texture_percentage'] ) : 30,
				'placeholder'         => $placeholder,
				'format'              => $format,
				'currencySymbol'      => $currency_sym,
				'priceDecimals'       => $price_decimals,
				'decimalSeparator'    => $decimal_sep,
				'thousandSeparator'   => $thousand_sep,
				'currencyPosition'    => $currency_pos,
			)
		);

		// TEMP: remove after confirming assets load on Bricks product pages
		add_action( 'wp_footer', array( $this, 'debug_footer_comment' ), 5 );
	}

	/**
	 * Resolve product ID on single product context (Bricks-safe).
	 *
	 * Tries queried object, current post, global $post, then URL-based fallback
	 * so it works when the main query is the Bricks template and not the product.
	 *
	 * @return int Product ID or 0.
	 */
	private function get_single_product_id() {
		// 1. Queried object (e.g. default single product)
		$product_id = get_queried_object_id();
		if ( $product_id ) {
			$post = get_post( $product_id );
			if ( $post && $post->post_type === 'product' ) {
				return $product_id;
			}
		}

		// 2. Current post ID (e.g. in the loop)
		$product_id = get_the_ID();
		if ( $product_id ) {
			$post = get_post( $product_id );
			if ( $post && $post->post_type === 'product' ) {
				return $product_id;
			}
		}

		// 3. Global $post
		global $post;
		if ( ! empty( $post->ID ) && get_post_type( $post->ID ) === 'product' ) {
			return (int) $post->ID;
		}

		// 4. URL-based fallback (Bricks: main query may be template, not product)
		$product_id = $this->get_product_id_from_request_url();
		if ( $product_id ) {
			return $product_id;
		}

		return 0;
	}

	/**
	 * Resolve product ID from current request path (WooCommerce single-product URL).
	 *
	 * @return int Product ID or 0.
	 */
	private function get_product_id_from_request_url() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return 0;
		}

		$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! is_string( $path ) || $path === '' ) {
			return 0;
		}

		$path = trim( $path, '/' );
		if ( $path === '' ) {
			return 0;
		}

		$segments = array_filter( explode( '/', $path ) );
		$permalinks = get_option( 'woocommerce_permalinks', array() );
		$product_base = isset( $permalinks['product_base'] ) && $permalinks['product_base'] !== '' ? trim( $permalinks['product_base'], '/' ) : 'product';

		$slug = null;
		foreach ( $segments as $i => $segment ) {
			if ( $segment === $product_base && isset( $segments[ $i + 1 ] ) ) {
				$slug = $segments[ $i + 1 ];
				break;
			}
		}
		if ( $slug === null || $slug === '' ) {
			return 0;
		}

		$post = get_page_by_path( $slug, OBJECT, 'product' );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return 0;
		}

		$product_id = (int) $post->ID;
		if ( get_post_type( $product_id ) !== 'product' ) {
			return 0;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		return $product_id;
	}

	/**
	 * TEMP: HTML comment when enqueue_assets ran. Remove after confirmation.
	 */
	public function debug_footer_comment() {
		echo '<!-- ratio-pricing enqueue_assets reached -->';
	}
}

