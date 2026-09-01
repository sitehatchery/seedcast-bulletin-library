<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The `scbl_program` CPT: the parallel experiences at a church gathering
 * (Sunday School, Main Service, Kids Church, Youth, and so on). Copies of
 * currently-published Programs appear on each service page as "Today's
 * Programs."
 *
 * Programs are not publicly viewable on their own; they render only inside
 * service pages via the sections filter. Copies freeze on service save
 * (same pattern as announcements) and show an "Update?" affordance when
 * the source diverges.
 */
class ProgramCPT {

	const POST_TYPE = 'scbl_program';

	public function register(): void {
		$labels = [
			'name'          => __( 'Programs', 'seedcast-bulletin-library' ),
			'singular_name' => __( 'Program', 'seedcast-bulletin-library' ),
			'add_new'       => __( 'Add New', 'seedcast-bulletin-library' ),
			'add_new_item'  => __( 'Add New Program', 'seedcast-bulletin-library' ),
			'edit_item'     => __( 'Edit Program', 'seedcast-bulletin-library' ),
			'new_item'      => __( 'New Program', 'seedcast-bulletin-library' ),
			'search_items'  => __( 'Search Programs', 'seedcast-bulletin-library' ),
			'not_found'     => __( 'No programs yet.', 'seedcast-bulletin-library' ),
			'menu_name'     => __( 'Programs', 'seedcast-bulletin-library' ),
		];

		register_post_type( self::POST_TYPE, [
			'labels'             => $labels,
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
