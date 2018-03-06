<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Dashboard_Widgets {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'wp_dashboard_setup', [ $this, 'init' ] );

	}

	/**
	 * Register custom widgets.
	 *
	 * @action wp_dashboard_setup
	 */
	public function init() {

		/**
		 * Filter the user cap required to view the dashboard widgets.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$cap = (string) apply_filters( 'wpaas_admin_dashboard_widgets_cap', 'activate_plugins' );

		/**
		 * Filter whether dashboard widgets are enabled.
		 *
		 * @since 2.0.0
		 *
		 * @var bool
		 */
		$enabled = (bool) apply_filters( 'wpaas_admin_dashboard_widgets_enabled', true );

		if ( ! current_user_can( $cap ) || ! $enabled ) {

			return;

		}

		if ( Plugin::is_gd() && Plugin::has_used_wpem() ) {

			wp_add_dashboard_widget(
				'wpaas_dashboard_godaddy_garage',
				_x( 'GoDaddy Garage', 'The name of our company blog found at https://www.godaddy.com/garage', 'gd-system-plugin' ),
				[ $this, 'widget_godaddy_garage' ]
			);

		}

	}

	/**
	 * Widget: GoDaddy Garage.
	 */
	public function widget_godaddy_garage() {

		$garage_rss = $this->get_feed_items( 'https://www.godaddy.com/garage/wordpress/feed/' );
		$item       = ! empty( $garage_rss[0] ) ? $garage_rss[0] : null;

		if ( is_a( $item, 'SimplePie_Item' ) ) : ?>

			<div class="rss-widget">

				<ul>
					<li>
						<a href="<?php echo esc_url( $item->get_link() ) ?>" target="_blank" class="rsswidget"><?php echo esc_html( $item->get_title() ) ?></a>
						<span class="rss-date"><?php echo esc_html( $item->get_date( get_option( 'date_format' ) ) ) ?></span>
						<div class="rssSummary"><?php echo wp_trim_words( $item->get_description(), 25, ' [&hellip;]' ) ?></div>
					</li>
				</ul>

			</div>

			<?php unset( $garage_rss[0] ) ?>

		<?php endif; ?>

		<?php if ( ! empty( $garage_rss ) ) : ?>

			<div class="rss-widget">

				<ul>
				<?php foreach ( $garage_rss as $item ) : ?>
					<?php if ( is_a( $item, 'SimplePie_Item' ) ) : ?>
						<li>
							<a href="<?php echo esc_url( $item->get_link() ) ?>" target="_blank" class="rsswidget"><?php echo esc_html( $item->get_title() ) ?></a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
				</ul>

			</div>

		<?php endif;

	}

	/**
	 * Return items from a given RSS feed.
	 *
	 * @param  string $url
	 * @param  int    $limit (optional)
	 * @param  int    $offset (optional)
	 *
	 * @return array
	 */
	private function get_feed_items( $url, $limit = 5, $offset = 0 ) {

		if ( ! function_exists( 'fetch_feed' ) ) {

			require_once ABSPATH . WPINC . '/feed.php';

		}

		$rss = fetch_feed( $url );

		if ( is_wp_error( $rss ) ) {

			return [];

		}

		$limit = $rss->get_item_quantity( absint( $limit ) );

		if ( 0 === $limit ) {

			return [];

		}

		$items = $rss->get_items( absint( $offset ), $limit );

		foreach ( $items as $item ) {

			$output[] = [
				'title'     => '',
				'permalink' => $item->get_link(),
				'excerpt'   => '',
			];

		}

		return $items;

	}

}
