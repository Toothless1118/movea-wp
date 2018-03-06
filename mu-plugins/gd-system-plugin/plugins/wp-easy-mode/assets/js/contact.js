jQuery( document ).ready( function( $ ) {

	$.fn.selectString = function( string ) {

		var el    = $( this )[0],
		    start = el.value.indexOf( string ),
		    end   = start + string.length;

		if ( ! el || start < 0 ) {

			return;

		} else if ( el.setSelectionRange ) {

			// Webkit
			el.focus();
			el.setSelectionRange( start, end );

		} else if ( el.createTextRange ) {

			var range = el.createTextRange();

			// IE
			range.collapse( true );
			range.moveEnd( 'character', end );
			range.moveStart( 'character', start );
			range.select();

		} else if ( el.selectionStart ) {

			el.selectionStart = start;
			el.selectionEnd   = end;

		}

	}

	var $template = $( '#wpem-social-link-template' );

	$.each( $( '.wpem-contact-social-grid a' ), function() {

		if ( $( this ).hasClass( 'active' ) ) {

			addField( $( this ) );

		}

	} );

	$( '.wpem-contact-social-grid' ).on( 'click', 'a', function( e ) {

		e.preventDefault();

		var $field = $( '#wpem_social_profile_' + $( this ).data( 'key' ) ).closest( 'p' );

		if ( $field && $field.is( ':animated' ) ) {

			return false;

		}

		if ( $( this ).hasClass( 'active' ) ) {

			removeField( $( this ) );

		} else {

			addField( $( this ) );

		}

		$( this ).toggleClass( 'active' );

	} );

	function addField( $button ) {

		var $clone = $( $.trim( $template.clone().html() ) ),
		    key    = $button.data( 'key' ),
		    id     = 'wpem_social_profile_' + key;

		$clone.find( 'p' ).addClass( id );

		$clone.find( 'label' ).append( $button.prop( 'title' ) ).prop( 'for', id );

		$clone.find( 'label i' ).addClass( $button.find( 'i' ).prop( 'class' ) );

		$clone.find( 'input' )
			.prop( 'id', id )
			.prop( 'name', 'wpem_social_profiles[' + key + ']' )
			.prop( 'value', $button.data( 'url' ) )
			.prop( 'placeholder', $button.data( 'placeholder' ) );

		$clone.hide().prependTo( '#wpem-contact-social-fields' )
			.stop( true, true )
			.animate( {
				height: 'toggle',
				opacity: 'toggle'
			}, 300 );

		$clone.find( 'input' ).selectString( $button.data( 'select' ) );

	}

	function removeField( $button ) {

		$( '#wpem_social_profile_' + $button.data( 'key' ) ).closest( 'p' )
			.stop( true, true )
			.animate( {
				height: 'toggle',
				opacity: 'toggle'
			}, 300, function() {
				$( this ).remove();
			} );

	}

} );
