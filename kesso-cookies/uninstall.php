<?php
/**
 * Uninstall handler — removes all plugin options on delete.
 *
 * @package Kesso_Cookies
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Option names owned by the plugin.
 *
 * Kept as a literal list so uninstall works even if the autoloader / class
 * files are unavailable at uninstall time.
 */
$kesso_cookies_options = array(
    'kesso_cookies_banner_title',
    'kesso_cookies_banner_description',
    'kesso_cookies_button_accept',
    'kesso_cookies_button_reject',
    'kesso_cookies_button_customize',
    'kesso_cookies_button_save',
    'kesso_cookies_privacy_link_text',
    'kesso_cookies_privacy_link_url',
    'kesso_cookies_banner_layout',
    'kesso_cookies_banner_overlay',
    'kesso_cookies_custom_css',
    'kesso_cookies_settings_position',
    'kesso_cookies_settings_size',
    'kesso_cookies_settings_border_radius',
    'kesso_cookies_settings_background_color',
    'kesso_cookies_settings_icon_color',
    'kesso_cookies_settings_border_color',
    'kesso_cookies_blocking_enabled',
    'kesso_cookies_block_known_trackers',
    'kesso_cookies_theme',
    'kesso_cookies_show_credit',
    'kesso_cookies_show_trust_signal',
);

if ( is_multisite() ) {
    $kesso_cookies_sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $kesso_cookies_sites as $kesso_cookies_site_id ) {
        switch_to_blog( $kesso_cookies_site_id );
        foreach ( $kesso_cookies_options as $kesso_cookies_option ) {
            delete_option( $kesso_cookies_option );
        }
        restore_current_blog();
    }
} else {
    foreach ( $kesso_cookies_options as $kesso_cookies_option ) {
        delete_option( $kesso_cookies_option );
    }
}
