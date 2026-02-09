<?php
/**
 * Batch Operations REST API Endpoint
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles batch operations for applying all changes at once
 */
class Kesso_Init_Batch_Endpoint {

    /**
     * Main plugin instance
     *
     * @var Kesso_Init
     */
    private $kesso;
    
    /**
     * Last response data (for shutdown function)
     *
     * @var array
     */
    private $last_response = null;

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
            '/batch/apply',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'apply_all' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'plugins' => array(
                        'required' => false,
                        'type'     => 'array',
                        'default'  => array(),
                    ),
                    'builder' => array(
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => array( 'elementor', 'bricks', '' ),
                        'default'  => '',
                    ),
                    'bricks_url' => array(
                        'required' => false,
                        'type'     => 'string',
                        'default'  => '',
                    ),
                    'install_child_theme' => array(
                        'required' => false,
                        'type'     => 'boolean',
                        'default'  => false,
                    ),
                    'settings' => array(
                        'required' => false,
                        'type'     => 'object',
                        'default'  => array(),
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
     * Apply all changes in batch
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function apply_all( $request ) {
        // CRITICAL: Start multiple layers of output buffering to catch ALL output
        // This includes translation updates, notices, warnings, etc.
        $ob_levels = array();
        while ( ob_get_level() > 0 ) {
            $ob_levels[] = ob_get_level();
            ob_end_clean();
        }
        
        // Start fresh output buffer with callback to discard all output
        ob_start( array( $this, 'discard_output' ), 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE );
        
        // Increase execution time limit for long-running operations
        $original_time_limit = ini_get( 'max_execution_time' );
        if ( $original_time_limit > 0 && $original_time_limit < 300 ) {
            @set_time_limit( 300 ); // 5 minutes
        }
        
        // Suppress error display during REST API calls
        $original_display_errors = ini_get( 'display_errors' );
        $original_error_reporting = error_reporting();
        ini_set( 'display_errors', 0 );
        error_reporting( E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED );

        // Disable automatic translation updates during REST API calls
        add_filter( 'async_update_translation', '__return_false', 999 );
        add_filter( 'upgrader_pre_download', array( $this, 'suppress_translation_updates' ), 999, 3 );
        
        // Prevent WordPress from automatically updating translations after plugin install
        // Use priority 1 to run before WordPress processes translations
        add_action( 'upgrader_process_complete', array( $this, 'prevent_translation_updates' ), 1, 2 );
        
        // Suppress all WordPress admin notices and messages
        add_filter( 'wp_redirect', array( $this, 'suppress_redirects' ), 999 );
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'all_admin_notices' );
        remove_all_actions( 'network_admin_notices' );
        remove_all_actions( 'user_admin_notices' );
        
        // Suppress any output from upgrader hooks
        add_action( 'upgrader_process_complete', array( $this, 'suppress_translation_output' ), 999, 2 );
        add_filter( 'upgrader_pre_install', array( $this, 'suppress_translation_output_filter' ), 999, 2 );
        add_filter( 'upgrader_post_install', array( $this, 'suppress_translation_output_filter' ), 999, 3 );
        
        // Hook into language pack updates to prevent them
        add_filter( 'pre_site_transient_update_core', array( $this, 'suppress_translation_checks' ), 999 );
        add_filter( 'pre_site_transient_update_plugins', array( $this, 'suppress_translation_checks' ), 999 );
        add_filter( 'pre_site_transient_update_themes', array( $this, 'suppress_translation_checks' ), 999 );
        
        // Set up HTTP timeouts to prevent hanging
        add_filter( 'http_request_timeout', array( $this, 'set_http_timeout' ), 999 );
        add_filter( 'http_request_args', array( $this, 'set_http_request_args' ), 999 );

        // Initialize results structure
        $results = array(
            'plugins'     => array(
                'installed' => array(),
                'activated' => array(),
                'skipped'   => array(),
                'failed'    => array(),
            ),
            'theme'       => null,
            'child_theme' => null,
            'settings'    => null,
            'errors'      => array(),
        );

        // Wrap everything in try/catch to ensure we always return JSON
        try {
            $plugins            = $request->get_param( 'plugins' ) ?: array();
            $builder            = $request->get_param( 'builder' ) ?: '';
            $bricks_url         = $request->get_param( 'bricks_url' ) ?: '';
            $install_child      = (bool) $request->get_param( 'install_child_theme' );
            $settings           = $request->get_param( 'settings' ) ?: array();

            // Step 1: Install plugins (with independent error handling)
            if ( ! empty( $plugins ) && is_array( $plugins ) ) {
                try {
                    $plugin_result = $this->kesso->get_plugin_service()->install_plugins( $plugins );
                    if ( is_wp_error( $plugin_result ) ) {
                        $results['errors'][] = array(
                            'step'    => 'plugins',
                            'message' => $plugin_result->get_error_message(),
                        );
                    } else {
                        $results['plugins'] = $plugin_result;
                    }
                } catch ( Exception $e ) {
                    $results['errors'][] = array(
                        'step'    => 'plugins',
                        'message' => sprintf( __( 'Plugin installation error: %s', 'kesso-init' ), $e->getMessage() ),
                    );
                }
            }

            // Step 2: Install theme/builder
            $parent_theme = '';
            if ( ! empty( $builder ) ) {
                try {
                    if ( 'elementor' === $builder ) {
                        $theme_result = $this->kesso->get_theme_service()->install_elementor();
                        if ( is_wp_error( $theme_result ) ) {
                            $results['errors'][] = array(
                                'step'    => 'theme',
                                'message' => $theme_result->get_error_message(),
                            );
                        } else {
                            $results['theme'] = $theme_result;
                            $parent_theme = 'hello-elementor';
                        }
                    } elseif ( 'bricks' === $builder ) {
                        // Check if Bricks is already installed/active (file was uploaded via upload-bricks endpoint)
                        if ( kesso_init_is_theme_installed( 'bricks' ) ) {
                            // Bricks already installed, just set parent theme
                            $parent_theme = 'bricks';
                            $results['theme'] = array(
                                'theme'        => 'bricks',
                                'active_theme' => get_stylesheet() === 'bricks' ? 'bricks' : 'bricks',
                            );
                        } else {
                            // Bricks not installed - this shouldn't happen if file was uploaded
                            $results['errors'][] = array(
                                'step'    => 'theme',
                                'message' => __( 'Bricks theme not found. Please upload the Bricks ZIP file first.', 'kesso-init' ),
                            );
                        }
                    }
                } catch ( Exception $e ) {
                    $results['errors'][] = array(
                        'step'    => 'theme',
                        'message' => sprintf( __( 'Theme installation error: %s', 'kesso-init' ), $e->getMessage() ),
                    );
                }
            }

            // Step 3: Create and install child theme
            // Only create child theme for Bricks and only if Bricks installation succeeded
            if ( $install_child ) {
                try {
                    // Only proceed if Bricks was selected and successfully installed
                    if ( 'bricks' !== $builder ) {
                        $results['errors'][] = array(
                            'step'    => 'child_theme',
                            'message' => __( 'Child theme installation is only available for Bricks theme.', 'kesso-init' ),
                        );
                    } elseif ( empty( $parent_theme ) || 'bricks' !== $parent_theme ) {
                        // Bricks was selected but installation failed
                        $results['errors'][] = array(
                            'step'    => 'child_theme',
                            'message' => __( 'Cannot create child theme: Bricks theme installation failed or was not completed.', 'kesso-init' ),
                        );
                    } else {
                        // Bricks was successfully installed, proceed with child theme creation
                        // Check if child theme already exists for Bricks
                        $child_slug = $parent_theme . '-child';
                        if ( kesso_init_is_theme_installed( $child_slug ) ) {
                            // Child theme already exists, just activate it
                            switch_theme( $child_slug );
                            $results['child_theme'] = array(
                                'child_theme'  => $child_slug,
                                'parent_theme' => $parent_theme,
                                'active_theme' => $child_slug,
                                'status'      => 'already_exists',
                            );
                        } else {
                            // Create new child theme
                            $child_result = $this->kesso->get_theme_service()->create_and_install_child_theme( $parent_theme );
                            if ( is_wp_error( $child_result ) ) {
                                $results['errors'][] = array(
                                    'step'    => 'child_theme',
                                    'message' => $child_result->get_error_message(),
                                );
                            } else {
                                $results['child_theme'] = $child_result;
                            }
                        }
                    }
                } catch ( Exception $e ) {
                    $results['errors'][] = array(
                        'step'    => 'child_theme',
                        'message' => sprintf( __( 'Child theme creation error: %s', 'kesso-init' ), $e->getMessage() ),
                    );
                }
            }

            // Step 4: Update settings
            if ( ! empty( $settings ) && is_array( $settings ) ) {
                try {
                    // Ensure settings is an array (not object)
                    $settings_array = (array) $settings;
                    
                    // Only remove null values, but keep empty strings (they're valid for some settings)
                    $settings_array = array_filter( $settings_array, function( $value ) {
                        return $value !== null;
                    } );

                    if ( ! empty( $settings_array ) ) {
                        $settings_result = $this->kesso->get_settings_service()->update_settings( $settings_array );
                        if ( is_wp_error( $settings_result ) ) {
                            $results['errors'][] = array(
                                'step'    => 'settings',
                                'message' => $settings_result->get_error_message(),
                            );
                        } else {
                            $results['settings'] = array(
                                'updated' => true,
                                'keys'    => $settings_result['updated'] ?? array(),
                            );
                        }
                    } else {
                        // Settings array was empty after filtering
                        kesso_init_log( 'Settings array empty after filtering', $settings );
                    }
                } catch ( Exception $e ) {
                    $results['errors'][] = array(
                        'step'    => 'settings',
                        'message' => sprintf( __( 'Settings update error: %s', 'kesso-init' ), $e->getMessage() ),
                    );
                    kesso_init_log( 'Settings update exception', array(
                        'message' => $e->getMessage(),
                        'settings' => $settings,
                    ) );
                }
            }

            // Note: Wizard completion is now handled by the client-side sequential processor
            // to ensure it only marks as completed after all operations (including plugins) are done

        } catch ( Throwable $e ) {
            // Catch any unexpected errors (including fatal errors in PHP 7+)
            // This catches both Exception and Error in PHP 7+
            $results['errors'][] = array(
                'step'    => 'general',
                'message' => sprintf( __( 'Fatal error: %s in %s on line %d', 'kesso-init' ), $e->getMessage(), basename( $e->getFile() ), $e->getLine() ),
            );
            kesso_init_log( 'Batch endpoint fatal error', array(
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ) );
        } finally {
            // CRITICAL: Clean ALL output buffers before sending JSON
            // Discard everything that was output (translation updates, notices, etc.)
            $captured_output = '';
            while ( ob_get_level() > 0 ) {
                $buffer = ob_get_clean();
                if ( ! empty( $buffer ) ) {
                    $captured_output .= $buffer;
                }
            }
            
            // Log captured output for debugging (but don't send it)
            if ( ! empty( $captured_output ) ) {
                kesso_init_log( 'Output captured and discarded in batch endpoint', array(
                    'output_length' => strlen( $captured_output ),
                    'output_preview' => substr( $captured_output, 0, 500 ),
                    'contains_html' => ( strpos( $captured_output, '<' ) !== false ),
                    'contains_json' => ( strpos( $captured_output, '{' ) !== false ),
                ) );
            }

            // Restore error settings
            ini_set( 'display_errors', $original_display_errors );
            error_reporting( $original_error_reporting );
            
            // Restore time limit
            if ( $original_time_limit > 0 ) {
                @set_time_limit( $original_time_limit );
            }

            // Remove all filters and actions we added
            remove_filter( 'http_request_timeout', array( $this, 'set_http_timeout' ), 999 );
            remove_filter( 'http_request_args', array( $this, 'set_http_request_args' ), 999 );
            remove_filter( 'async_update_translation', '__return_false', 999 );
            remove_filter( 'upgrader_pre_download', array( $this, 'suppress_translation_updates' ), 999 );
            remove_filter( 'upgrader_process_complete', array( $this, 'prevent_translation_updates' ), 1 );
            remove_filter( 'wp_redirect', array( $this, 'suppress_redirects' ), 999 );
            remove_action( 'upgrader_process_complete', array( $this, 'suppress_translation_output' ), 999 );
            remove_filter( 'upgrader_pre_install', array( $this, 'suppress_translation_output_filter' ), 999 );
            remove_filter( 'upgrader_post_install', array( $this, 'suppress_translation_output_filter' ), 999 );
            remove_filter( 'pre_site_transient_update_core', array( $this, 'suppress_translation_checks' ), 999 );
            remove_filter( 'pre_site_transient_update_plugins', array( $this, 'suppress_translation_checks' ), 999 );
            remove_filter( 'pre_site_transient_update_themes', array( $this, 'suppress_translation_checks' ), 999 );
        }

        // Determine overall success
        $has_errors = ! empty( $results['errors'] );
        $message    = $has_errors
            ? __( 'Setup completed with some errors.', 'kesso-init' )
            : __( 'Setup completed successfully!', 'kesso-init' );

        // Build response data
        $response_data = kesso_init_api_response(
            ! $has_errors,
            $message,
            $results
        );

        // Validate JSON can be encoded before sending
        $json_string = wp_json_encode( $response_data );
        if ( false === $json_string ) {
            // JSON encoding failed - create error response
            $response_data = kesso_init_api_response(
                false,
                __( 'Failed to encode response data.', 'kesso-init' ),
                array( 'errors' => array( array( 'step' => 'general', 'message' => json_last_error_msg() ) ) )
            );
            $json_string = wp_json_encode( $response_data );
        }
        
        // CRITICAL: Ensure ALL output is cleaned before building response
        // Clean any remaining output buffers (in case something output after finally block)
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        
        // Log response size for debugging
        kesso_init_log( 'Batch endpoint response', array(
            'response_size' => strlen( $json_string ),
            'has_errors' => $has_errors,
            'operations_count' => count( $results['plugins']['installed'] ?? array() ) + count( $results['plugins']['activated'] ?? array() ),
        ) );

        // Build REST response
        $response = rest_ensure_response( $response_data );

        // Ensure we're sending JSON content type
        $response->header( 'Content-Type', 'application/json; charset=' . get_option( 'blog_charset' ) );
        
        // Store response for shutdown function (in case of fatal error)
        $this->last_response = $response_data;

        // Return response - DO NOT add output buffering here as it will swallow the JSON output
        return $response;
    }
    
    /**
     * Ensure JSON response is sent even on fatal errors
     * 
     * @param bool $ob_started Whether we started output buffering.
     */
    public function ensure_json_response( $ob_started = false ) {
        // Only run if we're in a REST API context and headers haven't been sent
        if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || headers_sent() ) {
            return;
        }
        
        // Check if there was a fatal error
        $error = error_get_last();
        if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
            // Clean any output
            if ( $ob_started && ob_get_level() > 0 ) {
                ob_end_clean();
            }
            
            // Send error response
            if ( ! headers_sent() ) {
                header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
                echo wp_json_encode( array(
                    'success' => false,
                    'message' => __( 'A fatal error occurred during setup, but operations may have completed. Please check your site.', 'kesso-init' ),
                    'data' => array(
                        'errors' => array(
                            array(
                                'step' => 'general',
                                'message' => sprintf( __( 'Fatal error: %s in %s on line %d', 'kesso-init' ), $error['message'], basename( $error['file'] ), $error['line'] ),
                            ),
                        ),
                    ),
                ) );
            }
        }
    }
    
    /**
     * Set HTTP request timeout
     *
     * @return int Timeout in seconds.
     */
    public function set_http_timeout() {
        return 15; // 15 seconds timeout
    }

    /**
     * Set HTTP request arguments with timeout
     *
     * @param array $args Request arguments.
     * @return array Modified arguments.
     */
    public function set_http_request_args( $args ) {
        $args['timeout'] = 15;
        return $args;
    }
    
    /**
     * Discard all output - callback for output buffer
     *
     * @param string $buffer Output buffer content.
     * @return string Always returns empty string to discard output.
     */
    public function discard_output( $buffer ) {
        // Discard all output - return empty string
        return '';
    }
    
    /**
     * Suppress translation updates during REST API calls
     *
     * @param bool|WP_Error $reply    Whether to bail without returning the package.
     * @param string         $package  The package file name.
     * @param WP_Upgrader    $upgrader The WP_Upgrader instance.
     * @return bool|WP_Error
     */
    public function suppress_translation_updates( $reply, $package, $upgrader ) {
        // If this is a translation update, suppress it during REST API calls
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            if ( strpos( $package, 'translation' ) !== false || strpos( $package, 'language-pack' ) !== false ) {
                // Return error to skip translation update
                return new WP_Error( 'translation_update_suppressed', __( 'Translation updates suppressed during REST API call.', 'kesso-init' ) );
            }
        }
        return $reply;
    }
    
    /**
     * Suppress redirects during REST API calls
     *
     * @param string $location Redirect location.
     * @return string|false Return false to prevent redirect.
     */
    public function suppress_redirects( $location ) {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false; // Prevent redirects during REST API calls
        }
        return $location;
    }
    
    /**
     * Suppress translation output during upgrader process
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $hook_extra Extra arguments.
     */
    public function suppress_translation_output( $upgrader, $hook_extra ) {
        // Suppress any output from translation updates
        if ( isset( $hook_extra['translations'] ) && ! empty( $hook_extra['translations'] ) ) {
            // Translation update detected - output is already buffered, will be discarded
        }
    }
    
    /**
     * Suppress translation output filter
     *
     * @param mixed $result Result value.
     * @param array $hook_extra Extra arguments.
     * @return mixed
     */
    public function suppress_translation_output_filter( $result, $hook_extra = array() ) {
        // Return result as-is, but output is buffered and will be discarded
        return $result;
    }
    
    /**
     * Prevent translation updates during REST API calls
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $hook_extra Extra arguments (passed by reference).
     */
    public function prevent_translation_updates( $upgrader, &$hook_extra ) {
        // If this is during a REST API call, prevent translation updates
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            // Clear translations from hook_extra to prevent WordPress from updating them
            if ( isset( $hook_extra['translations'] ) ) {
                $hook_extra['translations'] = array();
            }
        }
    }
    
    /**
     * Suppress translation checks during REST API calls
     *
     * @param mixed $pre_site_transient Pre-transient value.
     * @return mixed
     */
    public function suppress_translation_checks( $pre_site_transient ) {
        // During REST API calls, return false to skip translation checks
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false; // Skip transient, preventing translation update checks
        }
        return $pre_site_transient;
    }
}
