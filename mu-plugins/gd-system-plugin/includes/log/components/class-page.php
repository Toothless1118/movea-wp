<?php

namespace WPaaS\Log\Components;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Page extends Post {

	/**
	 * Run on load.
	 */
	protected function load() {

		foreach ( get_post_types( [ 'hierarchical' => false ] ) as $post_type ) {

			$this->excluded_post_types[] = $post_type;

		}

	}

}
