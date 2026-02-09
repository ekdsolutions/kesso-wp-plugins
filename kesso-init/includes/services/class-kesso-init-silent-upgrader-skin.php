<?php
/**
 * Silent Upgrader Skin - suppresses output during upgrades
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Safety check: ensure WP_Upgrader_Skin is available
if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
    return;
}

/**
 * Silent upgrader skin that suppresses all output
 */
class Kesso_Init_Silent_Upgrader_Skin extends WP_Upgrader_Skin {

    /**
     * Suppress feedback
     *
     * @param string $string Feedback string.
     * @param mixed  ...$args Additional arguments.
     */
    public function feedback( $string, ...$args ) {
        // Do nothing - suppress all output
    }

    /**
     * Suppress header
     */
    public function header() {
        // Do nothing
    }

    /**
     * Suppress footer
     */
    public function footer() {
        // Do nothing
    }

    /**
     * Suppress error output
     *
     * @param string|WP_Error $errors Errors.
     */
    public function error( $errors ) {
        // Store errors but don't output
        if ( is_wp_error( $errors ) ) {
            $this->skin->errors = $errors;
        }
    }
}

