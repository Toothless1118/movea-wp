<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class System_404 {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'wp', [ $this, 'stop_infinite_404_loops' ] );

	}

	/**
	 * Stop infinite 404 loops.
	 *
	 * Some plugins / themes fetch local resources over http.
	 * When the resources aren't there, WordPress returns a
	 * 404 page, which causes the theme / plugin to fire again
	 * and try to fetch the same missing resource and creates
	 * an infinite 404 loop. This intercedes and stops that
	 * behavior. Pages that end with .htm and .html will still
	 * render correctly.
	 *
	 * @action wp
	 */
	public function stop_infinite_404_loops() {

		global $wp_query;

		if ( empty( $wp_query->query['pagename'] ) ) {

			return;

		}

		$pagename = $wp_query->query['pagename'];

		if ( is_404() && preg_match( '/^[^?&=]+\.(css|gif|jpeg|jpg|js|png)(\?|&)?(.*)?$/i', $pagename ) ) {

			status_header( 404 );

			switch ( strtolower( pathinfo( $pagename, PATHINFO_EXTENSION ) ) ) {

				case 'css' :

					$this->header( 'Content-type: text/css' );

					echo "\n";

					break;

				case 'gif' :

					$this->header( 'Content-type: image/gif' );

					include Plugin::assets_dir( 'images/404.gif' );

					break;

				case 'jpg' :
				case 'jpeg' :

					$this->header( 'Content-type: image/jpeg' );

					include Plugin::assets_dir( 'images/404.jpg' );

					break;

				case 'js' :

					$this->header( 'Content-type: application/javascript' );

					echo "\n";

					break;

				case 'png' :

					$this->header( 'Content-type: image/png' );

					include Plugin::assets_dir( 'images/404.png' );

					break;

			}

			wp_die( '404' );

		}

	}

	/**
	 * Apply a header override.
	 *
	 * @param string $header
	 */
	private function header( $header ) {

		if ( 'cli' === PHP_SAPI ) {

			do_action( 'wpaas_header_sent', $header );

			return;

		}

		if ( ! headers_sent() ) {

			header( $header );

		}

	}

}
