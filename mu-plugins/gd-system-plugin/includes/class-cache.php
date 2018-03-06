<?php

namespace WPaaS;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Cache {

	/**
	 * Required user capability.
	 *
	 * @var string
	 */
	public static $cap = 'activate_plugins';

	/**
	 * Array of URLs to be purged.
	 *
	 * @var array
	 */
	public static $purge_urls = [];

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/**
		 * Filter the user cap required to flush cache.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		self::$cap = (string) apply_filters( 'wpaas_flush_cache_cap', self::$cap );

		add_action( 'init',                       [ $this, 'init' ], -PHP_INT_MAX );
		add_action( 'update_option',              [ $this, 'update_option' ], PHP_INT_MAX, 1 );
		add_action( '_core_updated_successfully', [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'customize_save',             [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'switch_theme',               [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'activated_plugin',           [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'deactivated_plugin',         [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'upgrader_process_complete',  [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
//		add_action( 'wp_update_nav_menu',         [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
//		add_action( 'wp_delete_nav_menu',         [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'wpaas_file_editor_save',     [ $this, 'do_ban' ], PHP_INT_MAX, 0 );
		add_action( 'clean_post_cache',           [ $this, 'do_purge' ], PHP_INT_MAX, 2 );
		add_action( 'clean_comment_cache',        [ $this, 'do_purge' ], PHP_INT_MAX );

		add_filter( 'script_loader_src', [ $this, 'nocache' ] );
		add_filter( 'style_loader_src',  [ $this, 'nocache' ] );

	}

	/**
	 * Make a non-blocking request to Varnish.
	 *
	 * @param string $method
	 * @param string $url (optional)
	 */
	private static function request( $method, $url = null ) {

		$url  = empty( $url ) ? home_url() : $url;
		$host = parse_url( $url, PHP_URL_HOST );
		$url  = set_url_scheme( str_replace( $host, Plugin::vip(), $url ), 'http' );

		wp_cache_flush();

		// This forces the APC cache to flush across the server
		update_option( 'gd_system_last_cache_flush', time() );

		wp_remote_request(
			esc_url_raw( $url ),
			[
				'method'   => $method,
				'blocking' => false,
				'headers'  => [
					'Host' => $host,
				],
			]
		);

	}

	/**
	 * Initialize script.
	 *
	 * @action init
	 */
	public function init() {

		$action = filter_input( INPUT_GET, 'wpaas_action' );
		$nonce  = filter_input( INPUT_GET, 'wpaas_nonce' );

		if (
			! current_user_can( self::$cap )
			||
			'flush_cache' !== $action
			||
			false === wp_verify_nonce( $nonce, 'wpaas_flush_cache' )
		) {

			return;

		}

		$this->do_ban();

		self::flush_transients();

		Admin\Growl::add( __( 'Cache cleared', 'gd-system-plugin' ) );

		wp_safe_redirect(
			esc_url_raw(
				remove_query_arg(
					[
						'GD_COMMAND', // Backwards compat
						'wpaas_action',
						'wpaas_nonce',
					]
				)
			)
		);

		exit;

	}

	/**
	 * Flush cache on shutdown when certain options are updated.
	 *
	 * @action update_option
	 *
	 * @param string $option
	 */
	public function update_option( $option ) {

		$options = [
//			'avatar_default',
//			'blogdescription',
//			'blogname',
//			'category_base',
//			'category_children',
//			'close_comments_days_old',
//			'close_comments_for_old_posts',
//			'comment_order',
//			'comment_registration',
//			'comments_per_page',
//			'date_format',
//			'default_comments_page',
//			'gmt_offset',
//			'page_comments',
//			'page_for_posts',
//			'page_on_front',
			'permalink_structure',
//			'posts_per_page',
//			'require_name_email',
//			'rewrite_rules',
//			'show_avatars',
			'sidebars_widgets',
//			'site_icon',
//			'start_of_week',
//			'sticky_posts',
//			'show_on_front',
//			'tag_base',
//			'thread_comments',
//			'thread_comments_depth',
//			'time_format',
//			'timezone_string',
//			'use_smilies',
//			'WPLANG',
		];

		if (
			in_array( $option, $options )
//			||
//			0 === strpos( $option, 'widget_' )
//			||
//			0 === strpos( $option, 'theme_mods_' )
		) {

			$this->do_ban();

		}

	}

	/**
	 * Set to ban cache on shutdown.
	 */
	public function do_ban() {

		if ( self::has_ban() ) {

			return;

		}

		remove_action( 'shutdown', [ __CLASS__, 'purge' ], PHP_INT_MAX );

		add_action( 'shutdown', [ __CLASS__, 'ban' ], PHP_INT_MAX );

	}

	/**
	 * Set purge URLs and set to purge cache on shutdown.
	 *
	 * @param int      $ID
	 * @param \WP_Post $post (optional)
	 */
	public function do_purge( $ID, $post = null ) {

		if ( self::has_ban() ) {

			return;

		}

		if ( ! is_a( $post, 'WP_Post' ) ) {

			// Assume anything that isn't a post is a comment
			$comment = get_comment( $ID );

			if ( ! is_a( $comment, 'WP_Comment' ) ) {

				return;

			}

			$post = get_post( $comment->comment_post_ID );

		}

		if ( wp_is_post_revision( $post ) ) {

			return;

		}

		/**
		 * Purge all URLs where a post might appear
		 */
		self::$purge_urls[] = untrailingslashit( home_url() );
		self::$purge_urls[] = trailingslashit( home_url() );
		self::$purge_urls[] = get_permalink( $post->ID );
		self::$purge_urls[] = get_post_type_archive_link( $post->post_type );
		self::$purge_urls[] = get_post_type_archive_feed_link( $post->post_type );
		self::$purge_urls[] = get_author_posts_url( (int) $post->post_author );

		// Taxonomy-related URLs
		foreach ( get_post_taxonomies( $post ) as $tax ) {

			$post_terms = wp_get_post_terms( $post->ID, $tax );

			if ( is_wp_error( $post_terms ) ) {

				continue;

			}

			foreach ( $post_terms as $term ) {

				self::$purge_urls[] = get_term_link( $term );
				self::$purge_urls[] = get_term_feed_link( $term->term_id, $term->taxonomy );

			}

		}

		foreach ( self::$purge_urls as $key => $url ) {

			// Archive page might return false
			if ( ! $url || is_wp_error( $url ) ) {

				unset( self::$purge_urls[ $key ] );

			}

		}

		self::$purge_urls = array_values( array_unique( self::$purge_urls ) );

		if ( ! self::has_purge() ) {

			add_action( 'shutdown', [ __CLASS__, 'purge' ], PHP_INT_MAX );

		}

	}

	/**
	 * Delete all transient data from the options table
	 *
	 * WordPress only deletes expired transients when something tries
	 * to call that transient key again. This means over time there could
	 * be many thousands of transient option rows polluting the database,
	 * which can result in noticable performance impact.
	 *
	 * This method should be called when the customer is explicitly
	 * clearing their site's cache. Since transients are a form of cache,
	 * we will flush them all away regardless of TTL status.
	 *
	 * @see HOSTAPPS-3157/WPDEV-708
	 *
	 * @return int|false Number of rows affected/selected or false on error.
	 */
	public static function flush_transients() {

		global $wpdb;

		return $wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%_transient_%';" );

	}

	/**
	 * Return a nonced flush cache URL.
	 *
	 * @return string
	 */
	public static function get_flush_url() {

		return esc_url(
			add_query_arg(
				[
					'wpaas_action' => 'flush_cache',
					'wpaas_nonce'  => wp_create_nonce( 'wpaas_flush_cache' ),
				]
			)
		);

	}

	/**
	 * Check if a BAN request is already set to fire on shutdown.
	 *
	 * @return bool
	 */
	public static function has_ban() {

		return has_action( 'shutdown', [ __CLASS__, 'ban' ] );

	}

	/**
	 * Check if a PURGE request is already set to fire on shutdown.
	 *
	 * @return bool
	 */
	public static function has_purge() {

		return has_action( 'shutdown', [ __CLASS__, 'purge' ] );

	}

	/**
	 * Ban all cache (async).
	 *
	 * @return bool
	 */
	public static function ban() {

		if ( 'shutdown' !== current_action() ) {

			return false;

		}

		self::request( 'BAN' );

		/**
		 * Fires after all site cache has been banned.
		 *
		 * @since 2.0.1
		 */
		do_action( 'wpaas_cache_banned' );

		return true;

	}

	/**
	 * Purge the Varnish cache selectively (async).
	 *
	 * @param  array $urls (optional)
	 *
	 * @return bool
	 */
	public static function purge( $urls = [] ) {

		if ( 'shutdown' !== current_action() ) {

			return false;

		}

		$urls = ( $urls ) ? $urls : self::$purge_urls;

		if ( ! $urls ) {

			return false;

		}

		$urls = array_unique( $urls );

		foreach ( $urls as $url ) {

			self::request( 'PURGE', $url );

		}

		/**
		 * Fires after cache has been purged on specific URLs.
		 *
		 * @since 2.0.1
		 *
		 * @param array $urls
		 */
		do_action( 'wpaas_cache_purged', $urls );

		return true;

	}

	/**
	 * Propogate nocache call to scripts and styles.
	 *
	 * When the `nocache` query arg is being used in the page
	 * request, or if the page is being viewed by a logged in
	 * user, we need to ensure that any scripts and styles
	 * from this domain being called also use it.
	 *
	 * @filter script_loader_src
	 * @filter style_loader_src
	 *
	 * @param  string $src
	 *
	 * @return string
	 */
	public function nocache( $src ) {

		$is_external = ( false === stripos( $src, Plugin::domain() ) );
		$is_nocache  = ( false !== stripos( filter_input( INPUT_SERVER, 'QUERY_STRING' ), 'nocache' ) );

		if ( ! $is_external && ( is_user_logged_in() || $is_nocache ) ) {

			return add_query_arg( 'nocache', 1, $src );

		}

		return $src;

	}

}
