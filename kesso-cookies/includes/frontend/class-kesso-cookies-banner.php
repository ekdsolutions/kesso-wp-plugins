<?php
/**
 * Frontend Banner Class
 *
 * @package Kesso_Cookies
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles frontend cookie consent banner
 */
class Kesso_Cookies_Banner {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_banner' ) );
        add_action( 'wp_head', array( $this, 'add_script_blocking' ) );
        // Emit the theme preset as early as possible so the token overrides
        // are in place before the banner renders.
        add_action( 'wp_head', array( $this, 'output_theme_marker' ), 2 );
    }

    /**
     * Emit the plugin's design-token theme preset onto <html> as a data
     * attribute. Kept in <head> so CSS variables cascade to the whole page
     * (banner + modal + settings link).
     */
    public function output_theme_marker() {
        if ( Kesso_Cookies_Helpers::is_page_builder() ) {
            return;
        }
        $theme = Kesso_Cookies_Defaults::get( 'kesso_cookies_theme' );
        $theme = in_array( $theme, array( 'minimal', 'warm', 'bold', 'elegant' ), true ) ? $theme : 'minimal';
        ?>
        <script>document.documentElement.setAttribute('data-kesso-cookies-theme', <?php echo wp_json_encode( $theme ); ?>);</script>
        <?php
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Don't enqueue in page builders
        if ( Kesso_Cookies_Helpers::is_page_builder() ) {
            return;
        }

        // Enqueue frontend styles
        wp_enqueue_style(
            'kesso-cookies-frontend',
            KESSO_COOKIES_URL . 'assets/css/frontend.css',
            array(),
            KESSO_COOKIES_VERSION
        );

        // Enqueue frontend scripts
        wp_enqueue_script(
            'kesso-cookies-frontend',
            KESSO_COOKIES_URL . 'assets/js/frontend.js',
            array(),
            KESSO_COOKIES_VERSION,
            true
        );

        // Add inline CSS for custom settings button styling
        $custom_css = $this->get_settings_button_css();
        if ( ! empty( $custom_css ) ) {
            wp_add_inline_style( 'kesso-cookies-frontend', $custom_css );
        }

        // Add custom CSS from admin settings
        $user_custom_css = wp_strip_all_tags( get_option( 'kesso_cookies_custom_css', '' ) );
        if ( ! empty( $user_custom_css ) ) {
            wp_add_inline_style( 'kesso-cookies-frontend', $user_custom_css );
        }

        // Localize script with settings
        wp_localize_script(
            'kesso-cookies-frontend',
            'kessoCookiesConfig',
            array(
                'bannerTitle'       => Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_title' ),
                'bannerDescription'  => Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_description' ),
                'buttonAccept'       => Kesso_Cookies_Defaults::get( 'kesso_cookies_button_accept' ),
                'buttonReject'       => Kesso_Cookies_Defaults::get( 'kesso_cookies_button_reject' ),
                'buttonCustomize'    => Kesso_Cookies_Defaults::get( 'kesso_cookies_button_customize' ),
                'buttonSave'          => Kesso_Cookies_Defaults::get( 'kesso_cookies_button_save' ),
                'privacyLinkText'     => Kesso_Cookies_Defaults::get( 'kesso_cookies_privacy_link_text' ),
                'privacyLinkUrl'      => $this->get_privacy_policy_url(),
                'cookieName'          => 'kesso_cookies_consent',
                'cookieVersion'       => '1.0',
                'cookieExpiry'        => 180, // days
            )
        );
    }

    /**
     * Get custom CSS for settings button styling
     *
     * @return string
     */
    private function get_settings_button_css() {
        $css = '';

        // Position
        $position = get_option( 'kesso_cookies_settings_position', 'bottom-right' );
        $valid_positions = array( 'top-right', 'top-left', 'bottom-right', 'bottom-left' );
        if ( in_array( $position, $valid_positions, true ) ) {
            $css .= '#kesso-cookies-settings-link {';
            if ( strpos( $position, 'top' ) === 0 ) {
                $css .= 'top: 20px;';
                $css .= 'bottom: auto;';
            } else {
                $css .= 'bottom: 20px;';
                $css .= 'top: auto;';
            }
            if ( strpos( $position, 'right' ) !== false ) {
                $css .= 'right: 20px;';
                $css .= 'left: auto;';
            } else {
                $css .= 'left: 20px;';
                $css .= 'right: auto;';
            }
            $css .= '}';
        }

        // Size (icon size only, matching kesso-access)
        $size = get_option( 'kesso_cookies_settings_size', 'md' );
        $icon_sizes = array(
            'xs' => '16px',
            'sm' => '20px',
            'md' => '25px',
            'lg' => '30px',
        );
        
        $paddings = array(
            'xs' => '8px',
            'sm' => '12px',
            'md' => '12px',
            'lg' => '12px',
        );
        
        if ( isset( $icon_sizes[ $size ] ) ) {
            // Apply size only to icon
            $css .= '#kesso-cookies-settings-link .kesso-cookies-settings-icon {';
            $css .= 'width: ' . esc_attr( $icon_sizes[ $size ] ) . ';';
            $css .= 'height: ' . esc_attr( $icon_sizes[ $size ] ) . ';';
            $css .= '}';
            
            // Apply padding to the link wrapper
            if ( isset( $paddings[ $size ] ) ) {
                $css .= '#kesso-cookies-settings-link {';
                $css .= 'padding: ' . esc_attr( $paddings[ $size ] ) . ';';
                $css .= '}';
            }
        }

        // Border radius
        $border_radius = get_option( 'kesso_cookies_settings_border_radius', '' );
        if ( $border_radius ) {
            $radius_values = array(
                'full' => '50%',
                'sm'   => '4px',
                'md'   => '8px',
                'lg'   => '12px',
            );
            if ( isset( $radius_values[ $border_radius ] ) ) {
                $css .= '#kesso-cookies-settings-link {';
                $css .= 'border-radius: ' . esc_attr( $radius_values[ $border_radius ] ) . ';';
                $css .= '}';
            }
        }

        // Background color
        $bg_color = get_option( 'kesso_cookies_settings_background_color', '#ffffff' );
        if ( $bg_color ) {
            $css .= '#kesso-cookies-settings-link {';
            $css .= 'background-color: ' . esc_attr( $bg_color ) . ';';
            $css .= '}';
        }

        // Icon color
        $icon_color = get_option( 'kesso_cookies_settings_icon_color', '#000000' );
        if ( $icon_color ) {
            $css .= '#kesso-cookies-settings-link .kesso-cookies-settings-icon {';
            $css .= 'color: ' . esc_attr( $icon_color ) . ';';
            $css .= 'fill: ' . esc_attr( $icon_color ) . ';';
            $css .= '}';
        }

        // Border color (with shadow generation, matching kesso-access)
        $border_color = Kesso_Cookies_Defaults::get( 'kesso_cookies_settings_border_color' );
        if ( $border_color ) {
            // Extract hex from rgba if needed for box-shadow
            $border_hex = $this->extract_hex_from_color( $border_color );
            $border_rgb = $this->hex_to_rgb( $border_hex );
            $shadow_color = 'rgba(' . $border_rgb['r'] . ', ' . $border_rgb['g'] . ', ' . $border_rgb['b'] . ', 0.05)';
            
            $css .= '#kesso-cookies-settings-link {';
            $css .= 'border-color: ' . esc_attr( $border_color ) . ';';
            $css .= 'border: 1px solid ' . esc_attr( $border_color ) . ';';
            $css .= 'box-shadow: 0 0 10px 5px ' . esc_attr( $shadow_color ) . ';';
            $css .= '}';
        } else {
            // Default box-shadow if no border color is set
            $css .= '#kesso-cookies-settings-link {';
            $css .= 'box-shadow: 0 0 10px 5px rgba(0, 0, 0, 0.05);';
            $css .= '}';
        }

        return $css;
    }

    /**
     * Extract hex color from various formats
     *
     * @param string $color Color value in any format.
     * @return string Hex color without #.
     */
    private function extract_hex_from_color( $color ) {
        // If already hex, return it
        if ( preg_match( '/^#?[0-9a-fA-F]{3,6}$/', $color ) ) {
            return ltrim( $color, '#' );
        }
        
        // Extract from rgb/rgba
        if ( preg_match( '/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches ) ) {
            $r = str_pad( dechex( (int) $matches[1] ), 2, '0', STR_PAD_LEFT );
            $g = str_pad( dechex( (int) $matches[2] ), 2, '0', STR_PAD_LEFT );
            $b = str_pad( dechex( (int) $matches[3] ), 2, '0', STR_PAD_LEFT );
            return $r . $g . $b;
        }
        
        // Default fallback
        return '000000';
    }

    /**
     * Convert hex color to RGB array
     *
     * @param string $hex Hex color value.
     * @return array RGB values.
     */
    private function hex_to_rgb( $hex ) {
        $hex = ltrim( $hex, '#' );
        
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            'r' => hexdec( substr( $hex, 0, 2 ) ),
            'g' => hexdec( substr( $hex, 2, 2 ) ),
            'b' => hexdec( substr( $hex, 4, 2 ) ),
        );
    }

    /**
     * Get privacy policy page URL
     *
     * @return string
     */
    private function get_privacy_policy_url() {
        $custom_url = get_option( 'kesso_cookies_privacy_link_url', '' );
        if ( ! empty( $custom_url ) ) {
            return esc_url( $custom_url );
        }

        // Try to find privacy policy page
        $privacy_page_id = get_option( 'wp_page_for_privacy_policy' );
        if ( $privacy_page_id ) {
            return get_permalink( $privacy_page_id );
        }

        return '';
    }

    /**
     * Provide an early registration shim in <head>.
     *
     * Primary blocking is done server-side by Kesso_Cookies_Script_Blocker; this
     * shim only captures programmatic registerScript() calls made before the
     * front-end script loads, so they are not lost. The queue is migrated into
     * the main controller during init.
     */
    public function add_script_blocking() {
        // Don't initialize in page builders
        if ( Kesso_Cookies_Helpers::is_page_builder() ) {
            return;
        }

        ?>
        <script>
        // Buffer early script registrations before the consent controller loads.
        (function() {
            window.kessoCookiesScripts = window.kessoCookiesScripts || {
                queue: [],
                register: function(config) {
                    this.queue.push(config);
                }
            };
        })();
        </script>
        <?php
    }

    /**
     * Render the cookie consent banner
     */
    public function render_banner() {
        // Don't render in page builders
        if ( Kesso_Cookies_Helpers::is_page_builder() ) {
            return;
        }

        $banner_title       = Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_title' );
        $banner_description = Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_description' );
        $button_accept      = Kesso_Cookies_Defaults::get( 'kesso_cookies_button_accept' );
        $button_reject      = Kesso_Cookies_Defaults::get( 'kesso_cookies_button_reject' );
        $button_customize   = Kesso_Cookies_Defaults::get( 'kesso_cookies_button_customize' );
        $privacy_link_text  = Kesso_Cookies_Defaults::get( 'kesso_cookies_privacy_link_text' );
        $privacy_link_url   = $this->get_privacy_policy_url();

        // Get layout and overlay settings
        $banner_layout  = Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_layout' );
        $banner_overlay = Kesso_Cookies_Defaults::get( 'kesso_cookies_banner_overlay' );
        
        // Build banner classes
        $banner_classes = 'kesso-cookies-banner';
        $banner_classes .= ' kesso-cookies-layout--' . esc_attr( $banner_layout );
        if ( $banner_overlay ) {
            $banner_classes .= ' kesso-cookies-overlay-enabled';
        }

        // Open the privacy link in a new tab only when it points off-site.
        $privacy_is_external = false;
        if ( ! empty( $privacy_link_url ) ) {
            $link_host = wp_parse_url( $privacy_link_url, PHP_URL_HOST );
            $home_host = wp_parse_url( home_url(), PHP_URL_HOST );
            $privacy_is_external = ( ! empty( $link_host ) && strtolower( $link_host ) !== strtolower( (string) $home_host ) );
        }
        ?>
        <div id="kesso-cookies-banner" class="<?php echo esc_attr( $banner_classes ); ?>" role="dialog" aria-labelledby="kesso-cookies-banner-title" aria-describedby="kesso-cookies-banner-desc" aria-modal="false">
            <?php if ( $banner_overlay ) : ?>
            <div class="kesso-cookies-banner-overlay"></div>
            <?php endif; ?>
            <div class="kesso-cookies-banner-container">
                <div class="kesso-cookies-banner-content">
                    <h3 id="kesso-cookies-banner-title" class="kesso-cookies-banner-title" dir="auto"><?php echo esc_html( $banner_title ); ?></h3>
                    <div id="kesso-cookies-banner-desc" class="kesso-cookies-banner-description" dir="auto">
                        <?php echo wp_kses_post( $banner_description ); ?>
                        <?php if ( ! empty( $privacy_link_url ) ) : ?>
                            <a href="<?php echo esc_url( $privacy_link_url ); ?>"<?php echo $privacy_is_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?> class="kesso-cookies-privacy-link"><?php echo esc_html( $privacy_link_text ); ?></a>
                        <?php endif; ?>
                      </div>
                    <?php if ( Kesso_Cookies_Defaults::get( 'kesso_cookies_show_credit' ) ) : ?>
                    <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-cookies-banner-credit" dir="auto"><?php esc_html_e( '🧀 Powered by', 'kesso-cookies' ); ?> <strong>kesso.io</strong></a>
                    <?php endif; ?>
                  </div>
                  <div class="kesso-cookies-banner-actions">
                      <button type="button" class="kesso-cookies-btn kesso-cookies-btn--reject" id="kesso-cookies-reject-all" aria-label="<?php echo esc_attr( $button_reject ); ?>">
                          <?php echo esc_html( $button_reject ); ?>
                      </button>
                      <button type="button" class="kesso-cookies-btn kesso-cookies-btn--customize" id="kesso-cookies-customize" aria-label="<?php echo esc_attr( $button_customize ); ?>">
                          <?php echo esc_html( $button_customize ); ?>
                      </button>
                      <button type="button" class="kesso-cookies-btn kesso-cookies-btn--accept" id="kesso-cookies-accept-all" aria-label="<?php echo esc_attr( $button_accept ); ?>">
                          <?php echo esc_html( $button_accept ); ?>
                      </button>
                  </div>
              </div>
          </div>
          
        <!-- Customize Panel -->
        <div id="kesso-cookies-panel" class="kesso-cookies-panel" role="dialog" aria-labelledby="kesso-cookies-panel-title" aria-modal="true" style="display: none;">
            <div class="kesso-cookies-panel-overlay"></div>
            <div class="kesso-cookies-panel-container">
                <div class="kesso-cookies-panel-header">
                    <h3 id="kesso-cookies-panel-title" class="kesso-cookies-panel-title" dir="auto"><?php echo esc_html__( 'Cookie Preferences', 'kesso-cookies' ); ?></h3>
                    <button type="button" class="kesso-cookies-panel-close" id="kesso-cookies-panel-close" aria-label="<?php echo esc_attr__( 'Close', 'kesso-cookies' ); ?>">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <?php if ( Kesso_Cookies_Defaults::get( 'kesso_cookies_show_trust_signal' ) ) : ?>
                <div class="kesso-cookies-panel-trust" dir="auto">
                    <svg class="kesso-cookies-panel-trust-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="9" width="12" height="8" rx="2"></rect>
                        <path d="M7 9V6a3 3 0 0 1 6 0v3"></path>
                    </svg>
                    <span>
                        <?php
                        $expiry_days = 180;
                        printf(
                            /* translators: %d: number of days consent is remembered */
                            esc_html__( 'Your choices are saved for %d days. You can change them anytime.', 'kesso-cookies' ),
                            (int) $expiry_days
                        );
                        if ( ! empty( $privacy_link_url ) ) {
                            echo ' <a href="' . esc_url( $privacy_link_url ) . '"' . ( $privacy_is_external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $privacy_link_text ) . '</a>';
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="kesso-cookies-panel-content">
                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title" dir="auto"><?php echo esc_html__( 'Essential Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-essential" checked disabled aria-label="<?php echo esc_attr__( 'Essential Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description" dir="auto"><?php echo esc_html__( 'These cookies are necessary for the website to function and cannot be disabled.', 'kesso-cookies' ); ?></p>
                    </div>

                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title" dir="auto"><?php echo esc_html__( 'Analytics Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-analytics" aria-label="<?php echo esc_attr__( 'Analytics Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description" dir="auto"><?php echo esc_html__( 'These cookies help us understand how visitors interact with our website by collecting and reporting aggregated statistical information.', 'kesso-cookies' ); ?></p>
                    </div>

                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title" dir="auto"><?php echo esc_html__( 'Marketing Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-marketing" aria-label="<?php echo esc_attr__( 'Marketing Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description" dir="auto"><?php echo esc_html__( 'These cookies are used to deliver advertisements and track campaign effectiveness.', 'kesso-cookies' ); ?></p>
                    </div>
                </div>
                <div class="kesso-cookies-panel-actions">
                    <button type="button" class="kesso-cookies-btn kesso-cookies-btn--secondary" id="kesso-cookies-panel-reject-all">
                        <?php echo esc_html( $button_reject ); ?>
                    </button>
                    <button type="button" class="kesso-cookies-btn kesso-cookies-btn--accept" id="kesso-cookies-panel-save">
                        <?php echo esc_html( Kesso_Cookies_Defaults::get( 'kesso_cookies_button_save' ) ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

