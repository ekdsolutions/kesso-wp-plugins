<?php
/**
 * Plugin Installation Service
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin installation and activation
 */
class Kesso_Init_Plugin_Service {

    /**
     * Install multiple plugins independently
     *
     * @param array $plugin_slugs Array of plugin slugs.
     * @return array|WP_Error
     */
    public function install_plugins( $plugin_slugs ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        $results = array(
            'installed' => array(),
            'activated' => array(),
            'skipped'   => array(),
            'failed'    => array(),
        );

        // Get plugin list from main class
        $kesso = Kesso_Init::get_instance();
        $plugin_list = $kesso->get_plugin_list();

        // Create a map of slugs to plugin data
        $plugin_map = array();
        foreach ( $plugin_list as $plugin ) {
            $plugin_map[ $plugin['slug'] ] = $plugin;
        }

        // Install each plugin independently - one failure doesn't block others
        foreach ( $plugin_slugs as $slug ) {
            try {
                // Skip if plugin not in our list
                if ( ! isset( $plugin_map[ $slug ] ) ) {
                    $results['failed'][] = array(
                        'slug'  => $slug,
                        'error' => __( 'Plugin not found in list.', 'kesso-init' ),
                    );
                    continue;
                }

                $plugin = $plugin_map[ $slug ];

                // Check if already installed
                if ( kesso_init_is_plugin_installed( $plugin['file'] ) ) {
                    $results['skipped'][] = $slug;

                    // Try to activate if not active
                    if ( ! kesso_init_is_plugin_active( $plugin['file'] ) ) {
                        $activate_result = $this->activate_plugin( $slug, $plugin['file'] );
                        if ( ! is_wp_error( $activate_result ) ) {
                            $results['activated'][] = $slug;
                            
                            // Enable auto-updates for the plugin
                            $this->enable_plugin_auto_update( $slug, $plugin['file'] );
                        } else {
                            // Activation failed but plugin is installed
                            $results['failed'][] = array(
                                'slug'  => $slug,
                                'error' => sprintf( __( 'Installation skipped (already installed), but activation failed: %s', 'kesso-init' ), $activate_result->get_error_message() ),
                            );
                        }
                    } else {
                        // Plugin is already active, enable auto-updates if not already enabled
                        $this->enable_plugin_auto_update( $slug, $plugin['file'] );
                    }
                    continue;
                }

                // Install plugin (with timeout protection)
                $install_result = $this->install_plugin( $slug, $plugin );
                if ( is_wp_error( $install_result ) ) {
                    $results['failed'][] = array(
                        'slug'  => $slug,
                        'error' => $install_result->get_error_message(),
                    );
                    continue; // Continue to next plugin
                }

                $results['installed'][] = $slug;

                // Activate plugin
                $activate_result = $this->activate_plugin( $slug, $plugin['file'] );
                if ( is_wp_error( $activate_result ) ) {
                    // Installation succeeded but activation failed
                    $results['failed'][] = array(
                        'slug'  => $slug,
                        'error' => sprintf( __( 'Installed but activation failed: %s', 'kesso-init' ), $activate_result->get_error_message() ),
                    );
                } else {
                    $results['activated'][] = $slug;
                    
                    // Enable auto-updates for the plugin
                    $this->enable_plugin_auto_update( $slug, $plugin['file'] );
                }

            } catch ( Exception $e ) {
                // Catch any unexpected errors for this plugin
                $results['failed'][] = array(
                    'slug'  => $slug,
                    'error' => sprintf( __( 'Unexpected error: %s', 'kesso-init' ), $e->getMessage() ),
                );
                // Continue to next plugin
                continue;
            }
        }

        return $results;
    }

    /**
     * Install a single plugin with timeout protection
     *
     * @param string $slug   Plugin slug.
     * @param array  $plugin Plugin data.
     * @return bool|WP_Error
     */
    private function install_plugin( $slug, $plugin ) {
        // Get download URL
        if ( 'wordpress.org' === $plugin['source'] ) {
            $download_url = kesso_init_get_plugin_download_url( $slug );
        } else {
            return new WP_Error( 'unsupported_source', __( 'Unsupported plugin source.', 'kesso-init' ) );
        }

        // Set timeout for this specific download
        add_filter( 'http_request_timeout', array( $this, 'get_plugin_download_timeout' ), 999 );
        add_filter( 'http_request_args', array( $this, 'set_plugin_download_args' ), 999 );

        try {
            // Initialize upgrader
            $upgrader = new Plugin_Upgrader( new Kesso_Init_Silent_Upgrader_Skin() );

            // Install plugin
            $result = $upgrader->install( $download_url );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            if ( false === $result ) {
                return new WP_Error( 'install_failed', __( 'Plugin installation failed.', 'kesso-init' ) );
            }

            return true;
        } catch ( Exception $e ) {
            return new WP_Error( 'install_exception', sprintf( __( 'Plugin installation exception: %s', 'kesso-init' ), $e->getMessage() ) );
        } finally {
            // Remove timeout filters
            remove_filter( 'http_request_timeout', array( $this, 'get_plugin_download_timeout' ), 999 );
            remove_filter( 'http_request_args', array( $this, 'set_plugin_download_args' ), 999 );
        }
    }

    /**
     * Get timeout for plugin downloads
     *
     * @return int Timeout in seconds.
     */
    public function get_plugin_download_timeout() {
        return 30; // 30 seconds per plugin download
    }

    /**
     * Set download arguments with timeout
     *
     * @param array $args Request arguments.
     * @return array Modified arguments.
     */
    public function set_plugin_download_args( $args ) {
        $args['timeout'] = 30;
        return $args;
    }

    /**
     * Activate a plugin
     *
     * @param string $slug         Plugin slug.
     * @param string $expected_file Expected plugin file path.
     * @return bool|WP_Error
     */
    private function activate_plugin( $slug, $expected_file ) {
        // Find the actual plugin file (may differ from expected)
        $plugin_file = $this->find_plugin_file( $slug, $expected_file );

        if ( is_wp_error( $plugin_file ) ) {
            return $plugin_file;
        }

        // Activate plugin
        $result = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Find the actual plugin file after installation
     *
     * @param string $slug         Plugin slug.
     * @param string $expected_file Expected plugin file path.
     * @return string|WP_Error
     */
    private function find_plugin_file( $slug, $expected_file = null ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        // 1. Check if the expected file exists and is valid
        if ( $expected_file && isset( $all_plugins[ $expected_file ] ) ) {
            return $expected_file;
        }

        // 2. Search for a plugin file that contains the slug in its path
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if ( strpos( $plugin_file, $slug . '/' ) === 0 ) {
                return $plugin_file;
            }
        }

        // 3. Fallback: search for a plugin whose slug is part of the file path
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            if ( strpos( $plugin_file, $slug ) !== false ) {
                return $plugin_file;
            }
        }

        return new WP_Error( 'plugin_file_not_found', sprintf( __( 'Plugin file for slug "%s" could not be found.', 'kesso-init' ), $slug ) );
    }

    /**
     * Install a single plugin (for queue-based installation)
     *
     * @param string $slug Plugin slug.
     * @return array|WP_Error Result with status: 'installed', 'activated', 'skipped', or 'failed'.
     */
    public function install_single_plugin( $slug ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        // Get plugin list from main class
        $kesso = Kesso_Init::get_instance();
        $plugin_list = $kesso->get_plugin_list();

        // Find plugin in list
        $plugin = null;
        foreach ( $plugin_list as $p ) {
            if ( $p['slug'] === $slug ) {
                $plugin = $p;
                break;
            }
        }

        if ( ! $plugin ) {
            return new WP_Error( 'plugin_not_found', sprintf( __( 'Plugin "%s" not found in list.', 'kesso-init' ), $slug ) );
        }

        try {
            // Check if already installed
            if ( kesso_init_is_plugin_installed( $plugin['file'] ) ) {
                // Try to activate if not active
                if ( ! kesso_init_is_plugin_active( $plugin['file'] ) ) {
                    $activate_result = $this->activate_plugin( $slug, $plugin['file'] );
                    if ( ! is_wp_error( $activate_result ) ) {
                        $this->enable_plugin_auto_update( $slug, $plugin['file'] );
                        return array(
                            'status' => 'activated',
                            'slug'   => $slug,
                            'name'   => $plugin['name'],
                        );
                    } else {
                        return array(
                            'status' => 'skipped',
                            'slug'   => $slug,
                            'name'   => $plugin['name'],
                            'error'  => $activate_result->get_error_message(),
                        );
                    }
                } else {
                    // Plugin is already active
                    $this->enable_plugin_auto_update( $slug, $plugin['file'] );
                    return array(
                        'status' => 'skipped',
                        'slug'   => $slug,
                        'name'   => $plugin['name'],
                    );
                }
            }

            // Install plugin
            $install_result = $this->install_plugin( $slug, $plugin );
            if ( is_wp_error( $install_result ) ) {
                return array(
                    'status' => 'failed',
                    'slug'   => $slug,
                    'name'   => $plugin['name'],
                    'error'  => $install_result->get_error_message(),
                );
            }

            // Activate plugin
            $activate_result = $this->activate_plugin( $slug, $plugin['file'] );
            if ( is_wp_error( $activate_result ) ) {
                return array(
                    'status' => 'installed',
                    'slug'   => $slug,
                    'name'   => $plugin['name'],
                    'error'  => sprintf( __( 'Installed but activation failed: %s', 'kesso-init' ), $activate_result->get_error_message() ),
                );
            }

            // Enable auto-updates
            $this->enable_plugin_auto_update( $slug, $plugin['file'] );

            return array(
                'status' => 'activated',
                'slug'   => $slug,
                'name'   => $plugin['name'],
            );

        } catch ( Exception $e ) {
            return array(
                'status' => 'failed',
                'slug'   => $slug,
                'name'   => $plugin['name'],
                'error'  => sprintf( __( 'Unexpected error: %s', 'kesso-init' ), $e->getMessage() ),
            );
        }
    }

    /**
     * Enable auto-updates for a plugin
     *
     * @param string $slug         Plugin slug.
     * @param string $expected_file Expected plugin file path.
     * @return bool
     */
    public function enable_plugin_auto_update( $slug, $expected_file ) {
        // Auto-updates are opt-in. Return early unless explicitly enabled.
        // Use the 'kesso_init_auto_updates' filter to opt in from a mu-plugin or theme.
        if ( ! apply_filters( 'kesso_init_auto_updates', false ) ) {
            return false;
        }

        // Find the actual plugin file
        $plugin_file = $this->find_plugin_file( $slug, $expected_file );

        if ( is_wp_error( $plugin_file ) ) {
            return false;
        }

        // Get current auto-update settings
        $auto_updates = (array) get_site_option( 'auto_update_plugins', array() );

        // Add plugin to auto-update list if not already there
        if ( ! in_array( $plugin_file, $auto_updates, true ) ) {
            $auto_updates[] = $plugin_file;
            update_site_option( 'auto_update_plugins', $auto_updates );
        }

        return true;
    }

    /**
     * Install a plugin from GitHub
     *
     * @param string $plugin_slug Plugin slug (directory name in GitHub).
     * @param string $github_repo GitHub repository (e.g., 'username/kesso-wp-plugins').
     * @param string $branch Branch name (default: 'master').
     * @return array|WP_Error Result with status: 'installed', 'activated', 'skipped', or 'failed'.
     */
    public function install_github_plugin( $plugin_slug, $github_repo = 'ekdsolutions/kesso-wp-plugins', $branch = 'master' ) {
        // Ensure required files are loaded
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once KESSO_INIT_PATH . 'includes/services/class-kesso-init-silent-upgrader-skin.php';

        // Initialize filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        // Check if already installed - try to find the plugin file
        $plugin_file = $this->find_plugin_file( $plugin_slug, $plugin_slug . '/' . $plugin_slug . '.php' );
        
        if ( ! is_wp_error( $plugin_file ) && kesso_init_is_plugin_installed( $plugin_file ) ) {
            // Try to activate if not active
            if ( ! kesso_init_is_plugin_active( $plugin_file ) ) {
                $activate_result = activate_plugin( $plugin_file );
                if ( ! is_wp_error( $activate_result ) ) {
                    $this->enable_plugin_auto_update( $plugin_slug, $plugin_file );
                    return array(
                        'status' => 'activated',
                        'slug'   => $plugin_slug,
                        'name'   => $plugin_slug,
                    );
                }
            } else {
                $this->enable_plugin_auto_update( $plugin_slug, $plugin_file );
                return array(
                    'status' => 'skipped',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                );
            }
        }

        // Download from GitHub
        $download_url = sprintf(
            'https://github.com/%s/archive/refs/heads/%s.zip',
            $github_repo,
            $branch
        );

        // Set timeout
        add_filter( 'http_request_timeout', array( $this, 'get_plugin_download_timeout' ), 999 );
        add_filter( 'http_request_args', array( $this, 'set_plugin_download_args' ), 999 );

        try {
            // Download the repository ZIP
            $temp_file = download_url( $download_url );
            
            if ( is_wp_error( $temp_file ) ) {
                return array(
                    'status' => 'failed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => sprintf( __( 'Failed to download from GitHub: %s', 'kesso-init' ), $temp_file->get_error_message() ),
                );
            }

            // Extract to a dedicated temp directory so the plugins folder is never
            // cluttered with the full repo contents during extraction.
            $repo_name    = basename( $github_repo );
            $temp_extract = $wp_filesystem->wp_content_dir() . 'uploads/kesso-tmp-' . $repo_name . '-' . uniqid() . '/';
            $wp_filesystem->mkdir( $temp_extract, 0755, true );

            $result = unzip_file( $temp_file, $temp_extract );

            if ( is_wp_error( $result ) ) {
                @unlink( $temp_file );
                $wp_filesystem->rmdir( $temp_extract, true );
                return array(
                    'status' => 'failed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => sprintf( __( 'Failed to extract ZIP: %s', 'kesso-init' ), $result->get_error_message() ),
                );
            }

            // Clean up temp file
            @unlink( $temp_file );

            // The extracted folder will be named like 'kesso-wp-plugins-master'
            // We need to move only the specific plugin subfolder to the plugins directory.
            $extracted_folder = $temp_extract . $repo_name . '-' . $branch;
            $plugin_source    = $extracted_folder . '/' . $plugin_slug;
            $plugin_dest = $unzip_path . $plugin_slug;

            // Check if plugin folder exists in extracted ZIP
            if ( ! $wp_filesystem->exists( $plugin_source ) ) {
                $wp_filesystem->rmdir( $temp_extract, true );
                return array(
                    'status' => 'failed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => sprintf( __( 'Plugin folder "%s" not found in repository.', 'kesso-init' ), $plugin_slug ),
                );
            }

            // Move plugin folder to correct location using WP_Filesystem
            if ( $wp_filesystem->exists( $plugin_dest ) ) {
                $wp_filesystem->rmdir( $plugin_dest, true );
            }

            // Use WP_Filesystem to move the directory
            if ( ! $wp_filesystem->move( $plugin_source, $plugin_dest, true ) ) {
                $wp_filesystem->rmdir( $temp_extract, true );
                return array(
                    'status' => 'failed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => __( 'Failed to move plugin folder to plugins directory.', 'kesso-init' ),
                );
            }

            // Clean up the full temp extraction directory
            $wp_filesystem->rmdir( $temp_extract, true );

            // Verify plugin directory exists
            $plugin_dir = $wp_filesystem->wp_content_dir() . 'plugins/' . $plugin_slug . '/';
            if ( ! $wp_filesystem->exists( $plugin_dir ) ) {
                return array(
                    'status' => 'failed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => sprintf( __( 'Plugin directory not found after installation: %s', 'kesso-init' ), $plugin_dir ),
                );
            }

            // Clear plugin cache so WordPress recognizes the new plugin
            wp_cache_delete( 'plugins', 'plugins' );
            delete_site_transient( 'update_plugins' );
            
            // Use the expected plugin file path
            // WordPress will recognize it even if not in cache yet
            $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
            
            // Verify the main plugin file exists on filesystem
            $plugin_path = $wp_filesystem->wp_content_dir() . 'plugins/' . $plugin_file;
            if ( ! $wp_filesystem->exists( $plugin_path ) ) {
                // Try to find the actual plugin file using get_plugins()
                $found_file = $this->find_plugin_file( $plugin_slug, $plugin_file );
                if ( is_wp_error( $found_file ) ) {
                    return array(
                        'status' => 'failed',
                        'slug'   => $plugin_slug,
                        'name'   => $plugin_slug,
                        'error'  => sprintf( __( 'Plugin installed but main file "%s" not found in plugin directory.', 'kesso-init' ), $plugin_file ),
                    );
                }
                $plugin_file = $found_file;
            }

            // Activate plugin
            $activate_result = activate_plugin( $plugin_file );
            if ( is_wp_error( $activate_result ) ) {
                return array(
                    'status' => 'installed',
                    'slug'   => $plugin_slug,
                    'name'   => $plugin_slug,
                    'error'  => sprintf( __( 'Installed but activation failed: %s', 'kesso-init' ), $activate_result->get_error_message() ),
                );
            }

            // Enable auto-updates
            $this->enable_plugin_auto_update( $plugin_slug, $plugin_file );

            return array(
                'status' => 'activated',
                'slug'   => $plugin_slug,
                'name'   => $plugin_slug,
            );

        } catch ( Exception $e ) {
            return array(
                'status' => 'failed',
                'slug'   => $plugin_slug,
                'name'   => $plugin_slug,
                'error'  => sprintf( __( 'Unexpected error: %s', 'kesso-init' ), $e->getMessage() ),
            );
        } finally {
            // Remove timeout filters
            remove_filter( 'http_request_timeout', array( $this, 'get_plugin_download_timeout' ), 999 );
            remove_filter( 'http_request_args', array( $this, 'set_plugin_download_args' ), 999 );
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir Directory path.
     * @return bool
     */
    private function delete_directory( $dir ) {
        global $wp_filesystem;
        
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        if ( $wp_filesystem && method_exists( $wp_filesystem, 'rmdir' ) ) {
            return $wp_filesystem->rmdir( $dir, true );
        }
        
        // Fallback to direct filesystem operations if WP_Filesystem not available
        if ( ! file_exists( $dir ) ) {
            return true;
        }
        
        if ( ! is_dir( $dir ) ) {
            return unlink( $dir );
        }
        
        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        foreach ( $files as $file ) {
            $file_path = $dir . '/' . $file;
            if ( is_dir( $file_path ) ) {
                $this->delete_directory( $file_path );
            } else {
                unlink( $file_path );
            }
        }
        
        return rmdir( $dir );
    }
}
