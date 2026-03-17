<?php
/**
 * Notice Collector Class
 *
 * @package Kesso_Msg
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Collects and stores admin notices
 */
class Kesso_Msg_Notice_Collector {

    /**
     * Get the transient key scoped to the current user.
     * Each admin has their own notice pool so that one user's notices don't bleed into another's.
     *
     * @return string
     */
    private function get_transient_key() {
        return 'kesso_msg_notices_' . get_current_user_id();
    }

    /**
     * Generate a stable notice ID from its type and message content.
     * Algorithm: DJB2-XOR (hash * 33 ^ charcode), masked to 32-bit unsigned.
     * The JavaScript counterpart uses the identical algorithm so IDs match
     * between client-sent notices and server-stored ones.
     *
     * @param string $message Notice message.
     * @param string $type    Notice type.
     * @return string
     */
    private function generate_notice_id( $message, $type ) {
        $str  = $type . '|' . substr( $message, 0, 100 );
        $hash = 5381;
        $len  = strlen( $str );
        for ( $i = 0; $i < $len; $i++ ) {
            $hash = ( ( $hash * 33 ) ^ ord( $str[ $i ] ) ) & 0xFFFFFFFF;
        }
        return 'kesso-msg-' . dechex( $hash );
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_head', array( $this, 'hide_notices_in_popup' ) );
        add_action( 'wp_ajax_kesso_msg_save_notices', array( $this, 'ajax_save_notices' ) );
        add_action( 'wp_ajax_kesso_msg_get_notices', array( $this, 'ajax_get_notices' ) );
        add_action( 'wp_ajax_kesso_msg_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'wp_ajax_kesso_msg_hide_notice', array( $this, 'ajax_hide_notice' ) );
        add_action( 'wp_ajax_kesso_msg_bulk_dismiss', array( $this, 'ajax_bulk_dismiss' ) );
        add_action( 'wp_ajax_kesso_msg_bulk_hide', array( $this, 'ajax_bulk_hide' ) );
    }

    /**
     * Hide notices that are set to show in popup
     */
    public function hide_notices_in_popup() {
        $notice_types = array( 'error', 'warning', 'success', 'info', 'update-nag' );
        $css_rules = array();

        foreach ( $notice_types as $type ) {
            $option_name = 'kesso_msg_show_' . $type;
            $show_in_popup = (bool) get_option( $option_name, true );

            // If set to show in popup, hide the regular notice
            if ( $show_in_popup ) {
                // Hide notices of this type
                $css_rules[] = ".notice.notice-{$type}:not(.kesso-msg-keep-visible), .notice-{$type}:not(.kesso-msg-keep-visible), .{$type}:not(.kesso-msg-keep-visible) { display: none !important; }";
                
                // Also handle update-nag specifically
                if ( 'update-nag' === $type ) {
                    $css_rules[] = ".update-nag:not(.kesso-msg-keep-visible) { display: none !important; }";
                }
                
                // WooCommerce specific notices
                if ( 'error' === $type ) {
                    $css_rules[] = ".woocommerce-error:not(.kesso-msg-keep-visible) { display: none !important; }";
                } elseif ( 'success' === $type ) {
                    $css_rules[] = ".woocommerce-message:not(.kesso-msg-keep-visible) { display: none !important; }";
                } elseif ( 'info' === $type ) {
                    $css_rules[] = ".woocommerce-info:not(.kesso-msg-keep-visible) { display: none !important; }";
                }
            }
        }

        if ( ! empty( $css_rules ) ) {
            echo '<style id="kesso-msg-hide-notices">' . implode( "\n", $css_rules ) . '</style>';
        }
    }

    /**
     * AJAX handler to save collected notices
     */
    public function ajax_save_notices() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notices = isset( $_POST['notices'] ) ? json_decode( stripslashes( $_POST['notices'] ), true ) : array();

        if ( ! is_array( $notices ) ) {
            $notices = array();
        }

        // Sanitize each notice before storage to prevent stored XSS
        foreach ( $notices as &$notice ) {
            if ( isset( $notice['html'] ) ) {
                $notice['html'] = wp_kses_post( $notice['html'] );
            }
            if ( isset( $notice['message'] ) ) {
                $notice['message'] = sanitize_text_field( $notice['message'] );
            }
            if ( isset( $notice['type'] ) ) {
                $notice['type'] = sanitize_key( $notice['type'] );
            }
        }
        unset( $notice );

        // Get existing notices (per-user)
        $existing = get_transient( $this->get_transient_key() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        // Get dismissed notice IDs
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        // Create a map of existing notice IDs for quick lookup
        $existing_ids = array();
        foreach ( $existing as $notice ) {
            if ( isset( $notice['id'] ) ) {
                $existing_ids[ $notice['id'] ] = true;
            }
        }

        // Add only new notices (not already in existing, and not dismissed)
        $new_notices = array();
        foreach ( $notices as $notice ) {
            if ( ! isset( $notice['id'] ) ) {
                continue;
            }
            
            // Skip if already exists or is dismissed
            if ( isset( $existing_ids[ $notice['id'] ] ) || in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }
            
            $new_notices[] = $notice;
            $existing_ids[ $notice['id'] ] = true;
        }

        // Merge new notices with existing
        $all_notices = array_merge( $existing, $new_notices );
        
        // Remove dismissed notices
        $all_notices = array_filter( $all_notices, function( $notice ) use ( $dismissed ) {
            return ! isset( $notice['id'] ) || ! in_array( $notice['id'], $dismissed, true );
        } );
        
        // Keep only last 50 notices
        $all_notices = array_slice( array_values( $all_notices ), -50 );

        // Store for 24 hours (per-user)
        set_transient( $this->get_transient_key(), $all_notices, DAY_IN_SECONDS );

        // Calculate filtered count (same logic as ajax_get_notices)
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }
        
        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        $filtered_count = 0;
        foreach ( $all_notices as $notice ) {
            // Generate ID if missing (backward compat for old stored notices)
            if ( ! isset( $notice['id'] ) || empty( $notice['id'] ) ) {
                $message = isset( $notice['message'] ) ? $notice['message'] : '';
                $type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
                $notice['id'] = ! empty( $message )
                    ? $this->generate_notice_id( $message, $type )
                    : 'kesso-msg-' . uniqid();
            }
            
            // Skip if dismissed
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }
            
            // Skip if hidden (hidden notices don't count)
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $hidden, true ) ) {
                continue;
            }
            
            // Skip if not set to show in popup
            if ( ! $this->should_show_in_popup( $notice['type'] ) ) {
                continue;
            }
            
            $filtered_count++;
        }

        wp_send_json_success( array( 'count' => $filtered_count ) );
    }


    /**
     * AJAX handler to get notices
     */
    public function ajax_get_notices() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notices = get_transient( $this->get_transient_key() );
        if ( ! is_array( $notices ) ) {
            $notices = array();
        }

        // Get dismissed and hidden notices for current user
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        // Filter notices based on settings, dismissed, and hidden status
        $filtered_notices = array();
        foreach ( $notices as $notice ) {
            // Generate ID if missing (backward compat for old stored notices)
            if ( ! isset( $notice['id'] ) || empty( $notice['id'] ) ) {
                $message = isset( $notice['message'] ) ? $notice['message'] : '';
                $type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
                $notice['id'] = ! empty( $message )
                    ? $this->generate_notice_id( $message, $type )
                    : 'kesso-msg-' . uniqid();
            }
            
            // Skip if dismissed
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }
            
            // Skip if not set to show in popup
            if ( ! $this->should_show_in_popup( $notice['type'] ) ) {
                continue;
            }
            
            // Include hidden notices but mark them as hidden
            $notice['is_hidden'] = isset( $notice['id'] ) && in_array( $notice['id'], $hidden, true );
            
            $filtered_notices[] = $notice;
        }

        // Calculate count excluding hidden notices
        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }
        
        $count = 0;
        foreach ( $filtered_notices as $notice ) {
            if ( ! isset( $notice['is_hidden'] ) || ! $notice['is_hidden'] ) {
                $count++;
            }
        }

        wp_send_json_success( array(
            'notices' => $filtered_notices,
            'count'   => $count,
        ) );
    }

    /**
     * Check if notice type should be shown in popup
     *
     * @param string $type Notice type.
     * @return bool
     */
    private function should_show_in_popup( $type ) {
        $option_name = 'kesso_msg_show_' . $type;
        return (bool) get_option( $option_name, true );
    }

    /**
     * AJAX handler to dismiss a notice
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : '';
        
        if ( empty( $notice_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notice ID', 'kesso-msg' ) ) );
        }

        // Get dismissed notices for current user
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        // Add to dismissed list if not already there
        if ( ! in_array( $notice_id, $dismissed, true ) ) {
            $dismissed[] = $notice_id;
            update_user_meta( get_current_user_id(), 'kesso_msg_dismissed', $dismissed );
        }

        // Get updated notice count
        $count = $this->get_filtered_count();

        wp_send_json_success( array(
            'count' => $count,
        ) );
    }

    /**
     * AJAX handler to hide a notice (remove from count but keep visible)
     */
    public function ajax_hide_notice() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : '';
        
        if ( empty( $notice_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notice ID', 'kesso-msg' ) ) );
        }

        // Get hidden notices for current user
        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        // Add to hidden list if not already there
        if ( ! in_array( $notice_id, $hidden, true ) ) {
            $hidden[] = $notice_id;
            update_user_meta( get_current_user_id(), 'kesso_msg_hidden', $hidden );
        }

        // Get updated notice count (excluding hidden)
        $count = $this->get_filtered_count();

        wp_send_json_success( array(
            'count' => $count,
        ) );
    }

    /**
     * AJAX handler to bulk dismiss notices
     */
    public function ajax_bulk_dismiss() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notice_ids = isset( $_POST['notice_ids'] ) ? $_POST['notice_ids'] : array();
        
        if ( ! is_array( $notice_ids ) || empty( $notice_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notice IDs', 'kesso-msg' ) ) );
        }

        // Sanitize notice IDs
        $notice_ids = array_map( 'sanitize_text_field', $notice_ids );

        // Get dismissed notices for current user
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        // Add to dismissed list
        foreach ( $notice_ids as $notice_id ) {
            if ( ! empty( $notice_id ) && ! in_array( $notice_id, $dismissed, true ) ) {
                $dismissed[] = $notice_id;
            }
        }
        
        update_user_meta( get_current_user_id(), 'kesso_msg_dismissed', $dismissed );

        // Get updated notice count
        $count = $this->get_filtered_count();

        wp_send_json_success( array(
            'count' => $count,
        ) );
    }

    /**
     * AJAX handler to bulk hide notices
     */
    public function ajax_bulk_hide() {
        check_ajax_referer( 'kesso_msg_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'kesso-msg' ) ) );
        }

        $notice_ids = isset( $_POST['notice_ids'] ) ? $_POST['notice_ids'] : array();
        
        if ( ! is_array( $notice_ids ) || empty( $notice_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid notice IDs', 'kesso-msg' ) ) );
        }

        // Sanitize notice IDs
        $notice_ids = array_map( 'sanitize_text_field', $notice_ids );

        // Get hidden notices for current user
        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        // Add to hidden list
        foreach ( $notice_ids as $notice_id ) {
            if ( ! empty( $notice_id ) && ! in_array( $notice_id, $hidden, true ) ) {
                $hidden[] = $notice_id;
            }
        }
        
        update_user_meta( get_current_user_id(), 'kesso_msg_hidden', $hidden );

        // Get updated notice count (excluding hidden)
        $count = $this->get_filtered_count();

        wp_send_json_success( array(
            'count' => $count,
        ) );
    }

    /**
     * Get filtered notice count (excluding dismissed and hidden)
     *
     * @return int
     */
    private function get_filtered_count() {
        $notices = get_transient( $this->get_transient_key() );
        if ( ! is_array( $notices ) ) {
            $notices = array();
        }

        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        $hidden = get_user_meta( get_current_user_id(), 'kesso_msg_hidden', true );
        if ( ! is_array( $hidden ) ) {
            $hidden = array();
        }

        $count = 0;
        foreach ( $notices as $notice ) {
            // Generate ID if missing (backward compat for old stored notices)
            if ( ! isset( $notice['id'] ) || empty( $notice['id'] ) ) {
                $message = isset( $notice['message'] ) ? $notice['message'] : '';
                $type    = isset( $notice['type'] ) ? $notice['type'] : 'info';
                $notice['id'] = ! empty( $message )
                    ? $this->generate_notice_id( $message, $type )
                    : 'kesso-msg-' . uniqid();
            }
            
            // Skip if dismissed
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }
            
            // Skip if hidden
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $hidden, true ) ) {
                continue;
            }
            
            // Skip if not set to show in popup
            if ( ! $this->should_show_in_popup( $notice['type'] ) ) {
                continue;
            }
            
            $count++;
        }

        return $count;
    }
}

