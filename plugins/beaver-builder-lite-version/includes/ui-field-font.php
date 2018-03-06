<# data.value = JSON.stringify( data.value ); #>
<div class="fl-font-field" data-value='{{{data.value}}}'>
	<select name="{{data.name}}[][family]" class="fl-font-field-font">
		<?php FLBuilderFonts::display_select_font( 'Default' ) ?>
	</select>
	<select name="{{data.name}}[][weight]" class="fl-font-field-weight">
		<?php FLBuilderFonts::display_select_weight( 'Default', '' ) ?>
	</select>
</div>
