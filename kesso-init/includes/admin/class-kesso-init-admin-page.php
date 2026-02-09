<?php
/**
 * Admin page handler
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin menu registration and page rendering
 */
class Kesso_Init_Admin_Page {

    /**
     * Page hook suffix
     *
     * @var string
     */
    private $hook_suffix;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu_page' ), 19 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the admin menu page
     */
    public function register_menu_page() {
        // Get the favicon URL from kesso-init plugin assets
        $icon_url = KESSO_INIT_URL . 'assets/img/kesso-favicon.png';
        
        $this->hook_suffix = add_menu_page(
            __( 'Kesso Init', 'kesso-init' ),
            '# ' . __( 'Initialization', 'kesso-init' ),
            'manage_options',
            'kesso-init',
            array( $this, 'render_page' ),
            $icon_url,
            99.1  // Position at bottom, first of the two
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
            #toplevel_page_kesso-init .wp-menu-image img {
                width: 18px;
                height: 20px;
                padding: 6px 0 0 0;
            }
        </style>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        // Only load on our page
        if ( $hook !== $this->hook_suffix ) {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Enqueue admin styles
        wp_enqueue_style(
            'kesso-init-admin',
            KESSO_INIT_URL . 'assets/css/admin.css',
            array(),
            KESSO_INIT_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'kesso-init-admin',
            KESSO_INIT_URL . 'assets/js/admin.js',
            array( 'wp-api-fetch' ),
            KESSO_INIT_VERSION,
            true
        );

        // Get plugin instance for data
        $kesso = Kesso_Init::get_instance();

        // Get plugins with status
        $plugins = $kesso->get_plugin_list();
        $plugins_with_status = array();
        foreach ( $plugins as $plugin ) {
            // Check by file path first
            $plugin['installed'] = kesso_init_is_plugin_installed( $plugin['file'] );
            $plugin['active']    = kesso_init_is_plugin_active( $plugin['file'] );
            
            // If not found by file, try to find by slug (for cases like Google Site Kit)
            if ( ! $plugin['installed'] && ! empty( $plugin['slug'] ) ) {
                $found_file = $this->find_plugin_by_slug( $plugin['slug'] );
                if ( $found_file ) {
                    $plugin['installed'] = kesso_init_is_plugin_installed( $found_file );
                    $plugin['active']    = kesso_init_is_plugin_active( $found_file );
                    $plugin['file']      = $found_file; // Update to actual file path
                }
            }
            
            $plugins_with_status[] = $plugin;
        }

        // Get Kesso plugins with status
        $kesso_plugins = $kesso->get_kesso_plugin_list();
        $kesso_plugins_with_status = array();
        foreach ( $kesso_plugins as $plugin ) {
            // Check by file path first
            $plugin['installed'] = kesso_init_is_plugin_installed( $plugin['file'] );
            $plugin['active']    = kesso_init_is_plugin_active( $plugin['file'] );
            
            // If not found by file, try to find by slug
            if ( ! $plugin['installed'] && ! empty( $plugin['slug'] ) ) {
                $found_file = $this->find_plugin_by_slug( $plugin['slug'] );
                if ( $found_file ) {
                    $plugin['installed'] = kesso_init_is_plugin_installed( $found_file );
                    $plugin['active']    = kesso_init_is_plugin_active( $found_file );
                    $plugin['file']      = $found_file; // Update to actual file path
                }
            }
            
            $kesso_plugins_with_status[] = $plugin;
        }

        // Localize script with data
        wp_localize_script(
            'kesso-init-admin',
            'kessoInit',
            array(
                'restUrl'      => rest_url( 'kesso/v1/' ),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'plugins'      => $plugins_with_status,
                'kessoPlugins' => $kesso_plugins_with_status,
                'languages'    => kesso_init_get_available_languages(),
                'timezones'    => $kesso->get_timezone_list(),
                'dateFormats'  => $kesso->get_date_formats(),
                'timeFormats'  => $kesso->get_time_formats(),
                'weekDays'     => $kesso->get_week_days(),
                'currentSettings' => $this->get_current_settings(),
                'themeStatus'  => $this->get_theme_status(),
                'strings'      => array(
                    'applying'           => __( 'Applying changes...', 'kesso-init' ),
                    'installingPlugins'  => __( 'Installing plugins...', 'kesso-init' ),
                    'installingTheme'    => __( 'Installing theme...', 'kesso-init' ),
                    'creatingChildTheme' => __( 'Creating child theme...', 'kesso-init' ),
                    'updatingSettings'   => __( 'Updating settings...', 'kesso-init' ),
                    'complete'           => __( 'Setup complete!', 'kesso-init' ),
                    'error'              => __( 'An error occurred', 'kesso-init' ),
                    'selectFavicon'      => __( 'Select Site Icon', 'kesso-init' ),
                    'useFavicon'         => __( 'Use as Site Icon', 'kesso-init' ),
                    'uploadBricks'       => __( 'Please upload the Bricks theme ZIP file.', 'kesso-init' ),
                ),
            )
        );
    }

    /**
     * Get current WordPress settings
     *
     * @return array
     */
    private function get_current_settings() {
        $site_icon_id = get_option( 'site_icon', 0 );
        $site_icon_url = '';
        
        if ( $site_icon_id ) {
            $site_icon_url = wp_get_attachment_image_url( $site_icon_id, 'thumbnail' );
        }
        
        return array(
            'blogname'        => get_option( 'blogname', '' ),
            'blogdescription' => get_option( 'blogdescription', '' ),
            'WPLANG'          => get_option( 'WPLANG', '' ),
            'timezone_string' => get_option( 'timezone_string', 'UTC' ),
            'date_format'     => get_option( 'date_format', 'F j, Y' ),
            'time_format'     => get_option( 'time_format', 'g:i a' ),
            'start_of_week'   => get_option( 'start_of_week', 0 ),
            'site_icon'       => $site_icon_id,
            'site_icon_url'   => $site_icon_url,
        );
    }

    /**
     * Get current theme status
     *
     * @return array
     */
    private function get_theme_status() {
        $active_theme = get_stylesheet();
        $parent_theme = get_template();
        $is_child_theme = ( $active_theme !== $parent_theme );
        
        // Check if Elementor plugin is installed
        $elementor_installed = kesso_init_is_plugin_installed( 'elementor/elementor.php' );
        $elementor_active = kesso_init_is_plugin_active( 'elementor/elementor.php' );
        
        // Check if Bricks theme is installed
        $bricks_installed = kesso_init_is_theme_installed( 'bricks' );
        $bricks_active = ( $active_theme === 'bricks' || $parent_theme === 'bricks' );
        
        // Check if Hello Elementor theme is installed
        $hello_elementor_installed = kesso_init_is_theme_installed( 'hello-elementor' );
        $hello_elementor_active = ( $active_theme === 'hello-elementor' || $parent_theme === 'hello-elementor' );
        
        // Determine which builder is active
        $active_builder = '';
        if ( $bricks_active ) {
            $active_builder = 'bricks';
        } elseif ( $elementor_active && ( $hello_elementor_active || $is_child_theme && $parent_theme === 'hello-elementor' ) ) {
            $active_builder = 'elementor';
        }
        
        return array(
            'active_theme'        => $active_theme,
            'parent_theme'        => $parent_theme,
            'is_child_theme'       => $is_child_theme,
            'elementor_installed' => $elementor_installed,
            'elementor_active'    => $elementor_active,
            'bricks_installed'    => $bricks_installed,
            'bricks_active'       => $bricks_active,
            'hello_elementor_installed' => $hello_elementor_installed,
            'hello_elementor_active'    => $hello_elementor_active,
            'active_builder'      => $active_builder,
        );
    }

    /**
     * Find plugin file by slug
     *
     * @param string $slug Plugin slug.
     * @return string|false Plugin file path or false if not found.
     */
    private function find_plugin_by_slug( $slug ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        // Search for plugin by slug in all plugin files
        foreach ( $all_plugins as $file => $plugin_data ) {
            // Check if the file path contains the slug
            if ( strpos( $file, $slug ) !== false ) {
                return $file;
            }
            
            // Also check plugin name/slug in plugin data
            $plugin_slug = dirname( $file );
            if ( $plugin_slug === $slug || strpos( $plugin_slug, $slug ) !== false ) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Render the admin page
     */
    public function render_page() {
        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'kesso-init' ) );
        }

        // Include the template
        include KESSO_INIT_PATH . 'templates/wizard-page.php';
    }
}

