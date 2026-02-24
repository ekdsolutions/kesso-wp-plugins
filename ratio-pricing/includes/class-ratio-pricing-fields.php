<?php
/**
 * Custom Fields Abstraction (ACF / SCF)
 *
 * Provides a single API for getting field values from either
 * Advanced Custom Fields (ACF) or Smart Custom Fields (SCF).
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fields helper – ACF or SCF
 */
class Ratio_Pricing_Fields {

	/**
	 * Check if ACF is available (get_field exists)
	 *
	 * @return bool
	 */
	public static function has_acf() {
		return function_exists( 'get_field' );
	}

	/**
	 * Check if SCF is available (SCF class with get method)
	 *
	 * @return bool
	 */
	public static function has_scf() {
		return class_exists( 'SCF' ) && method_exists( 'SCF', 'get' );
	}

	/**
	 * Whether a custom fields provider (ACF or SCF) is available
	 *
	 * @return bool
	 */
	public static function has_provider() {
		return self::has_acf() || self::has_scf();
	}

	/**
	 * Get custom field value for a post
	 *
	 * @param string $field_name Field name (or selector for ACF).
	 * @param int    $post_id    Post ID.
	 * @return mixed Field value, or null if not set / no provider.
	 */
	public static function get_field( $field_name, $post_id ) {
		if ( self::has_acf() ) {
			return get_field( $field_name, $post_id );
		}
		if ( self::has_scf() ) {
			return SCF::get( $field_name, $post_id );
		}
		return null;
	}

	/**
	 * Get field choices/labels for a select field (ACF only; SCF does not expose this API)
	 *
	 * @param string $field_name Field name.
	 * @return array Associative array of value => label, or empty if not available.
	 */
	public static function get_field_choices( $field_name ) {
		if ( function_exists( 'acf_get_field' ) ) {
			$field_object = acf_get_field( $field_name );
			if ( $field_object && isset( $field_object['choices'] ) && is_array( $field_object['choices'] ) ) {
				return $field_object['choices'];
			}
		}
		return array();
	}
}
