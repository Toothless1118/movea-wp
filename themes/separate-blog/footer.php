<?php
/**
* The template for displaying the footer
*
* Contains the closing of the #content div and all content after.
*
* @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
*
* @package Separate_Blog
*/
?>
<footer class="main-footer">
	<div class="container">
		<div class="row">
			<div class="col-md-4">
				<div class="logo">
					<h6 class="text-white"><?php esc_html_e( 'Address', 'separate-blog' ); ?></h6>
				</div>
				<div class="contact-details">
					<?php echo esc_textarea( wpautop( get_theme_mod( 'separate_blog_address' ), true ) ); ?>
					<p><?php echo sprintf( 'Email: %s', separate_blog_admin_email() ); ?></p>
					<ul class="social-menu">
						<?php if ( get_theme_mod( 'separate_blog_facebook' ) ): ?>
							<li class="list-inline-item">
								<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_facebook' ) ); ?>"><i class="fa fa-facebook"></i></a>
							</li>
						<?php endif;?>

						<?php if ( get_theme_mod( 'separate_blog_twitter' ) ): ?>
							<li class="list-inline-item">
								<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_twitter' ) ); ?>"><i class="fa fa-twitter"></i></a>
							</li>
						<?php endif; ?>

						<?php if ( get_theme_mod( 'separate_blog_google_plus' ) ): ?>
							<li class="list-inline-item">
								<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_google_plus' ) ); ?>"><i class="fa fa-google-plus"></i></a>
							</li>
						<?php endif; ?>

						<?php if ( get_theme_mod( 'separate_blog_instagram' ) ): ?>
							<li class="list-inline-item">
								<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_instagram' ) ); ?>"><i class="fa fa-instagram"></i></a>
							</li>
						<?php endif;?>
						<?php if ( get_theme_mod( 'separate_blog_youtube' ) ): ?>
						<li class="list-inline-item">
							<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_youtube' ) ); ?>"><i class="fa fa-youtube"></i></a>
						</li>
						<?php endif; ?>

						<?php if ( get_theme_mod( 'separate_blog_pinterest' ) ): ?>
							<li class="list-inline-item">
								<a href="<?php echo esc_url( get_theme_mod( 'separate_blog_pinterest' ) ); ?>"><i class="fa fa-pinterest"></i></a>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>
			<div class="col-md-4">
				<div class="menus d-flex">
					<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => 'ul', 'menu_class' => 'list-unstyled' ) ); ?>
				</div>
			</div>
			<div class="col-md-4">
				<div class="latest-posts">
					<?php
					$recent_posts = wp_get_recent_posts( array( 'numberposts' => 3 ) );
					foreach( $recent_posts as $recent ) {
						echo '<a href="' . esc_url( get_permalink( $recent['ID'] ) ) . '">
						<div class="post d-flex align-items-center">
							<div class="image">' . get_the_post_thumbnail( $recent['ID'], 'separate-blog-recent-thumbnail' ) . '</div>
							<div class="title"><strong>' . esc_html( get_the_title( $recent['ID'] ) ) . '</strong><span class="date last-meta">' . esc_html( get_the_time( get_option( 'date_format' ), $recent['ID'] ) ) . '</span></div>
						</div></a>';
					}	?>
				</div>
			</div>
		</div>
	</div>
		<div class="copyrights">
			<div class="container">
				<div class="row">
					<div class="col-md-6">
					&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> &middot;
						<?php $link = sprintf( '<a href="%1$s" title="%2$s" rel="%3$s">%4$s</a>', 'http://profiles.wordpress.org/dilipbheda', 'WordPress Profile', 'nofollow', 'Dilip Bheda' );
						printf( esc_html__( '%1$s Theme by %2$s', 'separate-blog' ), 'Separate Blog', esc_url( $link ) ); 
						?>
					</div>
				</div>
			</div>
		</div>
	</footer>
</div><!-- #content -->
<?php wp_footer(); ?>
</body>
</html>
