<?php
/*
Plugin Name: WP101 Video Tutorials
Description: WordPress video tutorials, delivered right in your dashboard.
Version: 0.3
Author: WP101Plugin.com
Author URI: http://wp101plugin.com/
*/

class WP101_Video_Tutorial {
	public static $instance;
	public static $api_base = 'https://gd.wp101.com/?wp101-api-server&';

	public function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'init' ) );

		// For Debug
		// delete_transient( 'wp101_topics' );
		// delete_transient( 'wp101_api_key_valid' );
	}

	public function init() {
		// Translations
		load_plugin_textdomain( 'wp101', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// Actions and filters
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_head', array( $this, 'wp101_admin_icon') );
	}

	public function admin_menu() {
		$hook = add_menu_page( _x( 'WP101', 'page title', 'wp101' ), _x( 'Video Tutorials', 'menu title', 'wp101' ), 'read', 'wp101', array( $this, 'render_listing_page' ) );
		add_action( "load-{$hook}", array( $this, 'load' ) );
	}

    public function wp101_admin_icon() {
	    echo '<style>#adminmenu #toplevel_page_wp101 div.wp-menu-image:before { content: "\f236" !important; }</style>';
    }

	private function validate_api_key_with_server( $key=NULL ) {
		if ( NULL === $key ) {
			$key = $this->get_key();
		}
		if ( ! $key ) {
			return 'invalid';
		}
		$query = wp_remote_get( self::$api_base . 'action=check_key&api_key=' . $key . '&url=' . urlencode( get_option('siteurl') ), array( 'timeout' => 45, 'sslverify' => false, 'user-agent' => 'WP101Plugin' ) );

		if ( is_wp_error( $query ) )
			return false; // Failed to query the server

		$result = json_decode( wp_remote_retrieve_body( $query ) );

		return $result->data->status;
	}

	private function get_key() {
	     if ( defined( 'GD_WP101_API_KEY' ) ) {
	           return GD_WP101_API_KEY;
	     }
	     return '';
	}

	public function load() {
		$this->enqueue();
		if ( $message = get_transient( 'wp101_message' ) ) {
			delete_transient( 'wp101_message' );
			add_action( 'admin_notices', array( $this, 'api_key_' . $message . '_message' ) );
		} elseif ( !isset( $_GET['configure'] ) ) {
			$result = $this->validate_api_key();
			if ( 'valid' !== $result && current_user_can( 'manage_options' ) ) {
				set_transient( 'wp101_message', $result, 300 );
				wp_redirect( admin_url( 'admin.php?page=wp101&configure=1' ) );
				exit();
			}
		}
	}

	private function enqueue() {
		wp_enqueue_style( 'wp101', plugins_url( "css/wp101.css", __FILE__ ), array(), '20140923k' );
	}

	private function validate_api_key() {
		if ( ! get_transient( 'wp101_api_key_valid' ) ) {
			// Check the API key against the server
			$response = $this->validate_api_key_with_server();
			if ( 'valid' === $response ) {
				set_transient( 'wp101_api_key_valid', 1, 30*24*3600 ); // Good for 30 days.
			}
			return $response;
		} else {
			return 'valid'; // Cached response
		}
	}

	private function get_document( $id ) {
		$topics = $this->get_help_topics();
		if ( isset( $topics[$id] ) )
			return $topics[$id];
		else
			return false;
	}

	private function get_help_topics() {
		if ( 'valid' === $this->validate_api_key() ) {
			if ( $topics = get_transient( 'wp101_topics' ) ) {
				return $topics;
			} elseif ( $this->get_key() ) {
				$result = wp_remote_get( self::$api_base . 'action=get_topics&api_key=' . $this->get_key() . '&url=' . urlencode( get_option('siteurl') ), array( 'timeout' => 45, 'sslverify' => false, 'user-agent' => 'WP101Plugin' ) );
				if ( ! is_wp_error( $result ) ) {
					$result = json_decode( $result['body'], true );
					if ( !$result['error'] && count( $result['data'] ) ) {
						set_transient( 'wp101_topics', $result['data'], 30 ); // Good for a day.
						return $result['data'];
					}
				}
			}
		}
	}

	private function get_help_topics_html( $edit_mode = false ) {
		$topics = $this->get_help_topics();
		if ( !$topics )
			return false;
		$return = '<ul class="wp101-topic-ul">';
		foreach ( $topics as $topic ) {
			$return .= '<li class="page-item-' . $topic['id'] . '"><span><a href="' . admin_url( 'admin.php?page=wp101&document=' . $topic['id'] ) . '">' . esc_html( $topic['title'] ) . '</a></span></li>';
		}
		$return .= '</ul>';
		return $return;
	}

	public function render_listing_page() {
		$document_id = isset( $_GET['document'] ) ? sanitize_title( $_GET['document'] ) : 1;
		if ( $document_id ) : ?>
			<style>
			div#wp101-topic-listing .page-item-<?php echo $document_id; ?> > span a {
				font-weight: bold;
			}
			</style>
		<?php endif; ?>
<div class="wrap" id="wp101-settings">
	<h2 class="wp101title"><?php _ex( 'WordPress Video Tutorials', 'h2 title', 'wp101' ); ?></h2>

	<?php if ( isset( $_GET['configure'] ) && $_GET['configure'] ) : ?>
		<?php $valid = $this->validate_api_key(); ?>
		<div class="updated">
		<?php if ( false === $valid ) : ?>
			<p><?php _e( 'We are having a little trouble loading the latest help videos. Please try again later.', 'wp101' ); ?></p>
		<?php elseif ( 'valid' !== $valid ) : ?>
			<p><?php printf( __( 'This version of WP101 is meant to be used with a Managed WordPress hosting partner. To otherwise view WP101 tutorial videos, please <a href="%s">subscribe at WP101.com</a>.', 'wp101' ), 'https://www.wp101.com/' ); ?></p>
		<?php else : ?>
			<p><?php _e( '<strong class="wp101-valid-key">WP101 is ready to go!</strong>', 'wp101' ); ?></p>
		<?php endif; ?>
		</div>
	<?php endif; ?>

<?php if ( 'valid' === $this->validate_api_key() ) : ?>

<?php $pages = $this->get_help_topics_html(); ?>
<?php if ( trim( $pages ) ) : ?>

<div id="wp101-topic">
<?php if ( $document_id ) : ?>
	<?php $document = $this->get_document( $document_id ); ?>
	<?php if ( $document ) : ?>
		<h2><?php echo esc_html( $document['title'] ); ?></h2>
		<?php echo $document['content']; ?>
	<?php else : ?>
	<p><?php _e( 'The requested tutorial could not be found', 'wp101' ); ?>
	<?php endif; ?>
<?php endif; ?>
</div>

<script>
jQuery(function($){
	var video = $('#wp101-topic iframe');
	var ratio = video.attr('height') / video.attr('width');
	var wp101Resize = function() {
		video.css('height', (video.width() * ratio) + 'px' );
	};
	var $win = $(window);
	$win.ready( wp101Resize );
	$win.resize( wp101Resize );
});
</script>

<div id="wp101-topic-listing">
<h3><?php _e( 'Video Tutorials', 'wp101' ); ?></h3>
<?php echo $pages; ?>
</div>

<?php endif; ?>

<?php endif; ?>

</div>
<?php
	}
}

new WP101_Video_Tutorial;

