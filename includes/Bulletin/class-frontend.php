<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sunday's front-end surface: template loader, asset enqueue, and
 * shortcodes.
 *
 * Template loading follows WordPress conventions: theme files override
 * plugin templates. A theme can drop a `scbl-service.php` or
 * `archive-scbl_service.php` in its root to fully customize; if it
 * doesn't, plugin templates in /templates/scbl/ are used.
 */
class Frontend {

	public function init(): void {
		// Priority 99 so this runs late - after most themes and page
		// builders have resolved. We only override if we detect that no
		// specific template has been chosen for this CPT.
		add_filter( 'template_include',       [ $this, 'load_template' ], 99 );
		add_action( 'wp_enqueue_scripts',     [ $this, 'assets' ] );
		add_action( 'pre_get_posts',          [ $this, 'archive_query' ] );

		// Shortcodes.
		add_shortcode( 'scbl_services',            [ $this, 'seedcast_services' ] );
		add_shortcode( 'scbl_next_service',        [ $this, 'seedcast_next_service' ] );
		add_shortcode( 'scbl_announcements',       [ $this, 'seedcast_announcements' ] );
	}

	/**
	 * Order the Service archive by service date (newest first) and honor
	 * the Sunday per-page setting. Using the main query means WordPress's
	 * rewrite-based pagination (/service/page/2/) works without extra work.
	 */
	public function archive_query( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) return;
		if ( ! $q->is_post_type_archive( ServiceCPT::POST_TYPE ) ) return;
		// Ordering by service date (post meta) is intrinsic to Sunday.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$q->set( 'meta_key', ServiceEditor::META_DATE );
		$q->set( 'orderby',  'meta_value' );
		$q->set( 'order',    'DESC' );
		$q->set( 'posts_per_page', absint( get_option( 'scbl_services_per_page', 10 ) ) );
	}

	/**
	 * Provide default templates when the theme hasn't specifically claimed
	 * the Bulletin Library CPTs. We check the resolved template's filename
	 * to detect whether the theme has a CPT-specific override — files like
	 * `single-scbl_service.php`, `archive-scbl_service.php`, or the
	 * plugin-conventional `scbl-service.php` — and defer to them if so.
	 * Otherwise the theme has fallen back to a generic `single.php` /
	 * `index.php` and we substitute a purpose-built template.
	 */
	public function load_template( string $template ): string {
		if ( is_singular( ServiceCPT::POST_TYPE ) ) {
			if ( self::theme_owns_template( $template, [ 'single-' . ServiceCPT::POST_TYPE, 'scbl-service' ] ) ) {
				return $template;
			}
			$plugin = SCBL_PLUGIN_DIR . 'templates/scbl/single-service.php';
			if ( file_exists( $plugin ) ) return $plugin;
		}
		if ( is_post_type_archive( ServiceCPT::POST_TYPE ) ) {
			if ( self::theme_owns_template( $template, [ 'archive-' . ServiceCPT::POST_TYPE, 'archive-scbl-service' ] ) ) {
				return $template;
			}
			$plugin = SCBL_PLUGIN_DIR . 'templates/scbl/archive-service.php';
			if ( file_exists( $plugin ) ) return $plugin;
		}
		return $template;
	}

	/**
	 * Whether the theme (or a page builder) has produced a specific
	 * template for our CPT. Matches on the file's basename - matches any
	 * of the given candidate stems with a .php extension.
	 */
	private static function theme_owns_template( string $template, array $candidate_stems ): bool {
		$base = basename( $template, '.php' );
		return in_array( $base, $candidate_stems, true );
	}

	public function assets(): void {
		$load = is_singular( ServiceCPT::POST_TYPE ) || is_post_type_archive( ServiceCPT::POST_TYPE );

		// Also load on any page whose content includes one of the Sunday
		// shortcodes - otherwise the shortcode's cards render unstyled.
		// This runs on wp_enqueue_scripts (before wp_head), so the CSS
		// lands in <head> where it should be.
		if ( ! $load && is_singular() ) {
			$post = get_post();
			if ( $post && (
				has_shortcode( (string) $post->post_content, 'scbl_services' ) ||
				has_shortcode( (string) $post->post_content, 'scbl_next_service' ) ||
				has_shortcode( (string) $post->post_content, 'scbl_announcements' )
			) ) {
				$load = true;
			}
		}

		if ( $load && ! get_option( 'scbl_disable_frontend_css', false ) ) {
			wp_enqueue_style( 'scbl-main', SCBL_PLUGIN_URL . 'assets/css/scbl-main.css', [ 'seedcast-core' ], SCBL_VERSION );
		}

		// Share button behaviour lives in the shared core script, which core
		// enqueues site wide. Nothing to do here.
	}

	/**
	 * Fallback for shortcode calls that reach the callback without the
	 * asset having been enqueued at wp_enqueue_scripts time - for
	 * example, when the shortcode is rendered from within an Elementor
	 * widget that composes content after wp_head has already run, or
	 * when a theme calls do_shortcode() directly from a template.
	 * Idempotent. Honours the scbl_disable_frontend_css option so a
	 * designer turning styles off still turns them off.
	 */
	private static function ensure_shortcode_assets(): void {
		if ( get_option( 'scbl_disable_frontend_css', false ) ) return;
		wp_enqueue_style( 'scbl-main', SCBL_PLUGIN_URL . 'assets/css/scbl-main.css', [ 'seedcast-core' ], SCBL_VERSION );
	}

	// ─── Shortcodes ────────────────────────────────────────────────────

	public function seedcast_services( $atts ): string {
		self::ensure_shortcode_assets();
		$atts = shortcode_atts( [ 'limit' => '', 'columns' => '3', 'start_date' => '' ], $atts, 'scbl_services' );
		$limit = $atts['limit'] !== '' ? absint( $atts['limit'] ) : absint( get_option( 'scbl_services_per_page', 10 ) );

		$args = [
			'post_type'      => ServiceCPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			// Sunday sorts services by their service date, which is stored
			// as post meta rather than post_date, so the meta_key sort is
			// intrinsic to the plugin's data model.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => ServiceEditor::META_DATE,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		];

		// Optional start_date: hide services dated before this date. Useful
		// for a "current season" widget or when a church wants to keep the
		// long historical archive out of a front-page recap.
		$start_date = trim( (string) $atts['start_date'] );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = [
				[ 'key' => ServiceEditor::META_DATE, 'value' => $start_date, 'compare' => '>=', 'type' => 'DATE' ],
			];
		}

		$q = new \WP_Query( $args );
		if ( ! $q->have_posts() ) return '';

		// Prime the post thumbnail cache in one query, so the per-card
		// has_post_thumbnail() / get_the_post_thumbnail() calls don't
		// each fire their own attachment lookup.
		update_post_thumbnail_cache( $q );

		ob_start();
		$cols = in_array( (int) $atts['columns'], [ 1, 2, 3, 4 ], true ) ? (int) $atts['columns'] : 3;
		echo '<div class="scbl-service-grid scbl-service-grid--cols-' . esc_attr( (string) $cols ) . '">';
		foreach ( $q->posts as $post ) {
			echo wp_kses_post( self::render_service_card( $post ) );
		}
		echo '</div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	public function seedcast_next_service( $atts ): string {
		self::ensure_shortcode_assets();
		$today = gmdate( 'Y-m-d' );
		// phpcs:disable WordPress.DB.SlowDBQuery -- date-based scheduling requires meta_key/meta_query
		$q = new \WP_Query( [
			'post_type'      => ServiceCPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => ServiceEditor::META_DATE,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [
				[ 'key' => ServiceEditor::META_DATE, 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
			],
		] );
		// phpcs:enable WordPress.DB.SlowDBQuery
		if ( ! $q->have_posts() ) return '';
		ob_start();
		echo '<div class="scbl-service-grid scbl-service-grid--cols-1 scbl-service-grid--featured">';
		echo wp_kses_post( self::render_service_card( $q->posts[0], true ) );
		echo '</div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	public function seedcast_announcements( $atts ): string {
		self::ensure_shortcode_assets();
		$atts = shortcode_atts( [ 'columns' => '3' ], $atts, 'scbl_announcements' );
		$today = gmdate( 'Y-m-d' );
		// Currently-active: start <= today AND (end empty OR end >= today).
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$q = new \WP_Query( [
			'post_type'      => AnnouncementCPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => AnnouncementEditor::META_START, 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ],
				[
					'relation' => 'OR',
					[ 'key' => AnnouncementEditor::META_END, 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
					[ 'key' => AnnouncementEditor::META_END, 'value' => '',     'compare' => '=' ],
				],
			],
		] );
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		if ( ! $q->have_posts() ) return '';
		ob_start();
		$cols = in_array( (int) $atts['columns'], [ 1, 2, 3, 4 ], true ) ? (int) $atts['columns'] : 3;
		echo '<div class="scbl-ann-grid scbl-ann-grid--cols-' . esc_attr( (string) $cols ) . '">';
		foreach ( $q->posts as $p ) {
			echo wp_kses_post( AnnouncementSection::render_card( AnnouncementSection::post_to_shape( $p ) ) );
		}
		echo '</div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}


	/**
	 * The service card as used on the archive + shortcodes. Left column is
	 * the date block (SUN / 12 / JUL), right column is a stack of content
	 * lines contributed by suite plugins (Sermon, Praise Reports, etc.)
	 * plus announcement count.
	 */
	public static function render_service_card( \WP_Post $post, bool $featured = false ): string {
		$date = (string) get_post_meta( $post->ID, ServiceEditor::META_DATE, true );
		$ts   = $date ? strtotime( $date ) : 0;
		$dow  = $ts ? strtoupper( gmdate( 'D', $ts ) ) : '';
		$day  = $ts ? gmdate( 'j', $ts ) : '';
		$mon  = $ts ? strtoupper( gmdate( 'M', $ts ) ) : '';

		// Content lines: "Sermon: Title (by Speaker)", "Praise Reports (3)",
		// "Announcements (2)", etc. Collected via a filter so any suite
		// plugin can contribute a line without Sunday knowing about it.
		/**
		 * @var array<int, array{label:string,count?:int,detail?:string}>
		 */
		$lines = apply_filters( 'scbl/service/card_lines', [], $post, $date );

		$title    = get_the_title( $post );
		$date_iso = $date; // stored as Y-m-d already
		$date_long = $ts ? gmdate( 'l, F j, Y', $ts ) : '';

		ob_start();
		?>
		<article class="scbl-service-card <?php echo esc_attr( $featured ? 'scbl-service-card--featured' : '' ); ?>" role="listitem">
			<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="scbl-service-card__link" aria-label="<?php echo esc_attr( sprintf( /* translators: %1$s: service title, %2$s: service date. */ __( '%1$s, %2$s', 'seedcast-bulletin-library' ), $title, $date_long ) ); ?>">
				<div class="scbl-service-card__image">
					<?php if ( has_post_thumbnail( $post ) ) : ?>
						<?php echo get_the_post_thumbnail( $post, 'medium_large', [ 'loading' => 'lazy', 'alt' => '', 'class' => 'scbl-service-card__img' ] ); ?>
					<?php else :
						$fallback_url = self::fallback_image_url();
						if ( $fallback_url ) : ?>
							<img class="scbl-service-card__img scbl-service-card__img--fallback"
							     src="<?php echo esc_url( $fallback_url ); ?>"
							     alt=""
							     loading="lazy" />
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="scbl-service-card__content">
					<div class="scbl-service-card__date" aria-hidden="true">
						<span class="scbl-service-card__dow"><?php echo esc_html( $dow ); ?></span>
						<span class="scbl-service-card__day"><?php echo esc_html( $day ); ?></span>
						<span class="scbl-service-card__mon"><?php echo esc_html( $mon ); ?></span>
					</div>
					<div class="scbl-service-card__body">
						<h3 class="scbl-service-card__title"><?php echo esc_html( $title ); ?></h3>
						<?php if ( $date_iso ) : ?>
							<time class="screen-reader-text" datetime="<?php echo esc_attr( $date_iso ); ?>"><?php echo esc_html( $date_long ); ?></time>
						<?php endif; ?>
						<?php if ( ! empty( $lines ) ) : ?>
							<ul class="scbl-service-card__lines">
								<?php foreach ( $lines as $line ) :
									if ( isset( $line['count'] ) && (int) $line['count'] === 0 ) continue;
									$label = $line['label'] ?? '';
									if ( isset( $line['count'] ) ) {
										$label .= ' (' . (int) $line['count'] . ')';
									}
									?>
									<li><?php echo esc_html( $label ); ?>
										<?php if ( ! empty( $line['detail'] ) ) : ?>
											<span class="scbl-service-card__detail"><?php echo esc_html( $line['detail'] ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</a>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * URL for the image shown when a service has no featured image of its
	 * own. Admin can set a custom default in Sunday Settings; otherwise
	 * the plugin's shipped church PNG is used. Returns an empty string if
	 * the admin has explicitly cleared the setting AND we can't fall back
	 * to the plugin default.
	 */
	public static function fallback_image_url(): string {
		$custom_id = (int) get_option( 'scbl_default_service_image', 0 );
		if ( $custom_id ) {
			$url = wp_get_attachment_image_url( $custom_id, 'medium_large' );
			if ( $url ) return $url;
		}
		return SCBL_PLUGIN_URL . 'assets/img/church-fallback.png';
	}
}
