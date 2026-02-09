<?php
/**
 * Settings Update Service
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles WordPress settings updates
 */
class Kesso_Init_Settings_Service {

    /**
     * Update WordPress settings
     *
     * @param array $settings Settings to update.
     * @return array|WP_Error
     */
    public function update_settings( $settings ) {
        $updated = array();
        $errors  = array();

        foreach ( $settings as $key => $value ) {
            try {
                switch ( $key ) {
                    case 'blogname':
                    case 'blogdescription':
                        // Sanitize text fields
                        $sanitized = sanitize_text_field( $value );
                        $old_value = get_option( $key, '' );
                        $result = update_option( $key, $sanitized );
                        // update_option returns false if value didn't change, which is not an error
                        if ( false !== $result || $old_value === $sanitized ) {
                            $updated[] = $key;
                        } else {
                            $errors[] = $key;
                        }
                        break;

                    case 'WPLANG':
                        // Sanitize language code
                        $sanitized = sanitize_text_field( $value );
                        $old_value = get_option( $key, '' );
                        $result = update_option( $key, $sanitized );
                        if ( false !== $result || $old_value === $sanitized ) {
                            $updated[] = $key;
                        } else {
                            $errors[] = $key;
                        }
                        break;

                    case 'timezone_string':
                        // Sanitize timezone
                        $sanitized = sanitize_text_field( $value );
                        $old_value = get_option( $key, 'UTC' );
                        $result = update_option( $key, $sanitized );
                        if ( false !== $result || $old_value === $sanitized ) {
                            $updated[] = $key;
                        } else {
                            $errors[] = $key;
                        }
                        break;

                    case 'date_format':
                    case 'time_format':
                        // Sanitize format strings
                        $sanitized = sanitize_text_field( $value );
                        $old_value = get_option( $key, '' );
                        $result = update_option( $key, $sanitized );
                        if ( false !== $result || $old_value === $sanitized ) {
                            $updated[] = $key;
                        } else {
                            $errors[] = $key;
                        }
                        break;

                    case 'start_of_week':
                        // Sanitize as integer
                        $sanitized = absint( $value );
                        $old_value = get_option( $key, 0 );
                        $result = update_option( $key, $sanitized );
                        if ( false !== $result || $old_value === $sanitized ) {
                            $updated[] = $key;
                        } else {
                            $errors[] = $key;
                        }
                        break;

                    case 'site_icon':
                        // Handle site icon (favicon) - expects attachment ID
                        if ( ! empty( $value ) ) {
                            // Convert to integer if it's a string
                            $attachment_id = is_numeric( $value ) ? absint( $value ) : 0;
                            
                            if ( $attachment_id > 0 ) {
                                // Verify attachment exists and is an image
                                $attachment = get_post( $attachment_id );
                                if ( $attachment && 'attachment' === $attachment->post_type ) {
                                    $mime_type = get_post_mime_type( $attachment_id );
                                    if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
                                        // Update site icon
                                        $result = update_option( 'site_icon', $attachment_id );
                                        if ( false !== $result ) {
                                            $updated[] = $key;
                                        } else {
                                            $errors[] = $key;
                                        }
                                    } else {
                                        $errors[] = $key;
                                    }
                                } else {
                                    $errors[] = $key;
                                }
                            } else {
                                // Invalid attachment ID
                                $errors[] = $key;
                            }
                        } else {
                            // Remove site icon if empty
                            delete_option( 'site_icon' );
                            $updated[] = $key;
                        }
                        break;

                    case 'create_privacy_policy':
                        // Handle privacy policy page creation
                        if ( ! empty( $value ) && ( $value === true || $value === 'true' || $value === '1' || $value === 1 ) ) {
                            $page_result = $this->create_privacy_policy_page();
                            if ( is_wp_error( $page_result ) ) {
                                $errors[] = $key;
                                kesso_init_log( 'Privacy policy page creation failed', $page_result->get_error_message() );
                            } else {
                                $updated[] = $key;
                            }
                        }
                        // Don't add to errors if checkbox is not checked - it's optional
                        break;

                    default:
                        // Unknown setting key
                        $errors[] = $key;
                        break;
                }
            } catch ( Exception $e ) {
                // Catch any exceptions for this setting
                $errors[] = $key;
                kesso_init_log( sprintf( 'Error updating setting %s: %s', $key, $e->getMessage() ) );
            }
        }

        // Return partial success if some settings were updated
        if ( ! empty( $updated ) && ! empty( $errors ) ) {
            return array(
                'updated' => $updated,
                'errors'  => $errors,
            );
        }

        // Return error if all settings failed
        if ( ! empty( $errors ) && empty( $updated ) ) {
            return new WP_Error(
                'settings_update_failed',
                sprintf(
                    /* translators: %s: comma-separated list of setting keys */
                    __( 'Settings could not be updated: %s', 'kesso-init' ),
                    implode( ', ', $errors )
                ),
                array( 'updated' => $updated, 'errors' => $errors )
            );
        }

        // All settings updated successfully
        return array( 'updated' => $updated );
    }

    /**
     * Create privacy policy page as draft
     *
     * @return int|WP_Error Page ID on success, WP_Error on failure.
     */
    private function create_privacy_policy_page() {
        // Check if privacy policy page already exists
        $existing_page = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'any',
            'title'          => 'Privacy Policy',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );

        if ( ! empty( $existing_page ) ) {
            // Page already exists, return its ID
            return $existing_page[0];
        }

        // Privacy policy content
        $content = '<h3>Privacy Policy</h3>
We respect the privacy of users of this website and place great importance on protecting the personal information provided to us as part of the use of the website. This website is operated by a private business, located in <strong>[YOUR COUNTRY]</strong>. The operator of the website is responsible for the collection and processing of personal information as described in this Privacy Policy. This Privacy Policy is intended to explain how personal information is collected on the website, how it is used, and how we act to protect users\' privacy, in accordance with the provisions of the Protection of Privacy Law, 1981, and its amendments.

&nbsp;
<h3>Information Collection</h3>
As part of using the website, personal information may be collected when voluntarily provided by users, including through contact forms. The collected information may include contact details such as name, phone number, email address, and the content of the inquiry, depending on the fields completed by the user. Providing personal information is voluntary. However, failure to provide certain information may prevent us from responding to inquiries or providing requested services.

&nbsp;
<h3>Use of Information</h3>
The information provided by users may be used for the following purposes:
<ul>
 	<li>Contacting users and responding to inquiries</li>
 	<li>Handling requests received through the website</li>
 	<li>Improving services and user experience</li>
</ul>
The information will not be used for marketing communications unless the user has explicitly requested this and provided consent. Where required under applicable data protection laws, personal information is processed based on the user\'s consent or our legitimate interest in operating and improving the website and responding to inquiries.

&nbsp;
<h3>Data Retention</h3>
Personal information is retained only for as long as necessary to fulfill the purposes for which it was collected or as required under applicable laws.

&nbsp;
<h3>Disclosure of Information to Third Parties</h3>
Personal information collected on the website will not be shared with third parties, except in the following cases:
<ul>
 	<li>For the ongoing operation of the website and its services</li>
 	<li>In accordance with legal requirements or a court order</li>
 	<li>When necessary to protect the rights of the website or its users</li>
</ul>
Personal information may be stored or processed on servers located outside the user\'s country of residence, including by service providers operating in different jurisdictions, in accordance with applicable laws.


&nbsp;
<h3>Use of Analytics Tools and Third-Party Services</h3>
The website uses analytics and measurement services, including Google services (such as Google Analytics, Google Tag Manager, and additional services) and Microsoft Clarity services, and Meta services, for the purpose of collecting anonymous statistical data regarding website usage, improving user experience, and analyzing activity. These services may use cookies and similar technologies, in accordance with the privacy policies of those providers.

&nbsp;
<h3>Cookies</h3>
The website may use cookies for its ongoing operation, statistical analysis, improving user experience, and content customization. Users may modify their browser settings to block or delete cookies, subject to the possibility that some website functionality may not operate properly.

&nbsp;
<h3>External Links and Third-Party Services</h3>
The website may include links to external websites or third-party services, including the option to contact via third-party applications such as WhatsApp, if such a button appears on the website. The use of these services is subject to the privacy policies of the respective third parties, and the website has no control over or responsibility for their policies.

&nbsp;
<h3>Data Security</h3>
The website is secured using an SSL certificate and is hosted on recognized and secure server infrastructures. However, absolute immunity against security breaches or malfunctions cannot be guaranteed, and we act to minimize risks using reasonable and accepted measures.

&nbsp;
<h3>User Rights</h3>
In accordance with applicable laws, users may request to review, correct, or delete personal information relating to them by contacting us using the details below. Where applicable under data protection laws, users may also have additional rights, including the right to request access to or deletion of personal information and the right to lodge a complaint with a relevant data protection authority in their country of residence. Requests will be handled in accordance with applicable laws. We do not sell or share personal information for commercial purposes.

&nbsp;
<h3>Contact Regarding Privacy</h3>
For any questions, requests, or inquiries regarding privacy and personal information, you may contact:

Email: <strong>[YOUR EMAIL]</strong>
Phone: <strong>[YOUR PHONE]</strong>';

        // Create the page
        $page_data = array(
            'post_title'    => 'Privacy Policy',
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
        );

        $page_id = wp_insert_post( $page_data, true );

        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }

        return $page_id;
    }
}
