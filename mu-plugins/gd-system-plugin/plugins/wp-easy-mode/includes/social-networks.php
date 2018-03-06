<?php

$username = sanitize_title( _x( 'username', 'Must be lowercase and use URL-safe characters', 'wp-easy-mode' ) );
$channel  = sanitize_title( _x( 'channel', 'Must be lowercase and use URL-safe characters', 'wp-easy-mode' ) );
$company  = sanitize_title( _x( 'company', 'Must be lowercase and use URL-safe characters', 'wp-easy-mode' ) );
$board    = sanitize_title( _x( 'board', 'Must be lowercase and use URL-safe characters', 'wp-easy-mode' ) );

$social_networks = [
	'facebook' => [
		'icon'   => 'facebook-official',
		'label'  => __( 'Facebook', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'facebook', "https://www.facebook.com/{$username}" ),
		'select' => $username,
	],
	'twitter' => [
		'label'  => __( 'Twitter', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'twitter', "https://twitter.com/{$username}" ),
		'select' => $username,
	],
	'instagram' => [
		'label'  => __( 'Instagram', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'instagram', "https://www.instagram.com/{$username}" ),
		'select' => $username,
	],
	'linkedin' => [
		'icon'  => 'linkedin-square',
		'label'  => __( 'LinkedIn', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'linkedin', "https://www.linkedin.com/in/{$username}" ),
		'select' => $username,
	],
	'googleplus' => [
		'icon'  => 'google-plus',
		'label'  => __( 'Google+', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'googleplus', "https://google.com/+{$username}" ),
		'select' => $username,
	],
	'pinterest' => [
		'label'  => __( 'Pinterest', 'wp-easy-mode' ),
		'url'    => wpem_get_social_profile_url( 'pinterest', "https://www.pinterest.com/{$username}" ),
		'select' => $username,
	],
];
