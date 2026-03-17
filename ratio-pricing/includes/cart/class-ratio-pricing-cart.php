<?php
/**
 * Cart Price Calculation Class
 *
 * Handles cart validation and server-side price calculation
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cart and checkout price calculation
 */
class Ratio_Pricing_Cart {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_cart_item_price' ), 10, 1 );
	}

	/**
	 * Validate add to cart - ensure size is selected
	 *
	 * @param bool $passed     Validation passed.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$ratio_pricing = Ratio_Pricing::instance();

		// Only validate print products
		if ( ! $ratio_pricing->is_print_product( $product_id ) ) {
			return $passed;
		}

		// Check if size is selected
		if ( empty( $_POST['ratio_pricing_size'] ) ) {
			wc_add_notice( __( 'Please select a size before adding to cart.', 'ratio-pricing' ), 'error' );
			return false;
		}

		$size = sanitize_text_field( $_POST['ratio_pricing_size'] );

		// Validate ratio exists
		$ratio = $ratio_pricing->get_product_ratio( $product_id );
		if ( ! $ratio ) {
			wc_add_notice( __( 'This artwork is currently unavailable in the selected size.', 'ratio-pricing' ), 'error' );
			return false;
		}

		// Validate size exists in ratio
		$size_price = $ratio_pricing->get_size_price( $ratio->ID, $size );
		if ( false === $size_price ) {
			wc_add_notice( __( 'The selected size is not available. Please choose another size.', 'ratio-pricing' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add cart item data (size and texture)
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id    Variation ID (not used for Simple products).
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$ratio_pricing = Ratio_Pricing::instance();

		// Only process print products
		if ( ! $ratio_pricing->is_print_product( $product_id ) ) {
			return $cart_item_data;
		}

		// Store size
		if ( ! empty( $_POST['ratio_pricing_size'] ) ) {
			$cart_item_data['ratio_pricing_size'] = sanitize_text_field( $_POST['ratio_pricing_size'] );
		}

		// Store texture (optional)
		if ( ! empty( $_POST['ratio_pricing_texture'] ) ) {
			$cart_item_data['ratio_pricing_texture'] = sanitize_text_field( $_POST['ratio_pricing_texture'] );
		}

		return $cart_item_data;
	}

	/**
	 * Calculate and set cart item price server-side
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function calculate_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		$ratio_pricing = Ratio_Pricing::instance();

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			// Guard against missing product data object
			if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			// Explicit guard: only process Simple products
			if ( ! $cart_item['data']->is_type( 'simple' ) ) {
				continue;
			}

			$product_id = $cart_item['product_id'];

			// Only process print products
			if ( ! $ratio_pricing->is_print_product( $product_id ) ) {
				continue;
			}

			// Get size from cart item data
			if ( empty( $cart_item['ratio_pricing_size'] ) ) {
				continue;
			}

			// Sanitize size again for security hardening
			$size = sanitize_text_field( $cart_item['ratio_pricing_size'] );

			// Get ratio
			$ratio = $ratio_pricing->get_product_ratio( $product_id );
			if ( ! $ratio ) {
				continue;
			}

			// Get base price for selected size
			$base_price = $ratio_pricing->get_size_price( $ratio->ID, $size );
			if ( false === $base_price ) {
				continue;
			}

			// Check if texture is selected
			$has_texture = ! empty( $cart_item['ratio_pricing_texture'] );

			// Calculate final price fresh each time so admin price changes take effect immediately.
			// Never cache this in the session — stale cached prices would survive admin updates.
			$final_price = $ratio_pricing->calculate_price( $base_price, $has_texture );

			// Set the cart item price
			$cart_item['data']->set_price( $final_price );
		}
	}
}
