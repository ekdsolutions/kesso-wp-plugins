/**
 * Kesso Cookies Frontend JavaScript
 * GDPR-compliant cookie consent management
 */
(function(window, document) {
    'use strict';

    // Check if kessoCookies is already defined
    if (typeof window.kessoCookies !== 'undefined') {
        return;
    }

    // Initialize the cookie consent system
    const KessoCookies = {
        config: null,
        consent: null,
        scripts: {
            queue: [],
            loaded: {}
        },

        /**
         * Initialize the cookie consent system
         */
        init: function() {
            // Get configuration from localized script
            if (typeof kessoCookiesConfig !== 'undefined') {
                this.config = kessoCookiesConfig;
            } else {
                // Use defaults if config not found
                this.config = {
                    cookieName: 'kesso_cookies_consent',
                    cookieVersion: '1.0',
                    cookieExpiry: 180
                };
            }

            // Load saved consent
            this.loadConsent();

            // Initialize UI
            this.initUI();

            // Process queued scripts
            this.processScriptQueue();
        },

        /**
         * Load consent from cookie or localStorage
         */
        loadConsent: function() {
            const cookieName = this.config ? this.config.cookieName : 'kesso_cookies_consent';
            const cookieVersion = this.config ? this.config.cookieVersion : '1.0';

            // Try cookie first
            const cookie = this.getCookie(cookieName);
            if (cookie) {
                try {
                    const parsed = JSON.parse(decodeURIComponent(cookie));
                    // Invalidate stored consent if the version has changed
                    if (parsed && parsed.version === cookieVersion) {
                        this.consent = parsed;
                        return;
                    }
                } catch (e) {
                    // Failed to parse consent cookie
                }
            }

            // Try localStorage
            try {
                const stored = localStorage.getItem(cookieName);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (parsed && parsed.version === cookieVersion) {
                        this.consent = parsed;
                        return;
                    }
                }
            } catch (e) {
                // localStorage may not be available
            }

            // No valid consent found
            this.consent = null;
        },

        /**
         * Save consent to cookie and localStorage
         */
        saveConsent: function(consentData) {
            const cookieName = this.config ? this.config.cookieName : 'kesso_cookies_consent';
            const cookieVersion = this.config ? this.config.cookieVersion : '1.0';
            const expiryDays = this.config ? (this.config.cookieExpiry || 180) : 180;

            // Store previous consent for event
            const previousConsent = this.consent ? JSON.parse(JSON.stringify(this.consent)) : null;

            this.consent = {
                essential: true, // Always true
                analytics: consentData.analytics || false,
                marketing: consentData.marketing || false,
                timestamp: Date.now(),
                version: cookieVersion
            };

            const consentString = JSON.stringify(this.consent);

            // Save to cookie with Secure flag on HTTPS
            const expiryDate = new Date();
            expiryDate.setTime(expiryDate.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
            const secureFlag = (location.protocol === 'https:') ? '; Secure' : '';
            document.cookie = `${cookieName}=${encodeURIComponent(consentString)}; expires=${expiryDate.toUTCString()}; path=/; SameSite=Lax${secureFlag}`;

            // Save to localStorage as backup
            try {
                localStorage.setItem(cookieName, consentString);
            } catch (e) {
                // localStorage may not be available
            }

            // Dispatch consent change event for developers to handle cleanup
            document.dispatchEvent(new CustomEvent('kessoConsentChanged', {
                detail: {
                    current: this.consent,
                    previous: previousConsent
                }
            }));

            // Process scripts based on new consent
            this.processScriptQueue();
        },

        /**
         * Get cookie value
         */
        getCookie: function(name) {
            const nameEQ = name + '=';
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }
            return null;
        },

        /**
         * Initialize UI elements
         */
        initUI: function() {
            const banner = document.getElementById('kesso-cookies-banner');
            const panel = document.getElementById('kesso-cookies-panel');
            const settingsLink = document.getElementById('kesso-cookies-settings-link');

            if (!banner || !panel) {
                return;
            }

            // Show banner if no consent, hide if consent exists
            const creditBanner = document.querySelector('.kesso-cookies-credit-banner');
            if (!this.consent) {
                this.showBanner();
            } else {
                // Hide banner and show settings link if consent exists
                this.hideBanner();
                // Also hide credit banner if consent exists
                if (creditBanner) {
                    creditBanner.style.display = 'none';
                    creditBanner.classList.remove('is-visible');
                }
                if (settingsLink) {
                    settingsLink.style.display = 'block';
                }
            }

            // Banner buttons
            const acceptAllBtn = document.getElementById('kesso-cookies-accept-all');
            const rejectAllBtn = document.getElementById('kesso-cookies-reject-all');
            const customizeBtn = document.getElementById('kesso-cookies-customize');

            if (acceptAllBtn) {
                acceptAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.acceptAll();
                });
            }

            if (rejectAllBtn) {
                rejectAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.rejectAll();
                });
            }

            if (customizeBtn) {
                customizeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.showPanel();
                });
            }

            // Panel controls
            const panelClose = document.getElementById('kesso-cookies-panel-close');
            const panelSave = document.getElementById('kesso-cookies-panel-save');
            const panelRejectAll = document.getElementById('kesso-cookies-panel-reject-all');
            const analyticsToggle = document.getElementById('kesso-cookies-analytics');
            const marketingToggle = document.getElementById('kesso-cookies-marketing');

            if (panelClose) {
                panelClose.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.hidePanel();
                });
            }

            if (panelSave) {
                panelSave.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveFromPanel();
                });
            }

            if (panelRejectAll) {
                panelRejectAll.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.rejectAll();
                });
            }

            // Update toggles based on current consent
            if (this.consent) {
                if (analyticsToggle) {
                    analyticsToggle.checked = this.consent.analytics || false;
                }
                if (marketingToggle) {
                    marketingToggle.checked = this.consent.marketing || false;
                }
            }

            // Settings link
            if (settingsLink) {
                settingsLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.showPanel();
                });
            }

            // Close panel on overlay click
            const panelOverlay = panel.querySelector('.kesso-cookies-panel-overlay');
            if (panelOverlay) {
                panelOverlay.addEventListener('click', () => {
                    this.hidePanel();
                });
            }
        },

        /**
         * Show banner
         */
        showBanner: function() {
            const banner = document.getElementById('kesso-cookies-banner');
            const creditBanner = document.querySelector('.kesso-cookies-credit-banner');

            // Show credit banner first so its height is measurable
            if (creditBanner) {
                creditBanner.classList.add('is-visible');
                creditBanner.style.display = 'block';
            }

            if (banner) {
                // Read credit banner height after it's visible, then batch the write
                const creditHeight = creditBanner ? creditBanner.offsetHeight : 0;
                banner.style.bottom = creditHeight ? creditHeight + 'px' : '';
                banner.classList.add('is-visible');
                banner.style.display = 'block';
                // Do not auto-focus any button to avoid drawing attention to a specific choice
                // Keyboard navigation will still work naturally when user tabs
            }
        },

        /**
         * Hide banner
         */
        hideBanner: function() {
            const banner = document.getElementById('kesso-cookies-banner');
            const creditBanner = document.querySelector('.kesso-cookies-credit-banner');
            
            if (banner) {
                banner.classList.remove('is-visible');
                banner.style.display = 'none';
                banner.style.bottom = '';
            }
            
            // Hide credit banner along with main banner
            if (creditBanner) {
                creditBanner.classList.remove('is-visible');
                creditBanner.style.display = 'none';
            }
        },

        /**
         * Show customize panel
         */
        showPanel: function() {
            const panel = document.getElementById('kesso-cookies-panel');
            const banner = document.getElementById('kesso-cookies-banner');
            if (panel) {
                panel.classList.add('is-visible');
                panel.style.display = 'block';
                // Hide banner when showing panel
                if (banner) {
                    this.hideBanner();
                }
                // Focus management
                const closeButton = panel.querySelector('.kesso-cookies-panel-close');
                if (closeButton) {
                    setTimeout(() => closeButton.focus(), 100);
                }
                // Prevent body scroll, preserving whatever was set before
                this._prevBodyOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
            }
        },

        /**
         * Hide customize panel
         */
        hidePanel: function() {
            const panel = document.getElementById('kesso-cookies-panel');
            if (panel) {
                panel.classList.remove('is-visible');
                panel.style.display = 'none';
                document.body.style.overflow = typeof this._prevBodyOverflow === 'string' ? this._prevBodyOverflow : '';
            }
        },

        /**
         * Accept all cookies
         */
        acceptAll: function() {
            // Prevent double-firing
            if (this._saving) {
                return;
            }
            
            try {
                this._saving = true;
                this.saveConsent({
                    analytics: true,
                    marketing: true
                });
                this.hideBanner();
                this.hidePanel();
                this.showSettingsLink();
                
                // Reset flag after a short delay
                setTimeout(() => {
                    this._saving = false;
                }, 500);
            } catch (e) {
                console.error('Kesso Cookies: Error accepting all cookies', e);
                this._saving = false;
            }
        },

        /**
         * Reject all non-essential cookies
         */
        rejectAll: function() {
            // Prevent double-firing
            if (this._saving) {
                return;
            }
            
            try {
                this._saving = true;
                this.saveConsent({
                    analytics: false,
                    marketing: false
                });
                this.hideBanner();
                this.hidePanel();
                this.showSettingsLink();
                
                // Reset flag after a short delay
                setTimeout(() => {
                    this._saving = false;
                }, 500);
            } catch (e) {
                console.error('Kesso Cookies: Error rejecting all cookies', e);
                this._saving = false;
            }
        },

        /**
         * Save preferences from panel
         */
        saveFromPanel: function() {
            // Prevent double-firing
            if (this._saving) {
                return;
            }
            
            try {
                this._saving = true;
                const analytics = document.getElementById('kesso-cookies-analytics');
                const marketing = document.getElementById('kesso-cookies-marketing');

                this.saveConsent({
                    analytics: analytics ? analytics.checked : false,
                    marketing: marketing ? marketing.checked : false
                });

                this.hidePanel();
                this.showSettingsLink();
                
                // Reset flag after a short delay
                setTimeout(() => {
                    this._saving = false;
                }, 500);
            } catch (e) {
                console.error('Kesso Cookies: Error saving preferences', e);
                this._saving = false;
            }
        },

        /**
         * Show settings link
         */
        showSettingsLink: function() {
            const settingsLink = document.getElementById('kesso-cookies-settings-link');
            if (settingsLink) {
                settingsLink.style.display = 'block';
            }
        },

        /**
         * Register a script to be loaded conditionally
         */
        registerScript: function(config) {
            if (!config || !config.category || !config.src) {
                return;
            }

            // Essential scripts are always loaded
            if (config.category === 'essential') {
                this.loadScript(config);
                return;
            }

            // Check if consent allows this category
            if (this.consent && this.consent[config.category]) {
                this.loadScript(config);
            } else {
                // Queue for later
                this.scripts.queue.push(config);
            }
        },

        /**
         * Load a script
         * 
         * Note: Once a script is loaded into the DOM, it cannot be reliably unloaded.
         * If consent is withdrawn, developers should listen to the 'kessoConsentChanged'
         * event and handle cleanup of third-party cookies or script state manually.
         * This is a technical limitation, not a GDPR compliance issue.
         */
        loadScript: function(config) {
            const scriptId = config.id || config.src;

            // Prevent duplicate loading
            if (this.scripts.loaded[scriptId]) {
                return;
            }

            this.scripts.loaded[scriptId] = true;

            const script = document.createElement('script');
            script.src = config.src;
            script.async = config.async !== false;
            script.defer = config.defer || false;

            if (config.onload) {
                script.onload = config.onload;
            }

            if (config.onerror) {
                script.onerror = config.onerror;
            }

            document.head.appendChild(script);
        },

        /**
         * Process queued scripts based on consent
         */
        processScriptQueue: function() {
            if (!this.consent) {
                return;
            }

            const queue = this.scripts.queue.slice();
            this.scripts.queue = [];

            queue.forEach((script) => {
                if (this.consent[script.category]) {
                    this.loadScript(script);
                } else if (!(script.category in this.consent) && script.category !== 'essential') {
                    // Unknown category — re-queue so it isn't silently dropped
                    this.scripts.queue.push(script);
                }
            });
        },

        /**
         * Get current consent state
         */
        getConsent: function() {
            return this.consent ? { ...this.consent } : null;
        },

        /**
         * Check if a category is consented
         */
        hasConsent: function(category) {
            if (category === 'essential') {
                return true; // Always true
            }
            return this.consent ? (this.consent[category] || false) : false;
        }
    };

    // Expose global API
    window.kessoCookies = KessoCookies;

    // Expose registerScript function for early script registration
    window.kessoCookiesScripts = {
        register: function(config) {
            if (KessoCookies.consent) {
                // Consent already exists, process immediately
                KessoCookies.registerScript(config);
            } else {
                // Queue for later
                KessoCookies.scripts.queue.push(config);
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            KessoCookies.init();
        });
    } else {
        KessoCookies.init();
    }

})(window, document);

