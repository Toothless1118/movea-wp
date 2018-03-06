/*global jQuery */

jQuery( document ).ready( function( $ ) {

  var $selects = $( '.jq_select' );

  $.each($selects, function( i, e ) {

    // need a dummy <p> wrapper element so that Select2's search field
    // will inherit the correct font size and line height styling
    var $wrapper = $( document.createElement('p') );

    $wrapper.appendTo( document.body );

    var $select = $( e );

    var opts = $select.data( 'select2-opts' ) || {};

    opts.dropdownParent = $wrapper;

    $select.select2(opts);

  });

});
