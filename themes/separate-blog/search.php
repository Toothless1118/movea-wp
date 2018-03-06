<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package Separate_Blog
 */

get_header(); ?>
	<?php separate_blog_banner(); ?>
  <section class="separate-posts no-padding-top">
    <div class="container">
			<?php
			if ( have_posts() ) : $GLOBALS['count'] = 1; ?>
				<section class="intro">
					<div class="container">
						<div class="row">
							<div class="col-lg-8">
								<h2 class="h3">
								<?php
								/* translators: %s: search query. */
								printf( esc_html__( 'Search Results for: %s', 'separate-blog' ), '<span>' . get_search_query() . '</span>' );
								?>
								</h2>
							</div>
						</div>
					</div>
				</section>
				<?php
				/* Start the Loop */
				while ( have_posts() ): the_post();

					/**
					 * Run the loop for the search to output the results.
					 * If you want to overload this in a child theme then include a file
					 * called content-search.php and that will be used instead.
					 */
					get_template_part( 'template-parts/content', 'search' );
				$GLOBALS['count'] ++;
				endwhile;

				separate_blog_pagination();

			else:

				get_template_part( 'template-parts/content', 'none' );

			endif; ?>
		</div>
	</section>
<?php get_footer();
