<?php
/**
 * Bulletin Library uninstall.
 *
 * Conservative by default. Only Bulletin Library's own options are removed. Content
 * (Services, Announcements, Programs) is preserved because it may still be
 * wanted for records. Define SCBL_FULL_UNINSTALL to also drop that content.
 *
 * Nothing here touches the sc_ options or the seedcast_submissions table.
 * Those belong to the shared library, and every other Seedcast plugin on the
 * site is still using them. Deleting a church's theme choice and captcha keys
 * because they uninstalled one plugin of several would be a real and
 * surprising loss.
 *
 * @package SeedcastBulletinLibrary
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$scbl_options = [
	'scbl_services_per_page',
	'scbl_service_slug',
	'scbl_service_block_editor',
	'scbl_archive_heading',
	'scbl_archive_intro',
	'scbl_label_service_singular',
	'scbl_label_service_plural',
	'scbl_church_location_name',
	'scbl_church_location_address',
	'scbl_default_service_image',
	'scbl_disable_frontend_css',
	'scbl_flush_rewrite_rules',
];
foreach ( $scbl_options as $scbl_opt ) {
	delete_option( $scbl_opt );
}

// Optional full uninstall: drops Bulletin Library's content.
if ( defined( 'SCBL_FULL_UNINSTALL' ) && SCBL_FULL_UNINSTALL ) {
	$scbl_cpts = [ 'scbl_service', 'scbl_announcement', 'scbl_program' ];
	foreach ( $scbl_cpts as $scbl_cpt ) {
		$scbl_ids = get_posts( [
			'post_type'   => $scbl_cpt,
			'numberposts' => -1,
			'post_status' => 'any',
			'fields'      => 'ids',
		] );
		foreach ( $scbl_ids as $scbl_id ) {
			wp_delete_post( $scbl_id, true );
		}
	}
}
