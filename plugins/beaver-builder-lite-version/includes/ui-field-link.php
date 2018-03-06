<div class="fl-link-field">
	<div class="fl-link-field-input-wrap">
		<input type="text" name="{{data.name}}" value="{{{data.value}}}" class="text fl-link-field-input" placeholder="<?php _ex( 'http://www.example.com', 'Link placeholder', 'fl-builder' ); ?>" />
		<button class="fl-link-field-select fl-builder-button fl-builder-button-small" href="javascript:void(0);" onclick="return false;"><?php _e( 'Select', 'fl-builder' ); ?></button>
	</div>
	<div class="fl-link-field-search">
		<span class="fl-link-field-search-title"><?php _e( 'Enter a post title to search.', 'fl-builder' ); ?></span>
		<input type="text" name="{{data.name}}-search" class="text text-full fl-link-field-search-input" placeholder="<?php esc_attr_e( 'Start typing...', 'fl-builder' ); ?>" />
		<button class="fl-link-field-search-cancel fl-builder-button fl-builder-button-small" href="javascript:void(0);" onclick="return false;"><?php _e( 'Cancel', 'fl-builder' ); ?></button>
	</div>
</div>
