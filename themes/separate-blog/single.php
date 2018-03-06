<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package Separate_Blog
 */

get_header(); ?>
	<div class="container">
    <div class="row">
			<?php
				while ( have_posts() ) : the_post();

					get_template_part( 'template-parts/content', 'detail' );

				endwhile; // End of the loop.
			?>
			<aside class="col-lg-4">
				<?php	get_sidebar(); ?>
			</aside>
		</div>
	</div>
<?php get_footer();
