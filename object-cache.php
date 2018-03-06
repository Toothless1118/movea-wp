<?php

/*
 * Plugin Name: APCu Object Cache
 * Description: APCu backend for the WP Object Cache.
 * Based on Plugin named APCu Object Cache Backend
 * Plugin URI: https://wordpress.org/plugins/apcu/
 * Author: Pierre Schmitz
 * Author URI: https://pierre-schmitz.com/
 * Plugin URI: https://wordpress.org/plugins/apcu/
 *
 *
 * @Authors James Dugger, Jonathan Bardo
 * @copyright 2017 GoDaddy Inc. 14455 N. Hayden Road Scottsdale, Arizona
 */

$oc_logged_in = false;

foreach ( $_COOKIE as $k => $v ) {

	if ( preg_match( '/^comment_author|wordpress_logged_in_[a-f0-9]+|woocommerce_items_in_cart|PHPSESSID_|edd_wp_session|edd_items_in_cartcc_cart_key|ccm_token/', $k ) ) {

		$oc_logged_in = true;

		break;

	}

}

$oc_blocked_page = ( defined( 'WP_ADMIN' ) || defined( 'DOING_AJAX' ) || defined( 'XMLRPC_REQUEST' ) || 'wp-login.php' === basename( $_SERVER['SCRIPT_FILENAME'] ) );

function wpaas_is_using_apcu() {

	return version_compare( PHP_VERSION, '5.6.0', '>=' ) && function_exists( 'apcu_fetch' );

}


if ( 'cli' !== php_sapi_name() && ! $oc_logged_in && ! $oc_blocked_page && wpaas_is_using_apcu() ) :

	/**
	 * Save the transients to the DB.  The explanation is a bit too long
	 * for code.  The tl;dr of it is that we don't have a single 'fast cache'
	 * source yet (like memcached) and so some long lived items like transients
	 * are still best cached in the db and then brought back into APC
	 *
	 * @param string  $transient
	 * @param mixed   $value
	 * @param int     $expire
	 * @param boolean $site = false
	 *
	 * @return bool
	 */
	function wpaas_save_transient( $transient, $value, $expire, $site = false ) {
		global $wp_object_cache, $wpdb;

		// The 'special' transient option names
		$transient_timeout = ( $site ? '_site' : '' ) . '_transient_timeout_' . $transient;
		$transient         = ( $site ? '_site' : '' ) . '_transient_' . $transient;

		// Cap expiration at 24 hours to avoid littering the DB
		if ( $expire == 0 ) {
			$expire = 24 * 60 * 60;
		}

		// Save to object cache
		$wp_object_cache->set( $transient, $value, 'options', $expire );
		$wp_object_cache->set( $transient_timeout, time() + $expire, 'options', $expire );

		// Update alloptions
		$alloptions                       = $wp_object_cache->get( 'alloptions', 'options' );
		$alloptions[ $transient ]         = $value;
		$alloptions[ $transient_timeout ] = time() + $expire;
		$wp_object_cache->set( 'alloptions', $alloptions, 'options' );

		// Use the normal update option logic
		if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
			$flag = $wpdb->suppress_errors;
			$wpdb->suppress_errors( true );
			if ( $site && is_multisite() ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO `{$wpdb->sitemeta}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, UNIX_TIMESTAMP( NOW() ) + %d, 'yes' ) ON DUPLICATE KEY UPDATE `option_name` = VALUES ( `option_name` ), `option_value` = VALUES ( `option_value` ), `autoload` = VALUES ( `autoload` );",
						$transient_timeout,
						$expire
					)
				);
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO `{$wpdb->sitemeta}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' ) ON DUPLICATE KEY UPDATE `option_name` = VALUES ( `option_name` ), `option_value` = VALUES ( `option_value` ), `autoload` = VALUES ( `autoload` );",
						$transient,
						maybe_serialize( $value )
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES ( %s, UNIX_TIMESTAMP( NOW() ) + %d, 'yes' ) ON DUPLICATE KEY UPDATE `option_name` = VALUES ( `option_name` ), `option_value` = VALUES ( `option_value` ), `autoload` = VALUES ( `autoload` );",
						$transient_timeout,
						$expire
					)
				);
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES ( %s, %s, 'no' ) ON DUPLICATE KEY UPDATE `option_name` = VALUES ( `option_name` ), `option_value` = VALUES ( `option_value` ), `autoload` = VALUES ( `autoload` );",
						$transient,
						maybe_serialize( $value )
					)
				);
			}
			$wpdb->suppress_errors( $flag );
		}

		return true;
	}

	function wpaas_prune_transients() {
		global $wpdb;

		if ( ! empty( $wpdb ) && $wpdb instanceof wpdb && function_exists( 'is_main_site' ) && function_exists( 'is_main_network' ) ) {

			$flag = $wpdb->suppress_errors;
			$wpdb->suppress_errors( true );

			// Lifted straight from schema.php

			// Deletes all expired transients.
			// The multi-table delete syntax is used to delete the transient record from table a,
			// and the corresponding transient_timeout record from table b.
			$time = time();
			$wpdb->query( "DELETE a, b FROM $wpdb->options a, $wpdb->options b WHERE
		a.option_name LIKE '\_transient\_%' AND
		a.option_name NOT LIKE '\_transient\_timeout\_%' AND
		b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
		AND b.option_value < $time" );

			if ( is_main_site() && is_main_network() ) {
				$wpdb->query( "DELETE a, b FROM $wpdb->options a, $wpdb->options b WHERE
		a.option_name LIKE '\_site\_transient\_%' AND
		a.option_name NOT LIKE '\_site\_transient\_timeout\_%' AND
		b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
		AND b.option_value < $time" );
			}


			$wpdb->suppress_errors( $flag );
		}
	}

	/**
	 * If another cache was flushed or updated, sync across all servers / processes using
	 * the database as the authority.  This uses the database as the authority for timestamps
	 * as well to avoid drift between servers.
	 * @return void
	 */
	function wpaas_init_sync_cache() {

		global $wpdb;

		if ( empty( $wpdb ) || ! ( $wpdb instanceof wpdb ) ) {

			return;

		}

		$flag = $wpdb->suppress_errors;
		$wpdb->suppress_errors( true );
		$result = $wpdb->get_results(
			"SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name = 'gd_system_last_cache_flush' UNION SELECT 'current_time', UNIX_TIMESTAMP( NOW() ) AS option_value;",
			ARRAY_A
		);
		$wpdb->suppress_errors( $flag );

		if ( empty( $result ) ) {

			return;

		}

		$master_flush = false;

		foreach ( $result as $row ) {

			switch ( $row['option_name'] ) {

				case 'current_time' :
					$current_time = $row['option_value'];
					break;

				case 'gd_system_last_cache_flush' :
					$master_flush = $row['option_value'];
					break;

			}

		}

		$local_flush = wp_cache_get( 'gd_system_last_cache_flush' );

		if ( false === $local_flush || $local_flush < $master_flush ) {

			wp_cache_flush( true );

			wp_cache_set( 'gd_system_last_cache_flush', $current_time );

		}

	}

	/**
	 * Start default implementation of object cache
	 */

	if ( ! defined( 'WP_APC_KEY_SALT' ) ) {

		define( 'WP_APC_KEY_SALT', '' );

	}

	function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
		global $wp_object_cache;

		if ( 'transient' == $group ) {
			wpaas_save_transient( $key, $data, $expire );

			return $wp_object_cache->add( "_transient_$key", $data, 'options', $expire );
		} elseif ( 'site-transient' == $group ) {
			wpaas_save_transient( $key, $data, $expire, true );

			return $wp_object_cache->add( "_site_transient_$key", $data, 'site-options', $expire );
		} else {
			return $wp_object_cache->add( $key, $data, $group, $expire );
		}
	}

	function wp_cache_incr( $key, $n = 1, $group = '' ) {
		global $wp_object_cache;

		return $wp_object_cache->incr2( $key, $n, $group );
	}

	function wp_cache_decr( $key, $n = 1, $group = '' ) {
		global $wp_object_cache;

		return $wp_object_cache->decr( $key, $n, $group );
	}

	function wp_cache_close() {
		return true;
	}

	function wp_cache_delete( $key, $group = '' ) {
		global $wp_object_cache, $wpdb;

		if ( 'transient' == $group ) {
			if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
				$flag = $wpdb->suppress_errors;
				$wpdb->suppress_errors( true );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM `{$wpdb->prefix}options` WHERE option_name IN ( %s, %s );",
						"_transient_{$key}",
						"_transient_timeout_{$key}"
					)
				);
				$wpdb->suppress_errors( $flag );
			}

			$wp_object_cache->delete( "_transient_timeout_$key", 'options' );

			// Update alloptions
			$alloptions = $wp_object_cache->get( 'alloptions', 'options' );
			unset( $alloptions["_transient_$key"] );
			unset( $alloptions["_transient_timeout_$key"] );
			$wp_object_cache->set( 'alloptions', $alloptions, 'options' );

			return $wp_object_cache->delete( "_transient_$key", 'options' );
		} elseif ( 'site-transient' == $group ) {
			if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
				$table = $wpdb->options;
				if ( is_multisite() ) {
					$table = $wpdb->sitemeta;
				}
				$flag = $wpdb->suppress_errors;
				$wpdb->suppress_errors( true );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM `{$table}` WHERE option_name IN ( %s, %s );",
						"_transient_{$key}",
						"_transient_timeout_{$key}"
					)
				);
				$wpdb->suppress_errors( $flag );
			}
			$wp_object_cache->delete( "_transient_timeout_$key", 'site-options' );

			// Update alloptions
			$alloptions = $wp_object_cache->get( 'alloptions', 'options' );
			unset( $alloptions["_site_transient_$key"] );
			unset( $alloptions["_site_transient_timeout_$key"] );
			$wp_object_cache->set( 'alloptions', $alloptions, 'options' );

			return $wp_object_cache->delete( "_site_transient_$key", 'site-options' );
		}

		return $wp_object_cache->delete( $key, $group );
	}

	function wp_cache_flush( $local_flush = false ) {
		global $wp_object_cache, $wpdb;

		if ( ! $local_flush ) {
			if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
				$flag = $wpdb->suppress_errors;
				$wpdb->suppress_errors( true );
				$wpdb->query( "INSERT INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES ( 'gd_system_last_cache_flush', UNIX_TIMESTAMP( NOW() ), 'no' ) ON DUPLICATE KEY UPDATE `option_name` = VALUES ( `option_name` ), `option_value` = VALUES ( `option_value` ), `autoload` = VALUES ( `autoload` );" );
				$wpdb->suppress_errors( $flag );
			}
		}

		return $wp_object_cache->flush();
	}

	function wp_cache_get( $key, $group = '', $force = false ) {
		global $wp_object_cache, $wpdb;

		if ( 'transient' == $group ) {
			$alloptions = $wp_object_cache->get( 'alloptions', 'options' );
			if ( isset( $alloptions["_transient_$key"] ) && isset( $alloptions["_transient_timeout_$key"] ) && $alloptions["_transient_timeout_$key"] > time() ) {
				return maybe_unserialize( $alloptions["_transient_$key"] );
			}
			$transient = $wp_object_cache->get( "_transient_$key", 'options', $force );
			$timeout   = $wp_object_cache->get( "_transient_timeout_$key", 'options', $force );
			if ( false !== $transient && ! empty( $timeout ) && $timeout > time() ) {
				return maybe_unserialize( $transient );
			}
			if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
				$flag = $wpdb->suppress_errors;
				$wpdb->suppress_errors( true );
				$result = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name IN ( %s, %s ) UNION SELECT 'current_time', UNIX_TIMESTAMP( NOW() ) AS option_value;",
						"_transient_{$key}",
						"_transient_timeout_{$key}"
					),
					ARRAY_A
				);
				$wpdb->suppress_errors( $flag );
				if ( ! empty( $result ) ) {
					$transient    = false;
					$timeout      = false;
					$current_time = time();
					foreach ( $result as $row ) {
						switch ( $row['option_name'] ) {
							case "_transient_$key" :
								$transient = $row['option_value'];
								break;
							case "_transient_timeout_$key" :
								$timeout = $row['option_value'];
								break;
							case 'current_time' :
								$current_time = $row['option_value'];
								break;
						}
					}
					if ( false !== $transient && ! empty( $timeout ) && $timeout > $current_time ) {
						return maybe_unserialize( $transient );
					}
				}
			}

			return false;
		} elseif ( 'site-transient' == $group ) {
			$transient = $wp_object_cache->get( "_site_transient_$key", 'options', $force );
			$timeout   = $wp_object_cache->get( "_site_transient_timeout_$key", 'options', $force );
			if ( false !== $transient && ! empty( $timeout ) && $timeout > time() ) {
				return maybe_unserialize( $transient );
			}
			if ( ! empty( $wpdb ) && $wpdb instanceof wpdb ) {
				$table = $wpdb->options;
				if ( is_multisite() ) {
					$table = $wpdb->sitemeta;
				}
				$flag = $wpdb->suppress_errors;
				$wpdb->suppress_errors( true );
				$result = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name, option_value FROM `{$table}` WHERE option_name IN ( %s, %s ) UNION SELECT 'current_time', UNIX_TIMESTAMP( NOW() ) AS option_value;",
						"_site_transient_{$key}",
						"_site_transient_timeout_{$key}"
					),
					ARRAY_A
				);
				$wpdb->suppress_errors( $flag );
				if ( ! empty( $result ) ) {
					$transient    = false;
					$timeout      = false;
					$current_time = time();
					foreach ( $result as $row ) {
						switch ( $row['option_name'] ) {
							case "_site_transient_$key" :
								$transient = $row['option_value'];
								break;
							case "_site_transient_timeout_$key" :
								$timeout = $row['option_value'];
								break;
							case 'current_time' :
								$current_time = $row['option_value'];
								break;
						}
					}
					if ( false !== $transient && ! empty( $timeout ) && $timeout > $current_time ) {
						return maybe_unserialize( $transient );
					}
				}
			}

			return false;
		} else {
			return $wp_object_cache->get( $key, $group, $force );
		}
	}

	function wp_cache_init() {
		global $wp_object_cache;

		if ( mt_rand( 1, 100 ) == 42 ) {

			wpaas_prune_transients();

		}

		add_action( 'muplugins_loaded', 'wpaas_init_sync_cache' );

		$wp_object_cache = new APCu_Object_Cache();

	}

	function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
		global $wp_object_cache;

		return $wp_object_cache->replace( $key, $data, $group, $expire );
	}

	function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
		global $wp_object_cache;

		if ( defined( 'WP_INSTALLING' ) == false ) {
			if ( 'transient' == $group ) {
				return wpaas_save_transient( $key, $data, $expire );
			} elseif ( 'site-transient' == $group ) {
				return wpaas_save_transient( $key, $data, $expire, true );
			} else {
				return $wp_object_cache->set( $key, $data, $group, $expire );
			}
		} else {
			return $wp_object_cache->delete( $key, $group );
		}
	}

	function wp_cache_switch_to_blog( $blog_id ) {
		global $wp_object_cache;

		return $wp_object_cache->switch_to_blog( $blog_id );
	}

	function wp_cache_add_global_groups( $groups ) {
		global $wp_object_cache;

		$wp_object_cache->add_global_groups( $groups );
	}

	function wp_cache_add_non_persistent_groups( $groups ) {
		global $wp_object_cache;

		$wp_object_cache->add_non_persistent_groups( $groups );
	}

	class GD_APCu_Object_Cache {

		private $prefix = '';
		private $local_cache = array();
		private $global_groups = array();
		private $non_persistent_groups = array();
		private $multisite = false;
		private $blog_prefix = '';

		public function __construct() {
			global $table_prefix;

			$this->multisite   = is_multisite();
			$this->blog_prefix = $this->multisite ? get_current_blog_id() . ':' : '';
			$this->prefix      = DB_HOST . '.' . DB_NAME . '.' . $table_prefix;
		}

		private function get_group( $group ) {
			return empty( $group ) ? 'default' : $group;
		}

		private function get_key( $group, $key ) {
			if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
				return $this->prefix . '.' . $group . '.' . $this->blog_prefix . ':' . $key;
			} else {
				return $this->prefix . '.' . $group . '.' . $key;
			}
		}

		public function add( $key, $data, $group = 'default', $expire = 0 ) {
			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
				return false;
			}
			if ( isset( $this->local_cache[ $group ][ $key ] ) ) {
				return false;
			}
			// FIXME: Somehow apcu_add does not return false if key already exists
			if ( ! isset( $this->non_persistent_groups[ $group ] ) && apcu_exists( $key ) ) {
				return false;
			}

			if ( is_object( $data ) ) {
				$this->local_cache[ $group ][ $key ] = clone $data;
			} else {
				$this->local_cache[ $group ][ $key ] = $data;
			}

			if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
				return apcu_add( $key, $data, (int) $expire );
			}

			return true;
		}

		public function add_global_groups( $groups ) {
			if ( is_array( $groups ) ) {
				foreach ( $groups as $group ) {
					$this->global_groups[ $group ] = true;
				}
			} else {
				$this->global_groups[ $groups ] = true;
			}
		}

		public function add_non_persistent_groups( $groups ) {
			if ( is_array( $groups ) ) {
				foreach ( $groups as $group ) {
					$this->non_persistent_groups[ $group ] = true;
				}
			} else {
				$this->non_persistent_groups[ $groups ] = true;
			}
		}

		public function decr( $key, $offset = 1, $group = 'default' ) {
			if ( $offset < 0 ) {
				return $this->incr( $key, abs( $offset ), $group );
			}

			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( isset( $this->local_cache[ $group ][ $key ] ) && $this->local_cache[ $group ][ $key ] - $offset >= 0 ) {
				$this->local_cache[ $group ][ $key ] -= $offset;
			} else {
				$this->local_cache[ $group ][ $key ] = 0;
			}

			if ( isset( $this->non_persistent_groups[ $group ] ) ) {
				return $this->local_cache[ $group ][ $key ];
			} else {
				$value = apcu_dec( $key, $offset );
				if ( $value < 0 ) {
					apcu_store( $key, 0 );

					return 0;
				}

				return $value;
			}
		}

		public function delete( $key, $group = 'default', $force = false ) {
			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			unset( $this->local_cache[ $group ][ $key ] );
			if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
				return apcu_delete( $key );
			}

			return true;
		}

		public function flush() {
			$this->local_cache = array();
			// TODO: only clear our own entries
			apcu_clear_cache();

			return true;
		}

		public function get( $key, $group = 'default', $force = false, &$found = null ) {
			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( ! $force && isset( $this->local_cache[ $group ][ $key ] ) ) {
				$found = true;
				if ( is_object( $this->local_cache[ $group ][ $key ] ) ) {
					return clone $this->local_cache[ $group ][ $key ];
				} else {
					return $this->local_cache[ $group ][ $key ];
				}
			} elseif ( isset( $this->non_persistent_groups[ $group ] ) ) {
				$found = false;

				return false;
			} else {
				$value = @apcu_fetch( $key, $found );
				if ( $found ) {
					if ( $force ) {
						$this->local_cache[ $group ][ $key ] = $value;
					}

					return $value;
				} else {
					return false;
				}
			}
		}

		public function incr( $key, $offset = 1, $group = 'default' ) {
			if ( $offset < 0 ) {
				return $this->decr( $key, abs( $offset ), $group );
			}

			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( isset( $this->local_cache[ $group ][ $key ] ) && $this->local_cache[ $group ][ $key ] + $offset >= 0 ) {
				$this->local_cache[ $group ][ $key ] += $offset;
			} else {
				$this->local_cache[ $group ][ $key ] = 0;
			}

			if ( isset( $this->non_persistent_groups[ $group ] ) ) {
				return $this->local_cache[ $group ][ $key ];
			} else if ( function_exists( 'apcu_inc' ) ) {
				$value = apcu_inc( $key, $offset );
				if ( $value < 0 ) {
					apcu_store( $key, 0 );

					return 0;
				}

				return $value;
			}

			return false;
		}

		public function replace( $key, $data, $group = 'default', $expire = 0 ) {
			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( isset( $this->non_persistent_groups[ $group ] ) ) {
				if ( ! isset( $this->local_cache[ $group ][ $key ] ) ) {
					return false;
				}
			} else {
				if ( ! isset( $this->local_cache[ $group ][ $key ] ) && ! apcu_exists( $key ) ) {
					return false;
				}
				apcu_store( $key, $data, (int) $expire );
			}

			if ( is_object( $data ) ) {
				$this->local_cache[ $group ][ $key ] = clone $data;
			} else {
				$this->local_cache[ $group ][ $key ] = $data;
			}

			return true;
		}

		public function reset() {
			// This function is deprecated as of WordPress 3.5
			// Be safe and flush the cache if this function is still used
			$this->flush();
		}

		public function set( $key, $data, $group = 'default', $expire = 0 ) {
			$group = $this->get_group( $group );
			$key   = $this->get_key( $group, $key );

			if ( is_object( $data ) ) {
				$this->local_cache[ $group ][ $key ] = clone $data;
			} else {
				$this->local_cache[ $group ][ $key ] = $data;
			}

			if ( ! isset( $this->non_persistent_groups[ $group ] ) ) {
				return apcu_store( $key, $data, (int) $expire );
			}

			return true;
		}

		public function stats() {
			// Only implemented because the default cache class provides this.
			// This method is never called.
			echo '';
		}

		public function switch_to_blog( $blog_id ) {
			$this->blog_prefix = $this->multisite ? $blog_id . ':' : '';
		}

	}

	if ( function_exists( 'apcu_inc' ) ) {
		class APCu_Object_Cache extends GD_APCu_Object_Cache {
			function incr( $key, $offset = 1, $group = 'default' ) {
				return parent::incr2( $key, $offset, $group );
			}
		}
	} else {
		class APCu_Object_Cache extends GD_APCu_Object_Cache {
			// Blank
		}
	}

endif;
