<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

abstract class Step {

	/**
	 * Array of args
	 *
	 * @var array
	 */
	protected $args = [];

	/**
	 * Hold the Log class
	 *
	 * @var object
	 */
	protected $log;

	/**
	 * Class constructor
	 *
	 * @param Log $log
	 */
	public function __construct( Log $log ) {

		$this->log = $log;

		add_action( 'init', [ $this, 'load' ], 11 );

	}

	/**
	 * Magic getter method
	 *
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get( $key ) {

		if ( property_exists( $this, $key ) ) {

			return $this->{$key};

		}

		if ( isset( $this->args[ $key ] ) ) {

			return $this->args[ $key ];

		}

		throw new \Exception( "Unrecognized property: '{$key}'" );

	}

	/**
	 * Magic setter method
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( $key, $value ) {

		$this->args[ $key ] = $value;

	}

	/**
	 * Step load
	 */
	public function load() {

		if ( wpem_get_current_step()->name !== $this->name ) {

			return;

		}

		$this->init();

	}

	/**
	 * Step init
	 */
	abstract protected function init();

	/**
	 * Step content
	 */
	abstract public function content();

	/**
	 * Step actions
	 */
	abstract public function actions();

	/**
	 * Step callback
	 */
	abstract public function callback();

}
