<?php

namespace WPaaS\Log\Components;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Theme_Helpers {

	/**
	 * Return an array of meta for a theme log.
	 *
	 * @param WP_Theme $theme
	 *
	 * @return array
	 */
	protected function get_log_meta( $theme ) {

		$meta = [
			'name'       => $theme->get( 'Name' ),
			'version'    => $theme->get( 'Version' ),
			'stylesheet' => $theme->get_stylesheet(),
			'template'   => $theme->get( 'Template' ),
			'author'     => $theme->get( 'Author' ),
		];

		// Whether the theme originated from WPEM
		if ( \WPaaS\Plugin::has_used_wpem() ) {

			$wpem_themes = array_filter(
				[
					get_option( 'wpem_theme' ),
					get_option( 'wpem_parent_theme' ),
				]
			);

			$meta['wpem_theme'] = in_array( $meta['stylesheet'], $wpem_themes, true ) ? $meta['stylesheet'] : false;

		}

		return $meta;

	}

}
