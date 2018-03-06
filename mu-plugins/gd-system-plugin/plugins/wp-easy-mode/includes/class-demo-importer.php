<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

final class Demo_Importer {

	/**
	 * Array of search/replace strings
	 *
	 * @var array
	 */
	private $strings = [];

	/**
	 * Class constructor
	 *
	 * @param string $stylesheet
	 * @param string $header_image_url (optional)
	 * @param string $color_scheme
	 */
	public function __construct( $stylesheet, $header_image_url = null, $color_scheme = null ) {

		set_time_limit( 300 ); // Just in case

		@ini_set( 'memory_limit', '1G' ); // Ask for more memory

		if ( ! $this->package( $stylesheet ) ) {

			return;

		}

		$this->options( $stylesheet );

		$this->sql();

		if ( ! is_null( $header_image_url ) ) {

			$this->header_image( $header_image_url );

		}

		if ( ! is_null( $color_scheme ) ) {

			$this->color_scheme( $color_scheme, $stylesheet );

		}

		$this->mark_success();

	}

	/**
	 * Unzip package
	 *
	 * @param  string $stylesheet
	 *
	 * @return bool
	 */
	private function package( $stylesheet ) {

		$url = Admin::demo_site_url(
			[
				'theme'  => urlencode( $stylesheet ),
				'action' => 'export',
			]
		);

		$archive  = $this->download_url( $url );
		$unzipped = $this->unzip_file( $archive, WP_CONTENT_DIR );

		$this->delete_file( $archive );

		return $unzipped;

	}

	/**
	 * Install options
	 *
	 * @param  string $stylesheet
	 *
	 * @return bool
	 */
	private function options( $stylesheet ) {

		$response = wp_remote_get(
			Admin::demo_site_url(
				[
					'action' => 'get_options',
					'theme'  => urlencode( $stylesheet ),
				]
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {

			return false;

		}

		$json    = wp_remote_retrieve_body( $response );
		$options = json_decode( $json, true );

		if ( ! $options ) {

			return false;

		}

		if ( isset( $options['home'] ) ) {

			$this->strings[ $options['home'] ] = get_option( 'home' );

		}

		if ( isset( $options['admin_email'] ) ) {

			$this->strings[ $options['admin_email'] ] = get_option( 'admin_email' );

		}

		if ( $this->strings ) {

			$args = array_fill( 0, count( $this->strings ), '"\/' );

			// Chicken/egg situation means we must replace strings and decode the JSON again
			$json = str_replace(
				array_map( 'addcslashes', array_keys( $this->strings ), $args ),
				array_map( 'addcslashes', array_values( $this->strings ), $args ),
				$json
			);

			$options = json_decode( $json, true );

		}

		foreach ( $options as $option => $value ) {

			switch ( $option ) {

				case 'active_plugins':

					/**
					 * Since we unzip the files on previous step
					 * the cache will not reflect the filesystem plugins
					 */
					wp_cache_delete( 'plugins', 'plugins' );

					$plugins_actived = activate_plugins( $value );

					if ( ! is_wp_error( $plugins_actived ) ) {

						update_option( 'wpem_plugins', $value );

					}

					break;

				case 'stylesheet':

					switch_theme( $value );

					update_option( 'wpem_theme', $value );

					break;

				case 'template':

					if ( $value !== $options['stylesheet'] ) {

						update_option( 'wpem_parent_theme', $value );

					}

					break;

				default:

					update_option( $option, $value );

			}

		}

		return true;

	}

	/**
	 * Import SQL file
	 *
	 * @return bool
	 */
	private function sql() {

		$url = Admin::demo_site_url(
			[
				'action' => 'export_db',
			]
		);

		$archive  = $this->download_url( $url );
		$unzipped = $this->unzip_file( $archive, WP_CONTENT_DIR );

		$this->delete_file( $archive );

		if ( ! $unzipped ) {

			return false;

		}

		$filepath = glob( WP_CONTENT_DIR . '/wp*.sql' );

		if ( ! isset( $filepath[0] ) || ! $this->sql_search_replace( $filepath[0] ) ) {

			return false;

		}

		$parts   = parse_url( DB_HOST );
		$db_host = empty( $parts['host'] ) ? 'localhost' : $parts['host'];
		$db_port = empty( $parts['port'] ) ? 3306        : $parts['port'];

		exec(
			sprintf(
				'mysql --user=%s --password=%s --host=%s --port=%d %s < %s',
				escapeshellarg( DB_USER ),
				escapeshellarg( DB_PASSWORD ),
				escapeshellarg( $db_host ),
				absint( $db_port ),
				escapeshellarg( DB_NAME ),
				escapeshellarg( $filepath[0] )
			)
		);

		$this->delete_file( $filepath[0] );

		$this->clear_object_cache();

		$this->regenerate_thumbnails();

		$this->sql_cleanup();

		return true;

	}

	/**
	 * Direct copy of \WP_CLI\Utils\wp_clear_object_cache()
	 */
	private function clear_object_cache() {

		global $wpdb, $wp_object_cache;

		$wpdb->queries = [];

		if ( ! is_object( $wp_object_cache ) ) {

			return;

		}

		$wp_object_cache->group_ops      = [];
		$wp_object_cache->stats          = [];
		$wp_object_cache->memcache_debug = [];
		$wp_object_cache->cache          = [];

		if ( is_callable( $wp_object_cache, '__remoteset' ) ) {

			$wp_object_cache->__remoteset(); // important

		}

	}

	/**
	 * Replace strings in SQL before importing
	 *
	 * @param  string $filepath
	 *
	 * @return bool
	 */
	private function sql_search_replace( $filepath ) {

		if ( ! is_readable( $filepath ) ) {

			return false;

		}

		global $wpdb;

		$this->strings[ "`wp_" ] = "`{$wpdb->prefix}";

		$sql      = (string) file_get_contents( $filepath );
		$search   = array_keys( $this->strings );
		$replace  = array_values( $this->strings );
		$_search  = [];
		$_replace = [];

		// Detect serialized data to revert search/replace
		foreach ( $this->find_serialized_strings( $sql ) as $string ) {

			$_search[]  = str_replace( $search, $replace, $string );
			$_replace[] = $string;

		}

		$sql = str_replace(
			array_merge( $search, $_search ),
			array_merge( $replace, $_replace ),
			$sql
		);

		return ( false !== file_put_contents( $filepath, $sql ) );

	}

	/**
	 * Return an array of serialized strings found in a DB dump.
	 *
	 * @param  string $sql
	 *
	 * @return array
	 */
	private function find_serialized_strings( $sql ) {

		preg_match_all( '/,\'(a:\d+:{.*?})\'\)/', $sql, $matches );

		unset( $matches[0] ); // Save memory

		if ( isset( $matches[1][0] ) ) {

			return array_filter( $matches[1], 'is_serialized' );

		}

		return [];

	}

	/**
	 * Clean up database values
	 *
	 * @return bool
	 */
	private function sql_cleanup() {

		$this->bulk_update_posts(
			'all',
			[
				'post_author'       => 1,
				'post_date'         => current_time( 'mysql', 0 ),
				'post_date_gmt'     => current_time( 'mysql', 1 ),
				'post_modified'     => current_time( 'mysql', 0 ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			]
		);

		$this->update_serialized_data(
			[
				'options'  => [ 'option_id' => 'option_value' ],
				'postmeta' => [ 'meta_id'   => 'meta_value' ],
				'termmeta' => [ 'meta_id'   => 'meta_value' ],
			]
		);

	}

	/**
	 * Update all posts with the same values
	 *
	 * @param  string|array $posts
	 * @param  array        $args (optional)
	 *
	 * @return bool
	 */
	private function bulk_update_posts( $posts, $args = [] ) {

		global $wpdb;

		if ( 'all' === $posts ) {

			$posts = array_map( 'absint', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts};" ) );

		}

		if ( ! $posts || ! is_array( $posts ) || ! is_array( $args ) ) {

			return false;

		}

		if ( ! defined( 'WP_POST_REVISIONS' ) ) {

			define( 'WP_POST_REVISIONS', false );

		}

		foreach ( $posts as $post_id ) {

			$args['ID'] = $post_id;

			wp_update_post( $args );

		}

		return true;

	}

	/**
	 * Update serialized data in specified tables/columns.
	 *
	 * @param  array $args
	 *
	 * @return bool
	 */
	private function update_serialized_data( array $args ) {

		global $wpdb;

		foreach ( $args as $table => $columns ) {

			foreach ( $columns as $id_column => $column ) {

				$values = $wpdb->get_results( "SELECT {$id_column}, {$column} FROM {$wpdb->$table};", ARRAY_A );
				$values = wp_list_pluck( $values, $column, $id_column );
				$values = array_filter( $values, 'is_serialized' );

				foreach ( $values as $id => $value ) {

					$value = maybe_unserialize( $value );

					if ( ! is_array( $value ) ) {

						continue;

					}

					$new_value = $this->search_replace_recursive(
						array_keys( $this->strings ),
						array_values( $this->strings ),
						$value
					);

					$wpdb->update(
						$wpdb->$table,
						[ $column    => maybe_serialize( $new_value ) ],
						[ $id_column => $id ],
						[ '%s' ],
						[ '%d' ]
					);

				}

			}

		}

	}

	/**
	 * Search and replace strings in array or object values recursively.
	 *
	 * @param  mixed $search
	 * @param  mixed $replace
	 * @param  mixed $subject
	 *
	 * @return mixed
	 */
	private function search_replace_recursive( $search, $replace, $subject ) {

		if ( ! is_array( $subject ) && ! is_object( $subject ) ) {

			return $subject;

		}

		foreach ( $subject as $key => &$value ) {

			if ( is_array( $value ) || is_object( $value ) ) {

				$value = $this->search_replace_recursive( $search, $replace, $value );

				continue;

			}

			if ( is_string( $value ) ) {

				$value = str_replace( $search, $replace, $value );

			}

		}

		return $subject;

	}

	/**
	 * Regenerate all image thumbnails with WP-CLI
	 */
	private function regenerate_thumbnails() {

		if ( exec( '/usr/local/bin/wp --info' ) ) {

			exec( 'php -d memory_limit=1G /usr/local/bin/wp media regenerate --yes > /dev/null 2>/dev/null &' ); // Non-blocking

		}

	}

	/**
	 * Download a file by its URL
	 *
	 * @param  string $url
	 * @param  null   $type
	 *
	 * @return bool|string
	 */
	private function download_url( $url, $type = null ) {

		if ( ! function_exists( 'download_url' ) ) {

			require_once ABSPATH . 'wp-admin/includes/file.php';

		}

		$file = download_url( $url );

		if ( is_wp_error( $file ) ) {

			return false;

		}

		// Added functionality to deal with image without extension
		if ( 'image' === $type ) {

			$tmp_ext  = pathinfo( $file, PATHINFO_EXTENSION );

			// Get the real image extension
			$file_ext = image_type_to_extension( exif_imagetype( $file ) );

			// Replace extension of basename file
			$new_file = basename( $file, ".$tmp_ext" ) . $file_ext;

			// Replace old file with new file in complete path location
			$new_file = str_replace( basename( $file ), $new_file, $file );

			// Rename from .tpm to actual file format
			rename( $file, $new_file );

			$file = $new_file;

		}

		return $file;

	}

	/**
	 * Unzip an archive
	 *
	 * @param  string $archive
	 * @param  string $destination
	 *
	 * @return bool
	 */
	private function unzip_file( $archive, $destination ) {

		if ( ! function_exists( 'unzip_file' ) ) {

			require_once ABSPATH . 'wp-admin/includes/file.php';

		}

		WP_Filesystem();

		return ! is_wp_error( unzip_file( $archive, $destination ) );

	}

	/**
	 * Delete a file
	 *
	 * @param  string $filepath
	 *
	 * @return bool
	 */
	private function delete_file( $filepath ) {

		return is_readable( $filepath ) ? @unlink( $filepath ) : false;

	}

	/**
	 * Download, import and assign a header image from a URL
	 *
	 * @param  string $url
	 *
	 * @return bool
	 */
	private function header_image( $url ) {

		$attachment_id = $this->import_image_from_url( $url );

		if ( ! $attachment_id ) {

			return false;

		}

		list( $url, $width, $height ) = wp_get_attachment_image_src( $attachment_id, 'full' );

		$data = (object) [
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'thumbnail_url' => $url,
			'height'        => $height,
			'width'         => $width,
		];

		// Assign to theme mod
		$key = '_wp_attachment_custom_header_last_used_' . get_stylesheet();

		update_post_meta( $attachment_id, $key, time() );
		update_post_meta( $attachment_id, '_wp_attachment_is_custom_header', get_stylesheet() );

		set_theme_mod( 'header_image', $url );
		set_theme_mod( 'header_image_data', $data );

		return true;

	}

	/**
	 * Update site color scheme, theme mod
	 *
	 * @param  string $color_scheme
	 * @param  string $stylesheet
	 *
	 * @return bool
	 */
	private function color_scheme( $color_scheme, $stylesheet ) {

		if ( 'default' === $color_scheme ) {

			return;

		}

		$color_scheme_array = wpem_get_theme_color_schemes( $color_scheme, $stylesheet );

		// If something is wrong with the color array we bail and install the default one
		if ( ! isset( $color_scheme_array['colors'] ) ) {

			return;

		}

		set_theme_mod( 'color_scheme', $color_scheme );

		update_option( 'wpem_color_scheme', $color_scheme );

		foreach ( $color_scheme_array['colors'] as $theme_mod => $value ) {

			set_theme_mod( $theme_mod, $value );

		}

	}

	/**
	 * Import image from a URL
	 *
	 * Largely based on media_sideload_image() but
	 * returns and ID instead of a URL.
	 *
	 * @param  string $url
	 *
	 * @return int|bool
	 */
	private function import_image_from_url( $url ) {

		$file_array = [];

		// Download file to temp location
		$file_array['tmp_name'] = $this->download_url( $url, 'image' );

		if ( ! $file_array['tmp_name'] ) {

			return false;

		}

		preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file_array['tmp_name'], $matches );

		if ( ! $matches ) {

			unlink( $file_array['tmp_name'] );

			return false;

		}

		$file_array['name'] = basename( $matches[0] );

		if ( ! function_exists( 'media_handle_sideload' ) ) {

			require_once ABSPATH . 'wp-admin/includes/media.php';

		}

		// Do the validation and storage stuff
		$id = media_handle_sideload( $file_array, 0 );

		$this->delete_file( $file_array['tmp_name'] );

		return is_wp_error( $id ) ? false : $id;

	}

	/**
	 * Save that the import was successfull
	 *
	 * @return void
	 */
	private function mark_success() {

		$log = new Log;
		$log->add( 'import_successful', 1 );

		/**
		 * Fires when the import was successful
		 *
		 * @since 2.0.6
		 */
		do_action( 'wpem_import_successful' );

	}

}
