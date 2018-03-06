<#

var placeholder = data.field.placeholder;

if ( 'object' !== typeof placeholder ) {
	placeholder = {
		top    : placeholder,
		right  : placeholder,
		bottom : placeholder,
		left   : placeholder,
	};
}

/**
 * We need to handle responsive dimension fields like this for backwards
 * compatibility with old margin, padding and border fields. If we did not do this
 * the keys would be margin_medium_top instead of the existing margin_top_medium.
 */
var responsive = data.name.replace( data.rootName, '' );

#>
<div class="fl-dimension-field-units">
	<div class="fl-dimension-field-unit">
		<input
		type="number"
		name="{{data.rootName}}_top{{responsive}}"
		value="{{data.settings[ data.rootName + '_top' + responsive ]}}"
		placeholder="{{placeholder.top}}"
		/>
		<label><?php _e( 'Top', 'fl-builder' ); ?></label>
	</div>
	<div class="fl-dimension-field-unit">
		<input
		type="number"
		name="{{data.rootName}}_right{{responsive}}"
		value="{{data.settings[ data.rootName + '_right' + responsive ]}}"
		placeholder="{{placeholder.right}}"
		/>
		<label><?php _e( 'Right', 'fl-builder' ); ?></label>
	</div>
	<div class="fl-dimension-field-unit">
		<input
		type="number"
		name="{{data.rootName}}_bottom{{responsive}}"
		value="{{data.settings[ data.rootName + '_bottom' + responsive ]}}"
		placeholder="{{placeholder.bottom}}"
		/>
		<label><?php _e( 'Bottom', 'fl-builder' ); ?></label>
	</div>
	<div class="fl-dimension-field-unit">
		<input
		type="number"
		name="{{data.rootName}}_left{{responsive}}"
		value="{{data.settings[ data.rootName + '_left' + responsive ]}}"
		placeholder="{{placeholder.left}}"
		/>
		<label><?php _e( 'Left', 'fl-builder' ); ?></label>
	</div>
</div>
