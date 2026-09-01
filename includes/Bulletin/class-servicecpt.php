<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The `scbl_service` custom post type: a landing page for one Sunday
 * service. Its service date (`_sunday_service_date`) is the anchor other
 * suite plugins key off when contributing content.
 *
 * URL slug is configurable in Sunday Settings.
 */
class ServiceCPT {

	const POST_TYPE = 'scbl_service';

	public function register(): void {
		$labels = [
			'name'          => __( 'Services', 'seedcast-bulletin-library' ),
			'singular_name' => __( 'Service', 'seedcast-bulletin-library' ),
			'add_new'       => __( 'Add New', 'seedcast-bulletin-library' ),
			'add_new_item'  => __( 'Add New Service', 'seedcast-bulletin-library' ),
			'edit_item'     => __( 'Edit Service', 'seedcast-bulletin-library' ),
			'new_item'      => __( 'New Service', 'seedcast-bulletin-library' ),
			'view_item'     => __( 'View Service', 'seedcast-bulletin-library' ),
			'search_items'  => __( 'Search Services', 'seedcast-bulletin-library' ),
			'not_found'     => __( 'No services yet.', 'seedcast-bulletin-library' ),
			'menu_name'     => __( 'Services', 'seedcast-bulletin-library' ),
		];

		register_post_type( self::POST_TYPE, [
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'show_in_menu'       => false, // Explicitly attached under the Sunday top-level menu for ordering.
			'show_in_admin_bar'  => true,  // Ensure the admin bar edit/view links appear even though show_in_menu is false.
			// show_in_rest is set true so the REST API is available for
			// integrations. Actual editor choice (classic vs. block) is
			// controlled by the `use_block_editor_for_post_type` filter,
			// which reads a setting toggled from Sunday Settings.
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'rewrite'            => [ 'slug' => get_option( 'scbl_service_slug', 'service' ) ],
			'capability_type'    => 'post',
		] );
	}
}
