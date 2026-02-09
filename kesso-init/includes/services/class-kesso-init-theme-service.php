<?php
/**
 * Theme Installation Service
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles theme installation, child theme creation, and activation
 */
class Kesso_Init_Theme_Service {

    /**
     * Install Elementor (plugin + Hello Elementor theme)
     *
     * @return array|WP_Error
     */
    public function install_elementor() {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        // Add timeout protection
        add_filter( 'http_request_timeout', array( $this, 'get_theme_download_timeout' ), 999 );
        add_filter( 'http_request_args', array( $this, 'set_theme_download_args' ), 999 );

        try {
            // 1. Install Elementor plugin
            $elementor_plugin_file = 'elementor/elementor.php';
            if ( ! kesso_init_is_plugin_installed( $elementor_plugin_file ) ) {
                $upgrader = new Plugin_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );
                $plugin_url = kesso_init_get_plugin_download_url( 'elementor' );
                $result = $upgrader->install( $plugin_url );

                if ( is_wp_error( $result ) || false === $result ) {
                    return new WP_Error( 'elementor_install_failed', __( 'Failed to install Elementor plugin.', 'kesso-init' ) );
                }

                // Activate Elementor
                activate_plugin( $elementor_plugin_file );
                
                // Enable auto-updates for Elementor
                $plugin_service = Kesso_Init::get_instance()->get_plugin_service();
                $plugin_service->enable_plugin_auto_update( 'elementor', $elementor_plugin_file );
            } else {
                // Activate if not active
                if ( ! kesso_init_is_plugin_active( $elementor_plugin_file ) ) {
                    activate_plugin( $elementor_plugin_file );
                }
                
                // Enable auto-updates for Elementor (even if already active)
                $plugin_service = Kesso_Init::get_instance()->get_plugin_service();
                $plugin_service->enable_plugin_auto_update( 'elementor', $elementor_plugin_file );
            }

            // 2. Install Hello Elementor theme
            $hello_elementor_slug = 'hello-elementor';
            if ( ! kesso_init_is_theme_installed( $hello_elementor_slug ) ) {
                $theme_upgrader = new Theme_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );
                $theme_url = 'https://downloads.wordpress.org/theme/hello-elementor.latest-stable.zip';
                $result = $theme_upgrader->install( $theme_url );

                if ( is_wp_error( $result ) || false === $result ) {
                    return new WP_Error( 'hello_install_failed', __( 'Failed to install Hello Elementor theme.', 'kesso-init' ) );
                }
            }

            // 3. Activate Hello Elementor theme
            switch_theme( $hello_elementor_slug );

            return array(
                'plugin'      => 'elementor',
                'theme'       => $hello_elementor_slug,
                'active_theme' => $hello_elementor_slug,
            );
        } catch ( Exception $e ) {
            return new WP_Error( 'elementor_install_exception', sprintf( __( 'Elementor installation exception: %s', 'kesso-init' ), $e->getMessage() ) );
        } finally {
            // Remove timeout filters
            remove_filter( 'http_request_timeout', array( $this, 'get_theme_download_timeout' ), 999 );
            remove_filter( 'http_request_args', array( $this, 'set_theme_download_args' ), 999 );
        }
    }

    /**
     * Get timeout for theme downloads
     *
     * @return int Timeout in seconds.
     */
    public function get_theme_download_timeout() {
        return 30; // 30 seconds per theme download
    }

    /**
     * Set theme download arguments with timeout
     *
     * @param array $args Request arguments.
     * @return array Modified arguments.
     */
    public function set_theme_download_args( $args ) {
        $args['timeout'] = 30;
        return $args;
    }

    /**
     * Install Bricks from remote URL
     *
     * @param string $url Remote ZIP URL.
     * @return array|WP_Error
     */
    public function install_bricks_from_url( $url ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        $upgrader = new Theme_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );
        $result = $upgrader->install( $url );

        if ( is_wp_error( $result ) || false === $result ) {
            return new WP_Error( 'bricks_install_failed', __( 'Failed to install Bricks theme from URL.', 'kesso-init' ) );
        }

        // Activate Bricks
        switch_theme( 'bricks' );

        return array(
            'theme'        => 'bricks',
            'active_theme' => 'bricks',
        );
    }

    /**
     * Install Bricks from uploaded file
     *
     * @param string $file_path Path to uploaded ZIP file.
     * @return array|WP_Error
     */
    public function install_bricks_from_upload( $file_path ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        // Check filesystem access
        $filesystem = $this->get_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return $filesystem;
        }

        $upgrader = new Theme_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );
        $result = $upgrader->install( $file_path );

        if ( is_wp_error( $result ) || false === $result ) {
            return new WP_Error( 'bricks_install_failed', __( 'Failed to install Bricks theme from uploaded file.', 'kesso-init' ) );
        }

        // Activate Bricks
        switch_theme( 'bricks' );

        return array(
            'theme'        => 'bricks',
            'active_theme' => 'bricks',
        );
    }

    /**
     * Create and install a child theme
     *
     * @param string $parent_theme_slug Parent theme slug.
     * @return array|WP_Error
     */
    public function create_and_install_child_theme( $parent_theme_slug ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        // Validate parent theme exists
        $parent_theme = wp_get_theme( $parent_theme_slug );
        if ( ! $parent_theme->exists() ) {
            return new WP_Error( 'parent_not_found', sprintf( __( 'Parent theme "%s" not found.', 'kesso-init' ), $parent_theme_slug ) );
        }

        // Check filesystem access
        $filesystem = $this->get_filesystem();
        if ( is_wp_error( $filesystem ) ) {
            return $filesystem;
        }

        // Generate child theme ZIP
        $zip_path = $this->generate_child_theme_zip( $parent_theme_slug, $parent_theme );
        if ( is_wp_error( $zip_path ) ) {
            return $zip_path;
        }

        // Install child theme from ZIP
        $upgrader = new Theme_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );
        $result = $upgrader->install( $zip_path );

        // Clean up temporary ZIP
        if ( file_exists( $zip_path ) ) {
            unlink( $zip_path );
        }

        if ( is_wp_error( $result ) || false === $result ) {
            return new WP_Error( 'child_install_failed', __( 'Failed to install child theme.', 'kesso-init' ) );
        }

        // Activate child theme
        $child_slug = $parent_theme_slug . '-child';
        switch_theme( $child_slug );

        return array(
            'child_theme'  => $child_slug,
            'parent_theme' => $parent_theme_slug,
            'active_theme' => $child_slug,
        );
    }

    /**
     * Generate child theme ZIP file
     *
     * @param string   $parent_slug Parent theme slug.
     * @param WP_Theme $parent_theme Parent theme object.
     * @return string|WP_Error Path to ZIP file or error.
     */
    private function generate_child_theme_zip( $parent_slug, $parent_theme ) {
        global $wp_filesystem;

        $child_slug = $parent_slug . '-child';
        $child_name = $parent_theme->get( 'Name' ) . ' Child';
        $child_version = '1.0.0';
        $child_description = sprintf( __( 'Child theme for %s', 'kesso-init' ), $parent_theme->get( 'Name' ) );

        // Create temporary directory using WordPress uploads directory (more reliable)
        $upload_dir = wp_upload_dir();
        $temp_base_dir = $upload_dir['basedir'] . '/kesso-temp';
        
        // Ensure temp base directory exists
        if ( ! $wp_filesystem->is_dir( $temp_base_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $temp_base_dir, FS_CHMOD_DIR ) ) {
                // Fallback to system temp directory
                $temp_base_dir = get_temp_dir();
            }
        }
        
        // Create unique identifier for this child theme
        $unique_id = time() . '-' . wp_generate_password( 8, false );
        
        // Create unique theme directory
        $temp_theme_dir = $temp_base_dir . '/' . $child_slug . '-' . $unique_id;

        // Create theme directory
        if ( ! $wp_filesystem->mkdir( $temp_theme_dir, FS_CHMOD_DIR, true ) ) {
            return new WP_Error( 'mkdir_failed', sprintf( __( 'Failed to create temporary directory: %s', 'kesso-init' ), $temp_theme_dir ) );
        }

        // Check if parent is Bricks
        $is_bricks = ( 'bricks' === $parent_slug );

        // Generate style.css
        $style_css = "/*\n";
        $style_css .= "Theme Name: {$child_name}\n";
        $style_css .= "Template: {$parent_slug}\n";
        $style_css .= "Version: {$child_version}\n";
        $style_css .= "Description: {$child_description}\n";
        $style_css .= "*/\n\n";
        
        if ( $is_bricks ) {
            $style_css .= "/* Bricks child theme styles */\n";
        }

        if ( ! $wp_filesystem->put_contents( $temp_theme_dir . '/style.css', $style_css, FS_CHMOD_FILE ) ) {
            return new WP_Error( 'file_write_failed', __( 'Failed to write style.css.', 'kesso-init' ) );
        }

        // Generate functions.php content
        $functions_php = "<?php \n";
        if ( $is_bricks ) {
            $functions_php .= "/**\n";
            $functions_php .= " * Register/enqueue custom scripts and styles\n";
            $functions_php .= " */\n";
            $functions_php .= "add_action( 'wp_enqueue_scripts', function() {\n";
            $functions_php .= "    // Enqueue your files on the canvas & frontend, not the builder panel. Otherwise custom CSS might affect builder)\n";
            $functions_php .= "    if ( ! bricks_is_builder_main() ) {\n";
            $functions_php .= "        wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );\n";
            $functions_php .= "    }\n";
            $functions_php .= "} );\n\n";
            $functions_php .= "/**\n";
            $functions_php .= " * Register custom elements\n";
            $functions_php .= " */\n";
            $functions_php .= "add_action( 'init', function() {\n";
            $functions_php .= "  \$element_files = [\n";
            $functions_php .= "    __DIR__ . '/elements/title.php',\n";
            $functions_php .= "  ];\n\n";
            $functions_php .= "  foreach ( \$element_files as \$file ) {\n";
            $functions_php .= "    \\Bricks\\Elements::register_element( \$file );\n";
            $functions_php .= "  }\n";
            $functions_php .= "}, 11 );\n\n";
            $functions_php .= "/**\n";
            $functions_php .= " * Add text strings to builder\n";
            $functions_php .= " */\n";
            $functions_php .= "add_filter( 'bricks/builder/i18n', function( \$i18n ) {\n";
            $functions_php .= "  // For element category 'custom'\n";
            $functions_php .= "  \$i18n['custom'] = esc_html__( 'Custom', 'bricks' );\n\n";
            $functions_php .= "  return \$i18n;\n";
            $functions_php .= "} );\n";
        } else {
            $functions_php .= "/**\n";
            $functions_php .= " * {$child_name} functions and definitions\n";
            $functions_php .= " *\n";
            $functions_php .= " * @package {$child_slug}\n";
            $functions_php .= " */\n\n";
            $functions_php .= "// Enqueue parent theme styles\n";
            $functions_php .= "add_action( 'wp_enqueue_scripts', function() {\n";
            $functions_php .= "    wp_enqueue_style(\n";
            $functions_php .= "        'parent-style',\n";
            $functions_php .= "        get_template_directory_uri() . '/style.css',\n";
            $functions_php .= "        array(),\n";
            $functions_php .= "        wp_get_theme()->parent()->get( 'Version' )\n";
            $functions_php .= "    );\n";
            $functions_php .= "} );\n";
        }

        if ( ! $wp_filesystem->put_contents( $temp_theme_dir . '/functions.php', $functions_php, FS_CHMOD_FILE ) ) {
            return new WP_Error( 'file_write_failed', __( 'Failed to write functions.php.', 'kesso-init' ) );
        }

        // For Bricks: create elements directory and custom element
        if ( $is_bricks ) {
            $elements_dir = $temp_theme_dir . '/elements';
            if ( ! $wp_filesystem->mkdir( $elements_dir, FS_CHMOD_DIR ) ) {
                return new WP_Error( 'mkdir_failed', __( 'Failed to create elements directory.', 'kesso-init' ) );
            }

            // Create custom title element
            $title_element = "<?php\n";
            $title_element .= "/**\n";
            $title_element .= " * Title Element\n";
            $title_element .= " */\n";
            $title_element .= "\n";
            $title_element .= "namespace Bricks;\n";
            $title_element .= "\n";
            $title_element .= "if ( ! defined( 'ABSPATH' ) ) exit;\n";
            $title_element .= "\n";
            $title_element .= "class Title extends Element {\n";
            $title_element .= "  public \$category = 'custom';\n";
            $title_element .= "  public \$name     = 'title';\n";
            $title_element .= "  public \$icon     = 'ti-text';\n";
            $title_element .= "\n";
            $title_element .= "  public function get_label() {\n";
            $title_element .= "    return esc_html__( 'Title', 'bricks' );\n";
            $title_element .= "  }\n";
            $title_element .= "\n";
            $title_element .= "  public function set_controls() {\n";
            $title_element .= "    \$this->controls['text'] = [\n";
            $title_element .= "      'tab'         => 'content',\n";
            $title_element .= "      'label'       => esc_html__( 'Text', 'bricks' ),\n";
            $title_element .= "      'type'        => 'text',\n";
            $title_element .= "      'default'     => esc_html__( 'Title', 'bricks' ),\n";
            $title_element .= "      'placeholder' => esc_html__( 'Enter title text', 'bricks' ),\n";
            $title_element .= "    ];\n";
            $title_element .= "  }\n";
            $title_element .= "\n";
            $title_element .= "  public function render() {\n";
            $title_element .= "    \$text = \$this->settings['text'] ?? esc_html__( 'Title', 'bricks' );\n";
            $title_element .= "    \$this->set_attribute( '_root', 'class', 'bricks-title' );\n";
            $title_element .= "    echo \"<div {\$this->render_attributes( '_root' )}>\$text</div>\";\n";
            $title_element .= "  }\n";
            $title_element .= "}\n";

            if ( ! $wp_filesystem->put_contents( $elements_dir . '/title.php', $title_element, FS_CHMOD_FILE ) ) {
                return new WP_Error( 'file_write_failed', __( 'Failed to write title element.', 'kesso-init' ) );
            }

            // Copy screenshot from parent Bricks theme if it exists
            $parent_screenshot = get_theme_root() . '/' . $parent_slug . '/screenshot.png';
            if ( file_exists( $parent_screenshot ) ) {
                $child_screenshot = $temp_theme_dir . '/screenshot.png';
                copy( $parent_screenshot, $child_screenshot );
            }
        }

        // Create ZIP file in the same directory as the theme
        $zip_path = $temp_base_dir . '/' . $child_slug . '-' . $unique_id . '.zip';
        $zip = new ZipArchive();
        if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            return new WP_Error( 'zip_create_failed', __( 'Failed to create ZIP file.', 'kesso-init' ) );
        }

        // Add files to ZIP
        $this->add_directory_to_zip( $temp_theme_dir, $zip, $child_slug );

        $zip->close();

        // Clean up temporary directory
        $wp_filesystem->rmdir( $temp_theme_dir, true );

        return $zip_path;
    }

    /**
     * Recursively add directory to ZIP
     *
     * @param string     $dir Directory path.
     * @param ZipArchive $zip ZIP archive object.
     * @param string     $base Base path for ZIP entries.
     */
    private function add_directory_to_zip( $dir, $zip, $base ) {
        $files = scandir( $dir );
        foreach ( $files as $file ) {
            if ( '.' === $file || '..' === $file ) {
                continue;
            }

            $file_path = $dir . '/' . $file;
            $zip_path  = $base . '/' . $file;

            if ( is_dir( $file_path ) ) {
                $zip->addEmptyDir( $zip_path );
                $this->add_directory_to_zip( $file_path, $zip, $zip_path );
            } else {
                $zip->addFile( $file_path, $zip_path );
            }
        }
    }

    /**
     * Get filesystem access
     *
     * @return bool|WP_Error
     */
    private function get_filesystem() {
        global $wp_filesystem;

        if ( ! function_exists( 'request_filesystem_credentials' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $url = admin_url( 'admin.php?page=kesso-init' );
        $method = 'direct';
        $context = WP_CONTENT_DIR;

        if ( false === ( $credentials = request_filesystem_credentials( $url, $method, false, $context, null ) ) ) {
            return new WP_Error( 'fs_credentials_failed', __( 'Filesystem credentials not provided.', 'kesso-init' ) );
        }

        if ( ! WP_Filesystem( $credentials, $context ) ) {
            return new WP_Error( 'fs_init_failed', __( 'Filesystem initialization failed.', 'kesso-init' ) );
        }

        return true;
    }
}

