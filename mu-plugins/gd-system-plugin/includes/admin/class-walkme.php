<?php

namespace WPaaS\Admin;

use WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class WalkMe {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'init' ] );

	}

	/**
	 * Initialize for logged in users only.
	 *
	 * @action init
	 */
	public function init() {

		if ( ! is_user_logged_in() || ! Plugin::is_gd() || ! Plugin::has_used_wpem() ) {

			return;

		}

		// So other plugins (WPEM) can know WalkMe is active
		define( 'GD_WALKME_ACTIVE', true );

		// This is good placement for both the WP Admin and Customizer
		add_action( 'admin_print_styles',  [ $this, 'dns_prefetch' ], 2 );
		add_action( 'admin_print_scripts', [ $this, 'print_scripts' ], 0 );

		// Only print on the front-end during Page Builder sessions or when the WP Admin Bar is showing.
		if (
			( ! is_admin() && class_exists( 'FLBuilder' ) && isset( $_GET['fl_builder'] ) )
			||
			( ! is_admin() && is_admin_bar_showing() )
		) {

			add_action( 'wp_enqueue_scripts', [ $this, 'dns_prefetch' ], 2 );
			add_action( 'wp_enqueue_scripts', [ $this, 'print_scripts' ], PHP_INT_MAX );

		}

		// Prevent the default tour for new users in Page Builder
		if ( ! is_admin() && class_exists( 'FLBuilder' ) && isset( $_GET['fl_builder'] ) ) {

			$user_id  = get_current_user_id();
			$launched = get_user_meta( $user_id, '_fl_builder_launched', true );

			if ( ! $launched ) {

				update_user_meta( $user_id, '_fl_builder_launched', true );

			}

		}

		// Expose post object on front-end pages being previewed in the Customizer
		if ( ! is_admin() && is_customize_preview() ) {

			add_action( 'wp_print_scripts', function () {

				$options = SCRIPT_DEBUG ? JSON_PRETTY_PRINT : 0;

				?>
				<script type="text/javascript">
				/* <![CDATA[ */
				parent.walkMeUserData.post = <?php echo wp_json_encode( $this->get_post_data(), $options ); ?>;
				/* ]]> */
				</script>
				<?php

			} );

		}

	}

	/**
	 * Print DNS prefetch elements.
	 *
	 * @action admin_print_styles
	 * @action wp_enqueue_scripts
	 */
	public function dns_prefetch() {

		echo '<link rel="dns-prefetch" href="//cdn.walkme.com" />' . PHP_EOL;
		echo '<link rel="dns-prefetch" href="//s3.amazonaws.com" />' . PHP_EOL; // Called inside *_https.js

	}

	/**
	 * Print inline scripts.
	 *
	 * @action admin_print_scripts
	 * @action wp_enqueue_scripts
	 */
	public function print_scripts() {

		$this->data();

		$env = Plugin::is_env( 'prod' ) ? '/' : '/test/';
		$url = "https://cdn.walkme.com/users/d0d425e1bc584619956e4a08cef17319{$env}walkme_d0d425e1bc584619956e4a08cef17319_https.js";

		?>
		<script type="text/javascript">(function() {var walkme = document.createElement('script'); walkme.type = 'text/javascript'; walkme.async = true; walkme.src = '<?php echo esc_url( $url ); ?>'; var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(walkme, s); window._walkmeConfig = {smartLoad:true}; })();</script>
		<?php

	}

	/**
	 * Print user data variable.
	 */
	private function data() {

		$user  = wp_get_current_user();
		$users = count_users();
		$theme = wp_get_theme();

		unset( $users['avail_roles']['none'] );

		$data = [
			'account' => [
				'brand'    => Plugin::brand(),
				'fqdn'     => gethostname(),
				'login'    => Plugin::account_id(),
				'php'      => PHP_VERSION,
				'reseller' => Plugin::reseller_id(),
				'staging'  => Plugin::is_staging_site(),
				'xid'      => Plugin::xid(),
			],
			'post' => $this->get_post_data(),
			'user' => [
				'id'     => $user->ID,
				'locale' => function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
				'role'   => ! empty( $user->roles[0] ) ? $user->roles[0] : '',
			],
			'wp' => [
				'blog_id'              => get_current_blog_id(),
				'context'              => $this->get_context(),
				'first_login'          => get_option( 'gd_system_first_login', null ),
				'locale'               => get_locale(),
				'multisite'            => is_multisite(),
				'parent_theme'         => $this->get_parent_theme(),
				'parent_theme_version' => $this->get_parent_theme( 'Version' ),
				'plugins'              => $this->get_active_plugins_array(),
				'site_id'              => is_multisite() ? (int) get_current_site()->id : 1,
				'site_url'             => site_url(),
				'temp_domain'          => Plugin::is_temp_domain(),
				'theme'                => $theme->get_stylesheet(),
				'theme_version'        => $theme->get( 'Version' ),
				'total_users'          => ! empty( $users['total_users'] ) ? $users['total_users'] : 1,
				'user_roles'           => ! empty( $users['avail_roles'] ) ? $users['avail_roles'] : [ 'administrator' => 1 ],
				'version'              => $GLOBALS['wp_version'],
				'wpaas'                => Plugin::version(),
				'wpem'                 => $this->get_wpem_timestamp(),
			],
		];

		$options = SCRIPT_DEBUG ? JSON_PRETTY_PRINT : 0;

		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		var walkMeUserData = <?php echo wp_json_encode( $data, $options ); ?>;
		/* ]]> */
		</script>
		<?php

	}

	/**
	 * Detect and return the current context.
	 *
	 * @return string|null
	 */
	private function get_context() {

		global $wp_customize;

		switch ( true ) {

			case is_a( $wp_customize, 'WP_Customize_Manager' ) :

				return 'customizer';

			case is_admin() :

				return 'wpadmin';

			case class_exists( 'FLBuilder' ) && isset( $_GET['fl_builder'] ) :

				return 'pagebuilder';

			case is_admin_bar_showing() :

				return 'fos';

			default :

				return null;

		}

	}

	/**
	 * Return meta data for the current post.
	 *
	 * @return array|null
	 */
	private function get_post_data() {

		$post = null;

		$show_on_front   = get_option( 'show_on_front', 'posts' );
		$page_on_front   = (int) get_option( 'page_on_front' );
		$page_for_posts  = (int) get_option( 'page_for_posts' );
		$is_latest_posts = ( is_home() && ( 'posts' === $show_on_front || ! $page_for_posts ) );

		switch ( true ) {

			case $is_latest_posts || is_archive() :

				break;

			case is_home() && $page_for_posts :

				$post = get_post( $page_for_posts );

				break;

			case is_front_page() && $page_on_front :

				$post = get_post( $page_on_front );

				break;

			default :

				global $post;

		}

		if ( ! is_a( $post, 'WP_Post' ) ) {

			return null;

		}

		/**
		 * When the context is wpadmin, we only want to include post data
		 * when editing a post on the `post.php` screen.
		 *
		 * We can't reference the `$post` global because on screens such as
		 * `edit.php` it will just use the first post in the list.
		 */
		if ( is_admin() && 1 !== preg_match( '/wp-admin\/post\.php/', $_SERVER['REQUEST_URI'] ) ) {

			return null;

		}

		return [
			'created'  => strtotime( $post->post_date_gmt ),
			'ID'       => (int) $post->ID,
			'modified' => strtotime( $post->post_modified_gmt ),
			'parent'   => (int) $post->post_parent,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'type'     => $post->post_type,
			'wpem_id'  => ( $wpem_id = get_post_meta( $post->ID, 'wpnux_page', true ) ) ? $wpem_id : null, // The post meta key for WPEM pages is `wpnux_page`
		];

	}

	/**
	 * Return the parent theme stylesheet slug, or a specific property.
	 *
	 * @param  string $property (optional)
	 *
	 * @return string|null
	 */
	private function get_parent_theme( $property = null ) {

		$template = get_template();

		if ( get_stylesheet() === $template ) {

			return null; // Current theme is not a child theme

		}

		$parent = wp_get_theme( $template );

		if ( ! $parent->exists() ) {

			return null; // Current theme's parent is missing

		}

		return ( $property ) ? $parent->get( $property ) : $parent->get_stylesheet();

	}

	/**
	 * Return an array of active plugins and their version.
	 *
	 * @return array
	 */
	private function get_active_plugins_array() {

		if ( ! function_exists( 'get_plugins' ) ) {

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		}

		$active_plugins = array_intersect_key(
			get_plugins(), // All plugins
			array_flip( (array) get_option( 'active_plugins', [] ) ) // Active plugins
		);

		foreach ( $active_plugins as &$plugin ) {

			$plugin = $plugin['Version'];

		}

		return $active_plugins;

	}

	/**
	 * Return a Unix timestamp of when WPEM was completed.
	 *
	 * @return int|null
	 */
	private function get_wpem_timestamp() {

		$log = json_decode( (string) get_option( 'wpem_log', '' ), true );

		if ( empty( $log['datetime'] ) || empty( $log['took'] ) ) {

			return null;

		}

		return strtotime( sprintf( '%s + %s seconds', $log['datetime'], round( $log['took'] ) ) );

	}

}
