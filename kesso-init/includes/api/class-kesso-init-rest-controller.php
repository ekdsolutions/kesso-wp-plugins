<?php
/**
 * REST API Controller - registers all endpoints
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main REST API controller that initializes all endpoints
 */
class Kesso_Init_Rest_Controller {

    /**
     * API namespace
     */
    const NAMESPACE = 'kesso/v1';

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

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all REST routes
     */
    public function register_routes() {
        // Initialize endpoint handlers (services will be loaded lazily)
        $plugins_endpoint  = new Kesso_Init_Plugins_Endpoint( $this->kesso );
        $theme_endpoint    = new Kesso_Init_Theme_Endpoint( $this->kesso );
        $settings_endpoint = new Kesso_Init_Settings_Endpoint( $this->kesso );
        $batch_endpoint    = new Kesso_Init_Batch_Endpoint( $this->kesso );

        // Register endpoints
        $plugins_endpoint->register_routes();
        $theme_endpoint->register_routes();
        $settings_endpoint->register_routes();
        $batch_endpoint->register_routes();

        // Status endpoint
        register_rest_route(
            self::NAMESPACE,
            '/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_status' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        // Mark wizard as completed
        register_rest_route(
            self::NAMESPACE,
            '/complete',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'mark_completed' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }

    /**
     * Check if user has permission
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'kesso-init' ),
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * Get current status
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_status( $request ) {
        $plugins = $this->kesso->get_plugin_list();

        $plugin_status = array();
        foreach ( $plugins as $plugin ) {
            $plugin_status[ $plugin['slug'] ] = array(
                'installed' => kesso_init_is_plugin_installed( $plugin['file'] ),
                'active'    => kesso_init_is_plugin_active( $plugin['file'] ),
            );
        }

        return rest_ensure_response(
            kesso_init_api_response(
                true,
                __( 'Status retrieved successfully.', 'kesso-init' ),
                array(
                    'wizard_completed' => Kesso_Init_Activation::is_completed(),
                    'plugins'          => $plugin_status,
                    'active_theme'     => get_stylesheet(),
                    'parent_theme'     => get_template(),
                )
            )
        );
    }

    /**
     * Mark wizard as completed
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function mark_completed( $request ) {
        Kesso_Init_Activation::mark_completed();
        
        return rest_ensure_response(
            kesso_init_api_response(
                true,
                __( 'Wizard marked as completed.', 'kesso-init' )
            )
        );
    }
}

