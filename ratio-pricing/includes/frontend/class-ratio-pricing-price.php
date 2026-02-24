<?php
/**
 * Price Display Class
 *
 * Centralized price range display functionality
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles price display for print products
 */
class Ratio_Pricing_Price {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 10, 2 );
		add_filter( 'woocommerce_loop_item_price', array( $this, 'filter_loop_price' ), 10, 2 );
	}

	/**
	 * Format price range consistently
	 *
	 * @param float $min_price Minimum price.
	 * @param float $max_price Maximum price.
	 * @return string Formatted price range HTML.
	 */
	public function format_price_range( $min_price, $max_price ) {
		// Use wc_price() for proper formatting
		$min_formatted = wc_price( $min_price );
		$max_formatted = wc_price( $max_price );

		// Extract just the price text (remove HTML wrapper)
		$min_value = strip_tags( $min_formatted );
		$max_value = strip_tags( $max_formatted );

		// Format: "₪150 – ₪480" (min, dash, max)
		return sprintf(
			'<span class="price">%s – %s</span>',
			esc_html( $min_value ),
			esc_html( $max_value )
		);
	}

	/**
	 * Filter price HTML for print products
	 *
	 * @param string     $price_html Price HTML.
	 * @param WC_Product $product    Product object.
	 * @return string
	 */
	public function filter_price_html( $price_html, $product ) {
		// Extra safety: ensure product is Simple type
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return $price_html;
		}

		$ratio_pricing = Ratio_Pricing::instance();

		if ( ! $ratio_pricing->is_print_product( $product->get_id() ) ) {
			return $price_html;
		}

		$price_range = $ratio_pricing->get_price_range( $product->get_id() );

		if ( ! $price_range ) {
			return $price_html;
		}

		return $this->format_price_range( $price_range['min'], $price_range['max'] );
	}

	/**
	 * Filter loop item price for archive/shop pages
	 *
	 * @param string     $price_html Price HTML.
	 * @param WC_Product $product    Product object.
	 * @return string
	 */
	public function filter_loop_price( $price_html, $product ) {
		// Use the same logic as filter_price_html
		return $this->filter_price_html( $price_html, $product );
	}
}

