<?php
/**
 * Helper functions
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if a plugin is installed
 *
 * @param string $plugin_file Plugin file path (e.g., 'plugin-folder/plugin-file.php').
 * @return bool
 */
function kesso_init_is_plugin_installed( $plugin_file ) {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugins = get_plugins();
    return isset( $plugins[ $plugin_file ] );
}

/**
 * Check if a plugin is active
 *
 * @param string $plugin_file Plugin file path.
 * @return bool
 */
function kesso_init_is_plugin_active( $plugin_file ) {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    return is_plugin_active( $plugin_file );
}

/**
 * Check if a theme is installed
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function kesso_init_is_theme_installed( $theme_slug ) {
    $theme = wp_get_theme( $theme_slug );
    return $theme->exists();
}

/**
 * Check if a theme is active
 *
 * @param string $theme_slug Theme slug.
 * @return bool
 */
function kesso_init_is_theme_active( $theme_slug ) {
    return get_stylesheet() === $theme_slug;
}

/**
 * Get WordPress.org plugin download URL
 *
 * @param string $slug Plugin slug.
 * @return string
 */
function kesso_init_get_plugin_download_url( $slug ) {
    return sprintf( 'https://downloads.wordpress.org/plugin/%s.latest-stable.zip', $slug );
}

/**
 * Sanitize and validate a ZIP file
 *
 * @param array $file $_FILES array element.
 * @return array|WP_Error File info or error.
 */
function kesso_init_validate_zip_upload( $file ) {
    // Check for upload errors
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', __( 'File upload failed.', 'kesso-init' ) );
    }

    // Check file extension
    $file_info = wp_check_filetype( $file['name'], array( 'zip' => 'application/zip' ) );
    if ( ! $file_info['ext'] ) {
        return new WP_Error( 'invalid_type', __( 'Only ZIP files are allowed.', 'kesso-init' ) );
    }

    // Check MIME type
    $mime = mime_content_type( $file['tmp_name'] );
    $allowed_mimes = array( 'application/zip', 'application/x-zip-compressed', 'application/octet-stream' );
    if ( ! in_array( $mime, $allowed_mimes, true ) ) {
        return new WP_Error( 'invalid_mime', __( 'Invalid file type.', 'kesso-init' ) );
    }

    return array(
        'name'     => sanitize_file_name( $file['name'] ),
        'tmp_name' => $file['tmp_name'],
        'size'     => $file['size'],
    );
}

/**
 * Create a standard API response
 *
 * @param bool   $success Whether the operation was successful.
 * @param string $message Response message.
 * @param array  $data    Additional data.
 * @return array
 */
function kesso_init_api_response( $success, $message, $data = array() ) {
    return array(
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    );
}

/**
 * Get available WordPress languages
 *
 * @return array
 */
function kesso_init_get_available_languages() {
    require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    
    $translations = wp_get_available_translations();
    $languages    = array();

    // Add English as default
    $languages[] = array(
        'value' => '',
        'label' => 'English (United States)',
    );

    foreach ( $translations as $locale => $data ) {
        // Prefer English labels so the dropdown is consistently in English.
        $label = '';
        if ( isset( $data['english_name'] ) && is_string( $data['english_name'] ) && $data['english_name'] !== '' ) {
            $label = $data['english_name'];
        } elseif ( isset( $data['native_name'] ) && is_string( $data['native_name'] ) && $data['native_name'] !== '' ) {
            $label = $data['native_name'];
        } else {
            $label = $locale;
        }

        // Add locale code to disambiguate similar names.
        $label .= ' (' . $locale . ')';

        $languages[] = array(
            'value' => $locale,
            'label' => $label,
        );
    }

    // Sort by label (keep English default on top).
    $default = array_shift( $languages );
    usort(
        $languages,
        function( $a, $b ) {
            return strcasecmp( (string) $a['label'], (string) $b['label'] );
        }
    );
    array_unshift( $languages, $default );

    return $languages;
}

/**
 * Log a message for debugging
 *
 * @param string $message Log message.
 * @param mixed  $data    Optional data to log.
 */
function kesso_init_log( $message, $data = null ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $log_message = '[Kesso Init] ' . $message;
        if ( null !== $data ) {
            $log_message .= ' - ' . print_r( $data, true );
        }
        error_log( $log_message );
    }
}

/**
 * Check if a theme can be deleted
 *
 * WordPress prevents deletion of the active theme. This function checks
 * if a theme is currently active and provides a helpful error message.
 *
 * @param string $theme_slug Theme slug to check.
 * @return bool|WP_Error True if theme can be deleted, WP_Error if not.
 */
function kesso_init_can_delete_theme( $theme_slug ) {
    $active_theme = get_stylesheet();
    $parent_theme = get_template();
    
    // Check if it's the active theme
    if ( $active_theme === $theme_slug ) {
        return new WP_Error(
            'theme_is_active',
            sprintf(
                /* translators: %s: theme slug */
                __( 'Cannot delete theme "%s" because it is currently active. Please switch to another theme first.', 'kesso-init' ),
                $theme_slug
            )
        );
    }
    
    // Check if it's the parent theme of an active child theme
    if ( $active_theme !== $parent_theme && $parent_theme === $theme_slug ) {
        return new WP_Error(
            'theme_is_parent',
            sprintf(
                /* translators: %s: theme slug */
                __( 'Cannot delete theme "%s" because it is the parent of the currently active child theme. Please switch to another theme first.', 'kesso-init' ),
                $theme_slug
            )
        );
    }
    
    return true;
}

/**
 * Get theme deletion instructions
 *
 * @param string $theme_slug Theme slug.
 * @return string Instructions for deleting the theme.
 */
function kesso_init_get_theme_deletion_instructions( $theme_slug ) {
    $can_delete = kesso_init_can_delete_theme( $theme_slug );
    
    if ( is_wp_error( $can_delete ) ) {
        return $can_delete->get_error_message();
    }
    
    return sprintf(
        /* translators: %s: theme slug */
        __( 'Theme "%s" can be deleted. Go to Appearance > Themes and click "Delete" on the theme.', 'kesso-init' ),
        $theme_slug
    );
}

