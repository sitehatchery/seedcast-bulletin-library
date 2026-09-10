<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When an announcement happens, and how to say so.
 *
 * The admin picks a frequency and fills in only what that frequency needs.
 * Everything else derives from the one array that produces: the first and
 * last dates written to the flat start/end meta that the list filters and
 * the shortcode query, the lines printed on the card, and the one-line
 * summary shown around the admin.
 *
 * This is deliberately not an event manager. There is no recurrence engine
 * and nothing lists future occurrences; the frequencies are the handful of
 * patterns a church bulletin actually describes.
 *
 * Announcements saved before scheduling existed have no schedule meta. They
 * read as "No set schedule" with their old start and end dates, which is how
 * they already behaved, so nothing needs migrating.
 */
class Schedule {

	const META = '_scbl_ann_schedule';

	/** Kinds whose rows each carry their own time, so the single Time field does not apply. */
	const ROW_TIMED = [ 'multiday', 'staggered' ];

	/** Recurring patterns that fall on the nth weekday of a month. */
	const NTH = [ 'first_weekday', 'second_weekday', 'third_weekday', 'last_weekday' ];

	/** Kinds whose start and end dates sit behind the "Set start and end dates" checkbox. */
	const OPTIONAL_DATES = [ 'none', 'multiday', 'weekly', 'monthly' ];

	/** Kinds tied to specific calendar dates rather than a repeating pattern. */
	const DATED = [ 'one_time', 'consecutive', 'staggered' ];

	/** @return array<string, string> */
	public static function frequencies(): array {
		return [
			'none'        => __( 'No set schedule', 'seedcast-bulletin-library' ),
			'one_time'    => __( 'One Time', 'seedcast-bulletin-library' ),
			'consecutive' => __( 'Consecutive', 'seedcast-bulletin-library' ),
			'multiday'    => __( 'Multiday', 'seedcast-bulletin-library' ),
			'staggered'   => __( 'Staggered', 'seedcast-bulletin-library' ),
			'recurring'   => __( 'Recurring', 'seedcast-bulletin-library' ),
		];
	}

	/** @return array<string, string> */
	public static function patterns(): array {
		return [
			'weekly'            => __( 'Weekly', 'seedcast-bulletin-library' ),
			'monthly'           => __( 'Monthly', 'seedcast-bulletin-library' ),
			'every_other_month' => __( 'Every Other Month', 'seedcast-bulletin-library' ),
			'first_weekday'     => __( 'First Weekday of Month', 'seedcast-bulletin-library' ),
			'second_weekday'    => __( 'Second Weekday of Month', 'seedcast-bulletin-library' ),
			'third_weekday'     => __( 'Third Weekday of Month', 'seedcast-bulletin-library' ),
			'last_weekday'      => __( 'Last Weekday of Month', 'seedcast-bulletin-library' ),
		];
	}

	/**
	 * One line under the Frequency select explaining the choice.
	 *
	 * @return array<string, string>
	 */
	public static function help(): array {
		return [
			'none'        => __( 'A notice rather than an event, such as a sign-up or a call for volunteers.', 'seedcast-bulletin-library' ),
			'one_time'    => __( 'Happens once, on one date.', 'seedcast-bulletin-library' ),
			'consecutive' => __( 'Every day from the start date through the end date, at the same time.', 'seedcast-bulletin-library' ),
			'multiday'    => __( 'Several days of the week, each with its own time. Good for one ministry that meets as more than one group.', 'seedcast-bulletin-library' ),
			'staggered'   => __( 'Specific dates, each with its own time. Good for quarterly or irregular gatherings.', 'seedcast-bulletin-library' ),
			'recurring'   => __( 'A regular pattern, such as weekly, monthly, or a set weekday of the month.', 'seedcast-bulletin-library' ),
		];
	}

	/**
	 * Which fields each kind of schedule uses. A kind is the frequency or,
	 * for Recurring, the chosen pattern. The editor renders every field once
	 * and shows or hides it from this table, in PHP on load and in JS on
	 * change, and sanitize() keeps only what the table allows, so the three
	 * can never disagree.
	 *
	 * show:     kinds that always show the field.
	 * toggled:  kinds that show it only while "Set start and end dates" is ticked.
	 * required: kinds that must fill it in.
	 *
	 * @return array<string, array{show: string[], toggled: string[], required: string[]}>
	 */
	public static function fields(): array {
		$patterns = array_keys( self::patterns() );
		$kinds    = array_merge( [ 'none', 'one_time', 'consecutive', 'multiday', 'staggered' ], $patterns );
		$weekday  = array_merge( [ 'weekly' ], self::NTH );

		return [
			'pattern'   => self::field( $patterns ),
			'weekday'   => self::field( $weekday, [], $weekday ),
			'date'      => self::field( [ 'one_time' ], [], [ 'one_time' ] ),
			'use_dates' => self::field( self::OPTIONAL_DATES ),
			'start'     => self::field( array_merge( [ 'consecutive', 'every_other_month' ], self::NTH ), self::OPTIONAL_DATES, [ 'consecutive', 'every_other_month' ] ),
			'end'       => self::field( [ 'consecutive' ], self::OPTIONAL_DATES, [ 'consecutive' ] ),
			'time'      => self::field( array_values( array_diff( $kinds, self::ROW_TIMED ) ) ),
			'days'      => self::field( [ 'multiday' ] ),
			'dates'     => self::field( [ 'staggered' ], [], [ 'staggered' ] ),
		];
	}

	private static function field( array $show, array $toggled = [], array $required = [] ): array {
		return [
			'show'     => $show,
			'toggled'  => $toggled,
			'required' => $required,
		];
	}

	public static function is_visible( string $field, string $kind, bool $use_dates ): bool {
		$fields = self::fields();
		if ( ! isset( $fields[ $field ] ) ) {
			return false;
		}
		if ( in_array( $kind, $fields[ $field ]['show'], true ) ) {
			return true;
		}
		return $use_dates && in_array( $kind, $fields[ $field ]['toggled'], true );
	}

	/** The frequency, or for Recurring the chosen pattern. */
	public static function kind( array $s ): string {
		$frequency = (string) ( $s['frequency'] ?? 'none' );
		if ( 'recurring' === $frequency ) {
			return (string) ( $s['pattern'] ?? 'weekly' );
		}
		return $frequency;
	}

	public static function uses_time( string $kind ): bool {
		return ! in_array( $kind, self::ROW_TIMED, true );
	}

	public static function is_dated( array $s ): bool {
		return in_array( self::kind( $s ), self::DATED, true );
	}

	/**
	 * The next day a dated schedule happens on or after $today, or '' when
	 * none is left. A Consecutive run already under way happens today.
	 */
	public static function next_date( array $s, string $today ): string {
		if ( 'staggered' === self::kind( $s ) ) {
			// sanitize() keeps these in date order, so the first one ahead is next.
			foreach ( $s['dates'] ?? [] as $row ) {
				if ( $row['date'] >= $today ) {
					return $row['date'];
				}
			}
			return '';
		}

		list( $first, $last ) = self::bounds( $s );
		if ( '' === $first || ( '' !== $last && $last < $today ) ) {
			return '';
		}
		return $first > $today ? $first : $today;
	}

	/** An announcement's schedule, reading pre-scheduling announcements as "No set schedule". */
	public static function get( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::META, true );
		if ( is_array( $stored ) && isset( $stored['frequency'] ) ) {
			return self::sanitize( $stored );
		}

		$start = (string) get_post_meta( $post_id, AnnouncementEditor::META_START, true );
		$end   = (string) get_post_meta( $post_id, AnnouncementEditor::META_END, true );
		return self::sanitize(
			[
				'frequency' => 'none',
				'use_dates' => '' !== $start || '' !== $end,
				'start'     => $start,
				'end'       => $end,
			]
		);
	}

	/**
	 * Cleans a schedule and reduces it to the fields its kind uses, in a
	 * fixed key order. The output is canonical: two schedules that mean the
	 * same thing come out identical, which is what lets a service copy be
	 * compared against its source with a plain !==.
	 *
	 * @param mixed $raw Posted fields, stored meta, or decoded JSON.
	 */
	public static function sanitize( $raw ): array {
		$raw       = is_array( $raw ) ? $raw : [];
		$frequency = (string) ( $raw['frequency'] ?? '' );
		if ( ! array_key_exists( $frequency, self::frequencies() ) ) {
			$frequency = 'none';
		}

		$out = [ 'frequency' => $frequency ];
		if ( 'recurring' === $frequency ) {
			$pattern        = (string) ( $raw['pattern'] ?? '' );
			$out['pattern'] = array_key_exists( $pattern, self::patterns() ) ? $pattern : 'weekly';
		}

		$kind      = self::kind( $out );
		$use_dates = ! empty( $raw['use_dates'] );

		if ( self::is_visible( 'use_dates', $kind, $use_dates ) ) {
			$out['use_dates'] = $use_dates;
		}
		if ( self::is_visible( 'weekday', $kind, $use_dates ) ) {
			$day            = self::clean_weekday( $raw['weekday'] ?? '' );
			$out['weekday'] = null === $day ? 0 : $day;
		}
		foreach ( [ 'date', 'start', 'end' ] as $key ) {
			if ( self::is_visible( $key, $kind, $use_dates ) ) {
				$out[ $key ] = self::clean_date( $raw[ $key ] ?? '' );
			}
		}

		if ( self::is_visible( 'days', $kind, $use_dates ) ) {
			$out['days'] = [];
			foreach ( (array) ( $raw['days'] ?? [] ) as $row ) {
				$day = is_array( $row ) ? self::clean_weekday( $row['weekday'] ?? '' ) : null;
				if ( null !== $day ) {
					$out['days'][] = [
						'weekday' => $day,
						'time'    => self::clean_text( $row['time'] ?? '' ),
					];
				}
			}
		}

		if ( self::is_visible( 'dates', $kind, $use_dates ) ) {
			$out['dates'] = [];
			foreach ( (array) ( $raw['dates'] ?? [] ) as $row ) {
				$date = is_array( $row ) ? self::clean_date( $row['date'] ?? '' ) : '';
				if ( '' !== $date ) {
					$out['dates'][] = [
						'date' => $date,
						'time' => self::clean_text( $row['time'] ?? '' ),
					];
				}
			}
			usort(
				$out['dates'],
				static function ( array $a, array $b ): int {
					$by_date = strcmp( $a['date'], $b['date'] );
					return 0 !== $by_date ? $by_date : strcmp( $a['time'], $b['time'] );
				}
			);
		}

		// A range entered backwards is a slip, not an intent.
		if ( ! empty( $out['start'] ) && ! empty( $out['end'] ) && $out['end'] < $out['start'] ) {
			$swap         = $out['start'];
			$out['start'] = $out['end'];
			$out['end']   = $swap;
		}

		return $out;
	}

	/**
	 * First and last date the schedule covers, '' where it is open-ended.
	 * These are what get written to the flat start/end meta.
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function bounds( array $s ): array {
		$kind = self::kind( $s );
		if ( 'one_time' === $kind ) {
			$date = (string) ( $s['date'] ?? '' );
			return [ $date, $date ];
		}
		if ( 'staggered' === $kind ) {
			$dates = array_column( $s['dates'] ?? [], 'date' );
			return $dates ? [ min( $dates ), max( $dates ) ] : [ '', '' ];
		}
		return [ (string) ( $s['start'] ?? '' ), (string) ( $s['end'] ?? '' ) ];
	}

	/**
	 * What the card says about when, one string per line.
	 *
	 * $as_of is the Sunday the reader is looking from: the service's week, or
	 * this week for the shortcode. With it, Staggered dates already behind the
	 * reader are dropped and Every Other Month names the months ahead. Without
	 * it the whole schedule is described, which is what the admin screens want.
	 *
	 * @param array  $s     Schedule.
	 * @param string $time  The single Time field; ignored by kinds whose rows carry their own.
	 * @param string $as_of Y-m-d, or '' for no reference point.
	 * @return string[]
	 */
	public static function lines( array $s, string $time, string $as_of = '' ): array {
		$time  = trim( $time );
		$kind  = self::kind( $s );
		$lines = [];

		if ( 'one_time' === $kind ) {
			if ( ! empty( $s['date'] ) ) {
				$lines[] = self::with_time( self::date_label( $s['date'], 'l, F j', $as_of ), $time );
			}
		} elseif ( 'consecutive' === $kind ) {
			if ( ! empty( $s['start'] ) && ! empty( $s['end'] ) ) {
				$lines[] = self::with_time(
					sprintf(
						/* translators: 1: first date, 2: last date. */
						__( 'Daily, %1$s – %2$s', 'seedcast-bulletin-library' ),
						self::date_label( $s['start'], 'M j', $as_of ),
						self::date_label( $s['end'], 'M j', $as_of )
					),
					$time
				);
			}
		} elseif ( 'multiday' === $kind ) {
			foreach ( $s['days'] ?? [] as $row ) {
				$lines[] = self::with_time( self::every( (int) $row['weekday'] ), (string) $row['time'] );
			}
		} elseif ( 'staggered' === $kind ) {
			$rows = $s['dates'] ?? [];
			if ( '' !== $as_of ) {
				$ahead = array_filter(
					$rows,
					static function ( array $row ) use ( $as_of ): bool {
						return $row['date'] >= $as_of;
					}
				);
				// Once every date has passed, show them all rather than nothing.
				$rows = $ahead ? $ahead : $rows;
			}
			foreach ( $rows as $row ) {
				$lines[] = self::with_time( self::date_label( $row['date'], 'D, M j', $as_of ), (string) $row['time'] );
			}
		} elseif ( 'weekly' === $kind ) {
			$lines[] = self::with_time( self::every( (int) ( $s['weekday'] ?? 0 ) ), $time );
		} elseif ( 'monthly' === $kind ) {
			$lines[] = self::with_time( __( 'Monthly', 'seedcast-bulletin-library' ), $time );
		} elseif ( 'every_other_month' === $kind ) {
			$lines[] = self::with_time( self::alternate_months( (string) ( $s['start'] ?? '' ), $as_of ), $time );
		} elseif ( in_array( $kind, self::NTH, true ) ) {
			$lines[] = self::with_time( self::nth_weekday( $kind, (int) ( $s['weekday'] ?? 0 ) ), $time );
		} elseif ( '' !== $time ) {
			// No set schedule: the time, if there is one, is all there is to say.
			$lines[] = $time;
		}

		if ( in_array( $kind, array_merge( [ 'multiday', 'weekly', 'monthly' ], self::NTH ), true ) ) {
			$range = self::range_label( (string) ( $s['start'] ?? '' ), (string) ( $s['end'] ?? '' ), $as_of );
			if ( '' !== $range ) {
				$lines[] = $range;
			}
		}

		return $lines;
	}

	/**
	 * One line for the admin screens: the list table, service suggestions and
	 * copy rows. A notice's dates only control when it is offered, so they
	 * stay off the card but are worth seeing here.
	 */
	public static function summary( array $s, string $time ): string {
		$lines = self::lines( $s, $time );
		if ( 'none' === self::kind( $s ) ) {
			$range = self::range_label( (string) ( $s['start'] ?? '' ), (string) ( $s['end'] ?? '' ), '' );
			if ( '' !== $range ) {
				array_unshift( $lines, $range );
			}
		}
		return implode( '; ', $lines );
	}

	public static function weekday_name( int $day ): string {
		global $wp_locale;
		if ( $wp_locale instanceof \WP_Locale ) {
			return (string) $wp_locale->get_weekday( $day );
		}
		return gmdate( 'l', (int) strtotime( '2026-01-04 +' . $day . ' days' ) ); // 2026-01-04 is a Sunday.
	}

	private static function every( int $day ): string {
		/* translators: %s: weekday name, e.g. Monday. */
		return sprintf( __( 'Every %s', 'seedcast-bulletin-library' ), self::weekday_name( $day ) );
	}

	private static function nth_weekday( string $kind, int $day ): string {
		$formats = [
			/* translators: %s: weekday name, e.g. Saturday. */
			'first_weekday'  => __( 'First %s of the month', 'seedcast-bulletin-library' ),
			/* translators: %s: weekday name, e.g. Saturday. */
			'second_weekday' => __( 'Second %s of the month', 'seedcast-bulletin-library' ),
			/* translators: %s: weekday name, e.g. Saturday. */
			'third_weekday'  => __( 'Third %s of the month', 'seedcast-bulletin-library' ),
			/* translators: %s: weekday name, e.g. Saturday. */
			'last_weekday'   => __( 'Last %s of the month', 'seedcast-bulletin-library' ),
		];
		return sprintf( $formats[ $kind ] ?? '%s', self::weekday_name( $day ) );
	}

	/**
	 * Every Other Month has no day, only a starting month. Looking from a
	 * given week it names the next three months in the sequence; with no
	 * reference point it says where the sequence starts.
	 */
	private static function alternate_months( string $start, string $as_of ): string {
		if ( '' === $start ) {
			return __( 'Every other month', 'seedcast-bulletin-library' );
		}
		if ( '' === $as_of ) {
			/* translators: %s: month and year the sequence starts, e.g. March 2026. */
			return sprintf( __( 'Every other month from %s', 'seedcast-bulletin-library' ), self::format( $start, 'F Y' ) );
		}

		$first = self::month_index( $start );
		$now   = self::month_index( $as_of );
		$next  = $now < $first ? $first : $now + ( ( $now - $first ) % 2 );
		$names = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$month   = $next + ( 2 * $i );
			$names[] = self::format( sprintf( '%04d-%02d-01', intdiv( $month, 12 ), ( $month % 12 ) + 1 ), 'M' );
		}
		/* translators: %s: the next three months in the sequence, e.g. Sep, Nov, Jan. */
		return sprintf( __( 'Every other month (%s)', 'seedcast-bulletin-library' ), implode( ', ', $names ) );
	}

	private static function month_index( string $date ): int {
		return ( (int) substr( $date, 0, 4 ) * 12 ) + (int) substr( $date, 5, 2 ) - 1;
	}

	private static function range_label( string $start, string $end, string $as_of ): string {
		if ( '' !== $start && '' !== $end ) {
			return sprintf( '%1$s – %2$s', self::date_label( $start, 'M j', $as_of ), self::date_label( $end, 'M j', $as_of ) );
		}
		if ( '' !== $end ) {
			/* translators: %s: last date, e.g. Dec 20. */
			return sprintf( __( 'Through %s', 'seedcast-bulletin-library' ), self::date_label( $end, 'M j', $as_of ) );
		}
		if ( '' !== $start && '' === $as_of ) {
			/* translators: %s: first date, e.g. Sep 14. */
			return sprintf( __( 'From %s', 'seedcast-bulletin-library' ), self::date_label( $start, 'M j', $as_of ) );
		}
		if ( '' !== $start && $start > $as_of ) {
			/* translators: %s: first date, e.g. Sep 14. */
			return sprintf( __( 'Starting %s', 'seedcast-bulletin-library' ), self::date_label( $start, 'M j', $as_of ) );
		}
		return '';
	}

	/** Formats a date, adding the year only when it differs from the reference year. */
	private static function date_label( string $date, string $format, string $as_of ): string {
		$year = '' !== $as_of ? substr( $as_of, 0, 4 ) : gmdate( 'Y' );
		return self::format( $date, substr( $date, 0, 4 ) === $year ? $format : $format . ', Y' );
	}

	/** Dates are calendar days, so they are formatted in UTC to stop a timezone shifting them. */
	private static function format( string $date, string $format ): string {
		$ts = strtotime( $date . ' 00:00:00 UTC' );
		if ( ! $ts ) {
			return $date;
		}
		return (string) wp_date( $format, $ts, new \DateTimeZone( 'UTC' ) );
	}

	private static function with_time( string $label, string $time ): string {
		$time = trim( $time );
		if ( '' === $label ) {
			return $time;
		}
		return '' === $time ? $label : $label . ' · ' . $time;
	}

	private static function clean_date( $value ): string {
		$date = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $date;
	}

	private static function clean_weekday( $value ): ?int {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$day = (int) $value;
			if ( $day >= 0 && $day <= 6 ) {
				return $day;
			}
		}
		return null;
	}

	private static function clean_text( $value ): string {
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}
}
