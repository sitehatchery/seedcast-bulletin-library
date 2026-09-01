<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * What a visitor would not find on a given service page.
 *
 * Reports absence rather than presence on purpose. A list of what is already
 * done is reassuring and useless; a list of what is missing is the thing that
 * gets someone to go and fill it in.
 */
class Completeness {

	/**
	 * Labels for content this service is missing.
	 *
	 * @param int $service_id Service post ID.
	 * @return array<int, string>
	 */
	public static function missing( int $service_id ): array {
		$missing = [];

		if ( ! has_post_thumbnail( $service_id ) ) {
			$missing[] = __( 'featured image', 'seedcast-bulletin-library' );
		}

		$service = get_post( $service_id );
		if ( ! $service || '' === trim( (string) $service->post_content ) ) {
			$missing[] = __( 'overview', 'seedcast-bulletin-library' );
		}

		if ( ! get_post_meta( $service_id, '_scbl_service_programs', true ) ) {
			$missing[] = __( 'programs', 'seedcast-bulletin-library' );
		}

		/**
		 * Other suite plugins report what they would have contributed to this
		 * service and did not. Sermon Library adding "sermon" when no sermon is
		 * attached to the week is the obvious first consumer, and is the point
		 * where this starts describing the whole ministry week rather than only
		 * the parts Sunday owns.
		 *
		 * @param array $missing    Labels for content a visitor would not find.
		 * @param int   $service_id Service post ID.
		 */
		return (array) apply_filters( 'scbl/service/missing', $missing, $service_id );
	}
}
