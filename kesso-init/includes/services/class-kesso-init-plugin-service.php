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
}
