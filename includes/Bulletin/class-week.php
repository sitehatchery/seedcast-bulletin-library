<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Week-of helper.
 *
 * A week runs Sunday through Saturday, inclusive. The Sunday service
 * anchors the week that follows it. All week-boundary logic lives here.
 */
class Week {

	/**
	 * The Sunday that anchors the week containing $date.
	 *
	 * @param string|int $date Any parseable date (Y-m-d, timestamp, etc).
	 * @return string Y-m-d of the anchoring Sunday.
	 */
	public static function anchor( $date ): string {
		$ts = is_numeric( $date ) ? (int) $date : strtotime( (string) $date );
		if ( ! $ts ) return '';
		// PHP's 'w' returns 0 for Sunday, so subtract that many days.
		$dow = (int) gmdate( 'w', $ts );
		return gmdate( 'Y-m-d', $ts - ( $dow * DAY_IN_SECONDS ) );
	}

	/**
	 * The last day (Saturday) of the week anchored on $sunday.
	 *
	 * @param string $sunday Y-m-d of a Sunday.
	 * @return string Y-m-d of the following Saturday.
	 */
	public static function end( string $sunday ): string {
		$ts = strtotime( $sunday );
		if ( ! $ts ) return '';
		return gmdate( 'Y-m-d', $ts + ( 6 * DAY_IN_SECONDS ) );
	}

	/**
	 * Whether a [start, end] range overlaps the week anchored on $sunday.
	 * An empty $end is treated as "runs indefinitely" (Ongoing announcements).
	 */
	public static function range_overlaps_week( string $start, string $end, string $sunday ): bool {
		if ( ! $start || ! $sunday ) return false;
		$week_end = self::end( $sunday );
		if ( ! $week_end ) return false;
		// Range overlaps week if range_start <= week_end AND (range_end empty OR range_end >= week_start).
		if ( $start > $week_end ) return false;
		if ( $end && $end < $sunday ) return false;
		return true;
	}

	/**
	 * A friendly label for a Sunday date - used in admin listings and
	 * headings. E.g. "Sun, Jul 12, 2026".
	 */
	public static function label( string $date ): string {
		$ts = strtotime( $date );
		if ( ! $ts ) return $date;
		return gmdate( 'D, M j, Y', $ts );
	}
}
