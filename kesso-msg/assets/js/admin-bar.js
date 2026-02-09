/**
 * Kesso Messages Admin Bar JavaScript
 * Handles notification bell click and popup display
 */
(function($) {
    'use strict';

    const KessoMsgPopup = {
        init: function() {
            this.bindEvents();
            // Don't load notices on init - wait for popup to open
            // this.loadNotices();
        },

        bindEvents: function() {
            // Click on bell icon
            $(document).on('click', '#wpadminbar #wp-admin-bar-kesso-msg-notifications a', function(e) {
                e.preventDefault();
                KessoMsgPopup.togglePopup();
            });

            // Close popup
            $(document).on('click', '.kesso-msg-popup-close', function(e) {
                e.preventDefault();
                KessoMsgPopup.closePopup();
            });

            // Checkbox change
            $(document).on('change', '.kesso-msg-popup-item-checkbox', function() {
                KessoMsgPopup.updateSelectionState();
            });

            // Bulk dismiss button
            $(document).on('click', '.kesso-msg-popup-bulk-dismiss', function(e) {
                e.preventDefault();
                KessoMsgPopup.bulkDismiss();
            });

            // Bulk hide button
            $(document).on('click', '.kesso-msg-popup-bulk-hide', function(e) {
                e.preventDefault();
                KessoMsgPopup.bulkHide();
            });

            // Close on backdrop click
            $(document).on('click', function(e) {
                if ($(e.target).closest('.kesso-msg-popup').length === 0 &&
                    $(e.target).closest('#wp-admin-bar-kesso-msg-notifications').length === 0) {
                    KessoMsgPopup.closePopup();
                }
            });

            // Prevent closing when clicking inside popup
            $(document).on('click', '.kesso-msg-popup', function(e) {
                e.stopPropagation();
            });
        },

        togglePopup: function() {
            const $popup = $('.kesso-msg-popup');
            if ($popup.length === 0) {
                this.createPopup();
            }

            if ($popup.hasClass('is-open')) {
                this.closePopup();
            } else {
                this.openPopup();
            }
        },

        openPopup: function() {
            const $popup = $('.kesso-msg-popup');
            if ($popup.length === 0) {
                this.createPopup();
            }
            $('.kesso-msg-popup').addClass('is-open');
            this.loadNotices();
        },

        closePopup: function() {
            $('.kesso-msg-popup').removeClass('is-open');
        },

        createPopup: function() {
            const popupHTML = `
                <div class="kesso-msg-popup">
                    <div class="kesso-msg-popup-header">
                        <div class="kesso-msg-popup-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span>Notifications</span>
                        </div>
                        <div class="kesso-msg-popup-header-actions" style="display: none;">
                            <button type="button" class="kesso-msg-popup-bulk-hide">Hide from Count</button>
                            <button type="button" class="kesso-msg-popup-bulk-dismiss">Dismiss</button>
                        </div>
                        <button type="button" class="kesso-msg-popup-close" aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="kesso-msg-popup-body">
                        <div class="kesso-msg-popup-loading">Loading...</div>
                    </div>
                    <div class="kesso-msg-popup-footer">
                        <a href="https://kesso.io" target="_blank" rel="noopener noreferrer" class="kesso-msg-popup-credit">
                            🧀 Powered by <strong>kesso.io</strong>
                        </a>
                    </div>
                </div>
            `;
            $('body').append(popupHTML);
        },

        loadNotices: function() {
            const $body = $('.kesso-msg-popup-body');
            if ($body.length === 0) {
                return;
            }

            $body.html('<div class="kesso-msg-popup-loading">Loading...</div>');

            $.ajax({
                url: kessoMsgConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kesso_msg_get_notices',
                    nonce: kessoMsgConfig.nonce
                },
                success: function(response) {
                    if (response.success && response.data.notices && response.data.notices.length > 0) {
                        KessoMsgPopup.renderNotices(response.data.notices);
                        KessoMsgPopup.updateBadge(response.data.count);
                    } else {
                        $body.html('<div class="kesso-msg-popup-empty">No notifications</div>');
                        KessoMsgPopup.updateBadge(0);
                    }
                },
                error: function() {
                    $body.html('<div class="kesso-msg-popup-empty">Error loading notifications</div>');
                }
            });
        },

        renderNotices: function(notices) {
            const $body = $('.kesso-msg-popup-body');
            
            if (!notices || notices.length === 0) {
                $body.html('<div class="kesso-msg-popup-empty">No notifications</div>');
                return;
            }

            let html = '<div class="kesso-msg-popup-list">';
            
            notices.forEach(function(notice) {
                const typeClass = 'kesso-msg-popup-item-type--' + (notice.type || 'info');
                const typeLabel = (notice.type || 'info').charAt(0).toUpperCase() + (notice.type || 'info').slice(1);
                const time = notice.time ? new Date(notice.time).toLocaleString() : '';
                const message = notice.message || '';
                
                // Generate ID if missing (for backward compatibility with old notices)
                let noticeId = notice.id;
                if (!noticeId && message) {
                    // Create a hash from message and type
                    const str = (notice.type || 'info') + '|' + message.substring(0, 100);
                    let hash = 0;
                    for (let i = 0; i < str.length; i++) {
                        const char = str.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash;
                    }
                    noticeId = 'kesso-msg-' + Math.abs(hash).toString(36);
                }
                noticeId = noticeId || '';
                
                // Debug: log if ID is still empty
                if (!noticeId) {
                    console.warn('Notice still missing ID after generation:', notice);
                }
                
                // Build buttons/links HTML
                let actionsHtml = '';
                if (notice.buttons && notice.buttons.length > 0) {
                    actionsHtml += '<div class="kesso-msg-popup-item-actions">';
                    notice.buttons.forEach(function(btn) {
                        actionsHtml += `<a href="${btn.href}" class="kesso-msg-popup-button" target="_blank" rel="noopener noreferrer">${btn.text}</a>`;
                    });
                    actionsHtml += '</div>';
                }
                if (notice.links && notice.links.length > 0) {
                    if (!actionsHtml) {
                        actionsHtml = '<div class="kesso-msg-popup-item-actions">';
                    }
                    notice.links.forEach(function(link) {
                        actionsHtml += `<a href="${link.href}" class="kesso-msg-popup-link" target="_blank" rel="noopener noreferrer">${link.text}</a>`;
                    });
                    if (!actionsHtml.includes('</div>')) {
                        actionsHtml += '</div>';
                    }
                }
                
                // Ensure we have a valid notice ID before rendering
                if (!noticeId) {
                    console.error('Cannot render notice without ID:', notice);
                    return; // Skip this notice
                }
                
                // Escape noticeId for use in HTML attributes
                const escapedNoticeId = String(noticeId).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                
                const isHidden = notice.is_hidden ? ' kesso-msg-popup-item--hidden' : '';
                html += `
                    <div class="kesso-msg-popup-item${isHidden}" data-notice-id="${escapedNoticeId}">
                        <div class="kesso-msg-popup-item-header">
                            <label class="kesso-msg-popup-item-checkbox-wrapper">
                                <input type="checkbox" class="kesso-msg-popup-item-checkbox" data-notice-id="${escapedNoticeId}" aria-label="Select notification">
                            </label>
                            <div class="kesso-msg-popup-item-type ${typeClass}">${typeLabel}</div>
                        </div>
                        <div class="kesso-msg-popup-item-message">${message}</div>
                        ${actionsHtml}
                        ${time ? '<div class="kesso-msg-popup-item-time">' + time + '</div>' : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            $body.html(html);
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
        },

        updateSelectionState: function() {
            const $checked = $('.kesso-msg-popup-item-checkbox:checked');
            const $actions = $('.kesso-msg-popup-header-actions');
            
            if ($checked.length > 0) {
                $actions.show();
            } else {
                $actions.hide();
            }
        },

        getSelectedNoticeIds: function() {
            const ids = [];
            $('.kesso-msg-popup-item-checkbox:checked').each(function() {
                const noticeId = $(this).data('notice-id');
                if (noticeId) {
                    ids.push(noticeId);
                }
            });
            return ids;
        },

        bulkDismiss: function() {
            const noticeIds = this.getSelectedNoticeIds();
            
            if (noticeIds.length === 0) {
                return;
            }
            
            if (!confirm('Are you sure you want to dismiss ' + noticeIds.length + ' notification(s)?')) {
                return;
            }
            
            $.ajax({
                url: kessoMsgConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kesso_msg_bulk_dismiss',
                    nonce: kessoMsgConfig.nonce,
                    notice_ids: noticeIds
                },
                success: function(response) {
                    if (response.success) {
                        // Remove dismissed notices from UI
                        noticeIds.forEach(function(noticeId) {
                            const $item = $('.kesso-msg-popup-item[data-notice-id="' + noticeId + '"]');
                            $item.fadeOut(200, function() {
                                $(this).remove();
                            });
                        });
                        
                        // Check if list is empty
                        setTimeout(function() {
                            const $list = $('.kesso-msg-popup-list');
                            if ($list.length === 0 || $list.children().length === 0) {
                                $('.kesso-msg-popup-body').html('<div class="kesso-msg-popup-empty">No notifications</div>');
                            }
                            
                            // Update badge and selection state
                            KessoMsgPopup.updateBadge(response.data.count);
                            KessoMsgPopup.updateSelectionState();
                        }, 250);
                    } else {
                        alert('Failed to dismiss notifications. Please try again.');
                    }
                },
                error: function() {
                    alert('Error dismissing notifications. Please try again.');
                }
            });
        },

        bulkHide: function() {
            const noticeIds = this.getSelectedNoticeIds();
            
            if (noticeIds.length === 0) {
                return;
            }
            
            $.ajax({
                url: kessoMsgConfig.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kesso_msg_bulk_hide',
                    nonce: kessoMsgConfig.nonce,
                    notice_ids: noticeIds
                },
                success: function(response) {
                    if (response.success) {
                        // Mark notices as hidden in UI
                        noticeIds.forEach(function(noticeId) {
                            const $item = $('.kesso-msg-popup-item[data-notice-id="' + noticeId + '"]');
                            $item.addClass('kesso-msg-popup-item--hidden');
                            $item.find('.kesso-msg-popup-item-checkbox').prop('checked', false);
                        });
                        
                        // Update badge and selection state
                        KessoMsgPopup.updateBadge(response.data.count);
                        KessoMsgPopup.updateSelectionState();
                    } else {
                        alert('Failed to hide notifications. Please try again.');
                    }
                },
                error: function() {
                    alert('Error hiding notifications. Please try again.');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        KessoMsgPopup.init();
    });

})(jQuery);

