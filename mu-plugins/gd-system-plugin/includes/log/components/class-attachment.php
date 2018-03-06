<?php

namespace WPaaS\Log\Components;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Attachment extends Component {

	/**
	 * Attachment > Create
	 *
	 * @action add_attachment
	 */
	public function callback_add_attachment() {

		$this->log_metric( 'publish' );

	}

	/**
	 * Attachment > Update
	 *
	 * @action edit_attachment
	 */
	public function callback_edit_attachment() {

		$this->log_metric( 'publish' );

	}

	/**
	 * Attachment > Delete
	 *
	 * @action delete_attachment
	 */
	public function callback_delete_attachment() {

		$this->log_metric( 'publish' );

	}

}
