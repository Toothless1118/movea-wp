<?php

namespace WPaaS\Admin;

use \WPaaS\Plugin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class File_Editor {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'init',                       [ $this, 'init' ], -PHP_INT_MAX );
		add_action( 'admin_init',                 [ $this, 'file_edit' ], -PHP_INT_MAX );
		add_action( 'admin_print_footer_scripts', [ $this, 'display_warning' ] );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ], PHP_INT_MAX );

	}

	/**
	 * Initialize script.
	 *
	 * @action init
	 */
	public function init() {

		/**
		 * Filter the user cap required to enable the file editor.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$cap = (string) apply_filters( 'wpaas_admin_file_editor_cap', 'edit_themes' );

		$action = filter_input( INPUT_GET, 'wpaas_action' );
		$nonce  = filter_input( INPUT_GET, 'wpaas_nonce' );

		if ( ! current_user_can( $cap ) || 'enable_file_editor' !== $action || false === wp_verify_nonce( $nonce, 'wpaas_enable_file_editor' ) ) {

			return;

		}

		$this->enable();

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
	 * Enable the file editor.
	 */
	private function enable() {

		update_site_option( 'wpaas_file_editor_enabled', 1 );

		Growl::add( __( 'File editing enabled', 'gd-system-plugin' ) );

	}

	/**
	 * Check if the current page is the file editor.
	 *
	 * We will use regex matching on the current request URI
	 * rather than get_current_screen() so we can call this
	 * method on earlier hooks.
	 *
	 * @return bool
	 */
	private function is_file_editor() {

		return preg_match( '/(theme|plugin)-editor\.php/i', $_SERVER['REQUEST_URI'] );

	}

	/**
	 * Check if the current request is trying to edit a file.
	 *
	 * @return bool
	 */
	private function is_file_editor_request() {

		return ( $this->is_file_editor() && isset( $_POST['action'] ) && 'update' === $_POST['action'] );

	}

	/**
	 * Catch file editor update requests.
	 *
	 * @action admin_init
	 */
	public function file_edit() {

		if ( ! $this->is_file_editor_request() ) {

			return;

		}

		if ( Plugin::is_file_editor_enabled() ) {

			/**
			 * Fires when a file has been saved using the file editor.
			 *
			 * @see   WPaaS\Cache
			 * @since 2.0.1
			 */
			do_action( 'wpaas_file_editor_save' );

			return;

		}

		wp_die( __( 'File editing is not enabled on this site.', 'gd-system-plugin' ) );

	}

	/**
	 * Display a warning message when the file editor is disabled.
	 *
	 * @action admin_print_footer_scripts
	 */
	public function display_warning() {

		if ( ! $this->is_file_editor() || Plugin::is_file_editor_enabled() ) {

			return;

		}

		?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( 'div.wrap' ).html( $( '#wpaas-file-editor-disabled-message' ).html() );
				$( 'body' ).removeClass( 'wpaas-hide-file-editor' );
				$button = $( '#wpaas-file-editor-enable' );
				$( '#wpaas-file-editor-verify' ).change( function() {
					$button.toggleClass( 'button-primary-disabled' );
				} );
				$button.click( function( e ) {
					if ( $( this ).hasClass( 'button-primary-disabled' ) ) {
						e.preventDefault;
						return false;
					}
				} );
			} );
		</script>
		<script type="text/template" id="wpaas-file-editor-disabled-message">
			<h1><?php _e( 'File Editor', 'gd-system-plugin' ) ?></h1>
			<p><?php _e( "For your security, the WordPress file editor is disabled by default.", 'gd-system-plugin' ) ?> <a href="https://www.godaddy.com/help/managed-wordpress-file-editing-limitations-8943"><?php _e( 'Learn More', 'gd-system-plugin' ) ?></a></p>
			<p><?php _e( 'If you enable file editing all plugin and theme files on the server will become editable.', 'gd-system-plugin' ) ?></p>
			<p>
				<label for="wpaas-file-editor-verify">
					<input type="checkbox" id="wpaas-file-editor-verify" value="" />
					<?php _e( 'I understand the risks involved with enabling this feature', 'gd-system-plugin' ) ?>
				</label>
			</p>
			<p><a href="<?php echo esc_url( self::url() ) ?>" id="wpaas-file-editor-enable" class="button button-primary button-primary-disabled"><?php _e( 'Enable File Editing', 'gd-system-plugin' ) ?></a></p>
		</script>
		<?php

	}

	/**
	 * Custom admin body class to hide the file editor.
	 *
	 * @param  string $classes
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {

		if ( $this->is_file_editor() && ! Plugin::is_file_editor_enabled() ) {

			$classes .= ' wpaas-hide-file-editor ';

		}

		return $classes;

	}

	/**
	 * Return a nonced URL to enable the file editor.
	 *
	 * @return string
	 */
	public static function url() {

		return esc_url(
			add_query_arg(
				[
					'wpaas_action' => 'enable_file_editor',
					'wpaas_nonce'  => wp_create_nonce( 'wpaas_enable_file_editor' ),
				]
			)
		);

	}

}
