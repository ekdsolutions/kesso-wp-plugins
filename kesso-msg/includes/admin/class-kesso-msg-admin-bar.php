<?php
/**
 * Admin Bar Integration Class
 *
 * @package Kesso_Msg
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin bar notification bell icon
 */
class Kesso_Msg_Admin_Bar {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_bar_menu', array( $this, 'add_notification_bell' ), 100 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Add notification bell to admin bar
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
     */
    public function add_notification_bell( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get notice count
        $notice_count = $this->get_notice_count();

        // Add to top-secondary (right side) after profile
        $wp_admin_bar->add_node( array(
            'id'     => 'kesso-msg-notifications',
            'parent' => 'top-secondary',
            'title'  => $this->get_bell_icon( $notice_count ),
            'href'   => '#',
            'meta'   => array(
                'class' => 'kesso-msg-bell-wrapper',
                'title' => __( 'View notifications', 'kesso-msg' ),
            ),
        ) );
    }

    /**
     * Get bell icon HTML
     *
     * @param int $count Notice count.
     * @return string
     */
    private function get_bell_icon( $count ) {
        $badge = '';
        if ( $count > 0 ) {
            $badge = '<span class="kesso-msg-bell-badge">' . esc_html( $count > 99 ? '99+' : $count ) . '</span>';
        }

        return sprintf(
            '<span class="kesso-msg-bell-icon" aria-label="%s">%s</span>%s',
            esc_attr__( 'Notifications', 'kesso-msg' ),
            $this->get_svg_icon(),
            $badge
        );
    }

    /**
     * Get SVG bell icon
     *
     * @return string
     */
    private function get_svg_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>';
    }

    /**
     * Get notice count
     *
     * @return int
     */
    private function get_notice_count() {
        $notices = get_transient( 'kesso_msg_notices' );
        if ( ! $notices || ! is_array( $notices ) ) {
            return 0;
        }

        // Get dismissed notices for current user
        $dismissed = get_user_meta( get_current_user_id(), 'kesso_msg_dismissed', true );
        if ( ! is_array( $dismissed ) ) {
            $dismissed = array();
        }

        $count = 0;
        foreach ( $notices as $notice ) {
            // Generate ID if missing (for backward compatibility)
            if ( ! isset( $notice['id'] ) || empty( $notice['id'] ) ) {
                $message = isset( $notice['message'] ) ? $notice['message'] : '';
                $type = isset( $notice['type'] ) ? $notice['type'] : 'info';
                if ( ! empty( $message ) ) {
                    $str = $type . '|' . substr( $message, 0, 100 );
                    $notice['id'] = 'kesso-msg-' . abs( crc32( $str ) );
                } else {
                    $notice['id'] = 'kesso-msg-' . uniqid();
                }
            }
            
            // Skip if dismissed
            if ( isset( $notice['id'] ) && in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }
            
            // Skip if not set to show in popup
            if ( ! isset( $notice['type'] ) || ! $this->should_show_in_popup( $notice['type'] ) ) {
                continue;
            }
            
            $count++;
        }

        return $count;
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
     * Enqueue assets
     *
     * @param string $hook Current hook.
     */
    public function enqueue_assets( $hook ) {
        // Enqueue admin bar styles
        wp_enqueue_style(
            'kesso-msg-admin-bar',
            KESSO_MSG_URL . 'assets/css/admin-bar.css',
            array(),
            KESSO_MSG_VERSION
        );

        // Enqueue notice collector script (runs on all admin pages)
        wp_enqueue_script(
            'kesso-msg-collector',
            KESSO_MSG_URL . 'assets/js/notice-collector.js',
            array( 'jquery' ),
            KESSO_MSG_VERSION,
            true
        );

        wp_localize_script(
            'kesso-msg-collector',
            'kessoMsgCollector',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'kesso_msg_nonce' ),
            )
        );

        // Enqueue admin bar scripts
        wp_enqueue_script(
            'kesso-msg-admin-bar',
            KESSO_MSG_URL . 'assets/js/admin-bar.js',
            array( 'jquery', 'kesso-msg-collector' ),
            KESSO_MSG_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'kesso-msg-admin-bar',
            'kessoMsgConfig',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'kesso_msg_nonce' ),
            )
        );
    }
}

