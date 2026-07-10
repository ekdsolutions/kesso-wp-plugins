<?php
/**
 * Canonical default values for every plugin option.
 *
 * Single source of truth so that register_setting() defaults, the admin form
 * placeholders/fallbacks, the front-end render fallbacks, and the "Reset to
 * Defaults" button can never drift apart.
 *
 * @package Kesso_Cookies
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides the canonical default for each option.
 */
class Kesso_Cookies_Defaults {

    /**
     * Full map of option name => default value.
     *
     * @return array
     */
    public static function all() {
        return array(
            'kesso_cookies_banner_title'              => __( 'We use cookies', 'kesso-cookies' ),
            'kesso_cookies_banner_description'        => __( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'kesso-cookies' ),
            'kesso_cookies_button_accept'             => __( 'Accept All', 'kesso-cookies' ),
            'kesso_cookies_button_reject'             => __( 'Reject All', 'kesso-cookies' ),
            'kesso_cookies_button_customize'          => __( 'Customize', 'kesso-cookies' ),
            'kesso_cookies_button_save'               => __( 'Save Preferences', 'kesso-cookies' ),
            'kesso_cookies_privacy_link_text'         => __( 'Privacy Policy', 'kesso-cookies' ),
            'kesso_cookies_privacy_link_url'          => '',
            'kesso_cookies_banner_layout'             => 'bottom',
            'kesso_cookies_banner_overlay'            => false,
            'kesso_cookies_custom_css'                => '',
            'kesso_cookies_settings_position'         => 'bottom-right',
            'kesso_cookies_settings_size'             => 'md',
            'kesso_cookies_settings_border_radius'    => '',
            'kesso_cookies_settings_background_color' => '#ffffff',
            'kesso_cookies_settings_icon_color'       => '#000000',
            'kesso_cookies_settings_border_color'     => 'rgba(0, 0, 0, 0.1)',
            'kesso_cookies_blocking_enabled'          => true,
            'kesso_cookies_block_known_trackers'      => true,
            'kesso_cookies_theme'                     => 'minimal',
            'kesso_cookies_show_credit'               => false,
            'kesso_cookies_show_trust_signal'         => true,
        );
    }

    /**
     * Get the registered default for a single option (no DB lookup).
     *
     * @param string $key Option name.
     * @return mixed
     */
    public static function default_for( $key ) {
        $all = self::all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : '';
    }

    /**
     * Get a stored option value, falling back to its canonical default.
     *
     * @param string $key Option name.
     * @return mixed
     */
    public static function get( $key ) {
        return get_option( $key, self::default_for( $key ) );
    }

    /**
     * List of every option name owned by the plugin (used by uninstall).
     *
     * @return string[]
     */
    public static function option_names() {
        return array_keys( self::all() );
    }
}
