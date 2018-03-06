<?php
/**
 * Template part for displaying page content detail in single.php
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Separate_Blog
 */

?>

<main class="post blog-post col-lg-8"> 
	<div class="container">
		<div class="post-single">
			<?php 
			global $post;

			if ( has_post_thumbnail() ) :
				echo '<div class="post-thumbnail">';
					the_post_thumbnail( 'full', array( 'class' => 'img-fluid' ) );
				echo '</div>';
			endif; 
			?>
			<div class="post-details">
				<div class="post-meta d-flex justify-content-between">
					<div class="category"><?php separate_blog_post_categories( $post ); ?></div>
				</div>
				<h1><?php the_title(); ?><a href="<?php the_permalink(); ?>"><i class="fa fa-bookmark-o"></i></a></h1>
				<div class="post-footer d-flex align-items-center flex-column flex-sm-row">
					<?php 
					echo sprintf( '<div class="avatar">%s</div>', get_avatar( get_the_author_meta( 'ID' ) , 50 ) ); 
					echo sprintf(
						/* translators: %s: post author. */
						'<div class="title"><span class="author vcard"><a class="url fn n" href="%s">%s</a></span></div>', esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ), get_the_author() 
						);
					?>
					<div class="d-flex align-items-center flex-wrap">       
						<?php
							echo sprintf(
								/* translators: %s: post date. */
								'<div class="date"><i class="fa fa-clock-o"></i><a href="%s" rel="bookmark">%s</a></div>', esc_url( get_permalink() ), esc_html( get_the_time( get_option( 'date_format' ) ) ) ); 
						?>
						<div class="comments meta-last"><i class="fa fa-commenting-o"></i><?php echo esc_html( get_comments_number() ); ?></div>
					</div>
				</div>
				<div class="post-body">
					<?php the_content(); ?>
				</div>
				<div class="post-tags">
					<?php echo get_the_tag_list(); ?>
				</div>
				<!-- Pagination -->
				<div class="posts-nav d-flex justify-content-between align-items-stretch flex-column flex-md-row">
					<?php previous_post_link( '%link', '<div class="icon prev"><i class="fa fa-angle-left"></i></div><div class="text"><h6>%title</h6></div>' ); ?>

					<?php next_post_link( '%link', '<div class="text"><h6>%title</h6></div><div class="icon next"><i class="fa fa-angle-right"></i></div>' ); ?>
				</div>
				<?php
					// If comments are open or we have at least one comment, load up the comment template.
					if ( comments_open() || get_comments_number() ) :
						comments_template();
					endif; 
				?>
			</div>
		</div>
	</div>
</main>