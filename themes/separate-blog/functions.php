<?php
/**
 * Separate Blog functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Separate_Blog
 */

if ( ! function_exists( 'separate_blog_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
	function separate_blog_setup() {
		/*
		 * Make theme available for translation.
		 * If you're building a theme based on Separate Blog, use a find and replace
		 * to change 'separate-blog' to the name of your theme in all the template files.
		 */
		load_theme_textdomain( 'separate-blog' );

		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		/*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/*
		 * Enable support for Post Thumbnails on posts and pages.
		 *
		 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		 */
		add_theme_support( 'post-thumbnails' );

		// This theme uses wp_nav_menu() in one location.
		register_nav_menus( array(
			'menu-1' => esc_html__( 'Primary', 'separate-blog' ),
			'footer' => esc_html__( 'Footer', 'separate-blog' )
		) );

		/*
		 * Switch default core markup for search form, comment form, and comments
		 * to output valid HTML5.
		 */
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		// Add theme support for selective refresh for widgets.
		add_theme_support( 'customize-selective-refresh-widgets' );

		/**
		 * Add support for core custom logo.
		 *
		 * @link https://codex.wordpress.org/Theme_Logo
		 */
		add_theme_support( 'custom-logo', array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		) );
	}
endif;
add_action( 'after_setup_theme', 'separate_blog_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function separate_blog_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'separate_blog_content_width', 640 );
}
add_action( 'after_setup_theme', 'separate_blog_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function separate_blog_widgets_init() {
	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar Categories', 'separate-blog' ),
		'id'            => 'categories',
		'description'   => esc_html__( 'Add widgets here.', 'separate-blog' ),
		'before_widget' => '<div class="widget categories">',
		'after_widget'  => '</div>',
		'before_title'  => '<header><h3 class="h6">',
		'after_title'   => '</h3></header>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar Tags', 'separate-blog' ),
		'id'            => 'sidebar-tags',
		'description'   => esc_html__( 'Add widgets here.', 'separate-blog' ),
		'before_widget' => '<div class="widget tags">',
		'after_widget'  => '</div>',
		'before_title'  => '<header><h3 class="h6">',
		'after_title'   => '</h3></header>',
	) );
}
add_action( 'widgets_init', 'separate_blog_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function separate_blog_scripts() {
	// css
	wp_enqueue_style( 'google-font', 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,700' );
	wp_enqueue_style( 'bootstrap-min', esc_url( get_template_directory_uri() . '/assets/bootstrap/css/bootstrap.min.css' ) );
	wp_enqueue_style( 'font-awesome-min', esc_url( get_template_directory_uri() . '/assets/font-awesome/font-awesome.min.css' ) );
	wp_enqueue_style( 'separate-blog-style', get_stylesheet_uri() );

	// js
	wp_enqueue_script( 'jquery-bootstrap-min', esc_url( get_template_directory_uri() . '/assets/bootstrap/js/bootstrap.min.js' ), array( 'jquery' ), '', true);
	wp_enqueue_script( 'separate-blog-separate-theme', esc_url( get_template_directory_uri() . '/assets/js/separate-blog-theme.js' ), '', '', true );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'separate_blog_scripts' );

/**
 * Theme banner
 */
function separate_blog_banner() {
	$banner_image = get_theme_mod( 'separate_blog_banner_image' ) ? get_theme_mod( 'separate_blog_banner_image' ) : esc_url( get_template_directory_uri() . '/assets/images/banner.jpeg' );
	$banner_title = get_theme_mod( 'separate_blog_banner_title' ) ? get_theme_mod( 'separate_blog_banner_title' ) : '';
	$banner_button = get_theme_mod( 'separate_blog_banner_button' ) ? get_theme_mod( 'separate_blog_banner_button' ) : 'learn more';
	$banner_button_url = get_theme_mod( 'separate_blog_banner_button_url' ) ? get_theme_mod( 'separate_blog_banner_button_url' ) : '#';
	?>
	<section style="background: url(<?php echo esc_url( $banner_image ); ?>); background-size: cover; background-position: center center" class="banner">
		<div class="container">
			<div class="row">
				<div class="col-lg-7">
					<?php if ( ! empty( $banner_title ) ) { ?>
						<h1><?php echo esc_html( $banner_title ); ?></h1>
					<?php } else {
						if ( is_user_logged_in() ) { ?>
							<h2>
								<a href="<?php echo esc_url( get_admin_url() . 'customize.php?url=' .  home_url( '/' ) ); ?>" class="btn btn-secondary">
									<?php esc_html_e( 'Edit Banner', 'separate-blog' ); ?>
								</a>
							</h2>
						<?php	}
					} ?>
					<?php if ( $banner_button ) : ?>
						<a href="<?php echo esc_url( $banner_button_url );?>" class="banner-link"><?php echo esc_html( $banner_button ); ?></a>
					<?php endif; ?>
				</div>
			</div><a href=".separate-posts" class="continue link-scroll"><i class="fa fa-long-arrow-down"></i><?php esc_html_e( 'Scroll Down', 'separate-blog' ); ?></a>
		</div>
	</section>
<?php
}

/**
 * Search form
 */
add_filter( 'get_search_form', 'separate_blog_search_form', 100 );
function separate_blog_search_form( $form ) {
    $form = '<form role="search" method="get" id="search-form" class="search-form" action="' . esc_url( home_url( '/' ) ) . '" >
    	<div class="form-group">
			<input placeholder="' . esc_attr__( 'What are you looking for?' , 'separate-blog' ) . '" type="text" value="' . esc_attr( get_search_query() ) . '" name="s" id="s" class="search-field" />
			<button class="submit" type="submit" id="submit"><i class="fa fa-search"></i></button>    
    	</div>	
    </form>';
    return $form;
}

/**
 * Replaces the excerpt "...." by a link
 */
function separate_blog_more( $more ) {
	global $post;
	if ( ! is_admin() ) {
		return '....';
	}
}
add_filter( 'excerpt_more', 'separate_blog_more' );

/**
 * Pagination
 */
function separate_blog_pagination() {
	the_posts_pagination( 
		array(
			'mid_size' 	=> 2,
			'prev_text' => '<i class="fa fa-angle-left"></i>',
			'next_text' => '<i class="fa fa-angle-right"></i>',
		) 
	);
}

/**
 * Next & previous post filter
 */
add_filter('next_post_link', 'separate_blog_next_post');
function separate_blog_next_post( $output ) {
    return str_replace( '<a href=', '<a class="next-post text-right d-flex align-items-center justify-content-end" href=', $output );
}

add_filter('previous_post_link', 'separate_blog_previous_post');
function separate_blog_previous_post( $output ) {
    return str_replace( '<a href=', '<a class="prev-post text-left d-flex align-items-center" href=', $output );
}

/**
 * Admin email
 */
function separate_blog_admin_email() {
	return '<a href="mailto:' . sanitize_email( get_option( 'admin_email' ) ) . '">' . sanitize_email( get_option( 'admin_email' ) ) . '</a>';
}

/**
 * Post category
 */
function separate_blog_post_categories( $post , $limit = false ){
	
	$post_categories = wp_get_post_categories( $post->ID );
	$cats = array();

	foreach( $post_categories as $key =>  $val ){

		if( $key == $limit && $limit != false ){
			break;
		}

		$cat = get_category( $val );
		$cats[] = '<a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
	}
	echo implode( ' , ', $cats );
}

/**
 * Add image size
 */
add_image_size( 'separate-blog-post-thumbnail', 516, 344, true );
add_image_size( 'separate-blog-recent-thumbnail', 38, 38, true );
add_image_size( 'separate-blog-letest-thumbnail', 60, 60, true );


/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Walker menu
 */
require get_template_directory() . '/inc/walker-menu.php';

/**
 * comment walker.
 */
require get_template_directory() . '/inc/wp-comment-walker.php';

