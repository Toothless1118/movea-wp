<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Data {

	/**
	 * Data object
	 *
	 * @var object|bool
	 */
	protected $data = false;

	/**
	 * Prefix for filter tags
	 *
	 * @var string
	 */
	protected $filter_prefix = '';

	/**
	 * Magic data getter
	 *
	 * Filters are automatically applied to values being
	 * fetched directly. Grouped values are returned
	 * unfiltered.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( 'data' === $key ) {

			return $this->data; // Unfiltered

		}

		$value = isset( $this->data->{$key} ) ? $this->data->{$key} : false;

		return $this->apply_filters( $key, $value );

	}

	/**
	 * Magic data setter
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return mixed
	 */
	public function __set( $key, $value ) {

		if ( ! $this->data ) {

			$this->data = new \stdClass;

		}

		$this->data->{$key} = $value;

	}

	/**
	 * Adds prefix to a filter tag
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return mixed
	 */
	private function apply_filters( $key, $value ) {

		$prefix = ( $this->filter_prefix ) ? $this->filter_prefix : strtolower( str_replace( '\\', '_', __CLASS__ ) );
		$tag    = ( $prefix !== $key ) ? sprintf( '%s_%s', $prefix, $key ) : $key;

		/**
		 * Filter arbitrary data being fetched directly
		 *
		 * @since 2.0.0
		 *
		 * @var mixed
		 */
		return apply_filters( $tag, $value );

	}

}
