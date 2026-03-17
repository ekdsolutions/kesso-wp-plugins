<?php
/**
 * Bricks Dynamic Tags
 *
 * Registers {ratio_pricing_size} and {ratio_pricing_price} as Bricks dynamic
 * tags so they can be used in any Bricks element, including mini cart loops.
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Bricks dynamic tags for ratio pricing data on cart items.
 */
class Ratio_Pricing_Bricks_Tags {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'bricks/dynamic_tags_list', array( $this, 'register_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 10, 3 );
	}

	/**
	 * Register tags in the Bricks tag picker.
	 *
	 * @param array $tags Existing tags.
	 * @return array
	 */
	public function register_tags( $tags ) {
		$tags[] = array(
			'name'  => '{ratio_pricing_size}',
			'label' => esc_html__( 'Selected Size', 'ratio-pricing' ),
			'group' => 'Ratio Pricing',
		);

		$tags[] = array(
			'name'  => '{ratio_pricing_price}',
			'label' => esc_html__( 'Calculated Price', 'ratio-pricing' ),
			'group' => 'Ratio Pricing',
		);

		return $tags;
	}

	/**
	 * Render a single tag.
	 *
	 * Called by Bricks when it encounters a standalone dynamic tag.
	 *
	 * @param string   $tag     Tag name including braces, e.g. {ratio_pricing_size}.
	 * @param WP_Post  $post    The post object in the current loop context.
	 * @param string   $context Render context ('text', 'link', etc.).
	 * @return string
	 */
	public function render_tag( $tag, $post, $context ) {
		if ( '{ratio_pricing_size}' === $tag ) {
			return esc_html( $this->get_cart_item_size( $post ) );
		}

		if ( '{ratio_pricing_price}' === $tag ) {
			return $this->get_cart_item_price_html( $post );
		}

		return $tag;
	}

	/**
	 * Replace tags inside a content string.
	 *
	 * Called by Bricks when rendering text content that may contain multiple tags.
	 *
	 * @param string  $content  Raw content string.
	 * @param WP_Post $post     The post object in the current loop context.
	 * @param string  $context  Render context.
	 * @return string
	 */
	public function render_content( $content, $post, $context ) {
		if ( strpos( $content, '{ratio_pricing_size}' ) !== false ) {
			$content = str_replace( '{ratio_pricing_size}', esc_html( $this->get_cart_item_size( $post ) ), $content );
		}

		if ( strpos( $content, '{ratio_pricing_price}' ) !== false ) {
			$content = str_replace( '{ratio_pricing_price}', $this->get_cart_item_price_html( $post ), $content );
		}

		return $content;
	}

	/**
	 * Find the cart item for the given post and return its selected size.
	 *
	 * When Bricks loops through mini-cart items, $post is the WC product post.
	 * We match by product_id. If the same product was added twice with different
	 * sizes, this returns the size of the first matching item — an edge case that
	 * is acceptable given the single-product-per-cart nature of print products.
	 *
	 * @param WP_Post|null $post Post object from Bricks loop context.
	 * @return string Size string, e.g. "40x60", or empty string if not found.
	 */
	private function get_cart_item_size( $post ) {
		$cart_item = $this->get_cart_item_for_post( $post );
		if ( ! $cart_item ) {
			return '';
		}
		return isset( $cart_item['ratio_pricing_size'] ) ? (string) $cart_item['ratio_pricing_size'] : '';
	}

	/**
	 * Find the cart item for the given post and return its formatted price HTML.
	 *
	 * Uses the same calculation as the server-side cart class so the displayed
	 * price always matches what WooCommerce charges.
	 *
	 * @param WP_Post|null $post Post object from Bricks loop context.
	 * @return string Formatted price HTML, or empty string if not applicable.
	 */
	private function get_cart_item_price_html( $post ) {
		$cart_item = $this->get_cart_item_for_post( $post );
		if ( ! $cart_item ) {
			return '';
		}

		if ( empty( $cart_item['ratio_pricing_size'] ) ) {
			return '';
		}

		$product_id   = $cart_item['product_id'];
		$ratio_pricing = Ratio_Pricing::instance();

		$ratio = $ratio_pricing->get_product_ratio( $product_id );
		if ( ! $ratio ) {
			return '';
		}

		$size       = sanitize_text_field( $cart_item['ratio_pricing_size'] );
		$base_price = $ratio_pricing->get_size_price( $ratio->ID, $size );
		if ( false === $base_price ) {
			return '';
		}

		$has_texture = ! empty( $cart_item['ratio_pricing_texture'] );
		$final_price = $ratio_pricing->calculate_price( $base_price, $has_texture );

		return wc_price( $final_price );
	}

	/**
	 * Locate the exact WooCommerce cart item for the current Bricks loop iteration.
	 *
	 * When the same product is added multiple times with different sizes, every
	 * row shares the same product_id — so matching by product_id alone always
	 * returns the first row. Instead we ask Bricks for the current loop object:
	 * for a WooCommerce cart loop Bricks sets that object to the full cart item
	 * array (including ratio_pricing_size), giving us a precise match.
	 *
	 * Falls back to product-id matching only when Bricks' loop object is not
	 * available (e.g. the tag is used outside a cart loop element).
	 *
	 * @param WP_Post|null $post Post from the Bricks loop context.
	 * @return array|null Cart item array or null.
	 */
	private function get_cart_item_for_post( $post ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return null;
		}

		// Primary path: Bricks exposes the current loop object via a static
		// method. For a WooCommerce cart loop each iteration's object IS the
		// cart item array — this is the only reliable way to distinguish two
		// rows of the same product that have different sizes.
		if ( class_exists( '\Bricks\Query' ) && method_exists( '\Bricks\Query', 'get_loop_object' ) ) {
			$loop_obj = \Bricks\Query::get_loop_object();
			if ( is_array( $loop_obj ) && isset( $loop_obj['product_id'], $loop_obj['data'] ) ) {
				return $loop_obj;
			}
		}

		// Fallback: match by product ID. Only correct when each product
		// appears once in the cart (e.g. tag used outside a cart loop).
		$product_id = 0;
		if ( $post instanceof WP_Post ) {
			$product_id = (int) $post->ID;
		} elseif ( is_numeric( $post ) ) {
			$product_id = (int) $post;
		}

		if ( ! $product_id ) {
			return null;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) && (int) $cart_item['product_id'] === $product_id ) {
				return $cart_item;
			}
		}

		return null;
	}
}
