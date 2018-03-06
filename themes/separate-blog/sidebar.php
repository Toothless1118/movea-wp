<?php
/**
* The sidebar containing the main widget area
*
* @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
*
* @package Separate_Blog
*/

if ( ! is_active_sidebar( 'categories' ) ) {
	return;
} ?>
<div class="widget search">
	<header>
		<h3 class="h6"><?php esc_html_e( 'Search', 'separate-blog' ); ?></h3>
	</header>
	<?php get_search_form(); ?>
</div>

<div class="widget latest-posts">
	<header>
		<h3 class="h6"><?php esc_html_e( 'Latest Posts', 'separate-blog' ); ?></h3>
	</header>
	<div class="blog-posts">
		<?php $letest_posts = wp_get_recent_posts( array( 'numberposts' => 3 ) );
		foreach( $letest_posts as $letest ) {
			echo '<a href="' . esc_url( get_permalink( $letest['ID'] ) ) . '">
			<div class="item d-flex align-items-center">
			<div class="image">' . get_the_post_thumbnail( $letest['ID'], 'separate-blog-letest-thumbnail' ) . '</div>
				<div class="title"><strong>' . esc_html( get_the_title( $letest['ID'] ) ) . '</strong>
				<div class="d-flex align-items-center">
				<div class="views"><i class="fa fa-clock-o"></i>' . esc_html( human_time_diff( get_the_time( 'U', $letest[ 'ID' ] ), current_time('timestamp') ) . ' ago' ) . '</div>
				<div class="comments"><i class="fa fa-commenting-o"></i>' . esc_html( get_comments_number( $letest['ID'] ) ) . '</div>
				</div></div>
			</div></a>';
		} ?>
	</div>
</div>

<?php 
	if ( is_active_sidebar( 'categories' ) ):

		dynamic_sidebar( 'categories' );

	endif;

	if ( is_active_sidebar( 'sidebar-tags' ) ):

		dynamic_sidebar( 'sidebar-tags' );

	endif;
?>
