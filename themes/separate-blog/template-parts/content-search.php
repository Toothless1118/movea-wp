<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Separate_Blog
 */

?>
<?php if ( $GLOBALS['count'] % 2 == 0 ) : ?>
	<div <?php post_class( array( 'row d-flex', 'align-items-stretch' ) ); ?> id="post-<?php the_ID(); ?>">
		<div class="image col-lg-5">
			<?php
				if ( has_post_thumbnail() ) : 
				the_post_thumbnail( 'separate-blog-post-thumbnail', array( 'class' => 'img-responsive', 'alt' => esc_attr( get_the_title() ) ) ); 
				endif; ?>
		</div>
		<div class="text col-lg-7">
			<div class="text-inner d-flex align-items-center">
				<div class="content">
					<header class="post-header">
						<?php
						if ( is_singular() ) :
							the_title( '<h2 class="h4">', '</h2>' );
						else :
							the_title( '<h2 class="h4"><a href="' . esc_url( get_permalink() ) . '" rel="post-link">', '</a></h2>' );
						endif; ?>
					</header>
					<div class="category">
						<?php separate_blog_entry_footer(); ?>
					</div>
					<p><?php the_excerpt(); ?></p>
					<?php 
						if ( 'post' === get_post_type() ) :
							separate_blog_posted_on(); 
						endif; 
					?>
				</div>
			</div>
		</div>
	</div>
<?php else : ?>
	<div <?php post_class( array( 'row d-flex', 'align-items-stretch' ) ); ?> id="post-<?php the_ID(); ?>">
		<div class="text col-lg-7">
			<div class="text-inner d-flex align-items-center">
				<div class="content">
					<header class="post-header">
						<?php
						if ( is_singular() ) :
							the_title( '<h2 class="h4">', '</h2>' );
						else :
							the_title( '<h2 class="h4"><a href="' . esc_url( get_permalink() ) . '" rel="post-link">', '</a></h2>' );
						endif; ?>
					</header>
					<div class="category">
						<?php separate_blog_entry_footer(); ?>
					</div>
					<p><?php the_excerpt(); ?></p>
					<?php 
						if ( 'post' === get_post_type() ) :
							separate_blog_posted_on(); 
						endif; 
					?>
				</div>
			</div>
		</div>
		<div class="image col-lg-5">
			<?php
				if ( has_post_thumbnail() ) : 
				the_post_thumbnail( 'separate-blog-post-thumbnail', array( 'class' => 'img-responsive', 'alt' => esc_attr( get_the_title() ) ) ); 
				endif; ?>
		</div>
	</div>
<?php endif; ?>
