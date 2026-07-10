<?php
/**
 * Shared helper utilities.
 *
 * @package Kesso_Cookies
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Static helpers shared between the admin, banner, and script-blocker classes.
 */
class Kesso_Cookies_Helpers {

    /**
     * Detect whether the current request is a page-builder editor/preview.
     *
     * Centralised so the banner, consent link, and script blocker all agree.
     *
     * @return bool
     */
    public static function is_page_builder() {
        // Bricks Builder.
        if ( defined( 'BRICKS_VERSION' ) && function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
            return true;
        }

        // Elementor.
        if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
                return true;
            }
        }

        // Beaver Builder.
        if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
            return true;
        }

        // Divi Builder.
        if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
            return true;
        }

        // Visual Composer.
        if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
            return true;
        }

        // Builder query parameters are only meaningful for logged-in editors.
        // Gating on login prevents anonymous visitors from suppressing the
        // banner simply by appending e.g. "?bricks" to any URL.
        if ( is_user_logged_in() ) {
            foreach ( array( 'bricks', 'elementor-preview', 'fl_builder', 'et_fb', 'vc_editable' ) as $param ) {
                if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    return true;
                }
            }
        }

        return false;
    }
}
