( function( $ ) {
	FLBuilderSettingsConfig = 'undefined' === typeof FLBuilderSettingsConfig ? {} : FLBuilderSettingsConfig;
	$.extend( FLBuilderSettingsConfig, <?php echo json_encode( FLBuilderUISettingsForms::get_js_config() ); ?> );
} )( jQuery );
