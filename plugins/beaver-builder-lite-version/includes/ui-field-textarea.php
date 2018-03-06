<textarea
name="{{data.name}}"
<# if ( data.field.className ) { #>class="{{data.field.className}}" <# } #>
<# if ( data.field.placeholder ) { #>placeholder="{{data.field.placeholder}}" <# } #>
<# if ( data.field.rows ) { #>rows="{{data.field.rows}}" <# } #>
>{{data.value}}</textarea>
