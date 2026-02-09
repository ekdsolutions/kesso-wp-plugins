<?php
/**
 * Admin Settings Class
 *
 * @package Kesso_Cookies
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles WordPress admin settings page for cookie consent
 */
class Kesso_Cookies_Admin {

    /**
     * Settings page slug
     */
    const SETTINGS_PAGE = 'kesso-cookies-settings';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu_page' ), 99.3 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_footer', array( $this, 'print_reset_js' ) );
    }

    /**
     * Register the admin menu page
     */
    public function register_menu_page() {
        // Get the favicon URL
        $icon_url = KESSO_COOKIES_URL . 'assets/img/kesso-favicon.png';
        
        $hook_suffix = add_menu_page(
            __( 'Cookie Consent', 'kesso-cookies' ),
            '# ' . __( 'Cookies', 'kesso-cookies' ),
            'manage_options',
            self::SETTINGS_PAGE,
            array( $this, 'render_page' ),
            $icon_url,
            99.3  // Position at bottom, after kesso-access
        );
        
        // Add CSS for menu icon
        add_action( 'admin_head', array( $this, 'admin_menu_icon_css' ) );
    }

    /**
     * Add CSS for custom menu icon
     */
    public function admin_menu_icon_css() {
        ?>
        <style>
            #toplevel_page_<?php echo esc_attr( self::SETTINGS_PAGE ); ?> .wp-menu-image img {
                width: 18px;
                height: 20px;
                padding: 6px 0 0 0;
            }
        </style>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings group
        register_setting( 'kesso_cookies_settings', 'kesso_cookies_banner_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Cookies Consent', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_banner_description', array(
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => __( 'By clicking "Accept All", you consent to the use of analytics and marketing cookies, as described in our', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_button_accept', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Accept All', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_button_reject', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Reject All', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_button_customize', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Customize', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_button_save', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Save Preferences', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_privacy_link_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Privacy Policy', 'kesso-cookies' ),
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_privacy_link_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_banner_layout', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'bottom',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_banner_overlay', array(
            'type'              => 'boolean',
            'sanitize_callback' => array( $this, 'sanitize_boolean' ),
            'default'           => false,
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_custom_css', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_css' ),
            'default'           => '',
        ) );

        // Settings button styling
        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_position', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'bottom-right',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_size', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'md',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_border_radius', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_background_color', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_color' ),
            'default'           => '#ffffff',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_icon_color', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_color' ),
            'default'           => '#000000',
        ) );

        register_setting( 'kesso_cookies_settings', 'kesso_cookies_settings_border_color', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_color' ),
            'default'           => 'rgba(0, 0, 0, 0.1)',
        ) );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets( $hook ) {
        // Only load on our page
        if ( 'toplevel_page_' . self::SETTINGS_PAGE !== $hook ) {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style(
            'kesso-cookies-admin',
            KESSO_COOKIES_URL . 'assets/css/admin.css',
            array(),
            KESSO_COOKIES_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'kesso-cookies-admin',
            KESSO_COOKIES_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            KESSO_COOKIES_VERSION,
            true
        );
    }

    /**
     * Render the settings page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'kesso-cookies' ) );
        }

        ?>
        <!-- Kesso Banner -->
        <div class="kesso-banner">
            <div class="kesso-banner-content">
                <?php echo esc_html__( 'This plugin was developed and distributed with ❤️ for free use by', 'kesso-cookies' ); ?> 
                <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-banner-link">kesso.io</a>
            </div>
        </div>
        <div class="wrap">
            <div class="kesso-cookies-app">
                <main class="kesso-cookies-main">
                    <div class="kesso-cookies-page-heading">
                        <div class="kesso-cookies-heading-left">
                            <h1 class="kesso-cookies-title"><?php echo esc_html__( 'Cookie Consent', 'kesso-cookies' ); ?></h1>
                            <p class="kesso-cookies-subtitle"><?php echo esc_html__( 'Configure the cookie consent banner text and settings.', 'kesso-cookies' ); ?></p>
                        </div>
                    </div>

                    <form method="post" action="options.php" id="kesso-cookies-settings-form">
                        <?php settings_fields( 'kesso_cookies_settings' ); ?>

                        <div class="kesso-cookies-sections">
                            <!-- Banner Content -->
                            <section class="kesso-cookies-card">
                                <div class="kesso-cookies-card-header">
                                    <h2 class="kesso-cookies-card-title">
                                        <span class="material-symbols-outlined kesso-cookies-icon" aria-hidden="true">edit</span>
                                        <?php echo esc_html__( 'Banner Content', 'kesso-cookies' ); ?>
                                    </h2>
                                </div>
                                <div class="kesso-cookies-card-body kesso-cookies-card-body--stack">
                                    <div class="kesso-cookies-grid kesso-cookies-grid-2">
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Banner Title', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_banner_title" name="kesso_cookies_banner_title" value="<?php echo esc_attr( get_option( 'kesso_cookies_banner_title', __( 'We use cookies', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Privacy Policy Link Text', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_privacy_link_text" name="kesso_cookies_privacy_link_text" value="<?php echo esc_attr( get_option( 'kesso_cookies_privacy_link_text', __( 'Privacy Policy', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                    </div>

                                    <label class="kesso-cookies-field">
                                        <span class="kesso-cookies-label"><?php echo esc_html__( 'Banner Description', 'kesso-cookies' ); ?></span>
                                        <textarea class="kesso-cookies-control" id="kesso_cookies_banner_description" name="kesso_cookies_banner_description" rows="4"><?php echo esc_textarea( get_option( 'kesso_cookies_banner_description', __( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'kesso-cookies' ) ) ); ?></textarea>
                                    </label>

                                    <label class="kesso-cookies-field">
                                        <span class="kesso-cookies-label"><?php echo esc_html__( 'Privacy Policy Page URL', 'kesso-cookies' ); ?></span>
                                        <input type="url" class="kesso-cookies-control" id="kesso_cookies_privacy_link_url" name="kesso_cookies_privacy_link_url" value="<?php echo esc_url( get_option( 'kesso_cookies_privacy_link_url', '' ) ); ?>" placeholder="<?php echo esc_attr__( 'https://example.com/privacy-policy', 'kesso-cookies' ); ?>" />
                                        <p style="margin-top: 4px; font-size: 12px; color: var(--kesso-cookies-muted, #4c739a);"><?php echo esc_html__( 'Leave empty to auto-detect Privacy Policy page.', 'kesso-cookies' ); ?></p>
                                    </label>
                                </div>
                            </section>

                            <!-- Button Labels -->
                            <section class="kesso-cookies-card">
                                <div class="kesso-cookies-card-header">
                                    <h2 class="kesso-cookies-card-title">
                                        <span class="material-symbols-outlined kesso-cookies-icon" aria-hidden="true">tune</span>
                                        <?php echo esc_html__( 'Button Labels', 'kesso-cookies' ); ?>
                                    </h2>
                                </div>
                                <div class="kesso-cookies-card-body kesso-cookies-card-body--stack">
                                    <div class="kesso-cookies-grid kesso-cookies-grid-2">
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Accept All Button', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_button_accept" name="kesso_cookies_button_accept" value="<?php echo esc_attr( get_option( 'kesso_cookies_button_accept', __( 'Accept All', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Reject All Button', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_button_reject" name="kesso_cookies_button_reject" value="<?php echo esc_attr( get_option( 'kesso_cookies_button_reject', __( 'Reject All', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                    </div>

                                    <div class="kesso-cookies-grid kesso-cookies-grid-2">
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Customize Button', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_button_customize" name="kesso_cookies_button_customize" value="<?php echo esc_attr( get_option( 'kesso_cookies_button_customize', __( 'Customize', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                        <label class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Save Preferences Button', 'kesso-cookies' ); ?></span>
                                            <input type="text" class="kesso-cookies-control" id="kesso_cookies_button_save" name="kesso_cookies_button_save" value="<?php echo esc_attr( get_option( 'kesso_cookies_button_save', __( 'Save Preferences', 'kesso-cookies' ) ) ); ?>" />
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <!-- Settings Button Styling -->
                            <section class="kesso-cookies-card">
                                <div class="kesso-cookies-card-header">
                                    <h2 class="kesso-cookies-card-title">
                                        <span class="material-symbols-outlined kesso-cookies-icon" aria-hidden="true">palette</span>
                                        <?php echo esc_html__( 'Widget Styling', 'kesso-cookies' ); ?>
                                    </h2>
                                </div>
                                <div class="kesso-cookies-card-body kesso-cookies-card-body--stack">
                                    <div class="kesso-cookies-grid kesso-cookies-grid-3">
                                        <?php
                                        $styling_fields = array(
                                            array(
                                                'id'      => 'kesso_cookies_settings_position',
                                                'title'   => __( 'Position', 'kesso-cookies' ),
                                                'type'    => 'select',
                                                'options' => array(
                                                    'bottom-right' => __( 'Bottom Right', 'kesso-cookies' ),
                                                    'bottom-left'  => __( 'Bottom Left', 'kesso-cookies' ),
                                                    'top-right'    => __( 'Top Right', 'kesso-cookies' ),
                                                    'top-left'     => __( 'Top Left', 'kesso-cookies' ),
                                                ),
                                                'std'     => 'bottom-right',
                                            ),
                                            array(
                                                'id'      => 'kesso_cookies_settings_size',
                                                'title'   => __( 'Size', 'kesso-cookies' ),
                                                'type'    => 'select',
                                                'options' => array(
                                                    'sm' => __( 'SM', 'kesso-cookies' ),
                                                    'md' => __( 'MD', 'kesso-cookies' ),
                                                    'lg' => __( 'LG', 'kesso-cookies' ),
                                                ),
                                                'std'     => 'md',
                                            ),
                                            array(
                                                'id'      => 'kesso_cookies_settings_border_radius',
                                                'title'   => __( 'Border Radius', 'kesso-cookies' ),
                                                'type'    => 'select',
                                                'options' => array(
                                                    'full' => __( 'Full', 'kesso-cookies' ),
                                                    'sm'   => __( 'SM', 'kesso-cookies' ),
                                                    'md'   => __( 'MD', 'kesso-cookies' ),
                                                    'lg'   => __( 'LG', 'kesso-cookies' ),
                                                ),
                                                'std'     => '',
                                            ),
                                            array(
                                                'id'    => 'kesso_cookies_settings_background_color',
                                                'title' => __( 'Background Color', 'kesso-cookies' ),
                                                'type'  => 'color',
                                                'std'   => '#ffffff',
                                            ),
                                            array(
                                                'id'    => 'kesso_cookies_settings_icon_color',
                                                'title' => __( 'Icon Color', 'kesso-cookies' ),
                                                'type'  => 'color',
                                                'std'   => '#000000',
                                            ),
                                            array(
                                                'id'    => 'kesso_cookies_settings_border_color',
                                                'title' => __( 'Border Color', 'kesso-cookies' ),
                                                'type'  => 'color',
                                                'std'   => '#000000',
                                            ),
                                        );

                                        foreach ( $styling_fields as $field ) :
                                            $field_type = $field['type'] ?? 'text';
                                            $is_color_field = ( 'color' === $field_type );
                                            ?>
                                            <?php if ( $is_color_field ) : ?>
                                                <div class="kesso-cookies-field">
                                                    <span class="kesso-cookies-label"><?php echo esc_html( $field['title'] ); ?></span>
                                                    <?php $this->render_color_field( $field ); ?>
                                                </div>
                                            <?php else : ?>
                                                <label class="kesso-cookies-field">
                                                    <span class="kesso-cookies-label"><?php echo esc_html( $field['title'] ); ?></span>
                                                    <?php
                                                    if ( 'select' === $field_type ) {
                                                        $value = get_option( $field['id'], $field['std'] ?? '' );
                                                        $options = $field['options'] ?? array();
                                                        ?>
                                                        <select class="kesso-cookies-control kesso-cookies-select" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['id'] ); ?>">
                                                            <?php foreach ( $options as $option_key => $option_value ) : ?>
                                                                <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $value, $option_key ); ?>><?php echo esc_html( $option_value ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php
                                                    }
                                                    ?>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>

                            <!-- Banner Appearance -->
                            <section class="kesso-cookies-card">
                                <div class="kesso-cookies-card-header">
                                    <h2 class="kesso-cookies-card-title">
                                        <span class="material-symbols-outlined kesso-cookies-icon" aria-hidden="true">palette</span>
                                        <?php echo esc_html__( 'Banner Appearance', 'kesso-cookies' ); ?>
                                    </h2>
                                </div>
                                <div class="kesso-cookies-card-body kesso-cookies-card-body--stack">
                                    <div class="kesso-cookies-grid kesso-cookies-grid-3">
                                        <div class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Banner Layout', 'kesso-cookies' ); ?></span>
                                            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 8px;">
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                    <input type="radio" name="kesso_cookies_banner_layout" value="bottom" <?php checked( get_option( 'kesso_cookies_banner_layout', 'bottom' ), 'bottom' ); ?> />
                                                    <span><?php echo esc_html__( 'Full-width bottom banner', 'kesso-cookies' ); ?></span>
                                                </label>
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                    <input type="radio" name="kesso_cookies_banner_layout" value="floating" <?php checked( get_option( 'kesso_cookies_banner_layout', 'bottom' ), 'floating' ); ?> />
                                                    <span><?php echo esc_html__( 'Floating corner banner', 'kesso-cookies' ); ?></span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Background Overlay', 'kesso-cookies' ); ?></span>
                                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; border-radius: var(--kesso-cookies-radius-lg, 8px); border: 1px solid var(--kesso-cookies-slate-200, #e2e8f0); background: #f8fafc; min-height: 54px; margin-top: 8px;">
                                                <input type="hidden" name="kesso_cookies_banner_overlay" value="0" />
                                                <input type="checkbox" name="kesso_cookies_banner_overlay" value="1" <?php checked( get_option( 'kesso_cookies_banner_overlay', false ), true ); ?> style="width: 18px; height: 18px; cursor: pointer; margin: 0; flex-shrink: 0;" />
                                                <span style="font-size: 14px; color: var(--kesso-cookies-text, #0d141b);"><?php echo esc_html__( 'Enable overlay behind the banner', 'kesso-cookies' ); ?></span>
                                            </label>
                                        </div>

                                        <div class="kesso-cookies-field">
                                            <span class="kesso-cookies-label"><?php echo esc_html__( 'Custom CSS', 'kesso-cookies' ); ?></span>
                                            <textarea 
                                                class="kesso-cookies-control" 
                                                id="kesso_cookies_custom_css" 
                                                name="kesso_cookies_custom_css" 
                                                rows="1" 
                                                style="font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; min-height: 54px; height: 54px; resize: vertical; margin-top: 8px;"
                                                placeholder="<?php echo esc_attr__( 'Add CSS for the cookie banner', 'kesso-cookies' ); ?>"><?php echo esc_textarea( get_option( 'kesso_cookies_custom_css', '' ) ); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </section>

                        </div>

                        <footer class="kesso-cookies-footer kesso-cookies-glass-footer" role="contentinfo">
                            <div class="kesso-cookies-footer-inner">
                                <div class="kesso-cookies-footer-left">
                                    <div class="kesso-cookies-footer-status">
                                        <span class="kesso-cookies-footer-dot" aria-hidden="true"></span>
                                        <span class="kesso-cookies-footer-label"><?php echo esc_html__( 'Ready to Save', 'kesso-cookies' ); ?></span>
                                    </div>
                                    <p class="kesso-cookies-footer-subtitle"><?php echo esc_html__( 'Changes will be applied immediately.', 'kesso-cookies' ); ?></p>
                                </div>

                                <div class="kesso-cookies-footer-right">
                                    <button type="button" class="kesso-cookies-btn kesso-cookies-btn--ghost" id="kesso-cookies-reset-button"><?php echo esc_html__( 'Reset to Defaults', 'kesso-cookies' ); ?></button>
                                    <button type="submit" class="kesso-cookies-btn kesso-cookies-btn--primary">
                                        <?php echo esc_html__( 'Save Changes', 'kesso-cookies' ); ?>
                                    </button>
                                </div>
                            </div>
                        </footer>
                    </form>
                </main>
            </div>
        </div>
        <?php
    }

    /**
     * Sanitize boolean value
     *
     * @param mixed $value Value to sanitize.
     * @return bool
     */
    public function sanitize_boolean( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Sanitize color value
     *
     * @param string $input Color value to sanitize.
     * @return string
     */
    public function sanitize_color( $input ) {
        if ( empty( $input ) ) {
            return '';
        }

        $input = trim( $input );

        // Check if it's a valid hex color
        if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $input ) ) {
            return $input;
        }

        // Check if it's a valid rgba color
        if ( preg_match( '/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/', $input ) ) {
            return $input;
        }

        // If not valid, return empty string
        return '';
    }

    /**
     * Sanitize CSS value
     *
     * @param string $input CSS value to sanitize.
     * @return string
     */
    public function sanitize_css( $input ) {
        if ( empty( $input ) ) {
            return '';
        }

        // Strip out any potentially dangerous content
        $input = wp_strip_all_tags( $input );
        
        // Allow CSS characters and basic structure
        $input = preg_replace( '/[^a-zA-Z0-9\s\{\}\[\]\(\):;,\-\.#%!@\*\/]/', '', $input );

        return trim( $input );
    }

    /**
     * Render color picker field
     *
     * @param array $field Field configuration.
     */
    public function render_color_field( $field ) {
        $value = get_option( $field['id'], $field['std'] ?? '' );
        ?>
        <div class="kesso-cookies-color-picker-wrapper" data-field-id="<?php echo esc_attr( $field['id'] ); ?>">
            <input type="hidden"
                   class="kesso-cookies-color-picker-input"
                   id="<?php echo esc_attr( $field['id'] ); ?>"
                   name="<?php echo esc_attr( $field['id'] ); ?>"
                   value="<?php echo esc_attr( $value ); ?>" />
            <div class="kesso-cookies-color-picker">
                <div class="kesso-cookies-color-picker-preview" style="background-color: <?php echo esc_attr( $value ); ?>;"></div>
                <button type="button" class="kesso-cookies-color-picker-toggle"><?php echo esc_html__( 'Choose Color', 'kesso-cookies' ); ?></button>
            </div>
            <div class="kesso-cookies-color-picker-dropdown" style="display: none;">
                <div class="kesso-cookies-color-picker-spectrum">
                    <canvas class="kesso-cookies-color-picker-canvas" width="200" height="200"></canvas>
                    <div class="kesso-cookies-color-picker-pointer"></div>
                </div>
                <div class="kesso-cookies-color-picker-controls">
                    <div class="kesso-cookies-color-picker-slider-wrapper">
                        <label><?php echo esc_html__( 'Hue', 'kesso-cookies' ); ?></label>
                        <div class="kesso-cookies-color-picker-slider kesso-cookies-color-picker-hue">
                            <canvas class="kesso-cookies-color-picker-slider-canvas" width="200" height="20"></canvas>
                            <div class="kesso-cookies-color-picker-slider-thumb"></div>
                        </div>
                    </div>
                    <div class="kesso-cookies-color-picker-slider-wrapper">
                        <label><?php echo esc_html__( 'Opacity', 'kesso-cookies' ); ?></label>
                        <div class="kesso-cookies-color-picker-slider kesso-cookies-color-picker-alpha">
                            <canvas class="kesso-cookies-color-picker-slider-canvas" width="200" height="20"></canvas>
                            <div class="kesso-cookies-color-picker-slider-thumb"></div>
                        </div>
                    </div>
                </div>
                <div class="kesso-cookies-color-picker-inputs">
                    <input type="text" class="kesso-cookies-color-picker-hex" placeholder="#137fec" />
                    <input type="text" class="kesso-cookies-color-picker-rgba" placeholder="rgba(19, 127, 236, 1)" />
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Print JavaScript for reset button
     */
    public function print_reset_js() {
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_' . self::SETTINGS_PAGE !== $screen->id ) {
            return;
        }
        ?>
        <script>
        jQuery( document ).ready( function ( $ ) {
            // Handle reset button
            $( '#kesso-cookies-reset-button' ).on( 'click', function ( e ) {
                e.preventDefault();
                if ( confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their default values?', 'kesso-cookies' ) ); ?>' ) ) {
                    // Get all default values
                    var $form = $( '#kesso-cookies-settings-form' );
                    var defaults = {};
                    
                    // Set defaults for all fields
                    <?php
                    // Get all registered settings with their defaults
                    $settings = get_registered_settings();
                    foreach ( $settings as $setting_id => $setting_args ) {
                        if ( strpos( $setting_id, 'kesso_cookies_' ) === 0 && isset( $setting_args['default'] ) ) {
                            $field_id = esc_js( $setting_id );
                            $default_value = $setting_args['default'];
                            // Handle different types of defaults
                            if ( is_bool( $default_value ) ) {
                                $default_value = $default_value ? '1' : '0';
                            } elseif ( is_array( $default_value ) ) {
                                continue; // Skip arrays
                            } else {
                                $default_value = esc_js( $default_value );
                            }
                            echo "defaults['{$field_id}'] = '{$default_value}';\n";
                        }
                    }
                    ?>
                    
                    // Apply defaults to form fields
                    $.each( defaults, function ( fieldId, defaultValue ) {
                        var $field = $( '#' + fieldId );
                        if ( $field.length ) {
                            if ( $field.is( 'select' ) ) {
                                $field.val( defaultValue );
                            } else if ( $field.hasClass( 'kesso-cookies-color-picker-input' ) ) {
                                $field.val( defaultValue );
                                // Update color picker if function exists
                                if ( typeof window.kessoCookiesUpdateColorPicker === 'function' ) {
                                    window.kessoCookiesUpdateColorPicker( fieldId, defaultValue );
                                }
                            } else if ( $field.is( 'input[type="checkbox"]' ) ) {
                                $field.prop( 'checked', defaultValue === '1' || defaultValue === 'true' || defaultValue === true );
                            } else if ( $field.is( 'input[type="radio"]' ) ) {
                                $field.filter( '[value="' + defaultValue + '"]' ).prop( 'checked', true );
                            } else {
                                $field.val( defaultValue );
                            }
                        }
                    } );
                    
                    // Handle special cases
                    // Banner overlay checkbox
                    var overlayDefault = defaults['kesso_cookies_banner_overlay'] || '0';
                    $( 'input[name="kesso_cookies_banner_overlay"][value="' + overlayDefault + '"]' ).prop( 'checked', true );
                    
                    // Submit the form to save
                    $form.submit();
                }
            } );
        } );
        </script>
        <?php
    }
}

