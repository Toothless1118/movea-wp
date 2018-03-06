<?php

namespace WPaaS\Admin;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Notice {

	/**
	 * Message to display in the notice.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Array of classes for the notice.
	 *
	 * @var array
	 */
	private $classes;

	/**
	 * Required user capability.
	 *
	 * @var string
	 */
	private $cap;

	/**
	 * Class constructor.
	 *
	 * @param string $message
	 * @param array  $classes (optional)
	 * @param string $cap     (optional)
	 */
	public function __construct( $message, array $classes = [ 'updated' ], $cap = 'activate_plugins' ) {

		/**
		 * Filter the admin notice message.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$this->message = (string) apply_filters( 'wpaas_admin_notice_message', $message );

		if ( empty( $message ) ) {

			return;

		}

		/**
		 * Filter the admin notice classes.
		 *
		 * @since 2.0.0
		 *
		 * @var array
		 */
		$this->classes = (array) apply_filters( 'wpaas_admin_notice_classes', $classes );

		/**
		 * Filter the user cap required to view the admin notice.
		 *
		 * @since 2.0.0
		 *
		 * @var string
		 */
		$this->cap = (string) apply_filters( 'wpaas_admin_notice_cap', $cap );

		add_action( 'admin_notices',       [ $this, 'display' ], -PHP_INT_MAX );
		add_action( 'wpaas_admin_notices', [ $this, 'display' ], -PHP_INT_MAX );

	}

	/**
	 * Display admin notice.
	 *
	 * @action admin_notices
	 */
	public function display() {

		if ( ! current_user_can( $this->cap ) || ! $this->message ) {

			return;

		}

		?>
		<div class="wpaas-notice notice <?php echo esc_attr( implode( ' ', $this->classes ) ) ?>">
			<p><?php echo wp_kses_post( $this->message ) ?></p>
		</div>
		<?php

	}

}
