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

            // Process queued scripts registered via the JS API
            this.processScriptQueue();

            // Activate any server-blocked scripts the visitor has consented to
            this.activateBlockedScripts(this.getConsentedCategories());
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
            let hadStored = false;
            try {
                const stored = localStorage.getItem(cookieName);
                if (stored) {
                    hadStored = true;
                    const parsed = JSON.parse(stored);
                    if (parsed && parsed.version === cookieVersion) {
                        this.consent = parsed;
                        return;
                    }
                }
            } catch (e) {
                // localStorage may not be available
            }

            // A cookie/localStorage entry existed but was stale (e.g. a version
            // bump) — clear it so we don't leave orphaned consent records behind.
            if (cookie || hadStored) {
                this.clearStoredConsent();
            }

            // No valid consent found
            this.consent = null;
        },

        /**
         * Remove any stored consent (cookie + localStorage)
         */
        clearStoredConsent: function() {
            const cookieName = this.config ? this.config.cookieName : 'kesso_cookies_consent';
            const secureFlag = (location.protocol === 'https:') ? '; Secure' : '';
            document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax${secureFlag}`;
            try {
                localStorage.removeItem(cookieName);
            } catch (e) {
                // localStorage may not be available
            }
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
            this.activateBlockedScripts(this.getConsentedCategories());

            // Purge third-party cookies for categories that are not consented
            this.purgeWithdrawnCookies(previousConsent, this.consent);
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
            if (!this.consent) {
                this.showBanner();
            } else {
                // Hide banner and show settings link if consent exists
                this.hideBanner();
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

            if (banner) {
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

            if (banner) {
                banner.classList.remove('is-visible');
                banner.style.display = 'none';
            }
        },

        /**
         * Show customize panel
         */
        showPanel: function() {
            const panel = document.getElementById('kesso-cookies-panel');
            const banner = document.getElementById('kesso-cookies-banner');
            if (panel) {
                // Remember what had focus so we can restore it on close.
                this._lastFocused = document.activeElement;

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
                // Trap focus and enable Escape-to-close while the modal is open.
                this._panelKeydownHandler = (e) => this.handlePanelKeydown(e);
                document.addEventListener('keydown', this._panelKeydownHandler);
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

            // No choice made yet: the visitor still needs the banner.
            if (!this.consent) {
                this.showBanner();
            }

            // Tear down the focus trap and restore focus to the trigger.
            if (this._panelKeydownHandler) {
                document.removeEventListener('keydown', this._panelKeydownHandler);
                this._panelKeydownHandler = null;
            }
            if (this._lastFocused && typeof this._lastFocused.focus === 'function') {
                this._lastFocused.focus();
                this._lastFocused = null;
            }
        },

        /**
         * Keyboard handling for the open modal: Escape closes, Tab is trapped.
         */
        handlePanelKeydown: function(e) {
            const panel = document.getElementById('kesso-cookies-panel');
            if (!panel || panel.style.display === 'none') {
                return;
            }

            if (e.key === 'Escape' || e.key === 'Esc') {
                e.preventDefault();
                this.hidePanel();
                return;
            }

            if (e.key !== 'Tab') {
                return;
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const focusable = Array.prototype.slice.call(panel.querySelectorAll(selector))
                .filter((el) => el.offsetParent !== null);
            if (!focusable.length) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
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

            // Basic scheme safety for API-supplied sources.
            if (config.src && /^\s*(javascript|data|vbscript):/i.test(config.src)) {
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
        },

        /**
         * List of categories the visitor has currently consented to
         */
        getConsentedCategories: function() {
            const cats = [];
            if (this.consent) {
                if (this.consent.analytics) { cats.push('analytics'); }
                if (this.consent.marketing) { cats.push('marketing'); }
            }
            return cats;
        },

        /**
         * Re-activate scripts the server blocked (type="text/plain"), for the
         * given consented categories. Preserves attributes and document order.
         */
        activateBlockedScripts: function(categories) {
            if (!categories || !categories.length) {
                return;
            }

            const queue = [];
            document.querySelectorAll('script[type="text/plain"][data-kesso-blocked]').forEach((old) => {
                if (categories.indexOf(old.getAttribute('data-kesso-category')) === -1) {
                    return;
                }
                if (old.getAttribute('data-kesso-activated')) {
                    return;
                }
                old.setAttribute('data-kesso-activated', '1');
                queue.push(old);
            });

            // Scripts inserted from JS run as soon as they load, so an inline
            // snippet could run before the library it needs. Wait for each
            // external script unless it was already marked async.
            const activateNext = () => {
                const old = queue.shift();
                if (!old) {
                    return;
                }

                const fresh = document.createElement('script');
                for (let i = 0; i < old.attributes.length; i++) {
                    const attr = old.attributes[i];
                    const name = attr.name;
                    if (name === 'type' || name === 'data-kesso-blocked' ||
                        name === 'data-kesso-category' || name === 'data-kesso-activated') {
                        continue;
                    }
                    if (name === 'data-kesso-src') {
                        fresh.setAttribute('src', attr.value);
                    } else {
                        fresh.setAttribute(name, attr.value);
                    }
                }

                const isExternal = !!old.getAttribute('data-kesso-src');

                // Inline body (only when there is no external src)
                if (!isExternal && old.textContent) {
                    fresh.text = old.textContent;
                }

                const waitForLoad = isExternal && !old.hasAttribute('async');
                if (waitForLoad) {
                    fresh.async = false;
                    fresh.onload = activateNext;
                    fresh.onerror = activateNext;
                }

                if (old.parentNode) {
                    old.parentNode.replaceChild(fresh, old);
                }

                if (!waitForLoad) {
                    activateNext();
                }
            };

            activateNext();
        },

        /**
         * Delete known third-party cookies for categories that are not consented.
         * Loaded scripts can't be unloaded, but their cookies can be cleared.
         */
        purgeWithdrawnCookies: function(previous, current) {
            const withdrawn = [];
            ['analytics', 'marketing'].forEach((cat) => {
                if (!current || !current[cat]) {
                    withdrawn.push(cat);
                }
            });
            if (!withdrawn.length) {
                return;
            }

            const map = this.getPurgeCookieMap();
            withdrawn.forEach((cat) => {
                (map[cat] || []).forEach((pattern) => this.deleteCookieByPattern(pattern));
            });
        },

        /**
         * Default third-party cookie names per category (extendable via config).
         */
        getPurgeCookieMap: function() {
            const fromConfig = (this.config && this.config.purgeCookies) || {};
            const defaults = {
                analytics: ['_ga', '_ga_*', '_gid', '_gat', '_gat_*', '_gcl_au',
                    '__utma', '__utmb', '__utmc', '__utmt', '__utmz',
                    '_hjSession_*', '_hjSessionUser_*', '_hjIncludedInSessionSample_*',
                    '_clck', '_clsk', '_pk_id.*', '_pk_ses.*'],
                marketing: ['_fbp', '_fbc', 'fr', '_gcl_aw', '_gcl_dc',
                    'personalization_id', 'muc_ads', 'IDE', 'test_cookie',
                    '_uetsid', '_uetvid', 'li_sugr', 'bcookie', 'lidc',
                    'UserMatchHistory', '_ttp', '_pin_unauth']
            };
            return {
                analytics: defaults.analytics.concat(fromConfig.analytics || []),
                marketing: defaults.marketing.concat(fromConfig.marketing || [])
            };
        },

        /**
         * Expire a cookie by exact name, or by prefix when the pattern ends in '*'.
         * Attempts each plausible domain/path so it catches third-party writes.
         */
        deleteCookieByPattern: function(pattern) {
            const names = [];
            if (pattern.indexOf('*') !== -1) {
                const prefix = pattern.replace('*', '');
                document.cookie.split(';').forEach((c) => {
                    const name = c.split('=')[0].trim();
                    if (name && name.indexOf(prefix) === 0) {
                        names.push(name);
                    }
                });
            } else {
                names.push(pattern);
            }

            const host = location.hostname;
            const domains = ['', host, '.' + host];
            const parts = host.split('.');
            if (parts.length > 2) {
                domains.push('.' + parts.slice(-2).join('.'));
            }

            names.forEach((name) => {
                domains.forEach((d) => {
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' +
                        (d ? '; domain=' + d : '');
                });
            });
        }
    };

    // Expose global API
    window.kessoCookies = KessoCookies;

    // Migrate any registrations captured by the early <head> shim before we
    // replace it, so registrations made before this script loads aren't lost.
    if (window.kessoCookiesScripts && Array.isArray(window.kessoCookiesScripts.queue) && window.kessoCookiesScripts.queue.length) {
        KessoCookies.scripts.queue = KessoCookies.scripts.queue.concat(window.kessoCookiesScripts.queue);
    }

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

