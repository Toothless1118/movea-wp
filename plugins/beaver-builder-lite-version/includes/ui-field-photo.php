<#

var photo = null;

if ( FLBuilderSettingsConfig.attachments[ data.value ] ) {
	photo = FLBuilderSettingsConfig.attachments[ data.value ];
	photo.isAttachment = true;
} else if ( ! _.isEmpty( data.value ) ) {
	photo = {
		id: data.value,
		url: data.settings[ data.name + '_src' ],
		filename: data.settings[ data.name + '_src' ].split( '/' ).pop(),
		isAttachment: false
	};
}

var className = data.field.className ? ' ' + data.field.className : '';

if ( ! data.value || ! photo ) {
	className += ' fl-photo-empty';
} else if ( photo ) {
	className += photo.isAttachment ? ' fl-photo-has-attachment' : ' fl-photo-no-attachment';
}

#>
<div class="fl-photo-field fl-builder-custom-field{{className}}">
	<a class="fl-photo-select" href="javascript:void(0);" onclick="return false;"><?php _e( 'Select Photo', 'fl-builder' ); ?></a>
	<div class="fl-photo-preview">
		<div class="fl-photo-preview-img">
			<img src="<# if ( photo ) { var src = FLBuilder._getPhotoSrc( photo ); #>{{{src}}}<# } #>" />
		</div>
		<div class="fl-photo-preview-controls">
			<select name="{{data.name}}_src">
				<# if ( photo && data.settings[ data.name + '_src' ] ) {
					var sizes = FLBuilder._getPhotoSizeOptions( photo, data.settings[ data.name + '_src' ] );
				#>
				{{{sizes}}}
				<# } #>
			</select>
			<div class="fl-photo-preview-filename">
				<# if ( photo ) { #>{{{photo.filename}}}<# } #>
			</div>
			<br />
			<a class="fl-photo-edit" href="javascript:void(0);" onclick="return false;"><?php _e( 'Edit', 'fl-builder' ); ?></a>
			<# if ( data.show_remove ) { #>
			<a class="fl-photo-remove" href="javascript:void(0);" onclick="return false;"><?php _e( 'Remove', 'fl-builder' ); ?></a>
			<# } else { #>
			<a class="fl-photo-replace" href="javascript:void(0);" onclick="return false;"><?php _e( 'Replace', 'fl-builder' ); ?></a>
			<# } #>
		</div>
	</div>
	<input name="{{data.name}}" type="hidden" value='{{data.value}}' />
</div>
