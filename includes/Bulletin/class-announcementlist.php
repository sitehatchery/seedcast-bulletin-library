<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Custom columns + filter on the Announcements admin list so triage is
 * fast when the list grows: Start and End columns visible at a glance,
 * a "Currently active | Upcoming | Expired | All" filter above the list,
 * and expired rows styled dimmer so they visually recede.
 */
class AnnouncementList {

	public function init(): void {
		$cpt = AnnouncementCPT::POST_TYPE;
		add_filter( "manage_{$cpt}_posts_columns",       [ $this, 'columns' ] );
		add_action( "manage_{$cpt}_posts_custom_column", [ $this, 'column_content' ], 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", [ $this, 'sortable' ] );
		add_action( 'pre_get_posts',                     [ $this, 'apply_filter' ] );
		add_action( 'restrict_manage_posts',             [ $this, 'render_filter' ] );
		add_action( 'admin_head-edit.php',               [ $this, 'expired_row_styling' ] );
	}

	public function columns( array $cols ): array {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			// Insert thumbnail column right after checkbox, before title.
			if ( $k === 'cb' ) {
				$new['scbl_ann_thumb'] = '';
			}
			if ( $k === 'title' ) {
				$new['scbl_ann_start'] = __( 'Start', 'seedcast-bulletin-library' );
				$new['scbl_ann_end']   = __( 'End', 'seedcast-bulletin-library' );
				$new['scbl_ann_status']= __( 'Status', 'seedcast-bulletin-library' );
			}
		}
		return $new;
	}

	public function column_content( string $col, int $post_id ): void {
		if ( $col === 'scbl_ann_thumb' ) {
			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				echo get_the_post_thumbnail( $post_id, [ 40, 40 ], [ 'class' => 'scbl-ann-list__thumb' ] );
			} else {
				echo '<span class="scbl-ann-list__thumb scbl-ann-list__thumb--empty"></span>';
			}
		} elseif ( $col === 'scbl_ann_start' ) {
			echo esc_html( (string) get_post_meta( $post_id, AnnouncementEditor::META_START, true ) );
		} elseif ( $col === 'scbl_ann_end' ) {
			$end = (string) get_post_meta( $post_id, AnnouncementEditor::META_END, true );
			echo $end ? esc_html( $end ) : '<em class="scbl-ann-list__ongoing">' . esc_html__( 'ongoing', 'seedcast-bulletin-library' ) . '</em>';
		} elseif ( $col === 'scbl_ann_status' ) {
			$status = self::classify( $post_id );
			$label  = [ 'active' => __( 'Active', 'seedcast-bulletin-library' ), 'upcoming' => __( 'Upcoming', 'seedcast-bulletin-library' ), 'expired' => __( 'Expired', 'seedcast-bulletin-library' ) ][ $status ] ?? '';
			printf( '<span class="scbl-ann-status scbl-ann-status--%s">%s</span>', esc_attr( $status ), esc_html( $label ) );
		}
	}

	public function sortable( array $cols ): array {
		$cols['scbl_ann_start'] = 'scbl_ann_start';
		$cols['scbl_ann_end']   = 'scbl_ann_end';
		return $cols;
	}

	public function render_filter(): void {
		global $typenow;
		if ( $typenow !== AnnouncementCPT::POST_TYPE ) return;
		// This is a read-only admin list-table filter; capability-gated by
		// edit.php itself. Nonces are unnecessary and would break bookmarkable
		// filter URLs.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['scbl_ann_filter'] ) ? sanitize_key( wp_unslash( $_GET['scbl_ann_filter'] ) ) : '';
		?>
		<select name="scbl_ann_filter">
			<option value=""         <?php selected( $current, '' ); ?>><?php esc_html_e( 'All statuses', 'seedcast-bulletin-library' ); ?></option>
			<option value="active"   <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Currently active', 'seedcast-bulletin-library' ); ?></option>
			<option value="upcoming" <?php selected( $current, 'upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'seedcast-bulletin-library' ); ?></option>
			<option value="expired"  <?php selected( $current, 'expired' ); ?>><?php esc_html_e( 'Expired', 'seedcast-bulletin-library' ); ?></option>
		</select>
		<?php
	}

	public function apply_filter( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) return;
		if ( ( $q->get( 'post_type' ) ?? '' ) !== AnnouncementCPT::POST_TYPE ) return;
		// Read-only list-table filter. See render_filter() above.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['scbl_ann_filter'] ) ) return;

		$today = gmdate( 'Y-m-d' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = sanitize_key( wp_unslash( $_GET['scbl_ann_filter'] ) );

		if ( $filter === 'active' ) {
			$q->set( 'meta_query', [
				'relation' => 'AND',
				[ 'key' => AnnouncementEditor::META_START, 'value' => $today, 'compare' => '<=' ],
				[
					'relation' => 'OR',
					[ 'key' => AnnouncementEditor::META_END, 'value' => $today, 'compare' => '>=' ],
					[ 'key' => AnnouncementEditor::META_END, 'value' => '',     'compare' => '=' ],
				],
			] );
		} elseif ( $filter === 'upcoming' ) {
			$q->set( 'meta_query', [
				[ 'key' => AnnouncementEditor::META_START, 'value' => $today, 'compare' => '>' ],
			] );
		} elseif ( $filter === 'expired' ) {
			$q->set( 'meta_query', [
				[ 'key' => AnnouncementEditor::META_END, 'value' => $today, 'compare' => '<' ],
			] );
		}
	}

	/**
	 * Classify one announcement's current status vs. today.
	 * @return string 'active'|'upcoming'|'expired'
	 */
	public static function classify( int $post_id ): string {
		$today = gmdate( 'Y-m-d' );
		$start = (string) get_post_meta( $post_id, AnnouncementEditor::META_START, true );
		$end   = (string) get_post_meta( $post_id, AnnouncementEditor::META_END,   true );
		if ( $start && $start > $today ) return 'upcoming';
		if ( $end   && $end   < $today ) return 'expired';
		return 'active';
	}

	/**
	 * Attaches the scbl-ann-expired CSS class to expired-announcement
	 * rows on the list table. Row dimming rule lives in scbl-admin.css.
	 */
	public function expired_row_styling(): void {
		global $typenow;
		if ( $typenow !== AnnouncementCPT::POST_TYPE ) return;

		add_filter( 'post_class', function ( $classes, $class, $post_id ) {
			if ( get_post_type( $post_id ) === AnnouncementCPT::POST_TYPE
				 && self::classify( $post_id ) === 'expired' ) {
				$classes[] = 'scbl-ann-expired';
			}
			return $classes;
		}, 10, 3 );
	}
}
