<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the public assistant may answer from Bulletin Library: announcements.
 *
 * "When is the next worship night?" is a question about an announcement, and
 * the answer is a date this plugin already knows how to work out. So each
 * announcement goes to the assistant with its next date already decided, in
 * the church's own timezone, beside every date it has and where it happens.
 * The model is never left to do calendar arithmetic, or to read a date from
 * last month as one still coming.
 *
 * Offered to the engine through seedcast/ask/corpora rather than as a product,
 * since the engine writes nothing for this plugin. Public, said in as many
 * words: everything here is printed on the announcements page already. The
 * contact on an announcement is left out all the same; the card carries it,
 * and the assistant has no need to repeat a person's details.
 *
 * Announcements have no pages of their own, so a citation links to the page
 * carrying [scbl_announcements], straight to that announcement's card.
 */
class Ask {

	public const FILTER = 'scbl_ask_retrieve';

	/** Most announcements returned for one question. */
	private const MAX = 6;

	/** Days an announcement stays answerable after its last date. */
	private const GRACE_DAYS = 45;

	/**
	 * Words that ask what is coming up rather than about one thing, stemmed.
	 */
	private const WHATS_ON = [ 'event', 'happen', 'upcoming', 'coming', 'announcement', 'calendar', 'schedul', 'week', 'weekend', 'month', 'going', 'next' ];

	private const STOPWORDS = [
		'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'do', 'does', 'did', 'for', 'from', 'how', 'i', 'if',
		'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'our', 'the', 'their', 'there', 'this', 'to', 'us', 'was',
		'we', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'you', 'your', 'about', 'any', 'have',
		'has', 'church', 'time', 'date', 'day', 'start', 'held', 'tell', 'know',
	];

	public function init(): void {
		add_filter( 'seedcast/ask/corpora', [ $this, 'declare' ] );
		add_filter( self::FILTER, [ $this, 'retrieve' ], 10, 3 );
	}

	/**
	 * @param mixed $corpora What other plugins offered.
	 * @return array
	 */
	public function declare( $corpora ): array {
		$corpora = is_array( $corpora ) ? $corpora : [];

		$corpora['bulletin'] = [
			'sensitivity' => 'public',
			'retrieve'    => self::FILTER,
		];

		return $corpora;
	}

	/**
	 * Announcements a question is about.
	 *
	 * By its words, matched against each announcement's title and text; or,
	 * for "what's coming up" questions with nothing more particular in them,
	 * every current announcement soonest first.
	 *
	 * @param mixed  $found    Anything an earlier filter found.
	 * @param string $question What the visitor asked.
	 * @param array  $context  Unused.
	 * @return array
	 */
	public function retrieve( $found, $question, $context = [] ): array {
		$found = is_array( $found ) ? $found : [];
		$words = self::words( (string) $question );

		if ( ! $words ) {
			return $found;
		}

		$today   = wp_date( 'Y-m-d' );
		$cutoff  = wp_date( 'Y-m-d', time() - self::GRACE_DAYS * DAY_IN_SECONDS );
		$whatson = (bool) array_intersect( $words, self::WHATS_ON );
		$subject = array_values( array_diff( $words, self::WHATS_ON ) );
		$matches = [];

		$posts = get_posts( [
			'post_type'      => AnnouncementCPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'has_password'   => false,
		] );

		foreach ( $posts as $post ) {
			$end = (string) get_post_meta( $post->ID, AnnouncementEditor::META_END, true );

			// Long over: not something anybody is asking after.
			if ( '' !== $end && $end < $cutoff ) {
				continue;
			}

			$title_words = self::words( $post->post_title );
			$body_words  = self::words( wp_strip_all_tags( $post->post_content ) . ' ' . get_post_meta( $post->ID, AnnouncementEditor::META_LOC, true ) );

			$in_title = count( array_intersect( $subject, $title_words ) );
			$in_body  = count( array_intersect( array_diff( $subject, $title_words ), $body_words ) );
			$score    = $in_title * 3 + $in_body;

			// Most of the title named is the announcement asked about.
			if ( $title_words && $in_title * 2 >= count( $title_words ) ) {
				$score += 4;
			}

			if ( $score < 3 && ! ( $whatson && ! $subject ) ) {
				continue;
			}

			$next = Schedule::next_date( Schedule::get( $post->ID ), $today );

			// Something still to come is the likelier answer.
			if ( '' !== $next ) {
				$score += 1;
			}

			$matches[] = [
				'post'  => $post,
				'score' => (float) max( $score, 2 ),
				'next'  => $next,
			];
		}

		usort(
			$matches,
			static function ( array $a, array $b ): int {
				if ( $a['score'] !== $b['score'] ) {
					return $b['score'] <=> $a['score'];
				}

				// Soonest first; nothing left to come last.
				return strcmp( '' === $a['next'] ? '9999' : $a['next'], '' === $b['next'] ? '9999' : $b['next'] );
			}
		);

		foreach ( array_slice( $matches, 0, self::MAX ) as $match ) {
			$found[] = $this->item( $match['post'], $match['score'], $today );
		}

		return $found;
	}

	/**
	 * One announcement as material for an answer.
	 */
	private function item( \WP_Post $post, float $score, string $today ): array {
		$schedule = Schedule::get( $post->ID );
		$time     = (string) get_post_meta( $post->ID, AnnouncementEditor::META_TIME, true );
		$next     = Schedule::next_date( $schedule, $today );
		$lines    = [];

		$past  = [];
		$when  = Schedule::lines( $schedule, $time, $today, $past );
		$dated = Schedule::is_dated( $schedule );

		if ( $dated ) {
			if ( '' !== $next ) {
				$lines[] = 'Next date: ' . self::long_date( $next ) . self::time_on( $schedule, $next, $time ) . ( $next === $today ? ' (today)' : '' );
			} else {
				$lines[] = 'Every date has now passed. Nothing is scheduled after today.';
			}
		}

		if ( $when ) {
			$all = [];

			foreach ( $when as $i => $line ) {
				$all[] = $line . ( ! empty( $past[ $i ] ) ? ' (already past)' : '' );
			}

			$lines[] = ( $dated ? 'All dates: ' : 'When: ' ) . implode( '; ', $all );
		}

		$location = trim( (string) get_post_meta( $post->ID, AnnouncementEditor::META_LOC, true ) );

		if ( '' !== $location ) {
			$lines[] = 'Where: ' . $location;
		}

		$body = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_content ) ) );

		if ( '' !== $body ) {
			$lines[] = $body;
		}

		$link = trim( (string) get_post_meta( $post->ID, AnnouncementEditor::META_LINK, true ) );

		if ( '' !== $link ) {
			$lines[] = 'More details: ' . $link;
		}

		$text = implode( "\n", $lines );

		return [
			'ref'      => 'announcement:' . $post->ID,
			'type'     => 'announcement',
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'url'      => self::page_url() . '#scbl-ann-' . $post->ID,
			'reason'   => '' !== $next ? 'next on ' . self::long_date( $next ) : 'an announcement',
			'text'     => $text,
			// The next date moves with the calendar, so an answer about it is
			// written afresh each day as well as on every edit.
			'modified' => (string) get_post_modified_time( 'U', true, $post ) . '|' . $today,
			'score'    => $score,
		];
	}

	/**
	 * The time on a given date: its own, for a schedule whose dates each
	 * carry one, otherwise the announcement's.
	 */
	private static function time_on( array $schedule, string $date, string $time ): string {
		foreach ( (array) ( $schedule['dates'] ?? [] ) as $row ) {
			if ( ( $row['date'] ?? '' ) === $date && '' !== trim( (string) ( $row['time'] ?? '' ) ) ) {
				return ', ' . trim( (string) $row['time'] );
			}
		}

		return '' !== trim( $time ) ? ', ' . trim( $time ) : '';
	}

	private static function long_date( string $ymd ): string {
		$date = date_create_immutable( $ymd, wp_timezone() );

		return $date ? wp_date( 'l, F j, Y', $date->getTimestamp() ) : $ymd;
	}

	/**
	 * The page announcements are read on: the one carrying the shortcode,
	 * found once a day, or the home page when there is none.
	 */
	public static function page_url(): string {
		$url = (string) get_transient( 'scbl_ask_announcements_url' );

		if ( '' === $url ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$id = (int) $wpdb->get_var(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[scbl\\_announcements%' ORDER BY menu_order, ID LIMIT 1"
			);

			$url = $id ? (string) get_permalink( $id ) : home_url( '/' );
			set_transient( 'scbl_ask_announcements_url', $url, DAY_IN_SECONDS );
		}

		/**
		 * Where a citation of an announcement links to.
		 *
		 * @param string $url The page carrying [scbl_announcements], or home.
		 */
		return (string) apply_filters( 'scbl_ask_announcements_url', $url );
	}

	/**
	 * The words of a question that carry meaning, lightly stemmed so "nights"
	 * finds "night".
	 *
	 * @return string[]
	 */
	private static function words( string $text ): array {
		$text  = mb_strtolower( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
		$text  = (string) preg_replace( "/[^\\p{L}\\p{N}\\s]+/u", ' ', str_replace( [ "'", '’' ], '', $text ) );
		$out   = [];

		foreach ( (array) preg_split( '/\s+/u', trim( $text ) ) as $word ) {
			if ( '' === $word || mb_strlen( $word ) < 3 || in_array( $word, self::STOPWORDS, true ) ) {
				continue;
			}

			if ( mb_strlen( $word ) > 4 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
				$word = substr( $word, 0, -1 );
			}

			foreach ( [ 'ing', 'ed', 'e' ] as $suffix ) {
				if ( mb_strlen( $word ) - strlen( $suffix ) >= 4 && substr( $word, -strlen( $suffix ) ) === $suffix ) {
					$word = substr( $word, 0, -strlen( $suffix ) );
					break;
				}
			}

			$out[] = $word;
		}

		return array_values( array_unique( $out ) );
	}
}
