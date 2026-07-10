<?php
/**
 * Uninstall handler — removes plugin data on delete.
 *
 * @package Kesso_Init
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up a single site's options/transients.
 */
function kesso_init_uninstall_cleanup() {
    delete_option( 'kesso_init_wizard_completed' );
    delete_transient( 'kesso_init_redirect' );

    // Remove any leftover temporary extraction directories from GitHub/theme installs.
    $upload = wp_get_upload_dir();
    if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
        $temp = trailingslashit( $upload['basedir'] ) . 'kesso-temp';
        if ( is_dir( $temp ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            global $wp_filesystem;
            if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() && $wp_filesystem ) {
                $wp_filesystem->rmdir( $temp, true );
            }
        }
    }
}

if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        kesso_init_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    kesso_init_uninstall_cleanup();
}
