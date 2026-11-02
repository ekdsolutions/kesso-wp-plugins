<?php
/**
 * Frontend Widget Class
 *
 * Handles the accessibility widget rendering and functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Kesso_Widget {
	
	public $svg_icons = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_footer', [ $this, 'print_skip_to_content_link' ], 20 );
		add_action( 'wp_footer', [ $this, 'print_toolbar' ], 30 );
	}

	/**
	 * Check if toolbar is active
	 *
	 * @return bool
	 */
	public function is_toolbar_active() {
		$toolbar_option = get_option( 'kesso_toolbar', 'enable' );
		$is_active = 'disable' !== $toolbar_option;
		$is_active = apply_filters( 'kesso_frontend_is_toolbar_active', $is_active );
		return $is_active;
	}

	/**
	 * Check if toolbar button is active
	 *
	 * @param string $button_type Button type
	 * @return bool
	 */
	public function is_toolbar_button_active( $button_type ): bool {
		$button_option = get_option( "kesso_toolbar_button_{$button_type}", 'enable' );
		return 'disable' !== $button_option;
	}

	/**
	 * Get toolbar button title
	 *
	 * @param string $button_type Button type
	 * @return string
	 */
	public function get_toolbar_button_title( $button_type ) {
		$title = $this->get_default_title_text( "kesso_toolbar_button_{$button_type}_title" );
		return '<span class="kesso-toolbar-icon">' . $this->get_toolbar_svg( $button_type, $title ) . '</span><span class="kesso-toolbar-text">' . esc_html( $title ) . '</span>';
	}

	/**
	 * Get default title text for an option
	 *
	 * @param string $option Option name
	 * @return string
	 */
	private function get_default_title_text( $option ) {
		$defaults = $this->get_default_values();
		$default = isset( $defaults[ $option ] ) ? $defaults[ $option ] : '';
		return get_option( $option, $default );
	}

	/**
	 * Get default values for all options
	 *
	 * @return array
	 */
	private function get_default_values() {
		static $defaults = null;
		
		if ( null === $defaults ) {
			$defaults = [
				'kesso_toolbar_title' => __( 'Accessibility Tools', 'kesso-access' ),
				'kesso_toolbar_button_resize_font_add_title' => __( 'Increase Text', 'kesso-access' ),
				'kesso_toolbar_button_resize_font_less_title' => __( 'Decrease Text', 'kesso-access' ),
				'kesso_toolbar_button_grayscale_title' => __( 'Grayscale', 'kesso-access' ),
				'kesso_toolbar_button_high_contrast_title' => __( 'High Contrast', 'kesso-access' ),
				'kesso_toolbar_button_negative_contrast_title' => __( 'Negative Contrast', 'kesso-access' ),
				'kesso_toolbar_button_light_bg_title' => __( 'Light Background', 'kesso-access' ),
				'kesso_toolbar_button_links_underline_title' => __( 'Links Underline', 'kesso-access' ),
				'kesso_toolbar_button_readable_font_title' => __( 'Readable Font', 'kesso-access' ),
				'kesso_toolbar_button_pause_animations_title' => __( 'Pause Animations', 'kesso-access' ),
				'kesso_toolbar_button_sitemap_title' => __( 'Sitemap', 'kesso-access' ),
				'kesso_toolbar_button_help_title' => __( 'Help', 'kesso-access' ),
				'kesso_toolbar_button_feedback_title' => __( 'Feedback', 'kesso-access' ),
			];
		}
		
		return $defaults;
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		// Don't enqueue in page builders
		if ( $this->is_page_builder() ) {
			return;
		}

		wp_register_script(
			'kesso-access',
			KESSO_ASSETS_URL . 'js/widget.js',
			[ 'jquery' ],
			KESSO_VERSION,
			true
		);

		wp_register_style(
			'kesso-access',
			KESSO_ASSETS_URL . 'css/widget.css',
			[],
			KESSO_VERSION
		);

		wp_enqueue_script( 'kesso-access' );
		wp_enqueue_style( 'kesso-access' );

		wp_localize_script(
			'kesso-access',
			'KessoOptions',
			[
				'focusable'          => ( 'enable' === get_option( 'kesso_focusable' ) ) ? '1' : '0',
				'remove_link_target' => ( 'enable' === get_option( 'kesso_remove_link_target' ) ) ? '1' : '0',
				'add_role_links'     => ( 'enable' === get_option( 'kesso_add_role_links' ) ) ? '1' : '0',
				'enable_save'        => ( 'enable' === get_option( 'kesso_save' ) ) ? '1' : '0',
				'save_expiration'    => get_option( 'kesso_save_expiration', '12' ),
				'label_open'         => __( 'Open toolbar', 'kesso-access' ),
				'label_close'        => __( 'Close toolbar', 'kesso-access' ),
			]
		);

		// Add inline CSS for custom widget styling
		$custom_css = $this->get_custom_css();
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'kesso-access', $custom_css );
		}
	}

	/**
	 * Print skip to content link
	 */
	public function print_skip_to_content_link() {
		// Don't render in page builders
		if ( $this->is_page_builder() ) {
			return;
		}

		$skip_to_content_link = get_option( 'kesso_skip_to_content_link', 'enable' );
		if ( 'disable' === $skip_to_content_link ) {
			return;
		}

		$element_id = get_option( 'kesso_skip_to_content_link_element_id', 'content' );
		?>
		<a id="kesso-skip-content" class="kesso-skip-link kesso-skip-content" tabindex="0" accesskey="s" href="#<?php echo esc_attr( $element_id ); ?>"><?php esc_html_e( 'Skip to content', 'kesso-access' ); ?></a>
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

		// Builder query parameters are only meaningful for a logged-in editor.
		// Gating on login prevents an anonymous visitor from hiding the widget
		// simply by appending e.g. "?bricks" to any URL.
		if ( is_user_logged_in() && ( isset( $_GET['bricks'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['vc_editable'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Print toolbar
	 */
	public function print_toolbar() {
		// Don't render in page builders
		if ( $this->is_page_builder() ) {
			return;
		}

		if ( ! $this->is_toolbar_active() ) {
			return;
		}

		$toolbar_title = $this->get_default_title_text( 'kesso_toolbar_title' );
		$toolbar_visibility = get_option( 'kesso_toolbar', 'enable' );

		// Get position from widget styling settings
		$position = get_option( 'kesso_widget_position', 'bottom-left' );
		if ( ! in_array( $position, [ 'top-right', 'top-left', 'bottom-right', 'bottom-left' ], true ) ) {
			$position = 'bottom-left';
		}

		$wrapper_classes = [
			'kesso-toolbar-position-' . $position,
		];

		if ( 'enable' !== $toolbar_visibility ) {
			$wrapper_classes[] = 'kesso-' . $toolbar_visibility;
		}

		$sitemap_link = get_option( 'kesso_toolbar_button_sitemap_link' );
		$help_link = get_option( 'kesso_toolbar_button_help_link' );
		$feedback_link = get_option( 'kesso_toolbar_button_feedback_link' );

		$has_custom_links = ( ! empty( $sitemap_link ) || ! empty( $help_link ) || ! empty( $feedback_link ) );

		$icon = 'one-click';

		?>
		<nav id="kesso-toolbar" class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" role="navigation">
			<div class="kesso-toolbar-toggle">
				<button type="button" class="kesso-toolbar-link kesso-toolbar-toggle-link" title="<?php echo esc_attr( $toolbar_title ); ?>" aria-expanded="false" aria-controls="kesso-toolbar-overlay">
					<span class="kesso-sr-only sr-only kesso-toolbar-toggle-label"><?php esc_html_e( 'Open toolbar', 'kesso-access' ); ?></span>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor" width="1em" aria-hidden="true" focusable="false">
						<title><?php echo esc_html( $toolbar_title ); ?></title>
						<?php echo $this->get_svg_icon( $icon ); ?>
					</svg>
				</button>
			</div>
			<div class="kesso-toolbar-overlay" id="kesso-toolbar-overlay">
				<div class="kesso-toolbar-inner">
					<p class="kesso-toolbar-title"><?php echo esc_html( $toolbar_title ); ?></p>

					<ul class="kesso-toolbar-items kesso-tools">
						<?php do_action( 'kesso_toolbar_before_buttons' ); ?>
						<?php if ( $this->is_toolbar_button_active( 'resize_font' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-resize-font kesso-btn-resize-plus" data-action="resize-plus" data-action-group="resize" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'resize_font_add' ); ?>
								</button>
							</li>

							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-resize-font kesso-btn-resize-minus" data-action="resize-minus" data-action-group="resize" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'resize_font_less' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'grayscale' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-background-group kesso-btn-grayscale" data-action="grayscale" data-action-group="schema" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'grayscale' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'high_contrast' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-background-group kesso-btn-high-contrast" data-action="high-contrast" data-action-group="schema" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'high_contrast' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'negative_contrast' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-background-group kesso-btn-negative-contrast" data-action="negative-contrast" data-action-group="schema" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'negative_contrast' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'light_bg' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-background-group kesso-btn-light-background" data-action="light-background" data-action-group="schema" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'light_bg' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'links_underline' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-links-underline" data-action="links-underline" data-action-group="toggle" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'links_underline' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'readable_font' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-readable-font" data-action="readable-font" data-action-group="toggle" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'readable_font' ); ?>
								</button>
							</li>
						<?php endif; ?>

						<?php if ( $this->is_toolbar_button_active( 'pause_animations' ) ) : ?>
							<li class="kesso-toolbar-item">
								<button type="button" class="kesso-toolbar-link kesso-btn-pause-animations" data-action="pause-animations" data-action-group="toggle" tabindex="-1">
									<?php echo $this->get_toolbar_button_title( 'pause_animations' ); ?>
								</button>
							</li>
						<?php endif; ?>
						<?php do_action( 'kesso_toolbar_after_buttons' ); ?>
						<li class="kesso-toolbar-item">
							<button type="button" class="kesso-toolbar-link kesso-btn-reset" data-action="reset" tabindex="-1">
								<span class="kesso-toolbar-icon"><?php echo $this->get_toolbar_svg( 'reset', esc_html__( 'Reset', 'kesso-access' ) ); ?></span>
								<span class="kesso-toolbar-text"><?php esc_html_e( 'Reset', 'kesso-access' ); ?></span>
							</button>
						</li>
					</ul>
					<?php if ( $has_custom_links ) : ?>
						<ul class="kesso-toolbar-items kesso-links">
							<?php if ( ! empty( $sitemap_link ) ) : ?>
								<li class="kesso-toolbar-item">
									<a href="<?php echo esc_url( $sitemap_link ); ?>" class="kesso-toolbar-link kesso-link-sitemap" tabindex="-1">
										<?php echo $this->get_toolbar_button_title( 'sitemap' ); ?>
									</a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $help_link ) ) : ?>
								<li class="kesso-toolbar-item">
									<a href="<?php echo esc_url( $help_link ); ?>" class="kesso-toolbar-link kesso-link-help" tabindex="-1">
										<?php echo $this->get_toolbar_button_title( 'help' ); ?>
									</a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $feedback_link ) ) : ?>
								<li class="kesso-toolbar-item">
									<a href="<?php echo esc_url( $feedback_link ); ?>" class="kesso-toolbar-link kesso-link-feedback" tabindex="-1">
										<?php echo $this->get_toolbar_button_title( 'feedback' ); ?>
									</a>
								</li>
							<?php endif; ?>
						</ul>
					<?php endif; ?>
					
					<?php if ( 'disable' !== get_option( 'kesso_show_credit', 'enable' ) ) : ?>
					<!-- Kesso Credit -->
					<div class="kesso-toolbar-credit">
						<a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-toolbar-credit-link" tabindex="-1">
							<?php esc_html_e( '🧀 Powered by', 'kesso-access' ); ?> <strong>kesso.io</strong>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</nav>
		<?php
	}

	/**
	 * Get toolbar SVG icon
	 *
	 * @param string $icon Icon name
	 * @param string $icon_title Icon title
	 * @return string
	 */
	private function get_toolbar_svg( $icon, $icon_title = '' ) {
		// Modern stroke-based icons (Lucide/Heroicons style).
		$icons = [
			'resize_font_add'   => '<path d="M3 20l4-12l4 12M4.5 16h5"/><path d="M15 10h6M18 7v6"/>',
			'resize_font_less'  => '<path d="M3 20l4-12l4 12M4.5 16h5"/><path d="M15 10h6"/>',
			'grayscale'         => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18a9 9 0 0 0 0-18z" fill="currentColor" stroke="none"/>',
			'high_contrast'     => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor" stroke="none"/>',
			'negative_contrast' => '<path d="M2.5 12s3.5-7 9.5-7s9.5 7 9.5 7s-3.5 7-9.5 7s-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/>',
			'light_bg'          => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
			'links_underline'   => '<path d="M10 14a4 4 0 0 1 0-6l3-3a4 4 0 0 1 6 6l-1.5 1.5"/><path d="M14 10a4 4 0 0 1 0 6l-3 3a4 4 0 0 1-6-6l1.5-1.5"/>',
			'readable_font'     => '<path d="M3 20l3-10l3 10M4.5 17h3"/><path d="M11 20v-6a3 3 0 0 1 6 0v6M11 16h6"/>',
			'pause_animations'  => '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
			'reset'             => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/>',
			'sitemap'           => '<rect x="9" y="2" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="16" y="16" width="6" height="6" rx="1"/><path d="M5 16v-2h14v2M12 8v6"/>',
			'help'              => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.5-2.5 2-2.5 3.5"/><path d="M12 17.5v.01"/>',
			'feedback'          => '<path d="M21 15a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/>',
		];

		if ( isset( $icons[ $icon ] ) ) {
			$icon_title_html = '';
			if ( ! empty( $icon_title ) ) {
				$icon_title_html = '<title>' . esc_html( $icon_title ) . '</title>';
			}

			return sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s%s</svg>',
				$icon_title_html,
				$icons[ $icon ]
			);
		}

		return '';
	}

	/**
	 * Get SVG icon for toolbar toggle
	 *
	 * @param string $icon Icon name
	 * @return string
	 */
	private function get_svg_icon( $icon ) {
		if ( null === $this->svg_icons ) {
			$this->svg_icons = [
				'wheelchair' => '<g><path d="M60.4,78.9c-2.2,4.1-5.3,7.4-9.2,9.8c-4,2.4-8.3,3.6-13,3.6c-6.9,0-12.8-2.4-17.7-7.3c-4.9-4.9-7.3-10.8-7.3-17.7c0-5,1.4-9.5,4.1-13.7c2.7-4.2,6.4-7.2,10.9-9.2l-0.9-7.3c-6.3,2.3-11.4,6.2-15.3,11.8C7.9,54.4,6,60.6,6,67.3c0,5.8,1.4,11.2,4.3,16.1s6.8,8.8,11.7,11.7c4.9,2.9,10.3,4.3,16.1,4.3c7,0,13.3-2.1,18.9-6.2c5.7-4.1,9.6-9.5,11.7-16.2l-5.7-11.4C63.5,70.4,62.5,74.8,60.4,78.9z"/><path d="M93.8,71.3l-11.1,5.5L70,51.4c-0.6-1.3-1.7-2-3.2-2H41.3l-0.9-7.2h22.7v-7.2H39.6L37.5,19c2.5,0.3,4.8-0.5,6.7-2.3c1.9-1.8,2.9-4,2.9-6.6c0-2.5-0.9-4.6-2.6-6.3c-1.8-1.8-3.9-2.6-6.3-2.6c-2,0-3.8,0.6-5.4,1.8c-1.6,1.2-2.7,2.7-3.2,4.6c-0.3,1-0.4,1.8-0.3,2.3l5.4,43.5c0.1,0.9,0.5,1.6,1.2,2.3c0.7,0.6,1.5,0.9,2.4,0.9h26.4l13.4,26.7c0.6,1.3,1.7,2,3.2,2c0.6,0,1.1-0.1,1.6-0.4L97,77.7L93.8,71.3z"/></g>',
				'one-click' => '<path d="M50 .8c5.7 0 10.4 4.7 10.4 10.4S55.7 21.6 50 21.6s-10.4-4.7-10.4-10.4S44.3.8 50 .8zM92.2 32l-21.9 2.3c-2.6.3-4.6 2.5-4.6 5.2V94c0 2.9-2.3 5.2-5.2 5.2H60c-2.7 0-4.9-2.1-5.2-4.7l-2.2-24.7c-.1-1.5-1.4-2.5-2.8-2.4-1.3.1-2.2 1.1-2.4 2.4l-2.2 24.7c-.2 2.7-2.5 4.7-5.2 4.7h-.5c-2.9 0-5.2-2.3-5.2-5.2V39.4c0-2.7-2-4.9-4.6-5.2L7.8 32c-2.6-.3-4.6-2.5-4.6-5.2v-.5c0-2.6 2.1-4.7 4.7-4.7h.5c19.3 1.8 33.2 2.8 41.7 2.8s22.4-.9 41.7-2.8c2.6-.2 4.9 1.6 5.2 4.3v1c-.1 2.6-2.1 4.8-4.8 5.1z"/>',
				'accessibility' => '<path d="M50 8.1c23.2 0 41.9 18.8 41.9 41.9 0 23.2-18.8 41.9-41.9 41.9C26.8 91.9 8.1 73.2 8.1 50S26.8 8.1 50 8.1M50 0C22.4 0 0 22.4 0 50s22.4 50 50 50 50-22.4 50-50S77.6 0 50 0zm0 11.3c-21.4 0-38.7 17.3-38.7 38.7S28.6 88.7 50 88.7 88.7 71.4 88.7 50 71.4 11.3 50 11.3zm0 8.9c4 0 7.3 3.2 7.3 7.3S54 34.7 50 34.7s-7.3-3.2-7.3-7.3 3.3-7.2 7.3-7.2zm23.7 19.7c-5.8 1.4-11.2 2.6-16.6 3.2.2 20.4 2.5 24.8 5 31.4.7 1.9-.2 4-2.1 4.7-1.9.7-4-.2-4.7-2.1-1.8-4.5-3.4-8.2-4.5-15.8h-2c-1 7.6-2.7 11.3-4.5 15.8-.7 1.9-2.8 2.8-4.7 2.1-1.9-.7-2.8-2.8-2.1-4.7 2.6-6.6 4.9-11 5-31.4-5.4-.6-10.8-1.8-16.6-3.2-1.7-.4-2.8-2.1-2.4-3.9.4-1.7 2.1-2.8 3.9-2.4 19.5 4.6 25.1 4.6 44.5 0 1.7-.4 3.5.7 3.9 2.4.7 1.8-.3 3.5-2.1 3.9z"/>',
			];
		}

		if ( isset( $this->svg_icons[ $icon ] ) ) {
			return $this->svg_icons[ $icon ];
		}

		return $this->svg_icons['accessibility'];
	}

	/**
	 * Get custom CSS for widget styling
	 *
	 * @return string
	 */
	private function get_custom_css() {
		$css = '';
		
		// Position is now handled by CSS classes, no inline CSS needed
		
		// Size (icon size only for widget toggle button, not options list)
		$size = get_option( 'kesso_widget_size', 'md' );
		$icon_sizes = [
			'xs' => '16px',
			'sm' => '20px',
			'md' => '25px',
			'lg' => '30px',
		];
		
		$paddings = [
			'xs' => '8px',
			'sm' => '12px',
			'md' => '12px',
			'lg' => '12px',
		];
		
		if ( isset( $icon_sizes[ $size ] ) ) {
			// Apply size only to widget toggle button icon
			$css .= '#kesso-toolbar .kesso-toolbar-toggle button svg {';
			$css .= 'width: ' . esc_attr( $icon_sizes[ $size ] ) . ';';
			$css .= 'height: ' . esc_attr( $icon_sizes[ $size ] ) . ';';
			$css .= 'min-width: unset;';
			$css .= 'min-height: unset;';
			$css .= '}';

			// Apply padding to toggle button
			if ( isset( $paddings[ $size ] ) ) {
				$css .= '#kesso-toolbar .kesso-toolbar-toggle button {';
				$css .= 'padding: ' . esc_attr( $paddings[ $size ] ) . ';';
				$css .= '}';
			}
		}
		
		// Border radius
		$border_radius = get_option( 'kesso_widget_border_radius', '' );
		if ( $border_radius ) {
			$radius_values = [
				'full' => '50%',
				'sm'   => '4px',
				'md'   => '8px',
				'lg'   => '12px',
			];
			
			if ( isset( $radius_values[ $border_radius ] ) ) {
				// Apply to toggle button
				$css .= '#kesso-toolbar .kesso-toolbar-toggle button {';
				$css .= 'border-radius: ' . esc_attr( $radius_values[ $border_radius ] ) . ';';
				$css .= '}';
				
				// For overlay, use 'lg' if border_radius is 'full'
				$overlay_radius = ( 'full' === $border_radius ) ? '12px' : $radius_values[ $border_radius ];
				$css .= '#kesso-toolbar .kesso-toolbar-overlay {';
				$css .= 'border-radius: ' . esc_attr( $overlay_radius ) . ';';
				$css .= '}';
			}
		}
		
		// Icon color
		$icon_color = get_option( 'kesso_widget_icon_color', '#000000' );
		if ( $icon_color ) {
			// Apply to toggle button icon
			$css .= '#kesso-toolbar .kesso-toolbar-toggle button svg {';
			$css .= 'fill: ' . esc_attr( $icon_color ) . ';';
			$css .= '}';

			// Apply as text colour in the options list (stroke icons inherit it).
			$css .= ':root { --kesso-access-text: ' . esc_attr( $icon_color ) . '; }';
		}
		
		// Border color
		// Fallback matches the admin default and kesso-cookies so the two
		// floating widgets look consistent when both are used side-by-side.
		$border_color = get_option( 'kesso_widget_border_color', 'rgba(0, 0, 0, 0.1)' );
		if ( $border_color ) {
			// Extract hex from rgba if needed for box-shadow
			$border_hex = $this->extract_hex_from_color( $border_color );
			$border_rgb = $this->hex_to_rgb( $border_hex );
			$shadow_color = 'rgba(' . $border_rgb['r'] . ', ' . $border_rgb['g'] . ', ' . $border_rgb['b'] . ', 0.05)';

			// Apply to toggle button
			$css .= '#kesso-toolbar .kesso-toolbar-toggle button {';
			$css .= 'border-color: ' . esc_attr( $border_color ) . ';';
			$css .= 'border: 1px solid ' . esc_attr( $border_color ) . ';';
			$css .= 'box-shadow: 0 0 10px 5px ' . esc_attr( $shadow_color ) . ';';
			$css .= '}';

			// Apply to overlay
			$css .= ':root { --kesso-access-border: ' . esc_attr( $border_color ) . '; }';
			$css .= '#kesso-toolbar .kesso-toolbar-overlay {';
			$css .= 'border-color: ' . esc_attr( $border_color ) . ';';
			$css .= '}';
		} else {
			// Default box-shadow if no border color is set
			$css .= '#kesso-toolbar .kesso-toolbar-toggle button {';
			$css .= 'box-shadow: 0 0 10px 5px rgba(0, 0, 0, 0.05);';
			$css .= '}';

			$css .= '#kesso-toolbar .kesso-toolbar-overlay {';
			$css .= 'box-shadow: 0 0 10px 5px rgba(0, 0, 0, 0.05);';
			$css .= '}';
		}
		
		// Background color
		$background_color = get_option( 'kesso_widget_background_color', '#ffffff' );
		if ( $background_color ) {
			// Apply to toggle button
			$css .= '#kesso-toolbar .kesso-toolbar-toggle button {';
			$css .= 'background-color: ' . esc_attr( $background_color ) . ';';
			$css .= '}';

			// Apply to overlay
			$css .= ':root { --kesso-access-bg: ' . esc_attr( $background_color ) . '; }';
		}
		
		return $css;
	}
	
	/**
	 * Extract hex color from rgba/rgb/hex string
	 *
	 * @param string $color Color string (hex, rgb, or rgba)
	 * @return string Hex color code
	 */
	private function extract_hex_from_color( $color ) {
		// If already a valid 3- or 6-digit hex, return it
		if ( preg_match( '/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
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
	 * @param string $hex Hex color code (with or without #)
	 * @return array Array with r, g, b values
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
}

