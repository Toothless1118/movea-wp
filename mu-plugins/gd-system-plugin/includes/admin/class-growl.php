<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Growl {

	/**
	 * Array of messages to display.
	 *
	 * @var array
	 */
	private static $messages = [];

	/**
	 * Class constructor.
	 */
	public function __construct() {

		/**
		 * Filter the admin growl messages.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		self::$messages = (array) apply_filters( 'wpaas_admin_growl_messages', self::messages() );

		if ( self::$messages ) {

			add_action( 'init', [ $this, 'init' ] );

		}

		delete_option( 'gd_system_growl_messages' );

	}

	/**
	 * Initialize the script.
	 *
	 * @action init
	 */
	public function init() {

		/**
		 * Filter the user cap required to view admin growls.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$cap = (string) apply_filters( 'wpaas_admin_growl_cap', 'activate_plugins' );

		if ( ! current_user_can( $cap ) ) {

			return;

		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_bar_menu',        [ $this, 'display' ] );

	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @action wp_enqueue_scripts
	 */
	public function enqueue_scripts() {

		$rtl    = is_rtl() ? '-rtl' : '';
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wpaas-gritter', Plugin::assets_url( "js/jquery-gritter{$suffix}.js" ), [ 'jquery' ], '1.7.4' );

		wp_enqueue_style( 'wpaas-gritter', Plugin::assets_url( "css/jquery-gritter{$rtl}{$suffix}.css" ), [], Plugin::version() );

	}

	/**
	 * Display any system messages to the user.
	 *
	 * @action admin_bar_menu
	 */
	public function display() {

		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				<?php foreach ( self::$messages as $message ) : ?>
					$.gritter.add( {
						title: "<?php echo esc_js( __( 'Success', 'gd-system-plugin' ) ) ?>",
						text: "<?php echo esc_js( $message ) ?>",
						time: <?php echo absint( 5 * 1000 ) ?>
					} );
				<?php endforeach; ?>
			} );
		</script>
		<?php

	}

	/**
	 * Return an array of messages from the database.
	 *
	 * @return array
	 */
	public static function messages() {

		return (array) get_option( 'gd_system_growl_messages', [] );

	}

	/**
	 * Add a message to be displayed to the user.
	 *
	 * @param string $message
	 */
	public static function add( $message ) {

		self::$messages   = self::messages();
		self::$messages[] = $message;

		update_option( 'gd_system_growl_messages', self::$messages );

	}

}
