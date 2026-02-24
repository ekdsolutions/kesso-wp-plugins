<?php
/**
 * Admin Settings Class
 *
 * @package Ratio_Pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin settings page
 */
class Ratio_Pricing_Admin {

	/**
	 * Settings page slug
	 */
	const SETTINGS_PAGE = 'ratio-pricing-settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ), 99.6 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_reset_js' ) );
	}

	/**
	 * Register the admin menu page
	 */
	public function register_menu_page() {
		$hook_suffix = add_menu_page(
			__( 'Ratio Pricing', 'ratio-pricing' ),
			__( 'Ratio Pricing', 'ratio-pricing' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			99.6
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'ratio_pricing_settings', 'ratio_pricing_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
			'default'           => $this->get_default_settings(),
		) );
	}

	/**
	 * Default placeholder and format for size select (used for 'default' and fallback).
	 *
	 * @return array
	 */
	private function get_default_size_ui_entry() {
		return array(
			'placeholder' => 'Choose a size...',
			'format'      => '{{size}} - {{price}} {{currency}}',
		);
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	private function get_default_settings() {
		$defaults = array(
			'texture_percentage'    => 30,
			'field_artwork_type'    => 'artwork_type',
			'field_ratio'           => 'ratio',
			'field_print_ratios'    => 'print_ratios',
			'field_size'            => 'size',
			'field_price'           => 'price',
			'field_texture'         => 'texture',
		);
		$defaults['size_ui'] = array( 'default' => $this->get_default_size_ui_entry() );
		return $defaults;
	}

	/**
	 * Get list of language codes for size_ui (Polylang languages + always 'default').
	 *
	 * @return array
	 */
	private function get_size_ui_languages() {
		$languages = array( 'default' );
		if ( function_exists( 'pll_languages_list' ) ) {
			$pll = pll_languages_list();
			if ( is_array( $pll ) && ! empty( $pll ) ) {
				$languages = array_unique( array_merge( array( 'default' ), $pll ) );
			}
		}
		return $languages;
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->get_default_settings();
		}

		$sanitized = array();

		// Texture percentage
		$sanitized['texture_percentage'] = isset( $input['texture_percentage'] ) ? floatval( $input['texture_percentage'] ) : 30;

		// Field mappings
		$field_keys = array( 'field_artwork_type', 'field_ratio', 'field_print_ratios', 'field_size', 'field_price', 'field_texture' );
		foreach ( $field_keys as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		// size_ui: per-language placeholder + format; always ensure 'default' exists
		$default_entry = $this->get_default_size_ui_entry();
		$sanitized['size_ui'] = array( 'default' => $default_entry );
		if ( isset( $input['size_ui'] ) && is_array( $input['size_ui'] ) ) {
			foreach ( $input['size_ui'] as $lang => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$sanitized['size_ui'][ $lang ] = array(
					'placeholder' => isset( $entry['placeholder'] ) ? sanitize_text_field( $entry['placeholder'] ) : $default_entry['placeholder'],
					'format'      => isset( $entry['format'] ) ? sanitize_text_field( $entry['format'] ) : $default_entry['format'],
				);
			}
		}

		return $sanitized;
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

		wp_enqueue_style(
			'ratio-pricing-admin',
			RATIO_PRICING_ASSETS_URL . 'css/admin.css',
			array(),
			RATIO_PRICING_VERSION
		);
	}

	/**
	 * Render the settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'ratio-pricing' ) );
		}

		$settings = wp_parse_args( get_option( 'ratio_pricing_settings', array() ), $this->get_default_settings() );
		if ( empty( $settings['size_ui'] ) || ! is_array( $settings['size_ui'] ) ) {
			$settings['size_ui'] = array( 'default' => $this->get_default_size_ui_entry() );
		}
		if ( ! isset( $settings['size_ui']['default'] ) ) {
			$settings['size_ui']['default'] = $this->get_default_size_ui_entry();
		}

		?>
		<div class="wrap">
			<div class="ratio-pricing-admin">
				<main class="ratio-pricing-main">
					<div class="ratio-pricing-page-heading">
						<div class="ratio-pricing-heading-left">
							<h1 class="ratio-pricing-title"><?php echo esc_html__( 'Ratio Pricing Settings', 'ratio-pricing' ); ?></h1>
							<p class="ratio-pricing-subtitle"><?php echo esc_html__( 'Configure dynamic pricing for artwork products based on size selections.', 'ratio-pricing' ); ?></p>
						</div>
					</div>

					<!-- Conceptual Explanation -->
					<section class="ratio-pricing-card">
						<div class="ratio-pricing-card-header">
							<h2 class="ratio-pricing-card-title"><?php echo esc_html__( 'How It Works', 'ratio-pricing' ); ?></h2>
						</div>
						<div class="ratio-pricing-card-body">
							<p><?php echo esc_html__( 'Ratio Pricing calculates product prices dynamically based on the size selected by the customer. The pricing flow works as follows:', 'ratio-pricing' ); ?></p>
							<ol>
								<li><?php echo esc_html__( 'Customer views an artwork product (Simple product type with artwork_type = "print")', 'ratio-pricing' ); ?></li>
								<li><?php echo esc_html__( 'Product is linked to a Ratio CPT (Custom Post Type) that contains available sizes and their prices', 'ratio-pricing' ); ?></li>
								<li><?php echo esc_html__( 'Customer selects a size from the available options', 'ratio-pricing' ); ?></li>
								<li><?php echo esc_html__( 'Base price is retrieved from the selected size in the Ratio CPT', 'ratio-pricing' ); ?></li>
								<li><?php echo esc_html__( 'If texture add-on is selected, the texture percentage is added to the base price', 'ratio-pricing' ); ?></li>
								<li><?php echo esc_html__( 'Final price is calculated and displayed to the customer', 'ratio-pricing' ); ?></li>
							</ol>
						</div>
					</section>

					<!-- Flow Diagram -->
					<section class="ratio-pricing-card">
						<div class="ratio-pricing-card-header">
							<h2 class="ratio-pricing-card-title"><?php echo esc_html__( 'Pricing Flow', 'ratio-pricing' ); ?></h2>
						</div>
						<div class="ratio-pricing-card-body">
							<div class="ratio-pricing-flow-diagram">
								<div class="flow-step">Product</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Check artwork_type</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Get Ratio CPT</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Select Size</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Base Price</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Optional Texture (+%)</div>
								<div class="flow-arrow">→</div>
								<div class="flow-step">Final Price</div>
							</div>
						</div>
					</section>

					<!-- Settings Form -->
					<form method="post" action="options.php" id="ratio-pricing-settings-form">
						<?php settings_fields( 'ratio_pricing_settings' ); ?>

						<section class="ratio-pricing-card">
							<div class="ratio-pricing-card-header">
								<h2 class="ratio-pricing-card-title"><?php echo esc_html__( 'Settings', 'ratio-pricing' ); ?></h2>
							</div>
							<div class="ratio-pricing-card-body ratio-pricing-card-body--stack">
								<!-- Texture Percentage -->
								<div class="ratio-pricing-field">
									<label for="texture_percentage" class="ratio-pricing-label">
										<?php echo esc_html__( 'Texture Price Percentage', 'ratio-pricing' ); ?>
									</label>
									<input 
										type="number" 
										id="texture_percentage" 
										name="ratio_pricing_settings[texture_percentage]" 
										value="<?php echo esc_attr( $settings['texture_percentage'] ); ?>" 
										step="0.1" 
										min="0" 
										class="ratio-pricing-input"
									/>
									<p class="ratio-pricing-field-desc"><?php echo esc_html__( 'Percentage to add to base price when texture add-on is selected (default: 30%).', 'ratio-pricing' ); ?></p>
								</div>

								<!-- Field Mappings -->
								<div class="ratio-pricing-field-group">
									<h3 class="ratio-pricing-field-group-title"><?php echo esc_html__( 'ACF / SCF Field Mappings', 'ratio-pricing' ); ?></h3>
									<p class="ratio-pricing-field-desc"><?php echo esc_html__( 'Map the custom field names (ACF or SCF) used in your project. Leave empty to use defaults.', 'ratio-pricing' ); ?></p>

									<div class="ratio-pricing-field">
										<label for="field_artwork_type" class="ratio-pricing-label">
											<?php echo esc_html__( 'Artwork Type Field', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_artwork_type" 
											name="ratio_pricing_settings[field_artwork_type]" 
											value="<?php echo esc_attr( $settings['field_artwork_type'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="artwork_type"
										/>
									</div>

									<div class="ratio-pricing-field">
										<label for="field_ratio" class="ratio-pricing-label">
											<?php echo esc_html__( 'Ratio Field (Post Object)', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_ratio" 
											name="ratio_pricing_settings[field_ratio]" 
											value="<?php echo esc_attr( $settings['field_ratio'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="ratio"
										/>
									</div>

									<div class="ratio-pricing-field">
										<label for="field_print_ratios" class="ratio-pricing-label">
											<?php echo esc_html__( 'Print Ratios Repeater Field', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_print_ratios" 
											name="ratio_pricing_settings[field_print_ratios]" 
											value="<?php echo esc_attr( $settings['field_print_ratios'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="print_ratios"
										/>
									</div>

									<div class="ratio-pricing-field">
										<label for="field_size" class="ratio-pricing-label">
											<?php echo esc_html__( 'Size Field (in Repeater)', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_size" 
											name="ratio_pricing_settings[field_size]" 
											value="<?php echo esc_attr( $settings['field_size'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="size"
										/>
									</div>

									<div class="ratio-pricing-field">
										<label for="field_price" class="ratio-pricing-label">
											<?php echo esc_html__( 'Price Field (in Repeater)', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_price" 
											name="ratio_pricing_settings[field_price]" 
											value="<?php echo esc_attr( $settings['field_price'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="price"
										/>
									</div>

									<div class="ratio-pricing-field">
										<label for="field_texture" class="ratio-pricing-label">
											<?php echo esc_html__( 'Texture Field', 'ratio-pricing' ); ?>
										</label>
										<input 
											type="text" 
											id="field_texture" 
											name="ratio_pricing_settings[field_texture]" 
											value="<?php echo esc_attr( $settings['field_texture'] ); ?>" 
											class="ratio-pricing-input"
											placeholder="texture"
										/>
									</div>
								</div>

								<!-- Size select (per language): placeholder + format -->
								<div class="ratio-pricing-field-group">
									<h3 class="ratio-pricing-field-group-title"><?php echo esc_html__( 'Size select (per language)', 'ratio-pricing' ); ?></h3>
									<p class="ratio-pricing-field-desc"><?php echo esc_html__( 'Placeholder for the first option and format for each size option. Use {{size}}, {{price}}, {{currency}}. Default is used as fallback when Polylang is active and the current language has no entry.', 'ratio-pricing' ); ?></p>
									<?php
									$size_ui_langs = $this->get_size_ui_languages();
									foreach ( $size_ui_langs as $lang_code ) :
										$lang_label = 'default' === $lang_code ? __( 'Default (fallback)', 'ratio-pricing' ) : strtoupper( $lang_code );
										$ph = isset( $settings['size_ui'][ $lang_code ]['placeholder'] ) ? $settings['size_ui'][ $lang_code ]['placeholder'] : $this->get_default_size_ui_entry()['placeholder'];
										$fmt = isset( $settings['size_ui'][ $lang_code ]['format'] ) ? $settings['size_ui'][ $lang_code ]['format'] : $this->get_default_size_ui_entry()['format'];
									?>
									<div class="ratio-pricing-field-group ratio-pricing-size-ui-lang" data-lang="<?php echo esc_attr( $lang_code ); ?>">
										<h4 class="ratio-pricing-field-group-subtitle"><?php echo esc_html( $lang_label ); ?></h4>
										<div class="ratio-pricing-field">
											<label for="size_ui_<?php echo esc_attr( $lang_code ); ?>_placeholder" class="ratio-pricing-label"><?php echo esc_html__( 'Placeholder', 'ratio-pricing' ); ?></label>
											<input type="text" id="size_ui_<?php echo esc_attr( $lang_code ); ?>_placeholder" name="ratio_pricing_settings[size_ui][<?php echo esc_attr( $lang_code ); ?>][placeholder]" value="<?php echo esc_attr( $ph ); ?>" class="ratio-pricing-input" placeholder="<?php echo esc_attr( $this->get_default_size_ui_entry()['placeholder'] ); ?>" />
										</div>
										<div class="ratio-pricing-field">
											<label for="size_ui_<?php echo esc_attr( $lang_code ); ?>_format" class="ratio-pricing-label"><?php echo esc_html__( 'Option format', 'ratio-pricing' ); ?></label>
											<input type="text" id="size_ui_<?php echo esc_attr( $lang_code ); ?>_format" name="ratio_pricing_settings[size_ui][<?php echo esc_attr( $lang_code ); ?>][format]" value="<?php echo esc_attr( $fmt ); ?>" class="ratio-pricing-input ratio-pricing-input--wide" placeholder="<?php echo esc_attr( $this->get_default_size_ui_entry()['format'] ); ?>" />
										</div>
									</div>
									<?php endforeach; ?>
								</div>
							</div>
						</section>

						<footer class="ratio-pricing-footer">
							<div class="ratio-pricing-footer-inner">
								<div class="ratio-pricing-footer-right">
									<button type="button" class="ratio-pricing-btn ratio-pricing-btn--ghost" id="ratio-pricing-reset-button"><?php echo esc_html__( 'Reset to Defaults', 'ratio-pricing' ); ?></button>
									<button type="submit" class="ratio-pricing-btn ratio-pricing-btn--primary">
										<?php echo esc_html__( 'Save Changes', 'ratio-pricing' ); ?>
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
		$default_entry = $this->get_default_size_ui_entry();
		?>
		<script>
		var ratioPricingAdminDefaults = {
			texture_percentage: 30,
			field_artwork_type: 'artwork_type',
			field_ratio: 'ratio',
			field_print_ratios: 'print_ratios',
			field_size: 'size',
			field_price: 'price',
			field_texture: 'texture',
			size_ui_entry: <?php echo wp_json_encode( $default_entry ); ?>
		};
		jQuery( document ).ready( function ( $ ) {
			$( '#ratio-pricing-reset-button' ).on( 'click', function ( e ) {
				e.preventDefault();
				if ( confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their default values?', 'ratio-pricing' ) ); ?>' ) ) {
					$( '#texture_percentage' ).val( ratioPricingAdminDefaults.texture_percentage );
					$( '#field_artwork_type' ).val( ratioPricingAdminDefaults.field_artwork_type );
					$( '#field_ratio' ).val( ratioPricingAdminDefaults.field_ratio );
					$( '#field_print_ratios' ).val( ratioPricingAdminDefaults.field_print_ratios );
					$( '#field_size' ).val( ratioPricingAdminDefaults.field_size );
					$( '#field_price' ).val( ratioPricingAdminDefaults.field_price );
					$( '#field_texture' ).val( ratioPricingAdminDefaults.field_texture );

					$( '.ratio-pricing-size-ui-lang' ).each( function () {
						var lang = $( this ).data( 'lang' );
						$( '#size_ui_' + lang + '_placeholder' ).val( ratioPricingAdminDefaults.size_ui_entry.placeholder );
						$( '#size_ui_' + lang + '_format' ).val( ratioPricingAdminDefaults.size_ui_entry.format );
					} );

					$( '#ratio-pricing-settings-form' ).submit();
				}
			} );
		} );
		</script>
		<?php
	}
}

