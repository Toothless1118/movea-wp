<?php

/**
 * Handles the revisions UI for the builder.
 *
 * @since 2.0
 */
final class FLBuilderRevisions {

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0
	 * @return void
	 */
	static public function init() {
		add_filter( 'fl_builder_ui_js_config',		 __CLASS__ . '::ui_js_config' );
		add_filter( 'fl_builder_main_menu', 		 __CLASS__ . '::main_menu_config' );
	}

	/**
	 * Adds revision data to the UI JS config.
	 *
	 * @since 2.0
	 * @param array $config
	 * @return array
	 */
	static public function ui_js_config( $config ) {
		$config['revisions'] = self::get_config( $config['postId'] );

		return $config;
	}

	/**
	 * Gets the revision config for a post.
	 *
	 * @since 2.0
	 * @param int $post_id
	 * @return array
	 */
	static public function get_config( $post_id ) {
		$revisions    = wp_get_post_revisions( $post_id );
		$current_time = current_time( 'timestamp' );
		$config       = array(
			'posts'   	 => array(),
			'authors' 	 => array(),
		);

		if ( count( $revisions ) > 1 ) {

			foreach ( $revisions as $revision ) {

				if ( ! current_user_can( 'read_post', $revision->ID ) ) {
					continue;
				}
				if ( wp_is_post_autosave( $revision ) ) {
					continue;
				}

				$timestamp = strtotime( $revision->post_date );

				$config['posts'][] = array(
					'id'   	 => $revision->ID,
					'author' => $revision->post_author,
					'date'   => array(
						'published'	=> date( 'F j', $timestamp ),
						'diff' 		=> human_time_diff( $timestamp, $current_time ),
					),
				);

				if ( ! isset( $config['authors'][ $revision->post_author ] ) ) {
					$config['authors'][ $revision->post_author ] = array(
						'name'   => get_the_author_meta( 'display_name', $revision->post_author ),
						'avatar' => sprintf( '<img height="30" width="30" class="avatar avatar-30 photo" src="%s" />', esc_url( get_avatar_url( $revision->post_author, 30 ) ) ),
					);
				}
			}
		}

		return $config;
	}

	/**
	 * Adds revision data to the main menu config.
	 *
	 * @since 2.0
	 * @param array $config
	 * @return array
	 */
	static public function main_menu_config( $config ) {
		$config['main']['items'][35] = array(
			'label' => __( 'Revisions', 'fl-builder' ),
			'type' => 'view',
			'view' => 'revisions',
		);

		$config['revisions'] = array(
			'name' 		 => __( 'Revisions', 'fl-builder' ),
			'isShowing'  => false,
			'isRootView' => false,
			'items' 	 => array(),
		);

		return $config;
	}

	/**
	 * Renders the layout for a revision preview in the builder.
	 *
	 * @since 2.0
	 * @param int $revision_id
	 * @return array
	 */
	static public function render_preview( $revision_id ) {
		FLBuilderModel::set_post_id( $revision_id );

		return FLBuilderAJAXLayout::render();
	}

	/**
	 * Restores the current layout to a revision with the specified ID.
	 *
	 * @since 2.0
	 * @param int $revision_id
	 * @return array
	 */
	static public function restore( $revision_id ) {
		$data = FLBuilderModel::get_layout_data( 'published', $revision_id );

		FLBuilderModel::update_layout_data( $data );

		return array(
			'layout' => FLBuilderAJAXLayout::render(),
			'config' => FLBuilderUISettingsForms::get_node_js_config(),
		);
	}
}

FLBuilderRevisions::init();
