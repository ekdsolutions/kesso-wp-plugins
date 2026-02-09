<?php
/**
 * Admin Settings Class
 *
 * Handles WordPress admin settings page for accessibility widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Kesso_Admin {

	const SETTINGS_PAGE = 'toplevel_page_kesso-settings';
	const TOOLBAR_PAGE = 'kesso_page_kesso-toolbar';
	const FIELD_TEXT = 'text';
	const FIELD_SELECT = 'select';

	protected $_sections = [];
	protected $_defaults = [];
	public $_page_title = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_page_title = strtoupper( __( '🧀 Kesso Accessibility Widget', 'kesso-widget' ) );

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 21 );
		add_action( 'admin_init', [ $this, 'admin_init' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_footer', [ $this, 'print_js' ] );
	}

	/**
	 * Enqueue admin assets (scoped, Kesso Init-like UI)
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		// Only load on our plugin pages.
		if ( self::SETTINGS_PAGE !== $screen->id && self::TOOLBAR_PAGE !== $screen->id ) {
			return;
		}

		$css_url = plugins_url( 'admin/assets/css/admin.css', KESSO_MAIN_FILE );
		wp_enqueue_style( 'kesso-access-admin', $css_url, array(), KESSO_VERSION );
		
		$js_url = plugins_url( 'admin/assets/js/admin.js', KESSO_MAIN_FILE );
		wp_enqueue_script( 'kesso-access-admin', $js_url, array(), KESSO_VERSION, true );
	}

	/**
	 * Register admin menu
	 */
	public function admin_menu() {
		// Get the favicon URL
		$icon_url = KESSO_ASSETS_URL . 'img/kesso-favicon.png';

		add_menu_page(
			__( 'Accessibility', 'kesso-widget' ),
			'# ' . __( 'Accessibility', 'kesso-widget' ),
			'manage_options',
			'kesso-settings',
			[ $this, 'display_settings_page' ],
			$icon_url,
			99.2  // Position at bottom, second of the two
		);
		add_submenu_page(
			'kesso-settings',
			__( 'Kesso Settings', 'kesso-widget' ),
			__( 'Settings', 'kesso-widget' ),
			'manage_options',
			'kesso-settings',
			[ $this, 'display_settings_page' ]
		);

		// Add CSS for menu icon
		add_action( 'admin_head', [ $this, 'admin_menu_icon_css' ] );
	}

	public function admin_menu_icon_css() {
		?>
		<style>
			#toplevel_page_kesso-settings .wp-menu-image img {
				width: 18px;
				height: 20px;
				padding: 6px 0 0 0;
			}
		</style>
		<?php
	}

	/**
	 * Initialize settings
	 */
	public function admin_init() {
		foreach ( $this->get_settings_sections() as $section_key => $section ) {
			add_settings_section(
				$section['id'],
				$section['title'],
				[ $this, 'add_settings_section' ],
				$section['page']
			);

			if ( empty( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				add_settings_field(
					$field['id'],
					$field['title'],
					[ $this, 'add_settings_field' ],
					$section['page'],
					$section['id'],
					$field
				);

				$sanitize_callback = [ $this, 'field_html' ];
				if ( ! empty( $field['sanitize_callback'] ) ) {
					$sanitize_callback = $field['sanitize_callback'];
				}

				// Use array format for register_setting (WordPress 4.7+)
				$args = [
					'sanitize_callback' => $sanitize_callback,
				];
				register_setting( $section['page'], $field['id'], $args );
			}
		}
	}

	/**
	 * Get settings sections
	 *
	 * @return array
	 */
	public function get_settings_sections() {
		if ( ! empty( $this->_sections ) ) {
			return $this->_sections;
		}

		$sections = [];
		$sections = $this->section_kesso_settings( $sections );
		$this->_sections = $sections;

		return $this->_sections;
	}

	/**
	 * Setup Settings fields
	 *
	 * @param array $sections
	 * @return array
	 */
	public function section_kesso_settings( $sections ) {
		$fields = [];

		// Toolbar Display Settings
		$fields[] = [
			'id'      => 'kesso_toolbar',
			'title'   => __( 'Display Toolbar', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'options' => [
				'enable'          => __( 'Show on all devices', 'kesso-widget' ),
				'visible-desktop' => __( 'Visible Desktop', 'kesso-widget' ),
				'visible-tablet'  => __( 'Visible Tablet', 'kesso-widget' ),
				'visible-phone'   => __( 'Visible Phone', 'kesso-widget' ),
				'hidden-desktop'  => __( 'Hidden Desktop', 'kesso-widget' ),
				'hidden-tablet'   => __( 'Hidden Tablet', 'kesso-widget' ),
				'hidden-phone'    => __( 'Hidden Phone', 'kesso-widget' ),
				'disable'         => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_toolbar_display' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_title',
			'title' => __( 'Toolbar Title', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'std'   => __( 'Accessibility Tools', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		// Add divider/heading for Toolbar Buttons
		$fields[] = [
			'id'    => 'toolbar_buttons_heading',
			'title' => '<h3 class="kesso-section-heading">' . __( 'Toolbar Buttons', 'kesso-widget' ) . '</h3>',
			'type'  => 'html',
		];

		$toolbar_options_classes = 'kesso-toolbar-button';

		// All toolbar button fields from section_a11y_toolbar() go here
		// (Resize Font, Grayscale, High Contrast, etc.)
		$fields[] = [
			'id'      => 'kesso_toolbar_button_resize_font',
			'title'   => __( 'Resize Font', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_resize_font_add_title',
			'title' => __( 'Increase Text', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row no-border',
			'std'   => __( 'Increase Text', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_resize_font_less_title',
			'title' => __( 'Decrease Text', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Decrease Text', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_grayscale',
			'title'   => __( 'Grayscale', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_grayscale_title',
			'title' => __( 'Grayscale', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Grayscale', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_high_contrast',
			'title'   => __( 'High Contrast', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_high_contrast_title',
			'title' => __( 'High Contrast', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'High Contrast', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_negative_contrast',
			'title'   => __( 'Negative Contrast', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_negative_contrast_title',
			'title' => __( 'Negative Contrast', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Negative Contrast', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_light_bg',
			'title'   => __( 'Light Background', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_light_bg_title',
			'title' => __( 'Light Background', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Light Background', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_links_underline',
			'title'   => __( 'Links Underline', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_links_underline_title',
			'title' => __( 'Links Underline', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Links Underline', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_readable_font',
			'title'   => __( 'Readable Font', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_readable_font_title',
			'title' => __( 'Readable Font', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Readable Font', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_toolbar_button_pause_animations',
			'title'   => __( 'Pause Animations', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'class'   => $toolbar_options_classes,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_pause_animations_title',
			'title' => __( 'Pause Animations', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'   => __( 'Pause Animations', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_sitemap_title',
			'title' => __( 'Sitemap', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes,
			'std'   => __( 'Sitemap', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'          => 'kesso_toolbar_button_sitemap_link',
			'title'       => __( 'Sitemap Link', 'kesso-widget' ),
			'type'        => self::FIELD_TEXT,
			'placeholder' => 'https://your-domain.com/sitemap',
			'desc'        => __( 'Link for sitemap page in your website. Leave blank to disable.', 'kesso-widget' ),
			'class'       => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'         => '',
			'sanitize_callback' => 'esc_url_raw',
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_help_title',
			'title' => __( 'Help', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes,
			'std'   => __( 'Help', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'          => 'kesso_toolbar_button_help_link',
			'title'       => __( 'Help Link', 'kesso-widget' ),
			'type'        => self::FIELD_TEXT,
			'placeholder' => 'https://your-domain.com/help',
			'desc'        => __( 'Link for help page in your website. Leave blank to disable.', 'kesso-widget' ),
			'class'       => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'         => '',
			'sanitize_callback' => 'esc_url_raw',
		];

		$fields[] = [
			'id'    => 'kesso_toolbar_button_feedback_title',
			'title' => __( 'Feedback Title', 'kesso-widget' ),
			'type'  => self::FIELD_TEXT,
			'class' => $toolbar_options_classes,
			'std'   => __( 'Feedback', 'kesso-widget' ),
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'          => 'kesso_toolbar_button_feedback_link',
			'title'       => __( 'Feedback', 'kesso-widget' ),
			'type'        => self::FIELD_TEXT,
			'placeholder' => 'https://your-domain.com/feedback',
			'desc'        => __( 'Link for feedback page in your website. Leave blank to disable.', 'kesso-widget' ),
			'class'       => $toolbar_options_classes . ' kesso-settings-child-row',
			'std'         => '',
			'sanitize_callback' => 'esc_url_raw',
		];

		// Add divider/heading for Widget Styling
		$fields[] = [
			'id'    => 'widget_styling_heading',
			'title' => '<h3 class="kesso-section-heading">' . __( 'Widget Styling', 'kesso-widget' ) . '</h3>',
			'type'  => 'html',
		];

		// Position field
		$fields[] = [
			'id'      => 'kesso_widget_position',
			'title'   => __( 'Position', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'options' => [
				'top-right'  => __( 'Top Right', 'kesso-widget' ),
				'top-left'  => __( 'Top Left', 'kesso-widget' ),
				'bottom-right' => __( 'Bottom Right', 'kesso-widget' ),
				'bottom-left'  => __( 'Bottom Left', 'kesso-widget' ),
			],
			'std'     => 'bottom-left',
			'sanitize_callback' => 'sanitize_text_field',
		];

		// Size field (icon size and padding combined)
		$fields[] = [
			'id'      => 'kesso_widget_size',
			'title'   => __( 'Size', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'options' => [
				'sm' => __( 'SM', 'kesso-widget' ),
				'md' => __( 'MD', 'kesso-widget' ),
				'lg' => __( 'LG', 'kesso-widget' ),
			],
			'std'     => 'md',
			'sanitize_callback' => 'sanitize_text_field',
		];

		// Border radius
		$fields[] = [
			'id'      => 'kesso_widget_border_radius',
			'title'   => __( 'Border Radius', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'options' => [
				'full' => __( 'Full', 'kesso-widget' ),
				'sm'   => __( 'SM', 'kesso-widget' ),
				'md'   => __( 'MD', 'kesso-widget' ),
				'lg'   => __( 'LG', 'kesso-widget' ),
			],
			'std'     => '',
			'sanitize_callback' => 'sanitize_text_field',
		];

		// Background color
		$fields[] = [
			'id'    => 'kesso_widget_background_color',
			'title' => __( 'Background Color', 'kesso-widget' ),
			'type'  => 'color',
			'std'   => '#ffffff',
			'sanitize_callback' => [ $this, 'sanitize_color' ],
		];

		// Icon color
		$fields[] = [
			'id'    => 'kesso_widget_icon_color',
			'title' => __( 'Icon Color', 'kesso-widget' ),
			'type'  => 'color',
			'std'   => '#000000',
			'sanitize_callback' => [ $this, 'sanitize_color' ],
		];

		// Border color
		$fields[] = [
			'id'    => 'kesso_widget_border_color',
			'title' => __( 'Border Color', 'kesso-widget' ),
			'type'  => 'color',
			'std'   => '#000000',
			'sanitize_callback' => [ $this, 'sanitize_color' ],
		];

		// Add divider/heading for General Settings
		$fields[] = [
			'id'    => 'general_settings_heading',
			'title' => '<h3 class="kesso-section-heading">' . __( 'General Settings', 'kesso-widget' ) . '</h3>',
			'type'  => 'html',
		];

		// Existing general settings fields
		$fields[] = [
			'id'          => 'kesso_skip_to_content_link_element_id',
			'title'       => __( 'Skip to Content Element ID', 'kesso-widget' ),
			'placeholder' => 'content',
			'type'        => self::FIELD_TEXT,
			'std'         => 'content',
			'sanitize_callback' => 'sanitize_text_field',
		];

		$fields[] = [
			'id'      => 'kesso_skip_to_content_link',
			'title'   => __( 'Skip to Content link', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'desc'    => __( 'Add skip to content link when using keyboard.', 'kesso-widget' ),
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'      => 'kesso_focusable',
			'title'   => __( 'Add Outline Focus', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'desc'    => __( 'Add outline to elements on keyboard focus.', 'kesso-widget' ),
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'disable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'      => 'kesso_remove_link_target',
			'title'   => __( 'Remove target attribute from links', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'desc'    => __( 'This option will reset all your target links to open in the same window or tab.', 'kesso-widget' ),
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'disable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'      => 'kesso_add_role_links',
			'title'   => __( 'Add landmark roles to all links', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'desc'    => __( 'This option will add <code>role="link"</code> to all links on the page.', 'kesso-widget' ),
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'      => 'kesso_save',
			'title'   => __( 'Sitewide Accessibility', 'kesso-widget' ),
			'desc'    => __( 'Consistent accessibility throughout your site visit. Site remembers you and stays accessible.', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'options' => [
				'enable'  => __( 'Enable', 'kesso-widget' ),
				'disable' => __( 'Disable', 'kesso-widget' ),
			],
			'std'     => 'enable',
			'sanitize_callback' => [ $this, 'sanitize_enabled_disabled' ],
		];

		$fields[] = [
			'id'      => 'kesso_save_expiration',
			'title'   => __( 'Remember user for', 'kesso-widget' ),
			'type'    => self::FIELD_SELECT,
			'desc'    => __( 'Define how long your toolbar settings will be remembered', 'kesso-widget' ),
			'options' => [
				'1'   => __( '1 Hour', 'kesso-widget' ),
				'6'   => __( '6 Hours', 'kesso-widget' ),
				'12'  => __( '12 Hours', 'kesso-widget' ),
				'24'  => __( '1 Day', 'kesso-widget' ),
				'48'  => __( '2 Days', 'kesso-widget' ),
				'72'  => __( '3 Days', 'kesso-widget' ),
				'168' => __( '1 Week', 'kesso-widget' ),
				'720' => __( '1 Month', 'kesso-widget' ),
			],
			'std'     => '12',
			'sanitize_callback' => [ $this, 'sanitize_expiration' ],
		];

		$sections[] = [
			'id'     => 'section-kesso-settings',
			'page'   => self::SETTINGS_PAGE,
			'title'  => __( 'Settings', 'kesso-widget' ),
			'intro'  => '',
			'fields' => $fields,
		];

		return $sections;
	}

	/**
	 * Add settings section
	 *
	 * @param array $args
	 */
	public function add_settings_section( $args = [] ) {
		$args = wp_parse_args( $args, [
			'id'    => '',
			'title' => '',
		] );

		foreach ( $this->_sections as $section ) {
			if ( $args['id'] !== $section['id'] ) {
				continue;
			}
			if ( ! empty( $section['intro'] ) ) {
				echo wp_kses_post( '<p>' . $section['intro'] . '</p>' );
			}
			break;
		}
	}

	/**
	 * Add settings field
	 *
	 * @param array $args
	 */
	public function add_settings_field( $args = [] ) {
		if ( empty( $args ) ) {
			return;
		}

		$args = wp_parse_args( $args, [
			'id'   => '',
			'std'  => '',
			'type' => self::FIELD_TEXT,
		] );

		if ( empty( $args['id'] ) || empty( $args['type'] ) ) {
			return;
		}

		$field_callback = 'render_' . $args['type'] . '_field';
		if ( method_exists( $this, $field_callback ) ) {
			call_user_func( [ $this, $field_callback ], $args );
		} elseif ( 'html' === $args['type'] ) {
			$this->render_html_field( $args );
		}
	}

	/**
	 * Render select field
	 *
	 * @param array $field
	 */
	public function render_select_field( $field ) {
		$options = [];
		foreach ( $field['options'] as $option_key => $option_value ) {
			$options[] = sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option_key ),
				selected( get_option( $field['id'], $field['std'] ), $option_key, false ),
				esc_html( $option_value )
			);
		}
		?>
		<select class="kesso-control kesso-select" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['id'] ); ?>">
			<?php echo implode( '', $options ); ?>
		</select>
		<?php
	}

	/**
	 * Render text field
	 *
	 * @param array $field
	 */
	public function render_text_field( $field ) {
		$classes = ! empty( $field['class'] ) ? $field['class'] : '';
		$classes = 'kesso-control ' . $classes;
		$classes = trim( $classes );
		?>
		<input type="text" class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>"
			   name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( get_option( $field['id'], $field['std'] ) ); ?>"<?php echo ! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?> />
		<?php
	}

	/**
	 * Display settings page
	 */
	public function display_settings_page() {
		$screen    = get_current_screen();
		$screen_id = $screen->id;
		if ( false !== strpos( $screen_id, 'toolbar' ) ) {
			$screen_id = self::TOOLBAR_PAGE;
		}

		// Use SETTINGS_PAGE for settings_fields since all fields are registered with that page
		$settings_page = self::SETTINGS_PAGE;

		// Group fields by section
		$grouped_fields = $this->group_fields_by_section();
		?>
		<!-- Kesso Banner -->
		<div class="kesso-banner">
			<div class="kesso-banner-content">
				<?php echo esc_html__( 'This plugin was developed and distributed with ❤️ for free use by', 'kesso-widget' ); ?> 
				<a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-banner-link">kesso.io</a>
			</div>
		</div>
		<div class="wrap">
			<div class="kesso-access-app">
				<main class="kesso-main">
					<div class="kesso-page-heading">
						<div class="kesso-heading-left">
							<h1 class="kesso-title"><?php echo esc_html__( 'Accessibility Widget', 'kesso-widget' ); ?></h1>
							<p class="kesso-subtitle"><?php echo esc_html__( 'Configure the toolbar, button labels, and widget appearance.', 'kesso-widget' ); ?></p>
						</div>
					</div>

					<form method="post" action="options.php" id="kesso-settings-form">
						<?php settings_fields( $settings_page ); ?>
						<?php settings_errors( $settings_page ); ?>

						<div class="kesso-sections">
						<?php
						// Ensure correct order: General Settings, Toolbar Button Texts, Widget Styling
						$ordered_sections = [];
						$section_order = [
							__( 'General Settings', 'kesso-widget' ),
							__( 'Toolbar Button Texts', 'kesso-widget' ),
							__( 'Widget Styling', 'kesso-widget' ),
						];
						foreach ( $section_order as $section_name ) {
							if ( isset( $grouped_fields[ $section_name ] ) ) {
								$ordered_sections[ $section_name ] = $grouped_fields[ $section_name ];
							}
						}
						// Add any other sections that weren't in the order list
						foreach ( $grouped_fields as $section_title => $fields ) {
							if ( ! isset( $ordered_sections[ $section_title ] ) ) {
								$ordered_sections[ $section_title ] = $fields;
							}
						}

						// Build feature toggles for the Toolbar card (checkbox grid)
						$all_fields_by_id = array();
						foreach ( $this->get_settings_sections() as $sec ) {
							if ( empty( $sec['fields'] ) ) {
								continue;
							}
							foreach ( $sec['fields'] as $f ) {
								if ( ! empty( $f['id'] ) ) {
									$all_fields_by_id[ $f['id'] ] = $f;
								}
							}
						}

						$feature_toggle_fields = array(); // enable/disable selects -> checkbox toggles (toolbar)
						$general_toggle_fields = array(); // enable/disable selects -> checkbox toggles (general settings)
						$general_toggle_field_ids = array(
							'kesso_skip_to_content_link',
							'kesso_focusable',
							'kesso_remove_link_target',
							'kesso_add_role_links',
							'kesso_save',
						);
						
						foreach ( $all_fields_by_id as $fid => $f ) {
							$type = $f['type'] ?? '';
							if ( self::FIELD_SELECT !== $type ) {
								continue;
							}
							$options = $f['options'] ?? array();
							if ( is_array( $options ) && isset( $options['enable'], $options['disable'] ) && 2 === count( $options ) ) {
								// Check if it's a general settings toggle field
								if ( in_array( $fid, $general_toggle_field_ids, true ) ) {
									$general_toggle_fields[ $fid ] = $f;
								}
								// Check if it's a toolbar button field
								elseif ( 0 === strpos( $fid, 'kesso_toolbar_button_' ) ) {
									$feature_toggle_fields[ $fid ] = $f;
								}
							}
						}

						$feature_copy_fields = array();
						$feature_copy_ids    = array();
						foreach ( $feature_toggle_fields as $toggle_id => $f_toggle ) {
							foreach ( $all_fields_by_id as $fid => $f ) {
								if ( ( $f['type'] ?? '' ) !== self::FIELD_TEXT ) {
									continue;
								}
								// Copy fields for feature: e.g. kesso_toolbar_button_grayscale_title, resize_font_add_title, etc.
								if ( 0 === strpos( $fid, $toggle_id . '_' ) ) {
									$feature_copy_fields[]       = $f;
									$feature_copy_ids[ $fid ] = true;
								}
							}
						}

						// Move 'Toolbar Title' field to Feature Copy section
						$toolbar_title_field = null;
						$general_fields = $ordered_sections[ __( 'General Settings', 'kesso-widget' ) ] ?? array();
						$general_fields_filtered = array();
						$toggle_field_ids = array_keys( $feature_toggle_fields );
						$general_toggle_field_ids_list = array_keys( $general_toggle_fields );
						
						foreach ( $general_fields as $field ) {
							$field_id = $field['id'] ?? '';
							// Skip toolbar title - move it to Feature Copy
							if ( 'kesso_toolbar_title' === $field_id ) {
								$toolbar_title_field = $field;
								continue;
							}
							// Skip enable/disable toggle fields (they're now checkboxes in Toolbar section)
							if ( in_array( $field_id, $toggle_field_ids, true ) ) {
								continue;
							}
							// Skip general settings toggle fields (they're now checkboxes in General Features section)
							if ( in_array( $field_id, $general_toggle_field_ids_list, true ) ) {
								continue;
							}
							$general_fields_filtered[] = $field;
						}
						
						// Add toolbar title to feature copy fields (at the beginning)
						if ( $toolbar_title_field ) {
							array_unshift( $feature_copy_fields, $toolbar_title_field );
						}

						$toolbar_text_fields = $ordered_sections[ __( 'Toolbar Button Texts', 'kesso-widget' ) ] ?? array();
						$toolbar_text_fields = array_values(
							array_filter(
								$toolbar_text_fields,
								function( $f ) use ( $feature_copy_ids, $toggle_field_ids ) {
									$field_id = $f['id'] ?? '';
									// Exclude feature copy fields
									if ( ! empty( $feature_copy_ids[ $field_id ] ) ) {
										return false;
									}
									// Exclude enable/disable toggle fields (they're in the checkbox grid)
									if ( in_array( $field_id, $toggle_field_ids, true ) ) {
										return false;
									}
									return true;
								}
							)
						);
						?>

						<?php
						// 1) Toolbar: feature checkbox grid + copy fields for each feature
						?>
						<section class="kesso-card" id="kesso-toolbar-card">
							<div class="kesso-card-header">
								<h2 class="kesso-card-title">
									<span class="material-symbols-outlined kesso-icon" aria-hidden="true">tune</span>
									<?php echo esc_html__( 'Toolbar', 'kesso-widget' ); ?>
								</h2>
							</div>
							<div class="kesso-card-body">
								<div class="kesso-access-feature-grid">
									<?php foreach ( $feature_toggle_fields as $toggle_id => $field ) : ?>
										<?php
										$current = get_option( $toggle_id, $field['std'] ?? 'enable' );
										$checked = ( 'enable' === $current );
										$title   = wp_strip_all_tags( (string) ( $field['title'] ?? $toggle_id ) );
										?>
										<div class="kesso-access-feature-card">
											<input type="hidden" name="<?php echo esc_attr( $toggle_id ); ?>" value="disable" />
											<label class="kesso-access-feature-label" for="<?php echo esc_attr( $toggle_id ); ?>__toggle">
												<input type="checkbox"
												       id="<?php echo esc_attr( $toggle_id ); ?>__toggle"
												       class="kesso-access-feature-checkbox"
												       name="<?php echo esc_attr( $toggle_id ); ?>"
												       value="enable"
													<?php checked( $checked ); ?> />
												<span class="kesso-access-feature-text">
													<span class="kesso-access-feature-name"><?php echo esc_html( $title ); ?></span>
													<span class="kesso-access-feature-desc"><?php echo esc_html__( 'Enable or disable this feature in the toolbar.', 'kesso-widget' ); ?></span>
												</span>
											</label>
										</div>
									<?php endforeach; ?>
								</div>

								<?php if ( ! empty( $feature_copy_fields ) ) : ?>
								<details class="kesso-collapsible-container" style="margin-top: 24px;">
									<summary class="kesso-collapsible-summary">
										<span class="material-symbols-outlined kesso-icon" aria-hidden="true">edit</span>
										<?php echo esc_html__( 'Toolbar Copy', 'kesso-widget' ); ?>
										<span class="material-symbols-outlined kesso-collapsible-icon" aria-hidden="true">expand_more</span>
									</summary>
									<div class="kesso-collapsible-content" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--kesso-border, #e2e8f0);">
										<div class="kesso-grid kesso-grid-2">
											<?php foreach ( $feature_copy_fields as $field ) : ?>
												<label class="kesso-field">
													<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
													<?php
													// Render the field input directly with kesso-control class
													$field_type = $field['type'] ?? self::FIELD_TEXT;
													if ( self::FIELD_TEXT === $field_type ) {
														$value = get_option( $field['id'], $field['std'] ?? '' );
														$classes = ! empty( $field['class'] ) ? $field['class'] : '';
														?>
														<input type="text" class="kesso-control <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>"
															   name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo ! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?> />
														<?php
													} else {
														$this->add_settings_field( $field );
													}
													?>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								</details>
								<?php endif; ?>
							</div>
						</section>

						<?php
						// General Features: checkbox grid for general settings toggle fields
						?>
						<?php if ( ! empty( $general_toggle_fields ) ) : ?>
						<section class="kesso-card" id="kesso-general-features-card">
							<div class="kesso-card-header">
								<h2 class="kesso-card-title">
									<span class="material-symbols-outlined kesso-icon" aria-hidden="true">settings</span>
									<?php echo esc_html__( 'General Features', 'kesso-widget' ); ?>
								</h2>
							</div>
							<div class="kesso-card-body kesso-card-body--stack">
								<div class="kesso-access-feature-grid">
									<?php foreach ( $general_toggle_fields as $toggle_id => $field ) : ?>
										<?php
										$current = get_option( $toggle_id, $field['std'] ?? 'enable' );
										$checked = ( 'enable' === $current );
										$title   = wp_strip_all_tags( (string) ( $field['title'] ?? $toggle_id ) );
										?>
										<div class="kesso-access-feature-card">
											<input type="hidden" name="<?php echo esc_attr( $toggle_id ); ?>" value="disable" />
											<label class="kesso-access-feature-label" for="<?php echo esc_attr( $toggle_id ); ?>__toggle">
												<input type="checkbox"
												       id="<?php echo esc_attr( $toggle_id ); ?>__toggle"
												       class="kesso-access-feature-checkbox"
												       name="<?php echo esc_attr( $toggle_id ); ?>"
												       value="enable"
													<?php checked( $checked ); ?> />
												<span class="kesso-access-feature-text">
													<span class="kesso-access-feature-name"><?php echo esc_html( $title ); ?></span>
													<span class="kesso-access-feature-desc"><?php echo esc_html__( 'Enable or disable this feature.', 'kesso-widget' ); ?></span>
												</span>
											</label>
										</div>
									<?php endforeach; ?>
								</div>

								<?php if ( ! empty( $general_fields_filtered ) ) : ?>
								<div class="kesso-grid kesso-grid-3">
									<?php foreach ( $general_fields_filtered as $field ) : ?>
										<?php if ( 'html' === ( $field['type'] ?? '' ) ) : ?>
											<div class="kesso-access-fullwidth"><?php echo wp_kses_post( $field['title'] ); ?></div>
											<?php continue; ?>
										<?php endif; ?>
										<?php
										$field_type = $field['type'] ?? self::FIELD_TEXT;
										$is_color_field = ( 'color' === $field_type );
										?>
										<?php if ( $is_color_field ) : ?>
											<div class="kesso-field">
												<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
												<?php $this->render_color_field( $field ); ?>
											</div>
										<?php else : ?>
											<label class="kesso-field">
												<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
												<?php
												// Render field with kesso-control class
												if ( self::FIELD_TEXT === $field_type ) {
													$value = get_option( $field['id'], $field['std'] ?? '' );
													$classes = ! empty( $field['class'] ) ? $field['class'] : '';
													?>
													<input type="text" class="kesso-control <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>"
														   name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo ! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?> />
													<?php
												} elseif ( self::FIELD_SELECT === $field_type ) {
													$options = [];
													foreach ( $field['options'] as $option_key => $option_value ) {
														$options[] = sprintf(
															'<option value="%1$s"%2$s>%3$s</option>',
															esc_attr( $option_key ),
															selected( get_option( $field['id'], $field['std'] ?? '' ), $option_key, false ),
															esc_html( $option_value )
														);
													}
													?>
													<select class="kesso-control kesso-select" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['id'] ); ?>">
														<?php echo implode( '', $options ); ?>
													</select>
													<?php
												} else {
													$this->add_settings_field( $field );
												}
												?>
											</label>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
								<?php endif; ?>
							</div>
						</section>
						<?php endif; ?>

						<?php
						// 3) Toolbar Button Texts (keep as is, minus the copy fields shown above)
						?>
						<section class="kesso-card">
							<div class="kesso-card-header">
								<h2 class="kesso-card-title">
									<span class="material-symbols-outlined kesso-icon" aria-hidden="true">link</span>
									<?php echo esc_html__( 'Useful Links', 'kesso-widget' ); ?>
								</h2>
							</div>
							<div class="kesso-card-body kesso-card-body--stack">
								<div class="kesso-grid kesso-grid-2">
									<?php foreach ( $toolbar_text_fields as $field ) : ?>
										<?php if ( 'html' === ( $field['type'] ?? '' ) ) : ?>
											<div class="kesso-access-fullwidth"><?php echo wp_kses_post( $field['title'] ); ?></div>
											<?php continue; ?>
										<?php endif; ?>
										<label class="kesso-field">
											<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
											<?php
											// Render the field input directly with kesso-control class
											$field_type = $field['type'] ?? self::FIELD_TEXT;
											if ( self::FIELD_TEXT === $field_type ) {
												$value = get_option( $field['id'], $field['std'] ?? '' );
												$classes = ! empty( $field['class'] ) ? $field['class'] : '';
												?>
												<input type="text" class="kesso-control <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>"
													   name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo ! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?> />
												<?php
											} else {
												$this->add_settings_field( $field );
											}
											?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</section>

						<?php
						// 4) Styling (keep, with improved color inputs)
						$styling_fields = $ordered_sections[ __( 'Widget Styling', 'kesso-widget' ) ] ?? array();
						?>
						<section class="kesso-card">
							<div class="kesso-card-header">
								<h2 class="kesso-card-title">
									<span class="material-symbols-outlined kesso-icon" aria-hidden="true">palette</span>
									<?php echo esc_html__( 'Widget Styling', 'kesso-widget' ); ?>
								</h2>
							</div>
							<div class="kesso-card-body kesso-card-body--stack">
								<div class="kesso-grid kesso-grid-3">
									<?php foreach ( $styling_fields as $field ) : ?>
										<?php if ( 'html' === ( $field['type'] ?? '' ) ) : ?>
											<div class="kesso-access-fullwidth"><?php echo wp_kses_post( $field['title'] ); ?></div>
											<?php continue; ?>
										<?php endif; ?>
										<?php
										$field_type = $field['type'] ?? self::FIELD_TEXT;
										$is_color_field = ( 'color' === $field_type );
										?>
										<?php if ( $is_color_field ) : ?>
											<div class="kesso-field">
												<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
												<?php $this->render_color_field( $field ); ?>
											</div>
										<?php else : ?>
											<label class="kesso-field">
												<span class="kesso-label"><?php echo esc_html( $field['title'] ); ?></span>
												<?php
												// Render field with kesso-control class
												if ( self::FIELD_TEXT === $field_type ) {
													$value = get_option( $field['id'], $field['std'] ?? '' );
													$classes = ! empty( $field['class'] ) ? $field['class'] : '';
													?>
													<input type="text" class="kesso-control <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $field['id'] ); ?>"
														   name="<?php echo esc_attr( $field['id'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo ! empty( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?> />
													<?php
												} elseif ( self::FIELD_SELECT === $field_type ) {
													$options = [];
													foreach ( $field['options'] as $option_key => $option_value ) {
														$options[] = sprintf(
															'<option value="%1$s"%2$s>%3$s</option>',
															esc_attr( $option_key ),
															selected( get_option( $field['id'], $field['std'] ?? '' ), $option_key, false ),
															esc_html( $option_value )
														);
													}
													?>
													<select class="kesso-control kesso-select" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['id'] ); ?>">
														<?php echo implode( '', $options ); ?>
													</select>
													<?php
												} else {
													$this->add_settings_field( $field );
												}
												?>
											</label>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
							</div>
						</section>
					</div>
				</form>

				<footer class="kesso-footer" role="contentinfo">
					<div class="kesso-footer-inner">
						<div class="kesso-footer-left">
							<div class="kesso-footer-status">
								<span class="kesso-footer-dot" aria-hidden="true"></span>
								<span class="kesso-footer-label"><?php echo esc_html__( 'Ready to Save', 'kesso-widget' ); ?></span>
							</div>
							<p class="kesso-footer-subtitle"><?php echo esc_html__( 'Review your changes and save when ready.', 'kesso-widget' ); ?></p>
						</div>

						<div class="kesso-footer-right">
							<button type="button" class="kesso-btn kesso-btn--ghost" id="kesso-reset-button"><?php echo esc_html__( 'Reset to Defaults', 'kesso-widget' ); ?></button>
							<button type="submit" form="kesso-settings-form" class="kesso-btn kesso-btn--primary"><?php echo esc_html__( 'Save Changes', 'kesso-widget' ); ?></button>
						</div>
					</div>
				</footer>
			</main>
			</div>
		</div>
		<?php
	}

	/**
	 * Print JavaScript for settings page
	 */
	public function print_js() {
		$screen = get_current_screen();
		if ( ! $screen || ( self::SETTINGS_PAGE !== $screen->id && self::TOOLBAR_PAGE !== $screen->id ) ) {
			return;
		}
		?>
		<script>
			jQuery( document ).ready( function ( $ ) {
				var $kessoToolbarOption = $( '#kesso_toolbar' ),
					$kessoToolbarCard = $( '#kesso-toolbar-card' );

				$kessoToolbarOption.on( 'change', function () {
					if ( 'disable' !== $( this ).val() ) {
						$kessoToolbarCard.fadeIn( 'fast' );
					} else {
						$kessoToolbarCard.hide();
					}
				} );
				$kessoToolbarOption.trigger( 'change' );

				
				// Handle reset button
				$( '#kesso-reset-button' ).on( 'click', function ( e ) {
					e.preventDefault();
					if ( confirm( '<?php echo esc_js( __( 'Are you sure you want to reset all settings to their default values?', 'kesso-widget' ) ); ?>' ) ) {
						// Get all default values from the form
						var $form = $( '#kesso-settings-form' );
						var defaults = {};
						
						// Set defaults for all fields
						<?php
						$sections = $this->get_settings_sections();
						foreach ( $sections as $section ) {
							foreach ( $section['fields'] as $field ) {
								if ( isset( $field['std'] ) && ! empty( $field['std'] ) ) {
									$field_id = esc_js( $field['id'] );
									$default_value = esc_js( $field['std'] );
									echo "defaults['{$field_id}'] = '{$default_value}';\n";
								}
							}
						}
						?>
						
						// Apply defaults to form fields
						$.each( defaults, function ( fieldId, defaultValue ) {
							var $field = $( '#' + fieldId );
							if ( $field.length ) {
								if ( $field.is( 'select' ) ) {
									$field.val( defaultValue );
								} else if ( $field.hasClass( 'kesso-color-picker-input' ) ) {
									$field.val( defaultValue );
									// Update color picker if function exists
									if ( typeof window.kessoUpdateColorPicker === 'function' ) {
										window.kessoUpdateColorPicker( fieldId, defaultValue );
									}
								} else {
									$field.val( defaultValue );
								}
							}
						} );
						
						// Submit the form to save
						$form.submit();
					}
				} );

			} );
		</script>
		<?php
	}

	/**
	 * Sanitize field HTML
	 *
	 * @param string $input
	 * @return string
	 */
	public static function field_html( $input ) {
		return stripslashes( wp_filter_post_kses( addslashes( $input ) ) );
	}

	/**
	 * Sanitize toolbar display
	 *
	 * @param string $input
	 * @return string
	 */
	public function sanitize_toolbar_display( $input ) {
		if ( empty( $input ) ) {
			return $input;
		}

		return in_array( $input, [ 'enable', 'visible-desktop', 'visible-tablet', 'visible-phone', 'hidden-desktop', 'hidden-tablet', 'hidden-phone', 'disable' ], true ) ? $input : 'enable';
	}

	/**
	 * Sanitize enabled/disabled
	 *
	 * @param string $input
	 * @return string
	 */
	public function sanitize_enabled_disabled( $input ) {
		if ( empty( $input ) ) {
			return '';
		}

		return in_array( $input, [ 'enable', 'disable' ], true ) ? $input : 'enable';
	}

	/**
	 * Sanitize expiration
	 *
	 * @param string $input
	 * @return string
	 */
	public function sanitize_expiration( $input ) {
		if ( empty( $input ) ) {
			return '12';
		}

		return in_array( $input, [ '1', '6', '12', '24', '48', '72', '168', '720' ], true ) ? $input : '12';
	}

	/**
	 * Sanitize color (accepts both hex and RGBA)
	 *
	 * @param string $input
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

		// Check if it's a valid RGBA color
		if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d.]+\s*)?\)$/', $input ) ) {
			// Validate and normalize RGBA values
			$matches = [];
			if ( preg_match( '/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/', $input, $matches ) ) {
				$r = max( 0, min( 255, (int) $matches[1] ) );
				$g = max( 0, min( 255, (int) $matches[2] ) );
				$b = max( 0, min( 255, (int) $matches[3] ) );
				$a = isset( $matches[4] ) ? max( 0, min( 1, (float) $matches[4] ) ) : 1;

				// Round alpha to 2 decimal places
				$a = round( $a, 2 );

				// Preserve RGBA format (even if alpha is 1, keep as RGBA if that's what was submitted)
				return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $a );
			}
		}

		// If invalid, return empty string
		return '';
	}

	// Add new field renderer for HTML and color fields
	public function render_html_field( $field ) {
		echo wp_kses_post( $field['title'] );
	}

	public function render_color_field( $field ) {
		$value = get_option( $field['id'], $field['std'] );
		?>
		<div class="kesso-color-picker-wrapper" data-field-id="<?php echo esc_attr( $field['id'] ); ?>">
			<input type="hidden"
			       class="kesso-color-picker-input"
			       id="<?php echo esc_attr( $field['id'] ); ?>"
			       name="<?php echo esc_attr( $field['id'] ); ?>"
			       value="<?php echo esc_attr( $value ); ?>" />
			<div class="kesso-color-picker">
				<div class="kesso-color-picker-preview" style="background-color: <?php echo esc_attr( $value ); ?>;"></div>
				<button type="button" class="kesso-color-picker-toggle">Choose Color</button>
			</div>
			<div class="kesso-color-picker-dropdown" style="display: none;">
				<div class="kesso-color-picker-spectrum">
					<canvas class="kesso-color-picker-canvas" width="200" height="200"></canvas>
					<div class="kesso-color-picker-pointer"></div>
				</div>
				<div class="kesso-color-picker-controls">
					<div class="kesso-color-picker-slider-wrapper">
						<label>Hue</label>
						<div class="kesso-color-picker-slider kesso-color-picker-hue">
							<canvas class="kesso-color-picker-slider-canvas" width="200" height="20"></canvas>
							<div class="kesso-color-picker-slider-thumb"></div>
						</div>
					</div>
					<div class="kesso-color-picker-slider-wrapper">
						<label>Opacity</label>
						<div class="kesso-color-picker-slider kesso-color-picker-alpha">
							<canvas class="kesso-color-picker-slider-canvas" width="200" height="20"></canvas>
							<div class="kesso-color-picker-slider-thumb"></div>
						</div>
					</div>
				</div>
				<div class="kesso-color-picker-inputs">
					<input type="text" class="kesso-color-picker-hex" placeholder="#137fec" />
					<input type="text" class="kesso-color-picker-rgba" placeholder="rgba(19, 127, 236, 1)" />
				</div>
			</div>
		</div>
		<?php
	}

	private function group_fields_by_section() {
		$sections = $this->get_settings_sections();
		$grouped = [];

		foreach ( $sections as $section ) {
			$current_section = '';
			$section_fields = [];
			$toolbar_setup_fields = [];
			$toolbar_text_fields = [];
			$in_toolbar_buttons = false;

			foreach ( $section['fields'] as $field ) {
				// Check if this is a heading
				if ( 'html' === $field['type'] && false !== strpos( $field['title'], 'class="' ) ) {
					// Extract heading text
					if ( preg_match( '/<h3[^>]*>(.*?)<\/h3>/', $field['title'], $matches ) ) {
						$heading_text = strip_tags( $matches[1] );
						
						// Before processing heading, handle any fields that came before it (like toolbar_title)
						if ( empty( $current_section ) && ! empty( $section_fields ) ) {
							// These are fields before any heading - add them to General Settings
							if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
								$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
							}
							// Ensure toolbar_title is first
							$title_field = null;
							$other_fields = [];
							foreach ( $section_fields as $pre_field ) {
								if ( 'kesso_toolbar_title' === $pre_field['id'] ) {
									$title_field = $pre_field;
								} else {
									$other_fields[] = $pre_field;
								}
							}
							$ordered_fields = $title_field ? [ $title_field ] : [];
							$ordered_fields = array_merge( $ordered_fields, $other_fields );
							$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $ordered_fields );
							$section_fields = [];
						}
						
						// If we were in Toolbar Buttons section, split the fields
						if ( 'Toolbar Buttons' === $current_section && ! empty( $section_fields ) ) {
							// Split toolbar fields into setup and text fields
							foreach ( $section_fields as $toolbar_field ) {
								$field_id = $toolbar_field['id'] ?? '';
								// Setup fields: Display Toolbar, Toolbar Title, and all enable/disable selects
								if ( in_array( $field_id, [ 'kesso_toolbar', 'kesso_toolbar_title' ], true ) ||
								     ( false !== strpos( $field_id, 'kesso_toolbar_button_' ) && 
								       false === strpos( $field_id, '_title' ) && 
								       false === strpos( $field_id, '_link' ) ) ) {
									$toolbar_setup_fields[] = $toolbar_field;
								}
								// Text fields: all title and link fields
								elseif ( false !== strpos( $field_id, '_title' ) || false !== strpos( $field_id, '_link' ) ) {
									$toolbar_text_fields[] = $toolbar_field;
								}
							}
							
							// Merge Toolbar Setup fields into General Settings
							if ( ! empty( $toolbar_setup_fields ) ) {
								if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
									$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
								}
								$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $toolbar_setup_fields );
							}
							if ( ! empty( $toolbar_text_fields ) ) {
								$grouped[ __( 'Toolbar Button Texts', 'kesso-widget' ) ] = $toolbar_text_fields;
							}
							
							$section_fields = [];
							$toolbar_setup_fields = [];
							$toolbar_text_fields = [];
						} else {
							// Save previous section
							if ( ! empty( $current_section ) && ! empty( $section_fields ) ) {
								// If it's General Settings, merge with any existing General Settings
								if ( __( 'General Settings', 'kesso-widget' ) === $current_section ) {
									if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
										$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
									}
									$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $section_fields );
								} else {
									$grouped[ $current_section ] = $section_fields;
								}
							}
							$section_fields = [];
						}
						
						$current_section = $heading_text;
						$in_toolbar_buttons = ( 'Toolbar Buttons' === $current_section );
					}
				} else {
					$section_fields[] = $field;
				}
			}

			// Handle remaining fields in Toolbar Buttons section
			if ( 'Toolbar Buttons' === $current_section && ! empty( $section_fields ) ) {
				foreach ( $section_fields as $toolbar_field ) {
					$field_id = $toolbar_field['id'] ?? '';
					if ( in_array( $field_id, [ 'kesso_toolbar', 'kesso_toolbar_title' ], true ) ||
					     ( false !== strpos( $field_id, 'kesso_toolbar_button_' ) && 
					       false === strpos( $field_id, '_title' ) && 
					       false === strpos( $field_id, '_link' ) ) ) {
						$toolbar_setup_fields[] = $toolbar_field;
					} elseif ( false !== strpos( $field_id, '_title' ) || false !== strpos( $field_id, '_link' ) ) {
						$toolbar_text_fields[] = $toolbar_field;
					}
				}
				
				// Merge Toolbar Setup fields into General Settings
				if ( ! empty( $toolbar_setup_fields ) ) {
					if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
						$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
					}
					// Ensure toolbar_title is first
					$title_field = null;
					$other_fields = [];
					foreach ( $toolbar_setup_fields as $field ) {
						if ( 'kesso_toolbar_title' === $field['id'] ) {
							$title_field = $field;
						} else {
							$other_fields[] = $field;
						}
					}
					$ordered_fields = $title_field ? [ $title_field ] : [];
					$ordered_fields = array_merge( $ordered_fields, $other_fields );
					$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $ordered_fields );
				}
				if ( ! empty( $toolbar_text_fields ) ) {
					$grouped[ __( 'Toolbar Button Texts', 'kesso-widget' ) ] = $toolbar_text_fields;
				}
			} elseif ( ! empty( $current_section ) && ! empty( $section_fields ) ) {
				// If it's General Settings, merge with any existing General Settings
				if ( __( 'General Settings', 'kesso-widget' ) === $current_section ) {
					if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
						$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
					}
					// Ensure toolbar_title is first
					$title_field = null;
					$other_fields = [];
					foreach ( $section_fields as $field ) {
						if ( 'kesso_toolbar_title' === $field['id'] ) {
							$title_field = $field;
						} else {
							$other_fields[] = $field;
						}
					}
					$ordered_fields = $title_field ? [ $title_field ] : [];
					$ordered_fields = array_merge( $ordered_fields, $other_fields );
					$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $ordered_fields );
				} else {
					$grouped[ $current_section ] = $section_fields;
				}
			} elseif ( ! empty( $section['title'] ) ) {
				// Fallback: use section title
				$grouped[ $section['title'] ] = $section['fields'];
			}
			
			// Handle any remaining fields that didn't get processed (fields before any heading)
			if ( empty( $current_section ) && ! empty( $section_fields ) ) {
				if ( ! isset( $grouped[ __( 'General Settings', 'kesso-widget' ) ] ) ) {
					$grouped[ __( 'General Settings', 'kesso-widget' ) ] = [];
				}
				// Ensure toolbar_title is first
				$title_field = null;
				$other_fields = [];
				foreach ( $section_fields as $pre_field ) {
					if ( 'kesso_toolbar_title' === $pre_field['id'] ) {
						$title_field = $pre_field;
					} else {
						$other_fields[] = $pre_field;
					}
				}
				$ordered_fields = $title_field ? [ $title_field ] : [];
				$ordered_fields = array_merge( $ordered_fields, $other_fields );
				$grouped[ __( 'General Settings', 'kesso-widget' ) ] = array_merge( $grouped[ __( 'General Settings', 'kesso-widget' ) ], $ordered_fields );
			}
		}

		return $grouped;
	}
}

