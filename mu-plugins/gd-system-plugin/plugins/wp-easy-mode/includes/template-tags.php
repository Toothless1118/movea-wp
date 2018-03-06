<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Display the template body class
 */
function wpem_template_body_class() {

	$classes = [
		'wp-core-ui',
		'wpem-screen',
		sprintf( 'wpem-step-%d', wpem_get_current_step()->position ),
		sprintf( 'wpem-step-%s', wpem_get_current_step()->name ),
		sprintf( 'wpem-type-%s', wpem_get_site_type() ),
		sprintf( 'wpem-industry-%s', wpem_get_site_industry() ),
	];

	/**
	 * Filter the template body class
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	$classes = (array) apply_filters( 'wpem_template_body_class', $classes );

	echo implode( ' ', array_map( 'esc_attr', $classes ) );

}

/**
 * Display the template document title
 */
function wpem_template_title() {

	$step = wpem_get_current_step();

	printf(
		'%s &lsaquo; %s',
		esc_html( $step->page_title ),
		esc_html( get_bloginfo( 'name' ) )
	);

}

/**
 * Display an ordered list of steps
 */
function wpem_template_list_steps() {

	$steps = \WPEM\wpem()->admin->get_steps();

	$count = count( $steps );

	$current_step = wpem_get_current_step();

	$before_current_step = true;

	echo '<ol class="wpem-steps-list">';

	foreach ( $steps as $i => $step ) {

		$classes = [
			'wpem-steps-list-item',
			sprintf( 'wpem-steps-list-item-%d', $step->position ),
			sprintf( 'wpem-steps-list-item-%s', $step->name ),
		];

		if ( 0 === $i ) {

			$classes[] = 'first-step';

		}

		if ( $count === ( $i + 1 ) ) {

			$classes[] = 'last-step';

		}

		if ( $step->name === $current_step->name ) {

			$before_current_step = false;

			$classes[] = 'active-step';

		}

		if ( $current_step->position > ( $i + 1 ) ) {

			$classes[] = 'done-step';

		}

		$classes = array_map( 'trim', $classes );

		// Last iteration step
		$last_i_step = ( 0 === $i ) ? $step : $steps[ $i - 1 ];

		$content = esc_html( $step->title );

		// We add a link if last step is the current step and we can skip it
		if (
			$last_i_step->name === $current_step->name && $last_i_step->can_skip
			|| $before_current_step
		) {

			$content = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $step->url ),
				$content
			);

		}

		printf(
			'<li class="%s">%s</li>',
			implode( ' ', array_map( 'esc_attr', $classes ) ),
			$content
		);

	}

	echo '</ol>';

}

/**
 * Display template head
 */
function wpem_template_head() {

	/**
	 * Fires when header scripts should be printed for a particular step
	 *
	 * @since 2.0.0
	 */
	do_action( 'wpem_print_header_scripts_' . wpem_get_current_step()->name );

	wp_print_styles( 'wpem-fullscreen' );

}

/**
 * Display template footer
 */
function wpem_template_footer() {

	wp_print_scripts( 'jquery-blockui' );
	wp_print_scripts( 'wpem' );

	/**
	 * Fires when footer scripts should be printed for a particular step
	 *
	 * @since 2.0.0
	 */
	do_action( 'wpem_print_footer_scripts_' . wpem_get_current_step()->name );

	$fqdn = gethostname();

	if ( false === strpos( $fqdn, 'secureserver.net' ) ) {

		return;

	}

	$host = ( false !== strpos( $fqdn, '.prod.' ) ) ? 'secureserver.net' : 'test-secureserver.net';

	?>
	<script>"undefined"==typeof _trfd&&(window._trfd=[]),_trfd.push({"tccl.baseHost":"<?php echo esc_js( $host ) ?>"}),_trfd.push({"ap":"MWPQSWv2"})</script>
	<script src="//img1.wsimg.com/tcc/tcc_l.combined.1.0.2.min.js"></script>
	<?php

}
