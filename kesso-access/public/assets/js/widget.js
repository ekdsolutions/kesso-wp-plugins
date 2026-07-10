/*!
 * Accessibility Widget JavaScript
 */
/* global jQuery, KessoOptions */

( function( $, window, document, undefined ) {
	'use strict';

	var Kesso_Accessibility_App = {
		cache: {
			$document: $( document ),
			$window: $( window )
		},

		cacheElements: function() {
			this.cache.$toolbar = $( '#kesso-toolbar' );
			this.cache.$toolbarLinks = this.cache.$toolbar.find( '.kesso-toolbar-link' );
			this.cache.$toolbarToolsLinks = this.cache.$toolbar.find( '.kesso-tools .kesso-toolbar-link' );
			this.cache.$btnToolbarToggle = this.cache.$toolbar.find( 'div.kesso-toolbar-toggle > button' );
			this.cache.$skipToContent = $( '#kesso-skip-content' );
			this.cache.$body = $( 'body' );
		},

		settings: {
			minFontSize: 120,
			maxFontSize: 200,
			buttonsClassPrefix: 'kesso-btn-',
			bodyClassPrefix: 'kesso-',
			bodyFontClassPrefix: 'kesso-resize-font-',
			storageKey: 'kesso',
			expires: KessoOptions.save_expiration ? KessoOptions.save_expiration * 36e5 /* hours to ms */ : 43200000 // 12 hours
		},

		variables: {
			currentFontSize: 120,
			currentSchema: null
		},

		activeActions: {},

		buildElements: function() {
			// Move the `toolbar/skip to content` to top
			this.cache.$body.prepend( this.cache.$toolbar );
			this.cache.$body.prepend( this.cache.$skipToContent );
		},

		bindEvents: function() {
			var $self = this;

			$self.cache.$btnToolbarToggle.on( 'click', function( event ) {
				event.preventDefault();
				$self.setToolbarOpen( ! $self.cache.$toolbar.hasClass( 'kesso-toolbar-open' ) );
			} );

			$( document ).on( 'keyup', function( event ) {
				var TAB_KEY = 9;

				if ( TAB_KEY !== event.which || ! $self.cache.$btnToolbarToggle.is( ':focus' ) ) {
					return;
				}
				$self.setToolbarOpen( true );
			} );

			// Escape closes the toolbar and returns focus to the toggle button.
			$( document ).on( 'keydown', function( event ) {
				var ESC_KEY = 27;

				if ( ESC_KEY === event.which && $self.cache.$toolbar.hasClass( 'kesso-toolbar-open' ) ) {
					$self.setToolbarOpen( false );
					$self.cache.$btnToolbarToggle.trigger( 'focus' );
				}
			} );

			$self.bindToolbarButtons();
		},

		setToolbarOpen: function( open ) {
			this.cache.$toolbar.toggleClass( 'kesso-toolbar-open', open );
			this.cache.$toolbarLinks.attr( 'tabindex', open ? '0' : '-1' );
			this.cache.$btnToolbarToggle.attr( 'aria-expanded', open ? 'true' : 'false' );

			var label = open
				? ( KessoOptions.label_close || 'Close toolbar' )
				: ( KessoOptions.label_open || 'Open toolbar' );
			this.cache.$toolbar.find( '.kesso-toolbar-toggle-label' ).text( label );
		},

		bindToolbarButtons: function() {
			var self = this;

			self.cache.$toolbarToolsLinks.on( 'click', function( event ) {
				event.preventDefault();

				var $this = $( this ),
					action = $this.data( 'action' ),
					actionGroup = $this.data( 'action-group' ),
					deactivate = false;

				if ( 'reset' === action ) {
					self.reset();
					return;
				}

				if ( -1 !== [ 'toggle', 'schema' ].indexOf( actionGroup ) ) {
					deactivate = $this.hasClass( 'active' );
				}

				self.activateButton( action, deactivate );
			} );
		},

		activateButton: function( action, deactivate ) {
			var $button = this.getButtonByAction( action ),
				actionGroup = $button.data( 'action-group' );

			this.activeActions[ action ] = ! deactivate;

			this.actions[ actionGroup ].call( this, action, deactivate );

			this.saveToLocalStorage();
		},

		getActiveButtons: function() {
			return this.cache.$toolbarToolsLinks.filter( '.active' );
		},

		getButtonByAction: function( action ) {
			return this.cache.$toolbarToolsLinks.filter( '.' + this.settings.buttonsClassPrefix + action );
		},

		actions: {
			toggle: function( action, deactivate ) {
				var $button = this.getButtonByAction( action ),
					fn = deactivate ? 'removeClass' : 'addClass';

				if ( deactivate ) {
					$button.removeClass( 'active' );
				} else {
					$button.addClass( 'active' );
				}

				this.cache.$body[ fn ]( this.settings.bodyClassPrefix + action );
			},
			resize: function( action, deactivate ) {
				var oldFontSize = this.variables.currentFontSize;

				if ( 'resize-plus' === action && this.settings.maxFontSize > oldFontSize ) {
					this.variables.currentFontSize += 10;
				}

				if ( 'resize-minus' === action && this.settings.minFontSize < oldFontSize ) {
					this.variables.currentFontSize -= 10;
				}

				if ( deactivate ) {
					this.variables.currentFontSize = this.settings.minFontSize;
				}

				this.cache.$body.removeClass( this.settings.bodyFontClassPrefix + oldFontSize );

				var isPlusActive = 120 < this.variables.currentFontSize,
					plusButtonAction = isPlusActive ? 'addClass' : 'removeClass';

				this.getButtonByAction( 'resize-plus' )[ plusButtonAction ]( 'active' );

				if ( isPlusActive ) {
					this.cache.$body.addClass( this.settings.bodyFontClassPrefix + this.variables.currentFontSize );
				}

				this.activeActions[ 'resize-minus' ] = false;
				this.activeActions[ 'resize-plus' ] = isPlusActive;
				this.cache.$window.trigger( 'resize' );
			},
			schema: function( action, deactivate ) {
				var currentSchema = this.variables.currentSchema;

				if ( currentSchema ) {
					this.cache.$body.removeClass( this.settings.bodyClassPrefix + currentSchema );
					this.getButtonByAction( currentSchema ).removeClass( 'active' );
					this.activeActions[ currentSchema ] = false;

					this.saveToLocalStorage();
				}

				if ( deactivate ) {
					this.variables.currentSchema = null;
					return;
				}

				currentSchema = this.variables.currentSchema = action;
				this.cache.$body.addClass( this.settings.bodyClassPrefix + currentSchema );
				this.getButtonByAction( currentSchema ).addClass( 'active' );
			}
		},

		reset: function() {
			for ( var action in this.activeActions ) {
				if ( this.activeActions.hasOwnProperty( action ) && this.activeActions[ action ] ) {
					this.activateButton( action, true );
				}
			}

			localStorage.removeItem( this.settings.storageKey );
		},

		saveToLocalStorage: function() {
			if ( '1' !== KessoOptions.enable_save ) {
				return;
			}

			if ( ! this.variables.expires ) {
				this.variables.expires = ( new Date() ).getTime() + this.settings.expires;
			}

			var data = {
				actions: this.activeActions,
				variables: {
					currentFontSize: this.variables.currentFontSize,
					expires: this.variables.expires
				}
			};

			localStorage.setItem( this.settings.storageKey, JSON.stringify( data ) );
		},

		setFromLocalStorage: function() {
			if ( '1' !== KessoOptions.enable_save ) {
				return;
			}

			var localData;
			try {
				localData = JSON.parse( localStorage.getItem( this.settings.storageKey ) );
			} catch ( e ) {
				localStorage.removeItem( this.settings.storageKey );
				return;
			}

			if ( ! localData ) {
				return;
			}

			var currentDate = new Date(),
				expires = localData.variables.expires;

			if ( currentDate.getTime() > expires ) {
				localStorage.removeItem( this.settings.storageKey );
				return;
			}

			var actions = localData.actions;

			if ( localData.variables.currentFontSize > 120 ) {
				localData.variables.currentFontSize -= 10;
			}

			$.extend( this.variables, localData.variables );

			for ( var action in actions ) {
				if ( actions.hasOwnProperty( action ) && actions[ action ] ) {
					this.activateButton( action, false );
				}
			}
		},

		handleGlobalOptions: function() {
			if ( '1' === KessoOptions.focusable ) {
				this.cache.$body.addClass( 'kesso-focusable' );
			}

			if ( '1' === KessoOptions.remove_link_target ) {
				$( 'a[target="_blank"]' ).attr( 'target', '' );
			}

			if ( '1' === KessoOptions.add_role_links ) {
				// Only anchors without an href lack the implicit link role; native
				// links already expose it, so leave them untouched.
				$( 'a:not([href]):not([role])' ).attr( 'role', 'link' );
			}
		},

		/**
		 * Smart positioning: detect other floating widgets in the same corner
		 * as the accessibility toolbar and offset it to avoid visual collisions.
		 * Best-effort — capped at 100px offset so we don't fly off-screen.
		 */
		smartPositioning: function() {
			var toolbar = this.cache.$toolbar && this.cache.$toolbar[0];
			if ( ! toolbar ) return;
			var tRect = toolbar.getBoundingClientRect();
			if ( tRect.width === 0 ) return;

			// Determine which corner we're in (based on where the toggle sits).
			var vw = window.innerWidth || document.documentElement.clientWidth;
			var vh = window.innerHeight || document.documentElement.clientHeight;
			var isBottom = ( tRect.top > vh / 2 );
			var isLeft   = ( tRect.left < vw / 2 );
			if ( ! isBottom ) return; // Only auto-adjust the bottom corners.

			// Cheap query: only candidates likely to be floating widgets.
			var selectors = [
				'a[class*="lang"], a[class*="switcher"]',
				'.wp-toolbar-lang, .lang-widget, .langswitch',
				'[id*="chat"], [class*="chat-widget"], [class*="Intercom"], [class*="livechat"]',
				'[id*="scroll-top"], [class*="scroll-top"], [class*="back-to-top"]',
				'[id*="whatsapp"], [class*="whatsapp"]',
				'a[href*="wa.me"], a[href*="whatsapp"]'
			];
			var candidates = document.querySelectorAll( selectors.join(',') );
			var maxOffset = 0;
			for ( var i = 0; i < candidates.length; i++ ) {
				var el = candidates[ i ];
				if ( el === toolbar || toolbar.contains( el ) ) continue;
				if ( el.closest && el.closest( '#kesso-toolbar' ) ) continue;
				if ( el.closest && el.closest( '#kesso-cookies-banner' ) ) continue;
				var cs = getComputedStyle( el );
				if ( cs.position !== 'fixed' && cs.position !== 'sticky' ) continue;
				if ( cs.visibility === 'hidden' || cs.display === 'none' ) continue;
				var r = el.getBoundingClientRect();
				if ( r.width === 0 || r.height === 0 ) continue;
				// Corner filter: candidate must be in the same bottom corner.
				var candLeft = isLeft ? ( r.left < 200 ) : ( vw - r.right < 200 );
				var candBottom = ( vh - r.bottom < 200 );
				if ( ! candLeft || ! candBottom ) continue;
				// If it vertically overlaps our toolbar zone, note the offset needed.
				var overlaps = ! ( r.bottom < tRect.top || r.top > tRect.bottom );
				var nearVertical = Math.abs( r.top - tRect.top ) < ( tRect.height + 20 );
				if ( overlaps || nearVertical ) {
					var offset = Math.ceil( r.height + 12 );
					if ( offset > maxOffset ) maxOffset = offset;
				}
			}
			if ( maxOffset > 0 && maxOffset < 100 ) {
				var currentBottom = parseInt( getComputedStyle( toolbar ).bottom, 10 ) || 20;
				toolbar.style.bottom = ( currentBottom + maxOffset ) + 'px';
			}
		},

		init: function() {
			this.cacheElements();
			this.buildElements();
			this.bindEvents();
			this.handleGlobalOptions();
			// Smart-positioning intentionally NOT called — jumps around too
			// unpredictably. The widget stays at the admin-configured corner.
		}
	};

	$( document ).ready( function( $ ) {
		Kesso_Accessibility_App.init();
		Kesso_Accessibility_App.setFromLocalStorage();
	} );

}( jQuery, window, document ) );
