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

		/**
		 * Scale page text to the selected level.
		 *
		 * Each element is measured once at its natural size and given an absolute
		 * size, so nesting can't compound and the theme's units don't matter. Text
		 * that is already large grows only up to a ceiling, so display headings
		 * can't push the page wider than the screen.
		 */
		applyFontScale: function() {
			var self = this,
				levels = { 130: 1.1, 140: 1.2, 150: 1.3, 160: 1.4, 170: 1.5, 180: 1.6, 190: 1.8, 200: 2 },
				factor = levels[ this.variables.currentFontSize ] || 1,
				skip = '#kesso-toolbar, #kesso-skip-content, #wpadminbar, .kesso-cookies-banner, .kesso-cookies-panel, .kesso-cookies-settings-link',
				ignoredTags = /^(SCRIPT|STYLE|NOSCRIPT|SVG|PATH|IMG|VIDEO|CANVAS|IFRAME|BR|HR)$/i,
				controlTags = /^(INPUT|SELECT|TEXTAREA|BUTTON)$/i,
				maxScaledSize = 48,
				targets = [];

			// Restore everything first so measurements are always the natural size.
			$.each( this.variables.scaledElements || [], function( index, item ) {
				item.el.style.removeProperty( 'font-size' );
				item.el.style.removeProperty( 'overflow-wrap' );
				if ( item.value ) {
					item.el.style.setProperty( 'font-size', item.value, item.priority );
				}
			} );
			this.variables.scaledElements = [];

			if ( 1 === factor ) {
				this.watchFontScale( false );
				return;
			}

			$.each( document.body.querySelectorAll( '*' ), function( index, el ) {
				if ( ignoredTags.test( el.tagName ) || el.closest( skip ) ) {
					return;
				}
				var hasOwnText = Array.prototype.some.call( el.childNodes, function( node ) {
					return 3 === node.nodeType && node.textContent.trim();
				} );
				if ( hasOwnText || controlTags.test( el.tagName ) ) {
					targets.push( { el: el, size: parseFloat( getComputedStyle( el ).fontSize ) } );
				}
			} );

			$.each( targets, function( index, target ) {
				var size = Math.max( target.size, Math.min( target.size * factor, maxScaledSize ) );
				self.variables.scaledElements.push( {
					el: target.el,
					value: target.el.style.getPropertyValue( 'font-size' ),
					priority: target.el.style.getPropertyPriority( 'font-size' )
				} );
				target.el.style.setProperty( 'font-size', size + 'px', 'important' );
				// Long emails, phone numbers and URLs may break rather than widen the page.
				target.el.style.setProperty( 'overflow-wrap', 'anywhere' );
			} );

			this.watchFontScale( true );
		},

		/**
		 * Keep the scale applied to content added later and after screen changes.
		 */
		watchFontScale: function( enable ) {
			var self = this;

			if ( ! window.MutationObserver ) {
				return;
			}

			if ( ! this.fontScaleWatcher ) {
				var timer;
				this.onFontScaleChange = function() {
					clearTimeout( timer );
					timer = setTimeout( function() {
						self.applyFontScale();
					}, 250 );
				};
				this.fontScaleWatcher = new MutationObserver( this.onFontScaleChange );
			}

			this.fontScaleWatcher.disconnect();
			window.removeEventListener( 'orientationchange', this.onFontScaleChange );

			if ( enable ) {
				this.fontScaleWatcher.observe( document.body, { childList: true, subtree: true } );
				window.addEventListener( 'orientationchange', this.onFontScaleChange );
			}
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
				this.applyFontScale();
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

		init: function() {
			this.cacheElements();
			this.buildElements();
			this.bindEvents();
			this.handleGlobalOptions();
		}
	};

	$( document ).ready( function( $ ) {
		Kesso_Accessibility_App.init();
		Kesso_Accessibility_App.setFromLocalStorage();
	} );

}( jQuery, window, document ) );
