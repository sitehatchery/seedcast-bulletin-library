<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The `scbl_announcement` custom post type. A notice that runs across
 * a date range: start date, optional end date (empty end = ongoing).
 *
 * Announcements are durational, not per-service. A single announcement
 * with a start and end date automatically appears on every service whose
 * week overlaps its range. See ServiceEditor for the copy mechanism.
 */
class AnnouncementCPT {

	const POST_TYPE = 'scbl_announcement';

	public function register(): void {
		$labels = [
			'name'          => __( 'Announcements', 'seedcast-bulletin-library' ),
			'singular_name' => __( 'Announcement', 'seedcast-bulletin-library' ),
			'add_new'       => __( 'Add New', 'seedcast-bulletin-library' ),
			'add_new_item'  => __( 'Add New Announcement', 'seedcast-bulletin-library' ),
			'edit_item'     => __( 'Edit Announcement', 'seedcast-bulletin-library' ),
			'new_item'      => __( 'New Announcement', 'seedcast-bulletin-library' ),
			'view_item'     => __( 'View Announcement', 'seedcast-bulletin-library' ),
			'search_items'  => __( 'Search Announcements', 'seedcast-bulletin-library' ),
			'not_found'     => __( 'No announcements yet.', 'seedcast-bulletin-library' ),
			'menu_name'     => __( 'Announcements', 'seedcast-bulletin-library' ),
		];

		register_post_type( self::POST_TYPE, [
			'labels'             => $labels,
			// Announcements aren't standalone pages - they only render as
			// sections on service pages. When Activities becomes a
			// separate plugin later, that plugin will introduce singular
			// views if wanted.
			'public'             => false,
			'publicly_queryable' => false,
			'exclude_from_search'=> true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_rest'       => false,
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'capability_type'    => 'post',
		] );
	}
}
