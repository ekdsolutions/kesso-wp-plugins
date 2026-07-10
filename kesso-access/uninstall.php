<?php
/**
 * Uninstall handler — removes all plugin options on delete.
 *
 * @package Kesso_Access
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove this plugin's options from the current site.
 *
 * NOTE: the option prefixes used here (`kesso_toolbar*`, `kesso_widget_*`) and
 * the explicit singletons are unique to kesso-access. We deliberately do NOT
 * delete by the broad `kesso_%` prefix, which would also wipe sibling plugins
 * (kesso-cookies → `kesso_cookies_*`, kesso-woo → `kesso_woo_*`, etc.).
 */
function kesso_access_uninstall_cleanup() {
    global $wpdb;

    // Bulk-delete the toolbar/* and widget_* option families.
    $like_toolbar = $wpdb->esc_like( 'kesso_toolbar' ) . '%';
    $like_widget  = $wpdb->esc_like( 'kesso_widget_' ) . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like_toolbar,
            $like_widget
        )
    );

    // Individually-named options.
    $singletons = array(
        'kesso_focusable',
        'kesso_remove_link_target',
        'kesso_add_role_links',
        'kesso_save',
        'kesso_save_expiration',
        'kesso_skip_to_content_link',
        'kesso_skip_to_content_link_element_id',
    );
    foreach ( $singletons as $option ) {
        delete_option( $option );
    }

    // Clear any cached options the bulk delete bypassed.
    wp_cache_flush();
}

if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        kesso_access_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    kesso_access_uninstall_cleanup();
}
