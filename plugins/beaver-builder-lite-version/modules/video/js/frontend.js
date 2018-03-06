(function($) {

	$(function() {
		$('.fl-embed-video').fitVids();

		// Fix multiple videos where autoplay is enabled.
		if ( $('.fl-module-video video').length > 1 ) {
			$('.fl-module-video video').mediaelementplayer( {pauseOtherPlayers: false} );
		}
	});

})(jQuery);
