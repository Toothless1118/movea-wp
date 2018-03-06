<script type="text/html" id="tmpl-fl-builder-settings-row">
	<# if ( data.isMultiple && data.supportsMultiple && data.template.length ) {
		var values = data.value,
			button = FLBuilderStrings.addField.replace( '%s', data.field.label ),
			i	   = 0;

		data.name += '[]';
	#>
	<tbody id="fl-field-{{data.rootName}}" class="fl-field fl-builder-field-multiples" data-type="form" data-preview='{{{data.preview}}}'>
		<# for( ; i < values.length; i++ ) {
			data.index = i;
			data.value = values[ i ];
		#>
		<tr class="fl-builder-field-multiple" data-field="{{data.rootName}}">
			<# var field = FLBuilderSettingsForms.renderField( data ); #>
			{{{field}}}
			<td class="fl-builder-field-actions">
				<i class="fl-builder-field-move fa fa-arrows"></i>
				<i class="fl-builder-field-copy fa fa-copy"></i>
				<i class="fl-builder-field-delete fa fa-times"></i>
			</td>
		</tr>
		<# } #>
		<tr>
			<# if ( ! data.field.label ) { #>
			<td colspan="2">
			<# } else { #>
			<td>&nbsp;</td><td>
			<# } #>
				<a href="javascript:void(0);" onclick="return false;" class="fl-builder-field-add fl-builder-button" data-field="{{data.rootName}}">{{button}}</a>
			</td>
		</tr>
	</tbody>
	<# } else { #>
	<tr id="fl-field-{{data.name}}" class="fl-field{{data.rowClass}}" data-type="{{data.field.type}}" data-preview='{{{data.preview}}}'>
		<# var field = FLBuilderSettingsForms.renderField( data ); #>
		{{{field}}}
	</tr>
	<# } #>
</script>
