<?php
/**
 * Plugins REST API Endpoint
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin installation REST endpoints
 */
class Kesso_Init_Plugins_Endpoint {

    /**
     * Main plugin instance
     *
     * @var Kesso_Init
     */
    private $kesso;

    /**
     * Constructor
     *
     * @param Kesso_Init $kesso Main plugin instance.
     */
    public function __construct( $kesso ) {
        $this->kesso = $kesso;
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/plugins/install',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_plugins' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'plugins' => array(
                        'required'          => true,
                        'type'              => 'array',
                        'description'       => __( 'Array of plugin slugs to install.', 'kesso-init' ),
                        'sanitize_callback' => function( $value ) {
                            return array_map( 'sanitize_text_field', $value );
                        },
                    ),
                ),
            )
        );

        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/plugins/install-single',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_single_plugin' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => __( 'Plugin slug to install.', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/plugins/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_plugins_status' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/plugins/install-github',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_github_plugin' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => __( 'Plugin slug to install from GitHub.', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'github_repo' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'description'       => __( 'GitHub repository (e.g., username/repo).', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'ekdsolutions/kesso-wp-plugins',
                    ),
                    'branch' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'description'       => __( 'Branch name.', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'master',
                    ),
                ),
            )
        );
    }

    /**
     * Check permission
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        // install_plugins is the capability WordPress core checks before any
        // plugin install/activate, and it is automatically revoked when
        // DISALLOW_FILE_MODS is set.
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'kesso-init' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Install plugins
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function install_plugins( $request ) {
        $plugin_slugs = $request->get_param( 'plugins' );

        if ( empty( $plugin_slugs ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    __( 'No plugins specified.', 'kesso-init' )
                )
            );
        }

        $result = $this->kesso->get_plugin_service()->install_plugins( $plugin_slugs );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    $result->get_error_message(),
                    array( 'error_code' => $result->get_error_code() )
                )
            );
        }

        return rest_ensure_response(
            kesso_init_api_response(
                true,
                sprintf(
                    /* translators: %d: number of plugins */
                    __( '%d plugin(s) processed successfully.', 'kesso-init' ),
                    count( $result['installed'] ) + count( $result['skipped'] )
                ),
                $result
            )
        );
    }

    /**
     * Install a single plugin (for queue-based installation)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function install_single_plugin( $request ) {
        $slug = $request->get_param( 'slug' );

        if ( empty( $slug ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    __( 'No plugin slug specified.', 'kesso-init' )
                )
            );
        }

        $result = $this->kesso->get_plugin_service()->install_single_plugin( $slug );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    $result->get_error_message(),
                    array( 'error_code' => $result->get_error_code() )
                )
            );
        }

        $message = '';
        switch ( $result['status'] ) {
            case 'activated':
                $message = sprintf( __( '%s installed and activated successfully.', 'kesso-init' ), $result['name'] );
                break;
            case 'installed':
                $message = sprintf( __( '%s installed successfully.', 'kesso-init' ), $result['name'] );
                break;
            case 'skipped':
                $message = sprintf( __( '%s was already installed.', 'kesso-init' ), $result['name'] );
                break;
            case 'failed':
                $message = sprintf( __( '%s installation failed: %s', 'kesso-init' ), $result['name'], $result['error'] ?? __( 'Unknown error', 'kesso-init' ) );
                break;
        }

        return rest_ensure_response(
            kesso_init_api_response(
                $result['status'] !== 'failed',
                $message,
                $result
            )
        );
    }

    /**
     * Get plugins status
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_plugins_status( $request ) {
        $plugins = $this->kesso->get_plugin_list();

        $status = array();
        foreach ( $plugins as $plugin ) {
            // Try to find the actual plugin file (folder name might differ after installation)
            $actual_file = $this->find_plugin_file_by_slug( $plugin['slug'], $plugin['file'] );
            
            $status[ $plugin['slug'] ] = array(
                'name'      => $plugin['name'],
                'installed' => $actual_file ? kesso_init_is_plugin_installed( $actual_file ) : false,
                'active'    => $actual_file ? kesso_init_is_plugin_active( $actual_file ) : false,
            );
        }

        return rest_ensure_response(
            kesso_init_api_response(
                true,
                __( 'Plugin status retrieved.', 'kesso-init' ),
                array( 'plugins' => $status )
            )
        );
    }

    /**
     * Find the actual plugin file by slug
     *
     * @param string $slug         Plugin slug.
     * @param string $expected_file Expected plugin file path.
     * @return string|false Plugin file path or false if not found.
     */
    private function find_plugin_file_by_slug( $slug, $expected_file ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        // First, try the expected file path
        if ( isset( $plugins[ $expected_file ] ) ) {
            return $expected_file;
        }

        // Match on the plugin's own directory exactly (e.g. slug "polylang"
        // matches "polylang/polylang.php"). The previous substring /
        // shared-token fallbacks could mis-identify an unrelated plugin that
        // merely shared a word (e.g. "code-snippets" vs "code-*").
        foreach ( $plugins as $file => $plugin_data ) {
            if ( dirname( $file ) === $slug ) {
                return $file;
            }
        }

        return false;
    }

    /**
     * Install a plugin from GitHub
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function install_github_plugin( $request ) {
        $slug = $request->get_param( 'slug' );
        $github_repo = $request->get_param( 'github_repo' ) ?: 'ekdsolutions/kesso-wp-plugins';
        $branch = $request->get_param( 'branch' ) ?: 'master';

        if ( empty( $slug ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    __( 'No plugin slug specified.', 'kesso-init' )
                )
            );
        }

        $result = $this->kesso->get_plugin_service()->install_github_plugin( $slug, $github_repo, $branch );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    $result->get_error_message(),
                    array( 'error_code' => $result->get_error_code() )
                )
            );
        }

        $message = '';
        switch ( $result['status'] ) {
            case 'activated':
                $message = sprintf( __( '%s installed and activated successfully.', 'kesso-init' ), $result['name'] );
                break;
            case 'installed':
                $message = sprintf( __( '%s installed successfully.', 'kesso-init' ), $result['name'] );
                break;
            case 'skipped':
                $message = sprintf( __( '%s was already installed.', 'kesso-init' ), $result['name'] );
                break;
            case 'failed':
                $message = sprintf( __( '%s installation failed: %s', 'kesso-init' ), $result['name'], $result['error'] ?? __( 'Unknown error', 'kesso-init' ) );
                break;
        }

        return rest_ensure_response(
            kesso_init_api_response(
                $result['status'] !== 'failed',
                $message,
                $result
            )
        );
    }
}

