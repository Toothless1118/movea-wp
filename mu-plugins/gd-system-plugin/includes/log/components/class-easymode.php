<?php

namespace WPaaS\Log\Components;

use WPaaS\Log\Timer;
use WPEM\Log as WPEM_Log;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class EasyMode extends Component {

	/**
	 * Make sure callback is only added if wizard is not done
	 */
	protected function do_callbacks_on_hooks() {

		if ( ! get_option( 'wpem_done' ) ) {

			parent::do_callbacks_on_hooks();

		}

	}

	/**
	 * Fires when easy-mode is done and send the data to asap
	 *
	 * @action wpem_done
	 */
	public function callback_wpem_done() {

		Timer::stop();

		if ( $wpem_log = get_option( WPEM_Log::OPTION_KEY ) ) {

			$this->log( 'log', 'Easy Mode Data', json_decode( $wpem_log, true ) ); // i18n not required

		}

	}

}
