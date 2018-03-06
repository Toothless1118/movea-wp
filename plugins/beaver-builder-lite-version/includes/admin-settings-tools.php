<div id="fl-tools-form" class="fl-settings-form">

	<h3 class="fl-settings-form-header"><?php _e( 'Cache', 'fl-builder' ); ?></h3>

	<form id="cache-form" action="<?php FLBuilderAdminSettings::render_form_action( 'tools' ); ?>" method="post">
		<div class="fl-settings-form-content">
			<p><?php _e( 'A CSS and JavaScript file is dynamically generated and cached each time you create a new layout. Sometimes the cache needs to be refreshed when you migrate your site to another server or update to the latest version. If you are running into any issues, please try clearing the cache by clicking the button below.', 'fl-builder' ); ?></p>
			<?php if ( is_network_admin() ) : ?>
			<p><strong><?php _e( 'NOTE:', 'fl-builder' ); ?></strong> <?php _e( 'This applies to all sites on the network.', 'fl-builder' ); ?></p>
			<?php elseif ( ! is_network_admin() && is_multisite() ) : ?>
			<p><strong><?php _e( 'NOTE:', 'fl-builder' ); ?></strong> <?php _e( 'This only applies to this site. Please visit the Network Admin Settings to clear the cache for all sites on the network.', 'fl-builder' ); ?></p>
			<?php endif; ?>

		</div>
		<p class="submit">
			<input type="submit" name="update" class="button-primary" value="<?php esc_attr_e( 'Clear Cache', 'fl-builder' ); ?>" />
			<?php wp_nonce_field( 'cache', 'fl-cache-nonce' ); ?>
		</p>
	</form>

	<hr />
<?php $debug = get_option( 'fl_debug_mode', false ); ?>
	<?php $header = ( $debug ) ? __( 'Debug Mode Enabled', 'fl-builder' ) : __( 'Debug Mode Disabled', 'fl-builder' ); ?>
	<h3 class="fl-settings-form-header"><?php echo $header ?></h3>

	<form id="cache-form" action="<?php FLBuilderAdminSettings::render_form_action( 'tools' ); ?>" method="post">
		<div class="fl-settings-form-content">
			<?php if ( ! $debug ) : ?>
			<p><?php _e( 'Enable debug mode to generate a unique support url.', 'fl-builder' ); ?></p>
		<?php else : ?>
			<p><?php _e( 'Copy this unique support URL and send it to Support as directed.', 'fl-builder' ); ?></p>
		<?php endif; ?>
			<?php if ( $debug ) :
				$url = add_query_arg( array(
					'fldebug' => $debug,
				), site_url() );
				?>
				<p><?php printf( '<code>%s</code>', $url ); ?></p>

			<?php endif; ?>
		</div>
		<p class="submit">
			<input type="submit" name="update" class="button-primary" value="<?php echo ( $debug ) ? esc_attr__( 'Disable Debug Mode', 'fl-builder' ) : esc_attr__( 'Enable Debug Mode', 'fl-builder' ); ?>" />
			<?php wp_nonce_field( 'debug', 'fl-debug-nonce' ); ?>
		</p>
	</form>

	<?php if ( is_network_admin() || ! self::multisite_support() ) : ?>

	<hr />

	<h3 class="fl-settings-form-header"><?php _e( 'Uninstall', 'fl-builder' ); ?></h3>

	<div class="fl-settings-form-content">
		<p><?php _e( 'Clicking the button below will uninstall the page builder plugin and delete all of the data associated with it. You can uninstall or deactivate the page builder from the plugins page instead if you do not wish to delete the data.', 'fl-builder' ); ?></p>
		<p><strong><?php _e( 'NOTE:', 'fl-builder' ); ?></strong> <?php _e( 'The builder does not delete the post meta <code>_fl_builder_data</code>, <code>_fl_builder_draft</code> and <code>_fl_builder_enabled</code> in case you want to reinstall it later. If you do, the builder will rebuild all of its data using those meta values.', 'fl-builder' ); ?></p>
		<?php if ( is_multisite() ) : ?>
		<p><strong><?php _e( 'NOTE:', 'fl-builder' ); ?></strong> <?php _e( 'This applies to all sites on the network.', 'fl-builder' ); ?></p>
		<?php endif; ?>
		<form id="uninstall-form" action="<?php FLBuilderAdminSettings::render_form_action( 'tools' ); ?>" method="post">
			<p class="submit">
				<input type="submit" name="uninstall-submit" class="button button-primary" value="<?php _e( 'Uninstall', 'fl-builder' ); ?>">
				<?php wp_nonce_field( 'uninstall', 'fl-uninstall' ); ?>
			</p>
		</form>
	</div>

	<?php endif; ?>

</div>
