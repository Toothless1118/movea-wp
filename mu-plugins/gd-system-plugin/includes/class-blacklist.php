<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Blacklist {

	/**
	 * Instance of the API.
	 *
	 * @var API_Interface
	 */
	private $api;

	/**
	 * Array of blacklisted plugins to deactivate.
	 *
	 * @var array
	 */
	private $found = [];

	/**
	 * Class constructor.
	 *
	 * @param API_Interface $api
	 */
	public function __construct( API_Interface $api ) {

		$this->api = $api;

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'activate_plugin',       [ $this, 'activate_plugin' ], PHP_INT_MAX, 2 );

		add_filter( 'plugin_action_links',         [ $this, 'plugin_action_links' ], PHP_INT_MAX, 2 );
		add_filter( 'plugin_install_action_links', [ $this, 'plugin_install_action_links' ], PHP_INT_MAX, 2 );

	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( ! in_array( $hook, [ 'plugin-install.php', 'plugins.php' ] ) ) {

			return;

		}

		$rtl    = is_rtl() ? '-rtl' : '';
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wpaas-tip', Plugin::assets_url( "js/jquery-tip{$suffix}.js" ), [ 'jquery' ], Plugin::version() );

		wp_enqueue_style( 'wpaas-tip', Plugin::assets_url( "css/jquery-tip{$rtl}{$suffix}.css" ), [], Plugin::version() );

	}

	/**
	 * Catch blacklisted plugins when they are activated.
	 *
	 * @action activate_plugin
	 *
	 * @param string $basename
	 * @param bool   $network_wide
	 */
	public function activate_plugin( $basename, $network_wide ) {

		if ( ! $this->is_blacklisted( $basename ) ) {

			return;

		}

		$this->found[] = $basename;

		if ( ! has_action( 'shutdown', [ $this, 'deactivate' ] ) ) {

			add_action( 'shutdown', [ $this, 'deactivate' ] );

		}

		$plugin_data = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $basename );

		Admin\Growl::add( sprintf( _x( '%s is not allowed on our system. It has been automatically deactivated.', 'Name of the disallowed plugin', 'gd-system-plugin' ), $plugin_data['Name'] ) );

	}

	/**
	 * Plugin list table action links.
	 *
	 * @filter plugin_action_links
	 *
	 * @param  array  $links
	 * @param  string $plugin
	 *
	 * @return array
	 */
	public function plugin_action_links( array $links, $plugin ) {

		if ( isset( $links['activate'] ) && $this->is_blacklisted( $plugin ) ) {

			unset( $links['edit'] );

			$links['activate'] = $this->message( is_rtl() ? 'left' : 'right' );

		}

		return $links;

	}

	/**
	 * Install action links for a plugin.
	 *
	 * @filter plugin_install_action_links
	 *
	 * @param  array $links
	 * @param  array $plugin
	 *
	 * @return array
	 */
	public function plugin_install_action_links( array $links, array $plugin ) {

		return $this->is_blacklisted( $plugin ) ? [ $this->message( is_rtl() ? 'right' : 'left' ) ] : $links;

	}

	/**
	 * Deactivate all blacklisted plugins found.
	 *
	 * @action shutdown
	 */
	public function deactivate() {

		deactivate_plugins( $this->found );

	}

	/**
	 * Check if a plugin is blacklisted.
	 *
	 * @param  string|array $plugin
	 *
	 * @return bool
	 */
	private function is_blacklisted( $plugin ) {

		if ( is_array( $plugin ) && ( empty( $plugin['slug'] ) || empty( $plugin['version'] ) ) ) {

			return false;

		}

		if ( ! is_array( $plugin ) ) {

			$data   = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin );
			$plugin = [
				'slug'    => $this->get_plugin_slug( $plugin ),
				'version' => $data['Version'],
			];

		}

		foreach ( $this->api->get_blacklist() as $blacklisted_plugin ) {

			if (
				0 === strcasecmp( $plugin['slug'], $blacklisted_plugin['name'] )
				&&
				version_compare( $plugin['version'], $blacklisted_plugin['minVersion'], '>=' )
				&&
				version_compare( $plugin['version'], $blacklisted_plugin['maxVersion'], '<=' )
			) {

				return true;

			}

		}

		return false;

	}

	/**
	 * Converts a plugin basename back into a friendly slug.
	 *
	 * From utils-wp.php (wp-cli)
	 *
	 * @param  string $basename
	 *
	 * @return string
	 */
	private function get_plugin_slug( $basename ) {

		return ( false === strpos( $basename, '/' ) ) ? basename( $basename, '.php' ) : dirname( $basename );

	}

	/**
	 * Message to display for blacklisted plugins.
	 *
	 * @param  string $direction
	 *
	 * @return string
	 */
	private function message( $direction = 'left' ) {

		$direction = wp_is_mobile() ? 'top' : $direction;

		return sprintf(
			'<span class="wpaas-blacklisted-plugin">%s <a href="javascript:void(0);" class="wpaas-tip" data-tooltip="%s" data-tooltip-direction="%s"><span class="dashicons dashicons-editor-help"></span></a></span>',
			__( 'Not Available', 'gd-system-plugin' ),
			__( 'This plugin is not allowed on our system due to performance, security, or compatibility concerns. Please contact support with any questions.', 'gd-system-plugin' ),
			esc_attr( $direction )
		);

	}

}
