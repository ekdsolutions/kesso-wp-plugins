<?php
/**
 * Core Ratio Pricing Class
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core functionality for Ratio Pricing
 */
class Ratio_Pricing {

	/**
	 * Instance
	 *
	 * @var Ratio_Pricing
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Ratio_Pricing
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
		// Core class - no hooks needed
	}

	/**
	 * Get plugin settings
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'texture_percentage'    => 30,
			'field_artwork_type'    => 'artwork_type',
			'field_ratio'           => 'ratio',
			'field_print_ratios'    => 'print_ratios',
			'field_size'            => 'size',
			'field_price'           => 'price',
			'field_texture'         => 'texture',
			'size_ui'               => array( 'default' => array( 'placeholder' => 'Choose a size...', 'format' => '{{size}} - {{price}} {{currency}}' ) ),
		);

		$settings = get_option( 'ratio_pricing_settings', array() );
		$merged   = wp_parse_args( $settings, $defaults );
		if ( empty( $merged['size_ui'] ) || ! is_array( $merged['size_ui'] ) ) {
			$merged['size_ui'] = $defaults['size_ui'];
		}
		if ( ! isset( $merged['size_ui']['default'] ) ) {
			$merged['size_ui']['default'] = $defaults['size_ui']['default'];
		}
		return $merged;
	}

	/**
	 * Check if product is a print product (Simple type with artwork_type = 'print')
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function is_print_product( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return false;
		}

		$settings = $this->get_settings();
		$artwork_type = Ratio_Pricing_Fields::get_field( $settings['field_artwork_type'], $product_id );

		if ( ! is_string( $artwork_type ) ) {
			return false;
		}
		return 'print' === strtolower( trim( $artwork_type ) );
	}

	/**
	 * Get the ratio CPT post object from product ACF field
	 *
	 * @param int $product_id Product ID.
	 * @return WP_Post|false
	 */
	public function get_product_ratio( $product_id ) {
		$settings = $this->get_settings();
		$ratio = Ratio_Pricing_Fields::get_field( $settings['field_ratio'], $product_id );

		// ACF post object field returns WP_Post object or ID; SCF may return ID
		if ( is_numeric( $ratio ) ) {
			$ratio = get_post( $ratio );
		}

		if ( ! $ratio || ! is_a( $ratio, 'WP_Post' ) ) {
			return false;
		}

		return $ratio;
	}

	/**
	 * Get print_ratios repeater field data
	 *
	 * @param int $ratio_id Ratio CPT post ID.
	 * @return array
	 */
	public function get_ratio_sizes( $ratio_id ) {
		$settings = $this->get_settings();
		$ratios = Ratio_Pricing_Fields::get_field( $settings['field_print_ratios'], $ratio_id );

		if ( ! is_array( $ratios ) ) {
			return array();
		}

		return $ratios;
	}

	/**
	 * Find price for specific size in repeater
	 *
	 * @param int    $ratio_id Ratio CPT post ID.
	 * @param string $size     Size string (e.g., "40x60").
	 * @return float|false
	 */
	public function get_size_price( $ratio_id, $size ) {
		$sizes = $this->get_ratio_sizes( $ratio_id );
		$settings = $this->get_settings();

		foreach ( $sizes as $ratio_item ) {
			if ( ! is_array( $ratio_item ) ) {
				continue;
			}

			$item_size = isset( $ratio_item[ $settings['field_size'] ] ) ? $ratio_item[ $settings['field_size'] ] : '';
			$item_price = isset( $ratio_item[ $settings['field_price'] ] ) ? $ratio_item[ $settings['field_price'] ] : '';

			if ( $item_size === $size && ! empty( $item_price ) ) {
				return floatval( $item_price );
			}
		}

		return false;
	}

	/**
	 * Calculate final price with texture add-on
	 *
	 * @param float $base_price         Base price.
	 * @param bool  $has_texture        Whether texture is selected.
	 * @param float $texture_percentage Texture percentage (default from settings).
	 * @return float
	 */
	public function calculate_price( $base_price, $has_texture = false, $texture_percentage = null ) {
		if ( null === $texture_percentage ) {
			$settings = $this->get_settings();
			$texture_percentage = floatval( $settings['texture_percentage'] );
		}

		$price = floatval( $base_price );

		if ( $has_texture && $texture_percentage > 0 ) {
			$price = $price * ( 1 + ( $texture_percentage / 100 ) );
		}

		return $price;
	}

	/**
	 * Get min and max prices from ratio repeater
	 *
	 * @param int $product_id Product ID.
	 * @return array|false Array with 'min' and 'max' keys, or false if not a print product.
	 */
	public function get_price_range( $product_id ) {
		if ( ! $this->is_print_product( $product_id ) ) {
			return false;
		}

		$ratio = $this->get_product_ratio( $product_id );
		if ( ! $ratio ) {
			return false;
		}

		$sizes = $this->get_ratio_sizes( $ratio->ID );
		if ( empty( $sizes ) ) {
			return false;
		}

		$settings = $this->get_settings();
		$prices = array();

		foreach ( $sizes as $ratio_item ) {
			if ( ! is_array( $ratio_item ) ) {
				continue;
			}

			$price = isset( $ratio_item[ $settings['field_price'] ] ) ? $ratio_item[ $settings['field_price'] ] : '';
			if ( ! empty( $price ) ) {
				$prices[] = floatval( $price );
			}
		}

		if ( empty( $prices ) ) {
			return false;
		}

		return array(
			'min' => min( $prices ),
			'max' => max( $prices ),
		);
	}
}

