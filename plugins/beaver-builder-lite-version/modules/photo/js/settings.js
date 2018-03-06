(function($){

	FLBuilder.registerModuleHelper('photo', {

		init: function()
		{
			var form        = $('.fl-builder-settings'),
				photoSource = form.find('select[name=photo_source]');

			this._photoSourceChanged();
			photoSource.on('change', this._photoSourceChanged);
		},

		_photoSourceChanged: function()
		{
			var form        = $('.fl-builder-settings'),
				photoSource = form.find('select[name=photo_source]').val(),
				linkType    = form.find('select[name=link_type]');

			linkType.find('option[value=page]').remove();

			if(photoSource == 'library') {
				linkType.append('<option value="page">' + FLBuilderStrings.photoPage + '</option>');
			}
		}
	});

})(jQuery);
