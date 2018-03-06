<div class="fl-icon-field fl-builder-custom-field<# if ( '' === data.value ) { #> fl-icon-empty<# } #><# if ( data.field.className ) { #> {{data.field.className}}<# } #>">
	<a class="fl-icon-select" href="javascript:void(0);" onclick="return false;"><?php _e( 'Select Icon', 'fl-builder' ); ?></a>
	<div class="fl-icon-preview">
		<i class="{{{data.value}}}" data-icon="{{{data.value}}}"></i>
		<a class="fl-icon-replace" href="javascript:void(0);" onclick="return false;"><?php _e( 'Replace', 'fl-builder' ); ?></a>
		<# if ( data.field.show_remove ) { #>
		<a class="fl-icon-remove" href="javascript:void(0);" onclick="return false;"><?php _e( 'Remove', 'fl-builder' ); ?></a>
		<# } #>
	</div>
	<input name="{{data.name}}" type="hidden" value="{{{data.value}}}" />
</div>
