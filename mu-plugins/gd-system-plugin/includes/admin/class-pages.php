<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Pages {

	/**
	 * Get our hidden tabs
	 */
	const HIDE_OPTION_KEY = 'wpaas_toplevel_page_hidden_tabs';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private $slug = 'godaddy';

	/**
	 * Admin menu position.
	 *
	 * @var string
	 */
	private $position = '2.000001';

	/**
	 * Array of registered tabs.
	 *
	 * @var array
	 */
	private $tabs = [];

	/**
	 * Current tab slug.
	 *
	 * @var string
	 */
	private $tab;

	/**
	 * Class constructor
	 */
	public function __construct() {

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_scripts' ] );

		if ( ! Plugin::is_gd() ) {

			return;

		}

		/**
		 * Filter the admin page slug.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$this->slug = (string) apply_filters( 'wpaas_admin_page_slug', $this->slug );

		/**
		 * Filter the admin page menu position.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$this->position = (string) apply_filters( 'wpaas_admin_page_menu_position', $this->position );

		add_action( 'init',             [ $this, 'init' ] );
		add_action( 'admin_menu',       [ $this, 'register_menu_page' ] );
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );

	}

	/**
	 * Register tabs
	 *
	 * @action init
	 */
	public function init() {

		$this->tabs = [
			'help'    => __( 'FAQ &amp; Support', 'gd-system-plugin' ),
			'hire'    => __( 'Hire a Pro', 'gd-system-plugin' ),
			'plugins' => __( 'Plugin Partners', 'gd-system-plugin' ),
		];

		/**
		 * Filter the admin page tabs.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		$this->tabs = (array) apply_filters( 'wpaas_admin_page_tabs', $this->tabs );

		/**
		 * Only display the `Hire A Pro` tab to customers that:
		 *
		 * 1. Have completed WPEM
		 * 2. Speak English
		 * 3. Are located in the United States
		 */
		if ( ! Plugin::has_used_wpem() || ! Plugin::is_english() || 'US' !== Plugin::wpem_country_code() ) {

			unset( $this->tabs['hire'] );

		}

		// Hide tabs specified by the user
		foreach ( get_option( self::HIDE_OPTION_KEY, [] ) as $key ) {

			if ( 'help' === $key ) {

				continue;

			}

			unset( $this->tabs[ $key ] );

		}

		$tab = filter_input( INPUT_GET, 'tab' );

		$this->tab = ! empty( $tab ) && array_key_exists( $tab, $this->tabs ) ? sanitize_key( $tab ) : $this->tab;

	}

	/**
	 * Enqueue styles needed for the admin bar.
	 *
	 * @action wp_enqueue_scripts
	 */
	public function enqueue_scripts() {

		if ( ! is_user_logged_in() ) {

			return;

		}

		$rtl    = is_rtl() ? '-rtl' : '';
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wpaas-admin', Plugin::assets_url( "css/admin{$rtl}{$suffix}.css" ), [], Plugin::version() );

	}

	/**
	 * Enqueue admin styles
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wpaas-admin', Plugin::assets_url( "css/admin{$suffix}.css" ), [], Plugin::version() );

		if ( sprintf( 'toplevel_page_%s', $this->slug ) !== $hook ) {

			return;

		}

		if ( 'help' === $this->tab ) {

			wp_enqueue_script(
				'wpaas-iframeresizer',
				Plugin::assets_url( 'js/iframeResizer.min.js' ),
				[],
				'3.5.1',
				false
			);

			wp_enqueue_script(
				'wpaas-iframeresizer-ie8',
				Plugin::assets_url( 'js/iframeResizer.ie8.polyfils.min.js' ),
				[],
				'3.5.1',
				false
			);

			wp_script_add_data( 'wpaas-iframeresizer-ie8', 'conditional', 'lte IE 8' );

		}

		switch ( $this->tab ) {

			case 'hire':

				add_thickbox();

				break;

			case 'plugins':

				wp_enqueue_style( 'thickbox' );

				wp_enqueue_style( 'plugin-install' );

				wp_enqueue_script( 'plugin-install' );

				break;

		}

	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 */
	public function register_menu_page() {

		/**
		 * Filter the user cap required to access the admin page.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$cap = (string) apply_filters( 'wpaas_admin_page_cap', 'activate_plugins' );

		global $submenu;

		$page_hook = add_menu_page(
			__( 'GoDaddy', 'gd-system-plugin' ),
			__( 'GoDaddy', 'gd-system-plugin' ),
			$cap,
			$this->slug,
			[ $this, 'render_menu_page' ],
			'div',
			$this->position
		);

		// Bail early if we need to hide a page in an option
		add_action( 'load-' . $page_hook, function() use ( $cap ) {

			if ( ! filter_input( INPUT_GET, 'hide' ) || ! current_user_can( $cap ) ) {

				return;

			}

			$option = get_option( self::HIDE_OPTION_KEY, [] );

			if ( ! isset( $option[ $this->tab ] ) ) {

				$option[] = $this->tab;

			}

			update_option( self::HIDE_OPTION_KEY, $option );

			wp_redirect( add_query_arg( 'tab', 'help', remove_query_arg( [ 'hide' ] ) ) );

			exit;

		} );

		foreach ( $this->tabs as $slug => $label ) {

			$parent_slug = $this->slug;

			$permalink = add_query_arg(
				[
					'page' => $this->slug,
					'tab'  => $slug,
				],
				'admin.php'
			);

			$submenu[ $this->slug ][] = [ $label, $cap, $permalink ];

			$closure = function( $submenu_file, $parent_file ) use ( $parent_slug, $slug, $permalink, &$closure ) {

				if ( $parent_file === $parent_slug ) {

					if ( $slug === filter_input( INPUT_GET, 'tab' ) ) {

						$submenu_file = $permalink;

					}

					// No need to continue applying the filter once we found our parent
					remove_filter( 'submenu_file', $closure );
				}

				return $submenu_file;

			};

			add_filter( 'submenu_file', $closure, 10, 2 );

		}

	}

	/**
	 * Modify admin body classes
	 *
	 * @action admin_body_class
	 *
	 * @param  string $classes
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {

		$classes = array_map( 'trim', explode( ' ', $classes ) );

		if ( 'plugins' === $this->tab ) {

			$classes[] = 'plugin-install-php';

		}

		$classes[] = sprintf( '%s-tab-%s', esc_attr( $this->slug ), esc_attr( $this->tab ) );

		return implode( ' ', $classes );

	}

	/**
	 * Render menu page
	 */
	public function render_menu_page() {

		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wpaas-pages', Plugin::assets_url( "js/wpaas-pages{$suffix}.js" ), [ 'jquery' ], Plugin::version() );

		wp_localize_script(
			'wpaas-pages',
			'wpaas_pages',
			[
				'confirm' => __( 'Are you sure? This cannot be undone.', 'wpaas' ),
			]
		);

		?>
		<div class="wrap">

			<h1><?php echo esc_html( get_admin_page_title() ) ?></h1>

			<?php if ( ! empty( $this->tabs ) ) : ?>

				<h2 class="nav-tab-wrapper">

					<?php foreach ( $this->tabs as $name => $label ) : ?>

						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => $name ] ) ) ?>" class="nav-tab<?php if ( $this->tab === $name ) : ?> nav-tab-active<?php endif; ?>"><?php echo esc_html( $label ) ?></a>

					<?php endforeach; ?>

				</h2>

			<?php endif;

			if ( isset( $this->tabs[ $this->tab ] ) && method_exists( $this, "render_menu_page_{$this->tab}" ) ) {

				$method = "render_menu_page_{$this->tab}";

				if ( is_callable( [ $this, $method ] ) ) {

					$this->$method();

				}

			}

		?>
		</div>
		<?php

	}

	public function render_menu_page_help() {

		$language  = get_option( 'WPLANG', 'www' );
		$parts     = explode( '_', $language );
		$subdomain = ! empty( $parts[1] ) ? strtolower( $parts[1] ) : strtolower( $language );

		// Overrides
		switch ( $subdomain ) {

			case '' :

				$subdomain = 'www'; // Default

				break;

			case 'uk' :

				$subdomain = 'ua'; // Ukrainian (Українська)

				break;

			case 'el' :

				$subdomain = 'gr'; // Greek (Ελληνικά)

				break;

		}

		?>
		<iframe src="<?php echo esc_url( "https://{$subdomain}.godaddy.com/help/managed-wordpress-1000021" ) ?>" frameborder="0" scrolling="no"></iframe>

		<script type="text/javascript">
			iFrameResize( {
				bodyBackground: 'transparent',
				checkOrigin: false,
				heightCalculationMethod: 'taggedElement'
			} );
		</script>
		<?php

	}

	/**
	 * Hire tab content
	 *
	 * Note: The $version var value should be incremented
	 * each time new changes are introduced to this page
	 * for tracking purposes.
	 */
	public function render_menu_page_hire() {

		$user = wp_get_current_user();

		/**
		 * We need the string reprensation of boolean
		 * Do not change to boolean/int value
		 */
		$query_args = [
			'utm_source'    => 'mwp',
			'framed'        => 'true',
			'is_new'        => 'false',
			'website_url'   => home_url(),
			'has_domain'    => Plugin::is_temp_domain() ? 'false' : 'true',
			'has_hosting'   => 'true',
			'email'         => (string) $user->user_email,
			'business_name' => get_bloginfo( 'blogname' ),
			'first_name'    => (string) $user->user_firstname,
			'last_name'     => (string) $user->user_lastname,
			'TB_iframe'     => 'true',// The following 3 args must be last in array
			'width'         => '600',
			'height'        => '400',
		];

		/**
		 * Add site type from wpem
		 */
		$site_type = (string) get_option( 'wpem_site_type' );

		$site_type_mapping = [
			'standard' => 'basic',
			'blog'     => 'blog',
			'store'    => 'store',
		];

		if ( ! empty( $site_type ) ) {

			$query_args['website_description'] = $site_type_mapping[ $site_type ];

		}

		/**
		 * Add contact info we have
		 */
		$contact = (array) get_option( 'wpem_contact_info', [] );

		if ( isset( $contact['phone'] ) ) {

			$query_args['phone_number'] = $contact['phone'];

		}

		/**
		 * Build the final url
		 */
		$pro_connect_url = add_query_arg(
			$query_args,
			'https://pro-connect.godaddy.com/pws'
		);

		?>
		<div class="dashboard-widgets-wrap">

			<div id="dashboard-widgets" class="metabox-holder">

				<div id="normal-sortables" class="meta-box-sortables ui-sortable">

					<div id="dashboard_pro_connect" class="postbox">

						<h2 class="hndle ui-sortable-handle"><span><?php _e( 'Stuck in a rut? We can help.', 'gd-system-plugin' ) ?></span></h2>

						<div class="inside">

							<div class="featured-image">

								<img src="<?php echo Plugin::assets_url( 'images/godaddy-tab-hire.png' ) ?>">

							</div>

							<p><?php _e( "Having a pro build your business' website is the fast, cost-effective way to a great-looking, branded web presence.", 'gd-system-plugin' ) ?></p>

							<div class="clear"></div>

							<p class="submit">

								<a href="<?php echo esc_url( $pro_connect_url ) ?>" class="thickbox button button-primary"><?php _e( 'Learn More', 'gd-system-plugin' ) ?></a>

							</p>

						</div>

					</div>

				</div>

			</div>

			<div class="clear option-hide">

				<label>

					<input type="checkbox" class="wpaas_hidden_tabs" data-url="<?php echo esc_url( add_query_arg( 'hide', true ) ) ?>" autocomplete="off">

					<?php _e( "Hide this tab", 'wpaas' ) ?>

				</label>

			</div>

		</div>
		<?php

	}

	public function render_menu_page_plugins() {

		$plugins = (array) $this->get_plugins();

		?>
		<div id="welcome-panel" class="welcome-panel">

			<div class="welcome-panel-content">

				<h2><?php _e( 'Meet the plugins that meet our high standards.', 'gd-system-plugin' ) ?></h2>

				<p class="about-description"><?php _e( "We've partnered with the world's top WordPress plugin authors to provide a list of plugins that work well with GoDaddy WordPress hosting.", 'gd-system-plugin' ) ?></p>

			</div>

		</div>

		<div id="plugin-filter">

			<div class="wp-list-table widefat plugin-install">

				<h2 class="screen-reader-text"><?php _e( 'Plugins list' ) ?></h2>

				<div id="the-list">

					<?php if ( ! $plugins ) : ?>

						<div class="error">

							<p><?php _e( 'Whoops! There was a problem fetching the list of plugins, please try reloading this page.', 'gd-system-plugin' ) ?></p>

						</div>

					<?php endif; ?>

					<?php foreach ( $plugins as $plugin ) :

						if ( ! function_exists( 'install_plugin_install_status' ) ) {

							require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

						}

						$status         = install_plugin_install_status( $plugin );
						$install_status = ! empty( $status['status'] ) ? $status['status'] : 'install';
						$install_url    = ! empty( $status['url'] ) ? $status['url'] : null;
						$install_file   = ! empty( $status['file'] ) ? $status['file'] : null;

						$more_details_link = add_query_arg(
							[
								'tab'       => 'plugin-information',
								'plugin'    => urlencode( $plugin['slug'] ),
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							],
							self_admin_url( 'plugin-install.php' )
						);

						?>

						<div class="plugin-card plugin-card-<?php echo esc_attr( $plugin['slug'] ) ?>">

							<div class="plugin-card-top">

								<div class="name column-name">

									<h3>

										<?php if ( $plugin['plugins_api'] ) : ?>

											<a href="<?php echo esc_url( $more_details_link ) ?>" class="thickbox" aria-label="<?php esc_attr_e( sprintf( __( 'More information about %s' ), $plugin['name'] ) ) ?>" data-title="<?php echo esc_attr( $plugin['name'] ) ?>">

										<?php endif; ?>

												<?php echo esc_html( $plugin['name'] ) ?>

												<img src="<?php echo esc_url( $plugin['icon'] ) ?>" class="plugin-icon" alt="">

										<?php if ( $plugin['plugins_api'] ) : ?>

											</a>

										<?php endif; ?>

									</h3>

								</div>

								<div class="action-links">

									<ul class="plugin-action-buttons">

										<?php if ( $plugin['plugins_api'] && 'install' === $install_status && $install_url ) : ?>

											<li><a class="install-now button" href="<?php echo esc_url( $install_url ) ?>" data-slug="<?php echo esc_attr( $plugin['slug'] ) ?>" data-name="<?php echo esc_attr( $plugin['name'] ) ?>" aria-label="<?php esc_attr_e( sprintf( __( 'Install %s now' ), $plugin['name'] ) ) ?>"><?php _e( 'Install Now' ) ?></a></li>

										<?php elseif ( $plugin['plugins_api'] && 'update_available' === $install_status && $install_url ) : ?>

											<li><a class="update-now button" href="<?php echo esc_url( $install_url ) ?>" data-plugin="<?php echo esc_attr( $install_file ) ?>" data-slug="<?php echo esc_attr( $plugin['slug'] ) ?>" data-name="<?php echo esc_attr( $plugin['name'] ) ?>" aria-label="<?php esc_attr_e( sprintf( __( 'Update %s now' ), $plugin['name'] ) ) ?>"><?php _e( 'Update Now' ) ?></a></li>

										<?php elseif ( false !== strpos( $install_status, '_installed' ) ) : ?>

											<li><span class="button button-disabled" title="<?php esc_attr_e( 'This plugin is already installed and is up to date' ) ?>"><?php _ex( 'Installed', 'plugin' ) ?></span></li>

										<?php endif; ?>

										<?php if ( ! $plugin['plugins_api'] ) : ?>

											<li><a href="<?php echo esc_url( $plugin['homepage'] ) ?>" target="_blank"><span class="dashicons dashicons-external"></span> <?php _e( 'Learn More', 'gd-system-plugin' ) ?></a></li>

										<?php endif; ?>

									</ul>

								</div>

								<div class="desc column-description">

									<p><?php echo esc_html( $plugin['short_description'] ) ?></p>

									<p class="authors">

										<cite><?php printf( __( 'By %s' ), wp_kses_post( $plugin['author'] ) ) ?></cite>

									</p>

								</div>

							</div>

						</div>

					<?php endforeach; ?>

				</div>

			</div>

		</div>
		<?php

	}

	/**
	 * Get plugin data
	 *
	 * @return array
	 */
	private function get_plugins() {

		$transient = 'gd_ppp_data';

		if ( false === ( $plugins = get_transient( $transient ) ) ) {

			$plugins = $this->fetch_plugins();

			if ( ! $plugins ) {

				return [];

			}

			foreach ( $plugins as $slug => $data ) {

				if ( $data && empty( $data['plugins_api'] ) ) {

					$plugins[ $slug ]['plugins_api']  = false;
					$plugins[ $slug ]['slug']         = $slug;
					$plugins[ $slug ]['icon']         = sprintf( '//cdn.rawgit.com/godaddy/wp-plugin-partners/master/%s', $data['icon'] ); // CDN cache is indefinite, ignores query vars
					$plugins[ $slug ]['last_updated'] = ! empty( $data['last_updated'] ) ? strtotime( $data['last_updated'] ) : null;

					continue;

				}

				if ( ! function_exists( 'plugins_api' ) ) {

					require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

				}

				$_data = (array) plugins_api(
					'plugin_information',
					[
						'slug'   => $slug,
						'fields' => [
							'active_installs'   => true,
							'added'             => false,
							'author_profile'    => false,
							'compatibility'     => false,
							'donate_link'       => false,
							'downloaded'        => true,
							'download_link'     => false,
							'icons'             => true,
							'sections'          => false,
							'short_description' => true,
							'ratings'           => false,
							'tags'              => false,
						],
					]
				);

				$_data['plugins_api'] = true;
				$_data['icon']        = array_shift( $_data['icons'] );

				unset(
					$_data['author_profile'],
					$_data['contributors'],
					$_data['icons'],
					$_data['num_ratings'],
					$_data['rating']
				);

				$_data['last_updated'] = strtotime( $_data['last_updated'] );
				$_data                 = array_merge( $_data, $data ); // Allow overrides

				$plugins[ $slug ] = $_data;

			}

			if ( ! $plugins ) {

				return [];

			}

			shuffle( $plugins );

			set_transient( $transient, $plugins, 12 * HOUR_IN_SECONDS ); // Twice daily

		}

		return $plugins;

	}

	/**
	 * Fetch plugin data
	 *
	 * @return array|bool
	 */
	private function fetch_plugins() {

		$response = wp_remote_get( sprintf( 'https://raw.githubusercontent.com/godaddy/wp-plugin-partners/master/manifest.json?ver=%d', time() ) );

		if ( ! $response || is_wp_error( $response ) ) {

			return false;

		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return ( ! $data ) ? false : (array) $data;

	}

}
