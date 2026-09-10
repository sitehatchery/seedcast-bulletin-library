<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The Sunday service pages module.
 */
class Bulletin {

	public function init(): void {
		( new AdminMenu() )->init();
		( new ContactsPage() )->init();
		( new SettingsSection() )->init();
		( new ShortcodeGenerator() )->init();

		add_action( 'init', [ new ServiceCPT(),      'register' ] );
		add_action( 'init', [ new AnnouncementCPT(), 'register' ] );
		add_action( 'init', [ new ProgramCPT(),      'register' ] );

		// Admin surfaces that operate on the CPTs.
		( new ServiceEditor() )->init();
		( new ServiceList() )->init();
		( new AnnouncementEditor() )->init();
		( new AnnouncementList() )->init();
		( new ProgramEditor() )->init();
		( new ProgramList() )->init();
		( new Handouts() )->init();
		( new Programs() )->init();

		// Front-end.
		( new Frontend() )->init();
		( new \SeedcastBulletinLibrary\Frontend\Schema() )->init();

		// Contribute sections to the service page via the sections filter -
		// every other suite plugin does the same to appear on the Sunday
		// page keyed by the service's week.
		//
		// Handouts do NOT get their own section; they render inline within
		// their Program's card via ProgramSection, keyed by handout.program_id.
		( new ProgramSection() )->init();
		( new AnnouncementSection() )->init();

		// Hide service-copy attachments from the standard Media Library so
		// the admin's media grid doesn't fill with duplicates. The copies
		// still exist and render on their service pages - they're just
		// excluded from browse-all views. (See ServiceEditor::duplicate_attachment
		// which sets the _sunday_service_copy meta.)
		add_action( 'pre_get_posts',              [ $this, 'hide_copies_from_media' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'hide_copies_from_media_ajax' ] );

		// Classic editor by default for Service; admins can flip a setting
		// (Settings > Seedcast > Sunday > Editor) to opt into the block editor if they
		// want richer content in the service overview. Announcements and
		// other CPTs stay classic across the board - they're form-shaped,
		// not content-shaped.
		add_filter( 'use_block_editor_for_post_type', [ $this, 'editor_choice' ], 10, 2 );
	}

	public function editor_choice( bool $use_block, string $post_type ): bool {
		if ( $post_type !== ServiceCPT::POST_TYPE ) return $use_block;
		return get_option( 'scbl_service_block_editor', '0' ) === '1';
	}

	public function hide_copies_from_media( \WP_Query $q ): void {
		if ( ! is_admin() ) return;
		if ( $q->get( 'post_type' ) !== 'attachment' ) return;
		$mq = $q->get( 'meta_query' ) ?: [];
		$mq[] = [
			'key'     => '_scbl_service_copy',
			'compare' => 'NOT EXISTS',
		];
		$q->set( 'meta_query', $mq );
	}

	public function hide_copies_from_media_ajax( array $args ): array {
		$mq = $args['meta_query'] ?? [];
		$mq[] = [
			'key'     => '_scbl_service_copy',
			'compare' => 'NOT EXISTS',
		];
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'] = $mq;
		return $args;
	}
}
