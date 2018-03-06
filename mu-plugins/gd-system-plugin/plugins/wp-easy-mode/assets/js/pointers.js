/* globals wpem_pointers, wpPointerL10n */

window.WPEM = window.WPEM || {};

window.WPEM.Pointers = ( function ( $ ) {

	var pointers     = {},
			updateServer = true;

	/**
	 * Render a pointer and opens it by default
	 * @param pointer
   */
	function render( pointer ) {

		var options = $.extend( pointer.options, {

			pointerClass: 'wp-pointer wpem-pointer',

			close: function() {

				if ( updateServer ) {

					$.post( pointer.ajaxurl, {
						pointer: pointer.id,
						action: 'dismiss-wp-pointer'
					} );

				}

			},

			show: function( event, t ) {

				t.pointer.css( 'display', 'none' );

				t.opened();

				setTimeout( function() {

					t.pointer.fadeIn( 'fast' );

				}, 5 );

			},

			hide: function( event, t ) {

				t.pointer.fadeOut( 'fast' );

				t.closed();

			}

		} );

		if ( pointer.hasOwnProperty( 'btn_primary' ) ) {

			options.buttons = function buttons( event, t ) {

				var btn_close = '',
						closeLabel  = ( wpPointerL10n ) ? wpPointerL10n.dismiss : 'Dismiss';

				if ( pointer.hasOwnProperty( 'btn_close' ) ) {

					btn_close = '<button class="button-secondary">' + closeLabel + '</button>';

				}

				var btn_primary_class = pointer.hasOwnProperty( 'btn_close' ) ? pointer.btn_primary_class : '';

				var $buttons = $(
						'<div class="buttons-wrapper">' +
						btn_close +
						'<button class="button-primary ' + btn_primary_class + '">' + pointer.btn_primary + '</button>' +
						'</div>'
				);

				return $buttons.bind( 'click.pointer', function( e ) {

					e.preventDefault();

					if ( pointer.hasOwnProperty( 'next_pointer' ) ) {

						t.element.pointer( 'close' );

						window.WPEM.Pointers.open( pointer.next_pointer );

						// Delete the reference in the array
						delete pointers[ pointer.id ];

					}

					if ( 'button-secondary' === e.target.className || pointer.hasOwnProperty( 'btn_primary_close' ) ) {

						t.element.pointer( 'close' );

						// Delete the reference in the array
						delete pointers[ pointer.id ];

					}

				} );

			};

			// Assign new created pointer to object for reference
			pointers[ pointer.id ] = {
				target: pointer.target,
				options: options
			};

			// This enables us to have pointer close by default
			if ( ! pointer.hasOwnProperty('close_on_load') ) {

				this.open( pointer.id );

			}

		}

	}

	/**
	 * Open a pointer by id
	 * @param id
	 */
	function open( id ) {

		if ( pointers.hasOwnProperty( id ) ) {

			var pointer = pointers[ id ];

			pointers[ id ].$target = $( pointer.target );

			pointer.$target.pointer( pointer.options ).pointer( 'open' );

			// Put pointers in front of everything
			$( '.wp-pointer' ).css( { 'z-index': 999999 } );

			return true;

		}

		return false;

	}

	/**
	 * Open first element in range of pointer
	 *
	 * @param id
	 * @param range
   */
	function openFirstInRange( id, range ) {

		$.each( range, function( index, value ) {

			return ! window.WPEM.Pointers.open( id + value );

		} );

	}

	/**
	 * Returns true if the current pointer is open
	 *
	 * @param id
	 * @returns {*}
	 */
	function isPointerOpen( id ) {

		if ( ! pointers.hasOwnProperty( id ) ) {

			return false;

		}

		// $target means the pointer was instanciated
		return pointers[ id ].hasOwnProperty( '$target' );

	}

	/**
	 * Temporarily close all pointers
	 */
	function hideAll() {

		// Work around since there is no hide method in the library
		updateServer = false;

		$.each( pointers, function( index, pointer ) {

			// $target means the pointer was instanciated
			if ( pointer.hasOwnProperty( '$target' ) ) {

				pointer.$target.pointer('close');

			}

		} );

		updateServer = true;

	}

	// Expose public methods
	return {
		render:           render,
		open:             open,
		openFirstInRange: openFirstInRange,
		hideAll:          hideAll,
		isPointerOpen:    isPointerOpen
	};

} )( jQuery );

jQuery( document ).ready( function( $ ) {

	$.each( wpem_pointers, function( i, pointer ) {

		window.WPEM.Pointers.render( pointer );

	} );

} );
