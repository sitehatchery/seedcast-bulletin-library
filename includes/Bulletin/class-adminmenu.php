<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sunday's top-level admin menu.
 *
 * All Sunday screens (Services, Announcements, Programs, Contacts, Settings)
 * live under a single "Sunday" top-level menu.
 */
class AdminMenu {

	const MENU_SLUG = 'seedcast-bulletin-library';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register' ], 8 );
		add_action( 'admin_menu', [ $this, 'sort_submenu' ], 999 );
		add_filter( 'parent_file',  [ $this, 'keep_menu_open' ] );
		add_filter( 'submenu_file', [ $this, 'keep_submenu_active' ], 10, 2 );
		// The top-level Sunday menu has no page of its own. Send it to the
		// Services list, early enough that headers have not gone out.
		add_action( 'admin_init', [ $this, 'maybe_redirect_top_level' ] );
	}

	public function maybe_redirect_top_level(): void {
		if ( ! is_admin() ) return;
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . ServiceCPT::POST_TYPE ) );
		exit;
	}

	/**
	 * Returns the correct parent slug when we're on one of Sunday's own
	 * screens. Without this, editing an Announcement collapses the whole
	 * Sunday menu because WordPress doesn't know the screen belongs there.
	 */
	public function keep_menu_open( string $parent_file ): string {
		$screen = get_current_screen();
		if ( ! $screen ) return $parent_file;
		$owned = [ ServiceCPT::POST_TYPE, AnnouncementCPT::POST_TYPE, ProgramCPT::POST_TYPE ];
		if ( in_array( $screen->post_type, $owned, true ) ) {
			return self::MENU_SLUG;
		}
		return $parent_file;
	}

	/**
	 * Sets the correct submenu item as active when we're on a screen that
	 * WordPress can't map cleanly (edit-tags, post edits, etc.).
	 */
	public function keep_submenu_active( ?string $submenu_file, string $parent_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) return $submenu_file;
		// Ministries taxonomy screen: highlight the Ministries submenu, not
		// the CPT it happens to be attached to. WordPress reports the
		// taxonomy edit screen's post_type as whichever object type the
		// taxonomy is attached to (typically the first one), so without
		// this the CPT check below would win and (say) Programs would
		// light up when the user clicked Ministries.
		if ( $screen->post_type === ServiceCPT::POST_TYPE ) {
			return 'edit.php?post_type=' . ServiceCPT::POST_TYPE;
		}
		if ( $screen->post_type === AnnouncementCPT::POST_TYPE ) {
			return 'edit.php?post_type=' . AnnouncementCPT::POST_TYPE;
		}
		if ( $screen->post_type === ProgramCPT::POST_TYPE ) {
			return 'edit.php?post_type=' . ProgramCPT::POST_TYPE;
		}
		return $submenu_file;
	}

	public function register(): void {
		add_menu_page(
			__( 'Bulletin Library', 'seedcast-bulletin-library' ),
			__( 'Bulletin Library', 'seedcast-bulletin-library' ),
			'edit_posts',
			self::MENU_SLUG,
			'',
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Services', 'seedcast-bulletin-library' ),
			__( 'Services', 'seedcast-bulletin-library' ),
			'edit_posts',
			'edit.php?post_type=' . ServiceCPT::POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Programs', 'seedcast-bulletin-library' ),
			__( 'Programs', 'seedcast-bulletin-library' ),
			'edit_posts',
			'edit.php?post_type=' . ProgramCPT::POST_TYPE
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Announcements', 'seedcast-bulletin-library' ),
			__( 'Announcements', 'seedcast-bulletin-library' ),
			'edit_posts',
			'edit.php?post_type=' . AnnouncementCPT::POST_TYPE
		);

		// add_menu_page creates a duplicate first submenu from the top level
		// item. Services is registered immediately above and is the intended
		// landing screen, so drop the duplicate.
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}


	/**
	 * Deterministically order the Sunday submenu. WordPress's `add_submenu_page`
	 * position argument is unreliable when multiple items are near the same
	 * position, and priorities on `admin_menu` only affect registration order
	 * (not final display order once WordPress re-sorts). The reliable way is
	 * to reorder the global $submenu array ourselves after everyone has had
	 * a chance to register - which is what this method does at priority 999.
	 *
	 * Desired order:
	 *   Services, Programs, Announcements, Contacts, Settings
	 */
	public function sort_submenu(): void {
		global $submenu;
		if ( ! isset( $submenu[ self::MENU_SLUG ] ) ) return;

		$rank_by_slug = [
			'edit.php?post_type=' . ServiceCPT::POST_TYPE               => 20,
			'edit.php?post_type=' . ProgramCPT::POST_TYPE               => 30,
			'edit.php?post_type=' . AnnouncementCPT::POST_TYPE          => 40,
			// The Settings item is a link to the shared page, so its slug is
			// the full relative URL rather than a page slug.
			'scbl-contacts'                                           => 70,
			'options-general.php?page=seedcast-settings&section=scbl_general' => 90,
		];

		usort( $submenu[ self::MENU_SLUG ], static function ( $a, $b ) use ( $rank_by_slug ) {
			$rank_a = self::rank_for( (string) ( $a[2] ?? '' ), $rank_by_slug );
			$rank_b = self::rank_for( (string) ( $b[2] ?? '' ), $rank_by_slug );
			return $rank_a <=> $rank_b;
		} );

		// Re-index so WordPress's expected integer keys stay contiguous.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering $submenu is the only reliable way to control admin submenu order; add_submenu_page's position argument is not honoured once WordPress re-sorts.
		$submenu[ self::MENU_SLUG ] = array_values( $submenu[ self::MENU_SLUG ] );
	}

	/** Returns the rank for a submenu slug, or 999 if unmatched (unknowns fall to end). */
	private static function rank_for( string $slug, array $ranks ): int {
		return $ranks[ $slug ] ?? 999;
	}
}
