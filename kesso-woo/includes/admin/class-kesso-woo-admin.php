<?php
/**
 * Admin Settings Class
 *
 * Handles WordPress admin settings page for Kesso WooCommerce
 *
 * @package Kesso_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles WordPress admin settings page
 */
class Kesso_Woo_Admin {

	/**
	 * Settings page slug
	 */
	const SETTINGS_PAGE = 'kesso-woo-settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ), 99.5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_reset_js' ) );
	}

	/**
	 * Register the admin menu page
	 */
	public function register_menu_page() {
		// Get the favicon URL
		$icon_url = KESSO_WOO_ASSETS_URL . 'img/kesso-favicon.png';
		
		$hook_suffix = add_menu_page(
			__( 'Kesso WooCommerce', 'kesso-woo' ),
			'# ' . __( 'WooExtension', 'kesso-woo' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_page' ),
			$icon_url,
			99.5  // Position at bottom, after kesso-cookies and Clarity
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
		// Register enable/disable setting
		register_setting( 'kesso_woo_settings', 'kesso_woo_enabled', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_enabled_disabled' ),
			'default'           => 'yes',
		) );
	}

	/**
	 * Sanitize enabled/disabled value
	 *
	 * @param string $input Input value.
	 * @return string
	 */
	public function sanitize_enabled_disabled( $input ) {
		if ( empty( $input ) ) {
			return 'yes';
		}

		return in_array( $input, array( 'yes', 'no' ), true ) ? $input : 'yes';
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
			'kesso-woo-admin',
			KESSO_WOO_ASSETS_URL . 'css/admin.css',
			array(),
			KESSO_WOO_VERSION
		);

		// Enqueue admin scripts
		wp_enqueue_script(
			'kesso-woo-admin',
			KESSO_WOO_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			KESSO_WOO_VERSION,
			true
		);
	}

	/**
	 * Render the settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'kesso-woo' ) );
		}

		?>
		<!-- Kesso Banner -->
		<div class="kesso-banner">
			<div class="kesso-banner-content">
				<?php echo esc_html__( 'This plugin was developed and distributed with ❤️ for free use by', 'kesso-woo' ); ?> 
				<a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-banner-link">kesso.io</a>
			</div>
		</div>
		<div class="wrap">
			<div class="kesso-woo-app">
				<main class="kesso-woo-main">
					<div class="kesso-woo-page-heading">
						<div class="kesso-woo-heading-left">
							<h1 class="kesso-woo-title"><?php echo esc_html__( 'WooCommerce Extensions', 'kesso-woo' ); ?></h1>
							<p class="kesso-woo-subtitle"><?php echo esc_html__( 'Configure WooCommerce utilities and enhancements for your store.', 'kesso-woo' ); ?></p>
						</div>
					</div>

					<form method="post" action="options.php" id="kesso-woo-settings-form">
						<?php settings_fields( 'kesso_woo_settings' ); ?>

						<div class="kesso-woo-sections">
							<!-- Features Section -->
							<section class="kesso-woo-card">
								<div class="kesso-woo-card-header">
									<h2 class="kesso-woo-card-title">
										<span class="material-symbols-outlined kesso-woo-icon" aria-hidden="true">settings</span>
										<?php echo esc_html__( 'Features', 'kesso-woo' ); ?>
									</h2>
								</div>
								<div class="kesso-woo-card-body kesso-woo-card-body--stack">
									<div class="kesso-woo-field">
										<label class="kesso-woo-toggle-label">
											<input type="hidden" name="kesso_woo_enabled" value="no" />
											<input type="checkbox" 
											       id="kesso_woo_enabled" 
											       name="kesso_woo_enabled" 
											       value="yes" 
											       class="kesso-woo-toggle-checkbox"
											       <?php checked( get_option( 'kesso_woo_enabled', 'yes' ), 'yes' ); ?> />
											<span class="kesso-woo-toggle-text">
												<span class="kesso-woo-toggle-name"><?php echo esc_html__( 'Enable Product Synchronization', 'kesso-woo' ); ?></span>
												<span class="kesso-woo-toggle-desc"><?php echo esc_html__( 'When enabled, product data (prices, stock, SKU, images, attributes, etc.) will automatically sync across all language translations when using Polylang.', 'kesso-woo' ); ?></span>
											</span>
										</label>
									</div>
								</div>
							</section>
						</div>

						<footer class="kesso-woo-footer kesso-woo-glass-footer" role="contentinfo">
							<div class="kesso-woo-footer-inner">
								<div class="kesso-woo-footer-left">
									<div class="kesso-woo-footer-status">
										<span class="kesso-woo-footer-dot" aria-hidden="true"></span>
										<span class="kesso-woo-footer-label"><?php echo esc_html__( 'Ready to Save', 'kesso-woo' ); ?></span>
									</div>
									<p class="kesso-woo-footer-subtitle"><?php echo esc_html__( 'Changes will be applied immediately.', 'kesso-woo' ); ?></p>
								</div>

								<div class="kesso-woo-footer-right">
									<button type="button" class="kesso-woo-btn kesso-woo-btn--ghost" id="kesso-woo-reset-button"><?php echo esc_html__( 'Reset to Defaults', 'kesso-woo' ); ?></button>
									<button type="submit" class="kesso-woo-btn kesso-woo-btn--primary">
										<?php echo esc_html__( 'Save Changes', 'kesso-woo' ); ?>
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
			$( '#kesso-woo-reset-button' ).on( 'click', function ( e ) {
				e.preventDefault();
				if ( confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their default values?', 'kesso-woo' ) ); ?>' ) ) {
					// Get all default values
					var $form = $( '#kesso-woo-settings-form' );
					
					// Reset to defaults
					$( '#kesso_woo_enabled' ).prop( 'checked', true );
					
					// Submit the form to save
					$form.submit();
				}
			} );
		} );
		</script>
		<?php
	}
}

