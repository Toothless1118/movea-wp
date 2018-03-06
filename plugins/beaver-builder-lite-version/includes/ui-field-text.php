<input
type="text"
name="{{data.name}}"
value="{{data.value}}"
class="text <# if ( data.field.className ) { #> {{data.field.className}}<# } #><# if ( ! data.field.size ) { #> text-full<# } #>"
<# if ( data.field.placeholder ) { #>placeholder="{{data.field.placeholder}}" <# } #>
<# if ( data.field.maxlength ) { #>maxlength="{{data.field.maxlength}}" <# } #>
<# if ( data.field.size ) { #>size="{{data.field.size}}" <# } #>
/>
