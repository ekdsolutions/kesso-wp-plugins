<?php
/**
 * Admin Settings Class
 *
 * @package Kesso_Msg
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles WordPress admin settings page for message notifications
 */
class Kesso_Msg_Admin {

    /**
     * Settings page slug
     */
    const SETTINGS_PAGE = 'kesso-msg-settings';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu_page' ), 99.4 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_footer', array( $this, 'print_reset_js' ) );
    }

    /**
     * Register the admin menu page
     */
    public function register_menu_page() {
        // Get the favicon URL
        $icon_url = KESSO_MSG_URL . 'assets/img/kesso-favicon.png';
        
        $hook_suffix = add_menu_page(
            __( 'Messages', 'kesso-msg' ),
            '# ' . __( 'Messages', 'kesso-msg' ),
            'manage_options',
            self::SETTINGS_PAGE,
            array( $this, 'render_page' ),
            $icon_url,
            99.4  // Position at bottom, after kesso-cookies
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
        // Get all registered admin notices types
        $notice_types = $this->get_notice_types();
        
        foreach ( $notice_types as $type ) {
            register_setting( 'kesso_msg_settings', 'kesso_msg_show_' . $type, array(
                'type'              => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_boolean' ),
                'default'           => true, // Show in popup by default
            ) );
        }
    }

    /**
     * Get all notice types
     *
     * @return array
     */
    private function get_notice_types() {
        return array(
            'error',
            'warning',
            'success',
            'info',
            'update-nag',
        );
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
            'kesso-msg-admin',
            KESSO_MSG_URL . 'assets/css/admin.css',
            array(),
            KESSO_MSG_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'kesso-msg-admin',
            KESSO_MSG_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            KESSO_MSG_VERSION,
            true
        );
    }

    /**
     * Render the settings page
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'kesso-msg' ) );
        }

        $notice_types = $this->get_notice_types();
        $notice_labels = array(
            'error'     => __( 'Error Messages', 'kesso-msg' ),
            'warning'   => __( 'Warning Messages', 'kesso-msg' ),
            'success'   => __( 'Success Messages', 'kesso-msg' ),
            'info'      => __( 'Info Messages', 'kesso-msg' ),
            'update-nag' => __( 'Update Notifications', 'kesso-msg' ),
        );

        ?>
        <!-- Kesso Banner -->
        <div class="kesso-banner">
            <div class="kesso-banner-content">
                <?php echo esc_html__( 'This plugin was developed and distributed with ❤️ for free use by', 'kesso-msg' ); ?> 
                <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-banner-link">kesso.io</a>
            </div>
        </div>
        <div class="wrap">
            <div class="kesso-msg-app">
                <main class="kesso-msg-main">
                    <div class="kesso-msg-page-heading">
                        <div class="kesso-msg-heading-left">
                            <h1 class="kesso-msg-title"><?php echo esc_html__( 'Message Settings', 'kesso-msg' ); ?></h1>
                            <p class="kesso-msg-subtitle"><?php echo esc_html__( 'Choose which admin messages to display in the notification popup and which to keep as regular notices.', 'kesso-msg' ); ?></p>
                        </div>
                    </div>

                    <form method="post" action="options.php" id="kesso-msg-settings-form">
                        <?php settings_fields( 'kesso_msg_settings' ); ?>

                        <div class="kesso-msg-sections">
                            <!-- Message Types -->
                            <section class="kesso-msg-card">
                                <div class="kesso-msg-card-header">
                                    <h2 class="kesso-msg-card-title">
                                        <span class="material-symbols-outlined kesso-msg-icon" aria-hidden="true">notifications</span>
                                        <?php echo esc_html__( 'Message Types', 'kesso-msg' ); ?>
                                    </h2>
                                </div>
                                <div class="kesso-msg-card-body kesso-msg-card-body--stack">
                                    <div class="kesso-msg-feature-grid">
                                        <?php foreach ( $notice_types as $type ) : ?>
                                            <?php
                                            $option_name = 'kesso_msg_show_' . $type;
                                            $checked = get_option( $option_name, true );
                                            $label = isset( $notice_labels[ $type ] ) ? $notice_labels[ $type ] : ucfirst( $type );
                                            ?>
                                            <div class="kesso-msg-feature-card">
                                                <input type="hidden" name="<?php echo esc_attr( $option_name ); ?>" value="0" />
                                                <label class="kesso-msg-feature-label" for="<?php echo esc_attr( $option_name ); ?>__toggle">
                                                    <input type="checkbox"
                                                           id="<?php echo esc_attr( $option_name ); ?>__toggle"
                                                           class="kesso-msg-feature-checkbox"
                                                           name="<?php echo esc_attr( $option_name ); ?>"
                                                           value="1"
                                                           <?php checked( $checked ); ?> />
                                                    <span class="kesso-msg-feature-text">
                                                        <span class="kesso-msg-feature-name"><?php echo esc_html( $label ); ?></span>
                                                        <span class="kesso-msg-feature-desc"><?php echo esc_html__( 'Show this message type in the notification popup.', 'kesso-msg' ); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <footer class="kesso-msg-footer kesso-msg-glass-footer" role="contentinfo">
                            <div class="kesso-msg-footer-inner">
                                <div class="kesso-msg-footer-left">
                                    <div class="kesso-msg-footer-status">
                                        <span class="kesso-msg-footer-dot" aria-hidden="true"></span>
                                        <span class="kesso-msg-footer-label"><?php echo esc_html__( 'Ready to Save', 'kesso-msg' ); ?></span>
                                    </div>
                                    <p class="kesso-msg-footer-subtitle"><?php echo esc_html__( 'Changes will be applied immediately.', 'kesso-msg' ); ?></p>
                                </div>

                                <div class="kesso-msg-footer-right">
                                    <button type="button" class="kesso-msg-btn kesso-msg-btn--ghost" id="kesso-msg-reset-button"><?php echo esc_html__( 'Reset to Defaults', 'kesso-msg' ); ?></button>
                                    <button type="submit" class="kesso-msg-btn kesso-msg-btn--primary">
                                        <?php echo esc_html__( 'Save Changes', 'kesso-msg' ); ?>
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
            $( '#kesso-msg-reset-button' ).on( 'click', function ( e ) {
                e.preventDefault();
                if ( confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their default values?', 'kesso-msg' ) ); ?>' ) ) {
                    // Get all default values
                    var $form = $( '#kesso-msg-settings-form' );
                    
                    // Set all checkboxes to checked (default: show in popup)
                    $( '.kesso-msg-feature-checkbox' ).prop( 'checked', true );
                    
                    // Submit the form to save
                    $form.submit();
                }
            } );
        } );
        </script>
        <?php
    }
}

