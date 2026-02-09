<?php
/**
 * Plugin activation and deactivation handler
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin activation, deactivation, and first-run redirect
 */
class Kesso_Init_Activation {

    /**
     * Transient name for redirect flag
     */
    const REDIRECT_TRANSIENT = 'kesso_init_redirect';

    /**
     * Option name for wizard completed flag
     */
    const COMPLETED_OPTION = 'kesso_init_wizard_completed';

    /**
     * Run on plugin activation
     */
    public static function activate() {
        // Set transient for first-run redirect
        set_transient( self::REDIRECT_TRANSIENT, 1, 30 );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        // Clean up transients
        delete_transient( self::REDIRECT_TRANSIENT );

        // Optionally keep the completed flag so users don't see wizard again
        // Uncomment below to reset on deactivation:
        // delete_option( self::COMPLETED_OPTION );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Check if we should redirect to wizard and do so
     */
    public static function maybe_redirect() {
        // Check for transient
        if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
            return;
        }

        // Delete transient immediately to prevent redirect loops
        delete_transient( self::REDIRECT_TRANSIENT );

        // Don't redirect if:
        // - Not in admin
        // - Doing AJAX
        // - Activating multiple plugins
        // - Wizard already completed
        if ( 
            ! is_admin() ||
            wp_doing_ajax() ||
            isset( $_GET['activate-multi'] ) ||
            get_option( self::COMPLETED_OPTION )
        ) {
            return;
        }

        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Redirect to wizard page
        wp_safe_redirect( admin_url( 'admin.php?page=kesso-init' ) );
        exit;
    }

    /**
     * Mark wizard as completed
     */
    public static function mark_completed() {
        update_option( self::COMPLETED_OPTION, true );
    }

    /**
     * Check if wizard has been completed
     *
     * @return bool
     */
    public static function is_completed() {
        return (bool) get_option( self::COMPLETED_OPTION, false );
    }

    /**
     * Reset wizard (allow it to run again)
     */
    public static function reset() {
        delete_option( self::COMPLETED_OPTION );
    }
}

