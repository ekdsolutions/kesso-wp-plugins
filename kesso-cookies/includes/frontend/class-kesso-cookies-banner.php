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
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Don't enqueue in page builders
        if ( $this->is_page_builder() ) {
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
                'bannerTitle'       => get_option( 'kesso_cookies_banner_title', __( 'We use cookies', 'kesso-cookies' ) ),
                'bannerDescription'  => get_option( 'kesso_cookies_banner_description', __( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'kesso-cookies' ) ),
                'buttonAccept'       => get_option( 'kesso_cookies_button_accept', __( 'Accept All', 'kesso-cookies' ) ),
                'buttonReject'       => get_option( 'kesso_cookies_button_reject', __( 'Reject All', 'kesso-cookies' ) ),
                'buttonCustomize'    => get_option( 'kesso_cookies_button_customize', __( 'Customize', 'kesso-cookies' ) ),
                'buttonSave'          => get_option( 'kesso_cookies_button_save', __( 'Save Preferences', 'kesso-cookies' ) ),
                'privacyLinkText'     => get_option( 'kesso_cookies_privacy_link_text', __( 'Privacy Policy', 'kesso-cookies' ) ),
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
        $border_color = get_option( 'kesso_cookies_settings_border_color', '#000000' );
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
     * Add script blocking mechanism in head
     */
    public function add_script_blocking() {
        // Don't initialize in page builders
        if ( $this->is_page_builder() ) {
            return;
        }

        ?>
        <script>
        // Initialize script blocking before any other scripts load
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
     * Check if we're in a page builder context
     *
     * @return bool
     */
    private function is_page_builder() {
        // Bricks Builder
        if ( defined( 'BRICKS_VERSION' ) && function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
            return true;
        }

        // Elementor
        if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
                return true;
            }
        }

        // Beaver Builder
        if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
            return true;
        }

        // Divi Builder
        if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
            return true;
        }

        // Visual Composer
        if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
            return true;
        }

        // Check for common builder query parameters
        if ( isset( $_GET['bricks'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['vc_editable'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Render the cookie consent banner
     */
    public function render_banner() {
        // Don't render in page builders
        if ( $this->is_page_builder() ) {
            return;
        }

        $banner_title       = get_option( 'kesso_cookies_banner_title', __( 'We use cookies', 'kesso-cookies' ) );
        $banner_description = get_option( 'kesso_cookies_banner_description', __( 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.', 'kesso-cookies' ) );
        $button_accept      = get_option( 'kesso_cookies_button_accept', __( 'Accept All', 'kesso-cookies' ) );
        $button_reject      = get_option( 'kesso_cookies_button_reject', __( 'Reject All', 'kesso-cookies' ) );
        $button_customize   = get_option( 'kesso_cookies_button_customize', __( 'Customize', 'kesso-cookies' ) );
        $privacy_link_text  = get_option( 'kesso_cookies_privacy_link_text', __( 'Privacy Policy', 'kesso-cookies' ) );
        $privacy_link_url   = $this->get_privacy_policy_url();
        
        // Get layout and overlay settings
        $banner_layout = get_option( 'kesso_cookies_banner_layout', 'bottom' );
        $banner_overlay = get_option( 'kesso_cookies_banner_overlay', false );
        
        // Build banner classes
        $banner_classes = 'kesso-cookies-banner';
        $banner_classes .= ' kesso-cookies-layout--' . esc_attr( $banner_layout );
        if ( $banner_overlay ) {
            $banner_classes .= ' kesso-cookies-overlay-enabled';
        }
        ?>
        <div id="kesso-cookies-banner" class="<?php echo esc_attr( $banner_classes ); ?>" role="dialog" aria-labelledby="kesso-cookies-banner-title" aria-modal="true">
            <?php if ( $banner_overlay ) : ?>
            <div class="kesso-cookies-banner-overlay"></div>
            <?php endif; ?>
            <div class="kesso-cookies-banner-container">
                <div class="kesso-cookies-banner-content">
                    <h3 id="kesso-cookies-banner-title" class="kesso-cookies-banner-title"><?php echo esc_html( $banner_title ); ?></h3>
                    <div class="kesso-cookies-banner-description">
                        <?php echo wp_kses_post( $banner_description ); ?>
                        <?php if ( ! empty( $privacy_link_url ) ) : ?>
                            <a href="<?php echo esc_url( $privacy_link_url ); ?>" target="_blank" rel="noopener noreferrer" class="kesso-cookies-privacy-link"><?php echo esc_html( $privacy_link_text ); ?></a>
                        <?php endif; ?>
                      </div>
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
          
          <!-- Kesso Credit Banner -->
          <div class="kesso-cookies-credit-banner">
              <div class="kesso-cookies-credit-banner-container">
                  <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-cookies-credit-banner-link">
                      <?php esc_html_e( '🧀 Powered by', 'kesso-cookies' ); ?> <strong>kesso.io</strong>
                  </a>
                  <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-cookies-credit-banner-link kesso-cookies-credit-banner-link--right">
                      <?php esc_html_e( 'kesso-cookies plugin', 'kesso-cookies' ); ?>
                  </a>
              </div>
          </div>

        <!-- Customize Panel -->
        <div id="kesso-cookies-panel" class="kesso-cookies-panel" role="dialog" aria-labelledby="kesso-cookies-panel-title" aria-modal="true" style="display: none;">
            <div class="kesso-cookies-panel-overlay"></div>
            <div class="kesso-cookies-panel-container">
                <div class="kesso-cookies-panel-header">
                    <h3 id="kesso-cookies-panel-title" class="kesso-cookies-panel-title"><?php echo esc_html__( 'Cookie Preferences', 'kesso-cookies' ); ?></h3>
                    <button type="button" class="kesso-cookies-panel-close" id="kesso-cookies-panel-close" aria-label="<?php echo esc_attr__( 'Close', 'kesso-cookies' ); ?>">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="kesso-cookies-panel-content">
                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title"><?php echo esc_html__( 'Essential Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-essential" checked disabled aria-label="<?php echo esc_attr__( 'Essential Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description"><?php echo esc_html__( 'These cookies are necessary for the website to function and cannot be disabled.', 'kesso-cookies' ); ?></p>
                    </div>

                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title"><?php echo esc_html__( 'Analytics Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-analytics" aria-label="<?php echo esc_attr__( 'Analytics Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description"><?php echo esc_html__( 'These cookies help us understand how visitors interact with our website by collecting and reporting aggregated statistical information.', 'kesso-cookies' ); ?></p>
                    </div>

                    <div class="kesso-cookies-category">
                        <div class="kesso-cookies-category-header">
                            <h4 class="kesso-cookies-category-title"><?php echo esc_html__( 'Marketing Cookies', 'kesso-cookies' ); ?></h4>
                            <label class="kesso-cookies-toggle">
                                <input type="checkbox" id="kesso-cookies-marketing" aria-label="<?php echo esc_attr__( 'Marketing Cookies', 'kesso-cookies' ); ?>" />
                                <span class="kesso-cookies-toggle-slider"></span>
                            </label>
                        </div>
                        <p class="kesso-cookies-category-description"><?php echo esc_html__( 'These cookies are used to deliver advertisements and track campaign effectiveness.', 'kesso-cookies' ); ?></p>
                    </div>
                </div>
                <div class="kesso-cookies-panel-actions">
                    <button type="button" class="kesso-cookies-btn kesso-cookies-btn--secondary" id="kesso-cookies-panel-reject-all">
                        <?php echo esc_html( $button_reject ); ?>
                    </button>
                    <button type="button" class="kesso-cookies-btn kesso-cookies-btn--accept" id="kesso-cookies-panel-save">
                        <?php echo esc_html( get_option( 'kesso_cookies_button_save', __( 'Save Preferences', 'kesso-cookies' ) ) ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}

