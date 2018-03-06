<?php

$vid_data = $module->get_data();
$preload  = FLBuilderModel::is_builder_active() ? ' preload="none"' : '';

?>

<div class="fl-video fl-<?php echo ( 'media_library' == $settings->video_type ) ? 'wp' : 'embed'; ?>-video" itemscope itemtype="https://schema.org/VideoObject">
	<?php

		global $wp_embed;

	if ( $vid_data && 'media_library' == $settings->video_type ) {
		echo '<meta itemprop="url" content="' . $vid_data->url . '" />';
		echo '<meta itemprop="thumbnail" content="' . $vid_data->poster . '" />';
		echo '[video width="100%" height="100%" ' . $vid_data->extension . '="' . $vid_data->url . '"' . $vid_data->video_webm . ' poster="' . $vid_data->poster . '"' . $vid_data->autoplay . $vid_data->loop . $preload . '][/video]';
	} elseif ( 'embed' == $settings->video_type ) {
		echo $wp_embed->autoembed( $settings->embed_code );
	}
	?>
</div>
