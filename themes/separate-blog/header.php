<?php
/**
* The header for separate blog theme
*
* This is the template that displays all of the <head> section and everything up until <div id="content">
*
* @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
*
* @package Separate_Blog
*/
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<header class="header">
		<nav class="navbar navbar-expand-lg">
			<div class="search-area">
				<div class="search-area-inner d-flex align-items-center justify-content-center">
					<div class="close-btn"><i class="fa fa-close"></i></div>
					<div class="row d-flex justify-content-center">
						<div class="col-md-8">
							<?php get_search_form(); ?>
						</div>
					</div>
				</div>
			</div>
			<div class="container">
				<div class="navbar-header d-flex align-items-center justify-content-between">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="navbar-brand">
						<?php 
							if ( has_custom_logo() ) :
								the_custom_logo();
							else:
								echo esc_html( get_bloginfo( 'name' ) );
							endif; 
						?>
				</a>
				<button type="button" data-toggle="collapse" data-target="#navbarcollapse" aria-controls="navbarcollapse" aria-expanded="false" aria-label="<?php esc_html_e( 'Toggle navigation', 'separate-blog' ); ?>" class="navbar-toggler"><span></span><span></span><span></span></button>
			</div>
			<div id="navbarcollapse" class="collapse navbar-collapse">
				<?php 
				wp_nav_menu( array( 'theme_location' => 'menu-1', 'menu_class' => 'navbar-nav ml-auto', 'container' => 'ul' , 'walker' => new Separate_Blog_Walker_Nav_Menu() ) ); 
				?>
				<div class="navbar-text"><a href="javascript:;" class="search-btn"><i class="fa fa-search"></i></a></div>
			</div>
		</div>
	</nav>
</header>
