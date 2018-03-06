<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Color_Scheme {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		if ( Plugin::is_gd() ) {

			add_action( 'admin_init', [ $this, 'godaddy' ], 1 );

		}

	}

	/**
	 * Register GoDaddy color scheme.
	 *
	 * @action admin_init
	 */
	public function godaddy() {

		$rtl    = is_rtl() ? '-rtl' : '';
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		$url = add_query_arg(
			[
				'ver' => Plugin::version(),
			],
			Plugin::assets_url( "css/color-scheme/godaddy/colors{$rtl}{$suffix}.css" )
		);

		wp_admin_css_color(
			'godaddy',
			'GoDaddy',
			$url,
			[
				'#212121',
				'#77c043',
				'#008a32',
				'#f2812e',
			],
			[
				'base'    => '#ededee',
				'focus'   => '#ededee',
				'current' => '#ededee',
			]
		);

	}

}
