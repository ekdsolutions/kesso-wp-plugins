<?php
/**
 * Wizard Page Template
 *
 * @package Kesso_Init
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

        <!-- Kesso Banner -->
        <div class="kesso-banner">
            <div class="kesso-banner-content">
                <?php esc_html_e( 'This plugin was developed and distributed with ❤️ for free use by', 'kesso-init' ); ?> 
                <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-banner-link">kesso.io</a>
            </div>
        </div>
<div class="wrap">
    <div class="kesso-init-app light">
        <main class="kesso-main">
            <div class="kesso-page-heading">
                <div class="kesso-heading-left">
                    <h1 class="kesso-title"><?php esc_html_e( 'Setup Wizard', 'kesso-init' ); ?></h1>
                    <p class="kesso-subtitle"><?php esc_html_e( 'Configure your entire WordPress site in one go. Review and apply all changes below.', 'kesso-init' ); ?></p>
                </div>
            </div>

            <form id="kesso-init-form">
                <div class="kesso-sections">
                    <!-- General Settings -->
                    <section class="kesso-card">
                        <div class="kesso-card-header">
                            <h2 class="kesso-card-title">
                                <span class="material-symbols-outlined kesso-icon" aria-hidden="true">tune</span>
                                <?php esc_html_e( 'General Settings', 'kesso-init' ); ?>
                            </h2>
                        </div>
                        <div class="kesso-card-body kesso-card-body--stack">
                            <div class="kesso-grid kesso-grid-2">
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Site Title', 'kesso-init' ); ?></span>
                                    <input class="kesso-control" type="text" id="blogname" name="blogname" placeholder="<?php esc_attr_e( 'e.g. My Awesome Agency', 'kesso-init' ); ?>" />
                                </label>
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Tagline', 'kesso-init' ); ?></span>
                                    <input class="kesso-control" type="text" id="blogdescription" name="blogdescription" placeholder="<?php esc_attr_e( 'e.g. Building the future', 'kesso-init' ); ?>" />
                                </label>
                            </div>

                            <div class="kesso-grid kesso-grid-2">
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Site Language', 'kesso-init' ); ?></span>
                                    <select class="kesso-control kesso-select" id="WPLANG" name="WPLANG">
                                        <option value=""><?php esc_html_e( 'English (United States)', 'kesso-init' ); ?></option>
                                    </select>
                                </label>
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Timezone', 'kesso-init' ); ?></span>
                                    <div class="kesso-timezone-wrapper">
                                        <input type="text" class="kesso-control kesso-timezone-input" id="timezone_string" placeholder="<?php esc_attr_e( 'Search timezone...', 'kesso-init' ); ?>" autocomplete="off" />
                                        <input type="hidden" id="timezone_string_value" name="timezone_string" value="" />
                                        <div class="kesso-timezone-dropdown" id="timezone_dropdown" style="display: none;"></div>
                                    </div>
                                </label>
                            </div>

                            <div class="kesso-grid kesso-grid-2">
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Date Format', 'kesso-init' ); ?></span>
                                    <select class="kesso-control kesso-select" id="date_format" name="date_format"></select>
                                </label>
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Time Format', 'kesso-init' ); ?></span>
                                    <select class="kesso-control kesso-select" id="time_format" name="time_format"></select>
                                </label>
                            </div>

                            <div class="kesso-grid kesso-grid-2">
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Start of Week', 'kesso-init' ); ?></span>
                                    <select class="kesso-control kesso-select" id="start_of_week" name="start_of_week"></select>
                                </label>
                                <label class="kesso-field">
                                    <span class="kesso-label"><?php esc_html_e( 'Create Privacy Policy Page', 'kesso-init' ); ?></span>
                                    <label class="kesso-checkbox-label">
                                        <input type="checkbox" id="create_privacy_policy" name="create_privacy_policy" value="1" />
                                        <span class="kesso-checkbox-text"><?php esc_html_e( 'Draft page with universal privacy policy content.', 'kesso-init' ); ?></span>
                                    </label>
                                </label>
                            </div>

                            <div class="kesso-field">
                                <span class="kesso-label"><?php esc_html_e( 'Site Favicon', 'kesso-init' ); ?></span>
                                <div class="kesso-favicon-uploader" id="kesso-favicon-uploader" role="button" tabindex="0">
                                    <div class="kesso-favicon-icon" id="kesso-favicon-preview" aria-hidden="true">
                                        <span class="material-symbols-outlined kesso-favicon-symbol">image</span>
                                    </div>
                                    <div class="kesso-favicon-copy">
                                        <p class="kesso-favicon-title"><?php esc_html_e( 'Click to upload or drag and drop', 'kesso-init' ); ?></p>
                                        <p class="kesso-favicon-subtitle"><?php esc_html_e( 'SVG, PNG, or JPG (max. 512x512px)', 'kesso-init' ); ?></p>
                                    </div>
                                    <button type="button" class="kesso-btn kesso-btn--secondary" id="kesso-favicon-button">
                                        <?php esc_html_e( 'Select File', 'kesso-init' ); ?>
                                    </button>
                                    <input type="hidden" id="site_icon" name="site_icon" value="" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Page Builder & Theme -->
                    <section class="kesso-card">
                        <div class="kesso-card-header">
                            <h2 class="kesso-card-title">
                                <span class="material-symbols-outlined kesso-icon" aria-hidden="true">architecture</span>
                                <?php esc_html_e( 'Page Builder & Theme', 'kesso-init' ); ?>
                            </h2>
                        </div>
                        <div class="kesso-card-body">
                            <div class="kesso-grid kesso-grid-2 kesso-builder-grid">
                                <label class="kesso-builder-card" data-builder="bricks">
                                    <input type="radio" id="builder-bricks" name="builder" value="bricks" class="kesso-builder-input" />
                                    <span class="kesso-builder-check material-symbols-outlined" aria-hidden="true">check_circle</span>
                                    <div class="kesso-builder-iconbox" aria-hidden="true">
                                        <span class="material-symbols-outlined kesso-builder-symbol">grid_view</span>
                                    </div>
                                    <h3 class="kesso-builder-name"><?php esc_html_e( 'Bricks Builder', 'kesso-init' ); ?></h3>
                                    <p class="kesso-builder-desc"><?php esc_html_e( 'High performance, visual site builder for WordPress professionals.', 'kesso-init' ); ?></p>
                                    <span class="kesso-builder-badge" aria-hidden="true"></span>
                                </label>

                                <label class="kesso-builder-card" data-builder="elementor">
                                    <input type="radio" id="builder-elementor" name="builder" value="elementor" class="kesso-builder-input" />
                                    <span class="kesso-builder-check material-symbols-outlined" aria-hidden="true">check_circle</span>
                                    <div class="kesso-builder-iconbox" aria-hidden="true">
                                        <span class="material-symbols-outlined kesso-builder-symbol kesso-builder-symbol--muted">layers</span>
                                    </div>
                                    <h3 class="kesso-builder-name"><?php esc_html_e( 'Elementor', 'kesso-init' ); ?></h3>
                                    <p class="kesso-builder-desc"><?php esc_html_e( 'The world\'s leading WordPress website builder platform.', 'kesso-init' ); ?></p>
                                    <span class="kesso-builder-badge" aria-hidden="true"></span>
                                </label>
                            </div>

                            <!-- Bricks upload (full width under builder grid) -->
                            <label class="kesso-favicon-uploader" id="kesso-bricks-upload" style="display:none;" for="bricks_zip">
                                <span class="kesso-favicon-icon" aria-hidden="true">
                                    <span class="material-symbols-outlined kesso-upload-symbol">upload_file</span>
                                </span>
                                <span class="kesso-favicon-copy">
                                    <span class="kesso-favicon-title"><?php esc_html_e( 'Upload Bricks ZIP', 'kesso-init' ); ?></span>
                                    <span class="kesso-favicon-subtitle"><?php esc_html_e( 'Choose the ZIP file you downloaded from your Bricks account.', 'kesso-init' ); ?></span>
                                </span>
                                <span class="kesso-btn kesso-btn--secondary" aria-hidden="true"><?php esc_html_e( 'Select File', 'kesso-init' ); ?></span>
                                <input class="kesso-upload-input" type="file" id="bricks_zip" name="bricks_zip" accept=".zip" />
                            </label>

                            <div class="kesso-child-theme-row">
                                <input type="checkbox" id="install_child_theme" name="install_child_theme" value="1" class="kesso-child-input" />
                                <label class="kesso-child-label" for="install_child_theme">
                                    <span class="kesso-child-title"><?php esc_html_e( 'Install Child Theme', 'kesso-init' ); ?></span>
                                    <span class="kesso-child-subtitle"><?php esc_html_e( 'Recommended for safe theme customizations and updates.', 'kesso-init' ); ?></span>
                                </label>
                                <span class="kesso-child-theme-badge" aria-hidden="true"></span>
                            </div>
                        </div>
                    </section>

                    <!-- Essential Plugins -->
                    <section class="kesso-card">
                        <div class="kesso-card-header kesso-card-header--row">
                            <h2 class="kesso-card-title">
                                <span class="material-symbols-outlined kesso-icon" aria-hidden="true">extension</span>
                                <?php esc_html_e( 'Essential Plugins', 'kesso-init' ); ?>
                            </h2>

                            <div class="kesso-plugins-actions">
                                <button type="button" class="kesso-btn kesso-btn--ghost" id="kesso-select-all"><?php esc_html_e( 'Select all', 'kesso-init' ); ?></button>
                                <button type="button" class="kesso-btn kesso-btn--ghost" id="kesso-clear-selection"><?php esc_html_e( 'Clear', 'kesso-init' ); ?></button>
                            </div>

                            <span class="kesso-card-meta" id="kesso-plugin-count">0 <?php esc_html_e( 'selected', 'kesso-init' ); ?></span>
                        </div>

                        <div class="kesso-card-body kesso-plugins-shell">
                            <div class="kesso-plugins-grid" id="kesso-plugins-grid">
                                <!-- Plugins injected by admin.js -->
                            </div>
                        </div>
                    </section>

                    <!-- Kesso Plugins -->
                    <section class="kesso-card">
                        <div class="kesso-card-header kesso-card-header--row">
                            <h2 class="kesso-card-title">
                                <span class="material-symbols-outlined kesso-icon" aria-hidden="true">code</span>
                                <?php esc_html_e( 'Kesso Plugins', 'kesso-init' ); ?>
                            </h2>

                            <div class="kesso-plugins-actions">
                                <button type="button" class="kesso-btn kesso-btn--ghost" id="kesso-select-all-kesso"><?php esc_html_e( 'Select all', 'kesso-init' ); ?></button>
                                <button type="button" class="kesso-btn kesso-btn--ghost" id="kesso-clear-selection-kesso"><?php esc_html_e( 'Clear', 'kesso-init' ); ?></button>
                            </div>

                            <span class="kesso-card-meta" id="kesso-kesso-plugin-count">0 <?php esc_html_e( 'selected', 'kesso-init' ); ?></span>
                        </div>

                        <div class="kesso-card-body kesso-plugins-shell">
                            <div class="kesso-plugins-grid" id="kesso-kesso-plugins-grid">
                                <!-- Kesso Plugins injected by admin.js -->
                            </div>
                        </div>
                    </section>
                </div>

                <div id="kesso-results" class="kesso-results" style="display:none;"></div>
            </form>

            <footer class="kesso-footer glass-footer" role="contentinfo">
                <div class="kesso-footer-inner">
                    <div class="kesso-footer-left">
                        <div class="kesso-footer-status">
                            <span class="kesso-footer-dot" aria-hidden="true"></span>
                            <span class="kesso-footer-label" id="kesso-progress-text"><?php esc_html_e( 'Ready to Launch', 'kesso-init' ); ?></span>
                        </div>
                        <p class="kesso-footer-subtitle"><?php esc_html_e( 'Applying these settings will refresh the dashboard.', 'kesso-init' ); ?></p>
                    </div>

                    <div class="kesso-footer-right">
                        <button type="button" class="kesso-btn kesso-btn--primary" id="kesso-apply-all">
                            <?php esc_html_e( 'Apply All Changes', 'kesso-init' ); ?>
                        </button>
                    </div>
                </div>
            </footer>

            <!-- Progress modal (shown during Apply process) -->
            <div class="kesso-modal" id="kesso-progress-modal" aria-hidden="true">
                <div class="kesso-modal-backdrop" data-kesso-close="1"></div>
                <div class="kesso-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="kesso-progress-title">
                    <div class="kesso-modal-header">
                        <div class="kesso-modal-title-wrap">
                            <div class="kesso-modal-title" id="kesso-progress-title"><?php esc_html_e( 'Applying Changes', 'kesso-init' ); ?></div>
                            <div class="kesso-modal-subtitle" id="kesso-progress-subtitle"><?php esc_html_e( 'Please keep this tab open while we configure your site.', 'kesso-init' ); ?></div>
                        </div>
                        <button type="button" class="kesso-modal-close" id="kesso-progress-close" aria-label="<?php esc_attr_e( 'Close', 'kesso-init' ); ?>" disabled>
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                    </div>
                    <div class="kesso-modal-body">
                        <div class="kesso-progress-card is-running" id="kesso-progress-card">
                            <span class="kesso-progress-indicator" aria-hidden="true"></span>
                            <div class="kesso-progress-copy">
                                <div class="kesso-progress-label"><?php esc_html_e( 'Current step', 'kesso-init' ); ?></div>
                                <div class="kesso-progress-step" id="kesso-progress-current-step"><?php esc_html_e( 'Settings', 'kesso-init' ); ?></div>
                            </div>
                        </div>

                        <div class="kesso-modal-actions">
                            <a class="kesso-btn kesso-btn--secondary kesso-modal-action"
                               id="kesso-progress-go-plugins"
                               href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"
                               style="display:none;">
                                <?php esc_html_e( 'Go to Plugins', 'kesso-init' ); ?>
                                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
