<?php
/**
 * Settings REST API Endpoint
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles settings update REST endpoints
 */
class Kesso_Init_Settings_Endpoint {

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
            '/settings/update',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'update_settings' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'blogname' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'blogdescription' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'WPLANG' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'timezone_string' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'date_format' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'time_format' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'start_of_week' => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'site_icon' => array(
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/settings',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }

    /**
     * Check permission
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
     * Update settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_settings( $request ) {
        $settings = array();

        // Collect all provided settings
        $allowed_keys = array(
            'blogname',
            'blogdescription',
            'WPLANG',
            'timezone_string',
            'date_format',
            'time_format',
            'start_of_week',
            'site_icon',
        );

        foreach ( $allowed_keys as $key ) {
            $value = $request->get_param( $key );
            if ( null !== $value ) {
                $settings[ $key ] = $value;
            }
        }

        if ( empty( $settings ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    __( 'No settings provided.', 'kesso-init' )
                )
            );
        }

        $result = $this->kesso->get_settings_service()->update_settings( $settings );

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
                __( 'Settings updated successfully.', 'kesso-init' ),
                array( 'updated' => array_keys( $settings ) )
            )
        );
    }

    /**
     * Get current settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_settings( $request ) {
        $settings = array(
            'blogname'        => get_option( 'blogname', '' ),
            'blogdescription' => get_option( 'blogdescription', '' ),
            'WPLANG'          => get_option( 'WPLANG', '' ),
            'timezone_string' => get_option( 'timezone_string', 'UTC' ),
            'date_format'     => get_option( 'date_format', 'F j, Y' ),
            'time_format'     => get_option( 'time_format', 'g:i a' ),
            'start_of_week'   => get_option( 'start_of_week', 0 ),
            'site_icon'       => get_option( 'site_icon', 0 ),
        );

        return rest_ensure_response(
            kesso_init_api_response(
                true,
                __( 'Settings retrieved successfully.', 'kesso-init' ),
                array( 'settings' => $settings )
            )
        );
    }
}

