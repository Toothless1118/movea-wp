	<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Separate_Blog
 */
get_header(); ?>
<section>
	<div class="error-page">
		<div class="container">
			<div class="row">
				<div class="col-lg-12">
					<div class="page-not-found"> 
						<h1><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'separate-blog' ); ?></h1>
						<p>
							<?php esc_html_e( 'It looks like nothing was found at this location. Maybe try the search at above header.', 'separate-blog' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<?php get_footer();
