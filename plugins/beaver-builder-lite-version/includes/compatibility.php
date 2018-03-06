<?php

/**
 * Misc functions for compatibility with other plugins.
 */

/**
 * Support for tinyPNG.
 *
 * Runs cropped photos stored in cache through tinyPNG.
 */
function fl_builder_tinypng_support( $cropped_path ) {

	if ( class_exists( 'Tiny_Settings' ) ) {
		try {
			$settings = new Tiny_Settings();
			$settings->xmlrpc_init();
			$compressor = $settings->get_compressor();
			if ( $compressor ) {
				$compressor->compress_file( $cropped_path['path'], false, false );
			}
		} catch ( Exception $e ) {
			//
		}
	}
}
add_action( 'fl_builder_photo_cropped', 'fl_builder_tinypng_support' );

/**
 * Support for WooCommerce Memberships.
 *
 * Makes sure builder content isn't rendered for protected posts.
 */
function fl_builder_wc_memberships_support() {

	if ( function_exists( 'wc_memberships_is_post_content_restricted' ) ) {

		function fl_builder_wc_memberships_maybe_render_content( $do_render, $post_id ) {

			if ( wc_memberships_is_post_content_restricted() ) {

				// check if user has access to restricted content
				if ( ! current_user_can( 'wc_memberships_view_restricted_post_content', $post_id ) ) {
					$do_render = false;
				} // End if().
				elseif ( ! current_user_can( 'wc_memberships_view_delayed_post_content', $post_id ) ) {
					$do_render = false;
				}
			}

			return $do_render;
		}
		add_filter( 'fl_builder_do_render_content', 'fl_builder_wc_memberships_maybe_render_content', 10, 2 );
	}
}
add_action( 'plugins_loaded', 'fl_builder_wc_memberships_support', 11 );

/**
 * Support for Option Tree.
 *
 * Older versions of Option Tree don't declare the ot_get_media_post_ID
 * function on the frontend which is needed for the media uploader and
 * throws an error if it doesn't exist.
 */
function fl_builder_option_tree_support() {

	if ( ! function_exists( 'ot_get_media_post_ID' ) ) {

		function ot_get_media_post_ID() { // @codingStandardsIgnoreLine

			// Option ID
			$option_id = 'ot_media_post_ID';

			// Get the media post ID
			$post_id = get_option( $option_id, false );

			// Add $post_ID to the DB
			if ( false === $post_id ) {

				global $wpdb;

				// Get the media post ID
				$post_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE `post_title` = 'Media' AND `post_type` = 'option-tree' AND `post_status` = 'private'" );

				// Add to the DB
				add_option( $option_id, $post_id );
			}

			return $post_id;
		}
	}
}
add_action( 'after_setup_theme', 'fl_builder_option_tree_support' );

/**
 * If FORCE_SSL_ADMIN is enabled but the frontend is not SSL fixes a CORS error when trying to upload a photo.
 * `add_filter( 'fl_admin_ssl_upload_fix', '__return_false' );` will disable.
 *
 * @since 1.10.2
 */
function fl_admin_ssl_upload_fix() {
	if ( defined( 'FORCE_SSL_ADMIN' ) && ! is_ssl() && is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		if ( isset( $_POST['action'] ) && 'upload-attachment' === $_POST['action'] && true === apply_filters( 'fl_admin_ssl_upload_fix', true ) ) {
			force_ssl_admin( false );
		}
	}
}
add_action( 'plugins_loaded', 'fl_admin_ssl_upload_fix', 11 );

/**
 * Disable support Buddypress pages since it's causing conflicts with `the_content` filter
 *
 * @param bool $is_editable Wether the post is editable or not
 * @param $post The post to check from
 * @return bool
 */
function fl_builder_bp_pages_support( $is_editable, $post = false ) {

	// Frontend check
	if ( ! is_admin() && class_exists( 'BuddyPress' ) && ! bp_is_blog_page() ) {
		$is_editable = false;
	}

	// Admin rows action link check and applies to page list
	if ( is_admin() && class_exists( 'BuddyPress' ) && $post && 'page' == $post->post_type ) {

		$bp = buddypress();
		if ( $bp->pages ) {
			foreach ( $bp->pages as $page ) {
				if ( $post->ID == $page->id ) {
					$is_editable = false;
					break;
				}
			}
		}
	}

	return $is_editable;
}
add_filter( 'fl_builder_is_post_editable', 'fl_builder_bp_pages_support', 11, 2 );

/**
 * There is an issue with Jetpack Photon and circle cropped photo module
 * returning the wrong image sizes from the bb cache folder.
 * This filter disables photon for circle cropped photo module images.
 */
function fl_photo_photon_exception( $val, $src, $tag ) {

	// Make sure its a bb cached image.
	if ( false !== strpos( $src, 'bb-plugin/cache' ) ) {

		// now make sure its a circle cropped image.
		if ( false !== strpos( basename( $src ), '-circle' ) ) {
			return apply_filters( 'fl_photo_photon_exception', true );
		}
	}
	// return original val
	return $val;
}
add_filter( 'jetpack_photon_skip_image', 'fl_photo_photon_exception', 10, 3 );

/**
 * WordPress pre 4.5 we need to make sure that ui-core|widget|mouse are loaded before sortable.
 */
function fl_before_sortable_enqueue_callback() {

	if ( version_compare( get_bloginfo( 'version' ), '4.5', '<' ) ) {
		wp_deregister_script( 'jquery-ui-widget' );
		wp_deregister_script( 'jquery-ui-mouse' );
		wp_deregister_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-core', site_url( '/wp-includes/js/jquery/ui/core.min.js' ), array( 'jquery' ), '1.8.12' );
		wp_enqueue_script( 'jquery-ui-widget', site_url( '/wp-includes/js/jquery/ui/widget.min.js' ), array( 'jquery' ), '1.8.12' );
		wp_enqueue_script( 'jquery-ui-mouse', site_url( '/wp-includes/js/jquery/ui/mouse.min.js' ), array( 'jquery' ), '1.8.12' );
	}
}
add_action( 'fl_before_sortable_enqueue', 'fl_before_sortable_enqueue_callback' );

/**
 * Try to unserialize data normally.
 * Uses a preg_callback to fix broken data caused by serialized data that has broken offsets.
 *
 * @since 1.10.6
 * @param string $data unserialized string
 * @return array
 */
function fl_maybe_fix_unserialize( $data ) {
	// @codingStandardsIgnoreStart
	$unserialized = @unserialize( $data );
	// @codingStandardsIgnoreEnd
	if ( ! $unserialized ) {
		$unserialized = unserialize( preg_replace_callback( '!s:(\d+):"(.*?)";!', 'fl_maybe_fix_unserialize_callback', $data ) );
	}
	return $unserialized;
}

/**
 * Callback function for fl_maybe_fix_unserialize()
 *
 * @since 1.10.6
 */
function fl_maybe_fix_unserialize_callback( $match ) {
	return ( strlen( $match[2] ) == $match[1] ) ? $match[0] : 's:' . strlen( $match[2] ) . ':"' . $match[2] . '";';
}

/**
 * Filter rendered module content and if safemode is active safely display a message.
 * @since 1.10.7
 */
function fl_builder_render_module_content_filter( $contents, $module ) {
	if ( isset( $_GET['safemode'] ) && FLBuilderModel::is_builder_active() ) {
		return sprintf( '<h3>[%1$s] %2$s %3$s</h3>', __( 'SAFEMODE', 'fl-builder' ), $module->name, __( 'module', 'fl-builder' ) );
	} else {
		return $contents;
	}
}

add_filter( 'fl_builder_render_module_content', 'fl_builder_render_module_content_filter', 10, 2 );

/**
 * Duplicate posts plugin fixes when cloning BB template.
 *
 * @since 1.10.8
 * @param int $meta_id The newly added meta ID
 * @param int $object_id ID of the object metadata is for.
 * @param string $meta_key Metadata key
 * @param string $meta_value Metadata value
 * @return void
 */
function fl_builder_template_meta_add( $meta_id, $object_id, $meta_key, $meta_value ) {
	global $pagenow;

	if ( 'admin.php' != $pagenow ) {
		return;
	}

	if ( ! isset( $_REQUEST['action'] ) || 'duplicate_post_save_as_new_post' != $_REQUEST['action'] ) {
		return;
	}

	$post_type = get_post_type( $object_id );
	if ( 'fl-builder-template' != $post_type || '_fl_builder_template_id' != $meta_key ) {
		return;
	}

	// Generate new template ID;
	$template_id = FLBuilderModel::generate_node_id();

	update_post_meta( $object_id, '_fl_builder_template_id', $template_id );
}
add_action( 'added_post_meta', 'fl_builder_template_meta_add', 10, 4 );

/**
 * Stop bw-minify from optimizing when builder is open.
 * @since 1.10.9
 */
function fl_bwp_minify_is_loadable_filter( $args ) {
	if ( FLBuilderModel::is_builder_active() ) {
		return false;
	}
	return $args;
}
add_filter( 'bwp_minify_is_loadable', 'fl_bwp_minify_is_loadable_filter' );

/**
 * Stop autoptimize from optimizing when builder is open.
 * @since 1.10.9
 */
function fl_autoptimize_filter_noptimize_filter( $args ) {
	if ( FLBuilderModel::is_builder_active() ) {
		return true;
	}
	return $args;
}
add_filter( 'autoptimize_filter_noptimize', 'fl_autoptimize_filter_noptimize_filter' );

/**
 * Plugin Enjoy Instagram loads its js and css on all frontend pages breaking the builder.
 * @since 2.0.1
 */
add_action( 'template_redirect', 'fix_aggiungi_script_instafeed_owl', 1000 );
function fix_aggiungi_script_instafeed_owl() {
	if ( FLBuilderModel::is_builder_active() ) {
		remove_action( 'wp_enqueue_scripts', 'aggiungi_script_instafeed_owl' );
	}
}

/**
* Siteground cache captures shutdown and breaks our dynamic js loading.
* @since 2.0.4.2
*/
add_action( 'plugins_loaded', 'fl_fix_sg_cache', 9 );
function fl_fix_sg_cache() {
	if ( isset( $_GET['fl_builder_load_settings_config'] ) ) {
		remove_action( 'plugins_loaded', 'sg_cachepress_start' );
	}
}
