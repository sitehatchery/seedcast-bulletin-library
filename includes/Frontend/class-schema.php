<?php
namespace SeedcastBulletinLibrary\Frontend;

use Seedcast\Core\Frontend\Meta as CoreMeta;
use Seedcast\Core\Frontend\Schema as CoreSchema;

use SeedcastBulletinLibrary\Bulletin\ServiceCPT;
use SeedcastBulletinLibrary\Bulletin\ServiceEditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SEO output for Bulletin Library's front-end pages: JSON-LD structured data
 * (Event on single services, BreadcrumbList everywhere applicable,
 * ItemList on the archive) plus Open Graph and Twitter Card meta tags
 * for social sharing previews.
 *
 * Meta tags only fire when no dedicated SEO plugin is active. Yoast,
 * RankMath and AIOSEO all do a better job than we can if they're
 * installed, so we get out of their way rather than duplicate tags.
 *
 * JSON-LD always fires — it's additive and useful even alongside an
 * SEO plugin (Yoast in particular is opinionated about Article/Product
 * but doesn't emit Event schema for arbitrary CPTs).
 */
class Schema {

	public function init(): void {
		add_action( 'wp_head', [ $this, 'output_meta' ], 2 );
		add_action( 'wp_head', [ $this, 'output_jsonld' ], 5 );

		// Invalidate the archive JSON-LD cache whenever a service is
		// saved or trashed.
		add_action( 'save_post_' . ServiceCPT::POST_TYPE, [ self::class, 'flush_archive_jsonld_cache' ] );
		add_action( 'trashed_post', [ self::class, 'flush_archive_jsonld_cache' ] );
		add_action( 'untrashed_post', [ self::class, 'flush_archive_jsonld_cache' ] );
	}

	public function output_meta(): void {
		if ( $this->seo_plugin_active() ) return;
		if ( ! is_singular( ServiceCPT::POST_TYPE ) && ! is_post_type_archive( ServiceCPT::POST_TYPE ) ) return;

		if ( is_singular( ServiceCPT::POST_TYPE ) ) {
			$this->service_meta();
		} else {
			$this->archive_meta();
		}
	}

	public function output_jsonld(): void {
		if ( is_singular( ServiceCPT::POST_TYPE ) ) {
			$this->service_jsonld();
			$this->breadcrumb_jsonld();
		} elseif ( is_post_type_archive( ServiceCPT::POST_TYPE ) ) {
			$this->archive_jsonld();
			$this->breadcrumb_jsonld();
		}
	}

	/**
	 * Detection lives in core: every suite plugin has to make the same call
	 * about whether to stay out of an SEO plugin's way, and the list of
	 * plugins to detect is not Bulletin Library's business to maintain alone.
	 */
	private function seo_plugin_active(): bool {
		return CoreMeta::seo_plugin_active();
	}

	private function service_meta(): void {
		$post_id = (int) get_the_ID();
		$post    = get_post( $post_id );
		if ( ! $post ) return;

		$description = $this->build_description( $post_id );
		$image       = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
		$this->print_meta_tags( get_the_title(), $description, $image, get_permalink( $post_id ), 'article' );
	}

	private function archive_meta(): void {
		$heading = (string) get_option( 'scbl_archive_heading', __( 'Sunday Services', 'seedcast-bulletin-library' ) );
		$intro   = (string) get_option( 'scbl_archive_intro', '' );
		$this->print_meta_tags(
			$heading,
			$intro !== '' ? wp_trim_words( wp_strip_all_tags( $intro ), 30 ) : $heading,
			'',
			get_post_type_archive_link( ServiceCPT::POST_TYPE ),
			'website'
		);
	}

	private function build_description( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) return '';

		// Prefer the dedicated description meta field. Falls back to
		// post_content for legacy posts that were saved before the
		// description became a first-class field.
		$description = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, ServiceEditor::META_DESCRIPTION, true ) ) );
		if ( '' === $description ) {
			$description = trim( wp_strip_all_tags( (string) $post->post_content ) );
		}
		if ( $description !== '' ) {
			return wp_trim_words( $description, 30 );
		}

		// Fall back to a synthesized description when nothing was written.
		// Uses the same card_lines filter the service card grid uses, so
		// "Programs (3), Announcements (2)" is what social previews get.
		$date  = (string) get_post_meta( $post_id, ServiceEditor::META_DATE, true );
		$lines = apply_filters( 'scbl/service/card_lines', [], $post, $date );
		$parts = [];
		foreach ( $lines as $line ) {
			if ( isset( $line['count'] ) && (int) $line['count'] === 0 ) continue;
			$label = (string) ( $line['label'] ?? '' );
			if ( isset( $line['count'] ) ) $label .= ' (' . (int) $line['count'] . ')';
			if ( $label !== '' ) $parts[] = $label;
		}
		if ( empty( $parts ) ) return '';

		return sprintf(
			/* translators: %1$s: service title; %2$s: comma-separated list of section labels with counts. */
			__( 'Service on %1$s: %2$s.', 'seedcast-bulletin-library' ),
			get_the_title( $post_id ),
			implode( ', ', $parts )
		);
	}

	/**
	 * Tag output lives in core. Bulletin Library decides what the title, description and
	 * image are; the tag set itself is identical in every suite plugin and had
	 * already drifted between copies before it was shared.
	 */
	private function print_meta_tags( string $title, string $description, string $image, string $url, string $og_type = 'article' ): void {
		CoreMeta::render( [
			'title'       => $title,
			'description' => $description,
			'image'       => $image,
			'url'         => $url,
			'type'        => $og_type,
		] );
	}

	private function service_jsonld(): void {
		$post_id = (int) get_the_ID();
		$post    = get_post( $post_id );
		if ( ! $post ) return;

		$date  = (string) get_post_meta( $post_id, ServiceEditor::META_DATE, true );
		$image = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';

		$data = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => get_the_title(),
			'url'         => get_permalink( $post_id ),
			'eventStatus' => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		];

		if ( $date !== '' ) {
			$data['startDate'] = $date;
		}

		$data['datePublished'] = get_the_date( 'c', $post_id );
		$data['dateModified']  = get_the_modified_date( 'c', $post_id );

		$description = $this->build_description( $post_id );
		if ( $description !== '' ) {
			$data['description'] = $description;
		}

		if ( $image !== '' ) {
			$data['image'] = $image;
		}

		// Nested VideoObject when a video URL is set - carries the "watch"
		// answer for anything that reads structured video data.
		$video_url = trim( (string) get_post_meta( $post_id, ServiceEditor::META_VIDEO, true ) );
		if ( '' !== $video_url ) {
			$data['video'] = [
				'@type'        => 'VideoObject',
				'name'         => get_the_title(),
				'description'  => $description !== '' ? $description : get_the_title(),
				'thumbnailUrl' => $image !== '' ? $image : $video_url,
				'contentUrl'   => $video_url,
				'uploadDate'   => get_the_date( 'c', $post_id ),
			];
		}

		// Sunday School at 9am and the main service at 10am are sub-events
		// of the day's gathering. Read the frozen Program copies rather
		// than the source Programs so what we advertise matches what
		// renders.
		$sub_events = $this->sub_events( $post_id );
		if ( ! empty( $sub_events ) ) {
			$data['subEvent'] = $sub_events;
		}

		$location = $this->church_location();
		if ( ! empty( $location ) ) {
			$data['location'] = $location;
		}

		$data['organizer'] = [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		];

		$this->print_jsonld( $data );
	}

	/**
	 * Build subEvent entries from the service's Program copies. Frozen at
	 * copy time, so what we advertise as sub-events matches what the page
	 * renders even if the source Program was later edited.
	 *
	 * @return array<int, array>
	 */
	private function sub_events( int $post_id ): array {
		if ( ! class_exists( '\SeedcastBulletinLibrary\Bulletin\Programs' ) ) {
			return [];
		}
		$copies = \SeedcastBulletinLibrary\Bulletin\Programs::get_copies( $post_id );
		if ( empty( $copies ) ) return [];

		$out = [];
		foreach ( $copies as $c ) {
			$title = (string) ( $c['title'] ?? '' );
			if ( '' === $title ) continue;
			$sub = [
				'@type' => 'Event',
				'name'  => $title,
			];
			$body = (string) ( $c['body'] ?? '' );
			if ( '' !== $body ) $sub['description'] = wp_trim_words( wp_strip_all_tags( $body ), 30 );
			$link = (string) ( $c['link'] ?? '' );
			if ( '' !== $link ) $sub['url'] = $link;
			$out[] = $sub;
		}
		return $out;
	}

	/**
	 * Attempt a Place block for the church's physical location. Reads from
	 * the shared Church helper in core, which is populated on the Seedcast
	 * settings screen. If nothing is configured, returns an empty array and
	 * the caller skips the location field. Google accepts events without a
	 * location, but the schema is stronger with one.
	 */
	private function church_location(): array {
		$name    = \Seedcast\Core\Church::name();
		$address = \Seedcast\Core\Church::address();
		if ( $name === '' ) {
			$name = (string) get_bloginfo( 'name' );
		}
		if ( $address === '' ) return [];

		return [
			'@type'   => 'Place',
			'name'    => $name,
			'address' => $address,
		];
	}

	private function archive_jsonld(): void {
		// Cached ItemList — invalidated on any service save via the
		// scbl_flush_archive_jsonld action registered below. Rebuilding
		// on every archive page load is unnecessary since it only
		// depends on the top 20 published services.
		$cache_key = 'scbl_archive_jsonld_v1';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$this->print_jsonld( $cached );
			return;
		}

		$q = new \WP_Query( [
			'post_type'      => ServiceCPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			// phpcs:disable WordPress.DB.SlowDBQuery
			'meta_key'       => ServiceEditor::META_DATE,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			// phpcs:enable WordPress.DB.SlowDBQuery
		] );

		// Prime post caches in one query so the per-item get_permalink() /
		// get_the_title() calls below hit the object cache instead of each
		// firing its own SELECT * FROM wp_posts.
		if ( ! empty( $q->posts ) ) {
			_prime_post_caches( $q->posts, false, false );
		}

		$items = [];
		$i = 0;
		foreach ( $q->posts as $post_id ) {
			$i++;
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $i,
				'url'      => get_permalink( $post_id ),
				'name'     => get_the_title( $post_id ),
			];
		}
		wp_reset_postdata();

		if ( empty( $items ) ) return;

		$data = [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => (string) get_option( 'scbl_archive_heading', __( 'Sunday Services', 'seedcast-bulletin-library' ) ),
			'itemListElement' => $items,
		];

		// 12 hours: fresh enough that a Sunday added on Saturday shows up
		// by Sunday, short enough that a mistake doesn't stick.
		set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

		$this->print_jsonld( $data );
	}

	/**
	 * Invalidate the archive JSON-LD cache. Fired on any service post
	 * transition so a newly-published or edited service shows up in the
	 * ItemList straight away.
	 */
	public static function flush_archive_jsonld_cache(): void {
		delete_transient( 'scbl_archive_jsonld_v1' );
	}

	/**
	 * The crumb trail for the current view, in the shared shape. The same array
	 * feeds Breadcrumb::render() if a template wants to show the trail, so the
	 * visible trail and the structured data always agree.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	public function crumbs(): array {
		$crumbs = [
			[
				'label' => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			],
			[
				'label' => (string) get_option( 'scbl_archive_heading', __( 'Sunday Services', 'seedcast-bulletin-library' ) ),
				'url'   => (string) get_post_type_archive_link( ServiceCPT::POST_TYPE ),
			],
		];

		if ( is_singular( ServiceCPT::POST_TYPE ) ) {
			$crumbs[] = [
				'label' => get_the_title(),
				'url'   => (string) get_permalink(),
			];
		}

		return $crumbs;
	}

	private function breadcrumb_jsonld(): void {
		$this->print_jsonld( CoreSchema::breadcrumbs( $this->crumbs() ) );
	}

	private function print_jsonld( array $data ): void {
		CoreSchema::emit( $data );
	}
}
