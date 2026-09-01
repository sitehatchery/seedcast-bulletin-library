<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin list customization for Services.
 *
 * Adds a Service Date column so admins can scan the list chronologically
 * (which is how they think about services - by date, not by publish
 * order). Sortable, and set as the default sort key descending so the
 * most recent Sunday is at the top.
 */
class ServiceList {

	public function init(): void {
		$cpt = ServiceCPT::POST_TYPE;
		add_filter( "manage_{$cpt}_posts_columns",         [ $this, 'columns' ] );
		add_action( "manage_{$cpt}_posts_custom_column",   [ $this, 'column_content' ], 10, 2 );
		add_filter( "manage_edit-{$cpt}_sortable_columns", [ $this, 'sortable' ] );
		add_action( 'pre_get_posts',                       [ $this, 'default_sort' ] );
	}

	public function columns( array $cols ): array {
		$new = [];
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( $k === 'title' ) {
				$new['scbl_service_date'] = __( 'Service Date', 'seedcast-bulletin-library' );
				$new['scbl_missing']      = __( 'Still missing', 'seedcast-bulletin-library' );
			}
		}
		return $new;
	}

	public function column_content( string $col, int $post_id ): void {
		if ( 'scbl_missing' === $col ) {
			$missing = Completeness::missing( $post_id );
			if ( ! $missing ) {
				printf(
					'<span class="scbl-complete">%s</span>',
					esc_html__( 'Complete', 'seedcast-bulletin-library' )
				);
				return;
			}
			echo esc_html( implode( ', ', $missing ) );
			return;
		}

		if ( $col !== 'scbl_service_date' ) return;
		$date = (string) get_post_meta( $post_id, ServiceEditor::META_DATE, true );
		if ( ! $date ) { echo '<span class="scbl-empty-dash">-</span>'; return; }
		$ts = strtotime( $date );
		if ( ! $ts ) { echo esc_html( $date ); return; }
		echo esc_html( gmdate( 'D, M j, Y', $ts ) );
	}

	public function sortable( array $cols ): array {
		$cols['scbl_service_date'] = 'scbl_service_date';
		return $cols;
	}

	/**
	 * Default the sort to service date descending when admin arrives on
	 * the Services list with no explicit sort. Also handles the case where
	 * they've explicitly clicked the column header to re-sort.
	 */
	public function default_sort( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) return;
		if ( ( $q->get( 'post_type' ) ?? '' ) !== ServiceCPT::POST_TYPE ) return;

		$orderby = $q->get( 'orderby' );
		if ( $orderby === 'scbl_service_date' || $orderby === '' || $orderby === 'date' ) {
			$q->set( 'meta_key', ServiceEditor::META_DATE );
			$q->set( 'orderby',  'meta_value' );
			// Respect an explicit order= param; otherwise default descending.
			if ( ! $q->get( 'order' ) ) {
				$q->set( 'order', 'DESC' );
			}
		}
	}
}
