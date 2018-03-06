<div class="fl-builder-bar">
	<div class="fl-builder-bar-content">
		<?php FLBuilder::render_ui_bar_title(); ?>
		<?php FLBuilder::render_ui_bar_buttons(); ?>
		<div class="fl-clear"></div>
		<div class="fl-builder-publish-actions-click-away-mask"></div>
		<div class="fl-builder-publish-actions is-hidden">
			<span class="fl-builder-button-group">
				<span class="fl-builder-button fl-builder-button-primary" data-action="discard" title="<?php _e( 'Discard changes and exit', 'fl-builder' ) ?>"><?php _e( 'Discard', 'fl-builder' ) ?></span>
				<span class="fl-builder-button fl-builder-button-primary" data-action="draft" title="<?php _e( 'Keep changes drafted and exit', 'fl-builder' ) ?>"><?php _e( 'Save Draft', 'fl-builder' ) ?></span>
				<# if( 'publish' !== FLBuilderConfig.postStatus && ! FLBuilderConfig.userCanPublish ) { #>
				<span class="fl-builder-button fl-builder-button-primary" data-action="publish" title="<?php _e( 'Submit changes for review and exit', 'fl-builder' ) ?>"><?php _e( 'Submit for Review', 'fl-builder' ) ?></span>
				<# } else { #>
				<span class="fl-builder-button fl-builder-button-primary" data-action="publish" title="<?php _e( 'Publish changes and exit', 'fl-builder' ) ?>"><?php _e( 'Publish', 'fl-builder' ) ?></span>
				<# } #>
			</span>
			<span class="fl-builder-button" data-action="dismiss"><?php _e( 'Cancel', 'fl-builder' ) ?></span>
		</div>
	</div>
</div>
<div class="fl-builder--preview-actions">
	<span class="title-accessory device-icons">
		<i class="dashicons dashicons-smartphone" data-mode="responsive"></i>
		<i class="dashicons dashicons-tablet" data-mode="medium"></i>
		<i class="dashicons dashicons-desktop" data-mode="default"></i>
	</span>
	<button class="fl-builder-button fl-builder-button-primary end-preview-btn"><?php _e( 'Continue Editing', 'fl-builder' ) ?></button>
</div>
<div class="fl-builder--revision-actions">
	<select></select>
	<button class="fl-builder-button fl-cancel-revision-preview"><?php _e( 'Cancel', 'fl-builder' ) ?></button>
	<button class="fl-builder-button fl-builder-button-primary fl-apply-revision-preview"><?php _e( 'Apply', 'fl-builder' ) ?></button>
</div>
