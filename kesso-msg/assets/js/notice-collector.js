/**
 * Kesso Messages Notice Collector
 * Collects admin notices from the DOM and sends them to server
 */
(function($) {
    'use strict';

    const KessoMsgCollector = {
        observer: null,
        retryCount: 0,
        maxRetries: 5,
        
        init: function() {
            // Wait for page to fully load
            $(document).ready(function() {
                // Collect immediately, then once more after a short delay
                // to catch notices injected by plugins after DOM-ready.
                KessoMsgCollector.collectNotices();
                setTimeout(function() {
                    KessoMsgCollector.collectNotices();
                }, 1500);

                // Watch for dynamically added notices
                KessoMsgCollector.startObserver();
            });
        },

        startObserver: function() {
            // Use MutationObserver to watch for new notices
            if (typeof MutationObserver !== 'undefined') {
                const targetNode = document.body || document.getElementById('wpbody-content') || document;
                let debounceTimer = null;

                this.observer = new MutationObserver(function(mutations) {
                    let shouldCollect = false;

                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            // Check if any added node is a notice
                            for (let i = 0; i < mutation.addedNodes.length; i++) {
                                const node = mutation.addedNodes[i];
                                if (node.nodeType === 1) { // Element node
                                    const $node = $(node);
                                    if ($node.is('.notice, .update-nag, .error, .warning, .success, .info') ||
                                        $node.find('.notice, .update-nag, .error, .warning, .success, .info').length > 0) {
                                        shouldCollect = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });

                    if (shouldCollect) {
                        // Debounce: collapse rapid-fire mutations into a single collection
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(function() {
                            KessoMsgCollector.collectNotices();
                        }, 400);
                    }
                });

                this.observer.observe(targetNode, {
                    childList: true,
                    subtree: true
                });
            }
        },

        collectNotices: function() {
            const notices = [];
            
            // Expanded selector to catch all WordPress admin notices
            // Including WooCommerce and other plugin notices
            // Only match actual notice containers, not buttons/links inside them
            const selectors = [
                '.notice:not(a):not(button):not(.button)',
                '.update-nag:not(a):not(button):not(.button)',
                '.woocommerce-message:not(a):not(button):not(.button)',
                '.woocommerce-error:not(a):not(button):not(.button)',
                '.woocommerce-info:not(a):not(button):not(.button)'
            ];
            
            $(selectors.join(', ')).each(function() {
                const $notice = $(this);
                
                // Skip if already processed or if it's our popup
                if ($notice.hasClass('kesso-msg-processed') || 
                    $notice.closest('.kesso-msg-popup').length > 0 ||
                    $notice.closest('#wpadminbar').length > 0) {
                    return;
                }
                
                // Skip if it's a child notice (we'll catch the parent)
                if ($notice.parent().is('.notice, .update-nag, .woocommerce-message, .woocommerce-error, .woocommerce-info')) {
                    return;
                }
                
                // Skip if it's a button, link, or other interactive element
                if ($notice.is('a, button, .button, input, select, textarea')) {
                    return;
                }
                
                // Skip if it's inside a button or link
                if ($notice.closest('a, button, .button').length > 0) {
                    return;
                }
                
                $notice.addClass('kesso-msg-processed');
                
                // Determine notice type
                let type = 'info';
                const classes = $notice.attr('class') || '';
                
                // Check for WooCommerce notices first (more specific)
                if (classes.indexOf('woocommerce-error') !== -1) {
                    type = 'error';
                } else if (classes.indexOf('woocommerce-message') !== -1) {
                    type = 'success';
                } else if (classes.indexOf('woocommerce-info') !== -1) {
                    type = 'info';
                } else if (classes.indexOf('notice-error') !== -1) {
                    type = 'error';
                } else if (classes.indexOf('notice-warning') !== -1) {
                    type = 'warning';
                } else if (classes.indexOf('notice-success') !== -1) {
                    type = 'success';
                } else if (classes.indexOf('update-nag') !== -1) {
                    type = 'update-nag';
                } else if (classes.indexOf('notice-info') !== -1) {
                    type = 'info';
                } else if (classes.indexOf('error') !== -1 && classes.indexOf('notice-dismiss') === -1) {
                    type = 'error';
                } else if (classes.indexOf('warning') !== -1 && classes.indexOf('notice-dismiss') === -1) {
                    type = 'warning';
                } else if (classes.indexOf('success') !== -1 && classes.indexOf('notice-dismiss') === -1) {
                    type = 'success';
                } else if (classes.indexOf('info') !== -1 && classes.indexOf('notice-dismiss') === -1) {
                    type = 'info';
                }
                
                // Extract message text - clone to avoid modifying original
                const $clone = $notice.clone();
                // Remove buttons and links from clone to get clean message text
                $clone.find('a.button, button.button, .notice-dismiss, a.notice-dismiss').remove();
                const message = $clone.text().trim();
                const html = $notice.html();
                
                // Skip if message is too short (likely just a button/link text)
                if (message && message.length > 10) {
                    // Create unique hash for this notice to prevent duplicates
                    const noticeHash = KessoMsgCollector.createNoticeHash(message, type);
                    
                    // Extract buttons and links
                    const buttons = [];
                    const links = [];
                    
                    $notice.find('a.button, button.button, a').each(function() {
                        const $el = $(this);
                        const text = $el.text().trim();
                        const href = $el.attr('href') || '#';
                        const isButton = $el.hasClass('button') || $el.is('button');
                        
                        if (text && href) {
                            if (isButton) {
                                buttons.push({
                                    text: text,
                                    href: href,
                                    class: $el.attr('class') || ''
                                });
                            } else {
                                links.push({
                                    text: text,
                                    href: href
                                });
                            }
                        }
                    });
                    
                    notices.push({
                        id: noticeHash,
                        type: type,
                        message: message,
                        html: html,
                        buttons: buttons,
                        links: links,
                        time: new Date().toISOString()
                    });
                }
            });
            
            // Send to server if we found notices
            if (notices.length > 0) {
                this.saveNotices(notices);
            }
        },

        createNoticeHash: function(message, type) {
            // DJB2-XOR: identical algorithm to the PHP generate_notice_id() method
            // so IDs match between client-sent notices and server-side lookups.
            const str = type + '|' + message.substring(0, 100);
            let hash = 5381;
            for (let i = 0; i < str.length; i++) {
                hash = (Math.imul(hash, 33) ^ str.charCodeAt(i)) >>> 0;
            }
            return 'kesso-msg-' + hash.toString(16);
        },

        saveNotices: function(notices) {
            $.ajax({
                url: kessoMsgCollector.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kesso_msg_save_notices',
                    nonce: kessoMsgCollector.nonce,
                    notices: JSON.stringify(notices)
                },
                success: function(response) {
                    // Update badge count if needed
                    if (response.success) {
                        KessoMsgCollector.updateBadge(response.data.count);
                    }
                }
            });
        },

        updateBadge: function(count) {
            const $badge = $('#wpadminbar .kesso-msg-bell-badge');
            if (count > 0) {
                if ($badge.length === 0) {
                    $('#wpadminbar .kesso-msg-bell-icon').after('<span class="kesso-msg-bell-badge">' + (count > 99 ? '99+' : count) + '</span>');
                } else {
                    $badge.text(count > 99 ? '99+' : count);
                }
            } else {
                $badge.remove();
            }
        }
    };

    // Initialize
    KessoMsgCollector.init();

})(jQuery);

