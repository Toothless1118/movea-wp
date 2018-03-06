<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Event;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Post_Helpers {

	/**
	 * Array of post types to always ignore.
	 *
	 * @var array
	 */
	protected $excluded_post_types = [
		'attachment',
		'nav_menu_item',
		'revision',
	];

	/**
	 * Array of post statuses to always ignore.
	 *
	 * @var array
	 */
	protected $excluded_post_statuses = [
		'auto-draft',
		'inherit',
	];

	/**
	 * Run on load.
	 */
	protected function load() {

		foreach ( get_post_types( [ 'hierarchical' => true ] ) as $post_type ) {

			$this->excluded_post_types[] = $post_type;

		}

	}

	/**
	 * Check if a post type is excluded.
	 *
	 * @param  string $post_type
	 *
	 * @return bool
	 */
	protected function is_excluded_post_type( $post_type ) {

		return in_array( $post_type, $this->excluded_post_types );

	}

	/**
	 * Check if a post status is excluded.
	 *
	 * @param  string $post_status
	 *
	 * @return bool
	 */
	protected function is_excluded_post_status( $post_status ) {

		return in_array( $post_status, $this->excluded_post_statuses );

	}

	/**
	 * Return a label for a given post type.
	 *
	 * @param  string $post_type
	 * @param  string $label (optional)
	 *
	 * @return string
	 */
	protected function get_post_type_label( $post_type, $label = 'singular_name' ) {

		$name = __( 'Post' );

		if ( post_type_exists( $post_type ) ) {

			$labels = get_post_type_object( $post_type )->labels;
			$name   = isset( $labels->{$label} ) ? $labels->{$label} : $name;

		}

		return $name;

	}

	/**
	 * Return the post revision ID for a given post.
	 *
	 * @param  WP_Post $post
	 *
	 * @return int
	 */
	protected function get_post_revision_id( $post ) {

		if ( ! wp_revisions_enabled( $post ) ) {

			return '';

		}

		$revision = get_children(
			[
				'post_type'      => 'revision',
				'post_status'    => 'inherit',
				'post_parent'    => $post->ID,
				'posts_per_page' => 1,
				'orderby'        => 'post_date',
				'order'          => 'DESC',
			]
		);

		if ( ! $revision ) {

			return '';

		}

		$revision = array_values( $revision );

		return $revision[0]->ID;

	}

	/**
	 * Return an array of meta for a post log.
	 *
	 * @param WP_Post $post
	 * @param string  $old_status (optional)
	 *
	 * @return array
	 */
	protected function get_log_meta( $post, $old_status = null ) {

		$meta = [
			'post_title'      => $post->post_title,
			'singular_name'   => strtolower( $this->get_post_type_label( $post->post_type ) ),
			'post_date'       => get_date_from_gmt( $post->post_date_gmt, __( 'M j, Y @ H:i' ) ),
			'post_date_gmt'   => Event::e_time( $post->post_date_gmt ),
			'post_id'         => $post->ID,
			'post_type'       => $post->post_type,
			'revision_id'     => $this->get_post_revision_id( $post ),
			'sticky'          => is_sticky( $post->ID ),
			'post_status'     => $post->post_status,
		];

		// Include the old post status, if available
		if ( null !== $old_status ) {

			$meta['old_post_status'] = $old_status;

		}

		// Only add WPEM meta if the site used WPEM
		if ( \WPaaS\Plugin::has_used_wpem() ) {

			// Whether the event occurred on a page that originated from WPEM
			// The post meta key for WPEM pages is `wpnux_page`
			$meta['wpem_id'] = ( $wpem_id = get_post_meta( $post->ID, 'wpnux_page', true ) ) ? $wpem_id : false;

		}

		// Only add Page Builder meta if the site used WPEM and the plugin is active
		if ( \WPaaS\Plugin::has_used_wpem() && class_exists( 'FLBuilder' ) ) {

			// Whether the page event was initiated by Page Builder
			$meta['pagebuilder'] = is_a( BeaverBuilder::get_post(), 'WP_Post' );

		}

		return $meta;

	}

}
