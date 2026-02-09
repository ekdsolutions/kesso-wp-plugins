<?php
/**
 * Theme REST API Endpoint
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles theme installation REST endpoints
 */
class Kesso_Init_Theme_Endpoint {

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
        // Install theme (Elementor from WP.org or remote URL for Bricks)
        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/theme/install',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_theme' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'builder' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'enum'              => array( 'elementor', 'bricks' ),
                        'description'       => __( 'Page builder to install.', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'bricks_url' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'description'       => __( 'Remote ZIP URL for Bricks.', 'kesso-init' ),
                        'sanitize_callback' => 'esc_url_raw',
                    ),
                ),
            )
        );

        // Upload Bricks ZIP
        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/theme/upload-bricks',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'upload_bricks' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        // Install child theme
        register_rest_route(
            Kesso_Init_Rest_Controller::NAMESPACE,
            '/child-theme/install',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'install_child_theme' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'parent_theme' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => __( 'Parent theme slug.', 'kesso-init' ),
                        'sanitize_callback' => 'sanitize_text_field',
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
     * Install theme/builder
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function install_theme( $request ) {
        $builder    = $request->get_param( 'builder' );
        $bricks_url = $request->get_param( 'bricks_url' );

        if ( 'elementor' === $builder ) {
            // Install Elementor plugin and Hello Elementor theme
            $result = $this->kesso->get_theme_service()->install_elementor();
        } else {
            // Install Bricks from URL if provided
            if ( ! empty( $bricks_url ) ) {
                $result = $this->kesso->get_theme_service()->install_bricks_from_url( $bricks_url );
            } else {
                return rest_ensure_response(
                    kesso_init_api_response(
                        false,
                        __( 'Bricks requires a ZIP file upload or remote URL.', 'kesso-init' )
                    )
                );
            }
        }

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
                    /* translators: %s: builder name */
                    __( '%s installed successfully.', 'kesso-init' ),
                    ucfirst( $builder )
                ),
                $result
            )
        );
    }

    /**
     * Upload and install Bricks ZIP
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function upload_bricks( $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['bricks_zip'] ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    __( 'No file uploaded.', 'kesso-init' )
                )
            );
        }

        // Validate the uploaded file
        $validated = kesso_init_validate_zip_upload( $files['bricks_zip'] );
        if ( is_wp_error( $validated ) ) {
            return rest_ensure_response(
                kesso_init_api_response(
                    false,
                    $validated->get_error_message()
                )
            );
        }

        // Install from uploaded file
        $result = $this->kesso->get_theme_service()->install_bricks_from_upload( $validated['tmp_name'] );

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
                __( 'Bricks theme installed successfully.', 'kesso-init' ),
                $result
            )
        );
    }

    /**
     * Install child theme
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function install_child_theme( $request ) {
        $parent_theme = $request->get_param( 'parent_theme' );

        $result = $this->kesso->get_theme_service()->create_and_install_child_theme( $parent_theme );

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
                __( 'Child theme created and activated successfully.', 'kesso-init' ),
                $result
            )
        );
    }
}

