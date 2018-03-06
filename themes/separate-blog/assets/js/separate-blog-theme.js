jQuery(document).ready(function ( $ ) {
    'use strict';

    // ------------------------------------------------------- //
    // Equalixe height
    // ------------------------------------------------------ //
    function equalizeHeight( x, y ) {
        var textHeight = $( x ).height();
        $( y ).css( 'min-height', textHeight );
    }
    equalizeHeight( '.separate-posts .text', '.separate-posts .image' );

    $( window ).resize(function () {
        equalizeHeight( '.separate-posts .text', '.separate-posts .image' );
    });

    // ---------------------------------------------- //
    // Preventing URL update on navigation link click
    // ---------------------------------------------- //
    $( '.link-scroll' ).bind( 'click', function ( e ) {
        var anchor = $( this );
        $( 'html, body' ).stop().animate( {
            scrollTop: $( anchor.attr( 'href' ) ).offset().top - 90
        }, 700 );
        e.preventDefault();
    });

    // ---------------------------------------------- //
    // Divider Section Parallax Background
    // ---------------------------------------------- //
    $( window ).on( 'scroll', function () {
        var scroll = $( this ).scrollTop();
        if ( $( window ).width() > 1250 ) {
            $( 'section.divider' ).css( {
                'background-position': 'left -' + scroll / 8 + 'px'
            } );
        } else {
            $( 'section.divider' ).css( {
                'background-position': 'center bottom'
            } );
        }
    });


    // ---------------------------------------------- //
    // Search Bar
    // ---------------------------------------------- //
    $( '.search-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        $( '.search-area' ).fadeIn();
    });
    $( '.search-area .close-btn' ).on( 'click', function () {
        $( '.search-area' ).fadeOut();
    });

    // ---------------------------------------------- //
    // Navbar Toggle Button
    // ---------------------------------------------- //
    $( '.navbar-toggler' ).on( 'click', function () {
        $( '.navbar-toggler' ).toggleClass( 'active' );
    });
    
});
