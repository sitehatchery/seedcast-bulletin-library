<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Announcement section on the service page.
 *
 * Hooks the service-page sections filter and the archive card_lines filter.
 * `render_card` is exposed as a static so the upcoming-activities shortcode
 * can share the same visual card.
 */
class AnnouncementSection {

	public function init(): void {
		add_filter( 'scbl/service/sections',   [ $this, 'section' ], 20, 3 );
		add_filter( 'scbl/service/card_lines', [ $this, 'card_line' ], 20, 3 );
	}

	public function section( array $sections, \WP_Post $service, string $service_date ): array {
		if ( get_option( 'scbl_show_announcements', '1' ) !== '1' ) return $sections;

		$copies = ServiceEditor::get_copies( $service->ID );
		if ( empty( $copies ) ) return $sections;

		ob_start();
		?>
		<section class="scbl-section scbl-section--announcements">
			<h2 class="scbl-section__title"><?php esc_html_e( "This Week's Announcements", 'seedcast-bulletin-library' ); ?></h2>
			<div class="scbl-ann-grid scbl-ann-grid--cols-3">
				<?php foreach ( $copies as $c ) : ?>
					<?php echo self::render_card( $c ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered by helper below ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		$sections[] = [
			'key'    => 'announcements',
			'weight' => 40, // Programs=5, Praise Reports=20, Handouts=30, Announcements=40
			'html'   => (string) ob_get_clean(),
		];
		return $sections;
	}

	public function card_line( array $lines, \WP_Post $service, string $service_date ): array {
		$copies = ServiceEditor::get_copies( $service->ID );
		if ( empty( $copies ) ) return $lines;
		$lines[] = [
			'label' => __( 'Announcements', 'seedcast-bulletin-library' ),
			'count' => count( $copies ),
		];
		return $lines;
	}

	/**
	 * Renders one announcement card from a copy-shaped array. Used by both
	 * the service page section (fed by service copies) and the upcoming
	 * activities shortcode (fed by live announcements normalized to the
	 * same shape).
	 *
	 * Every field is optional and rendered conditionally. Empty fields
	 * simply produce no output - no placeholders, no "Location: TBD."
	 * The card's shape adapts to whatever data is present.
	 *
	 * @param array $c {
	 *     source_id: int, title: str, body: str, link: str,
	 *     time: str, location: str, contact: {name,email,phone},
	 *     image_id: int, start: str, end: str
	 * }
	 */
	public static function render_card( array $c ): string {
		$title    = (string) ( $c['title']    ?? '' );
		$body     = (string) ( $c['body']     ?? '' );
		$link     = (string) ( $c['link']     ?? '' );
		$time     = (string) ( $c['time']     ?? '' );
		$location = (string) ( $c['location'] ?? '' );
		$image_id = (int)    ( $c['image_id'] ?? 0 );
		$contact  = is_array( $c['contact'] ?? null ) ? $c['contact'] : [];
		$c_name   = (string) ( $contact['name']  ?? '' );
		$c_email  = (string) ( $contact['email'] ?? '' );
		$c_phone  = (string) ( $contact['phone'] ?? '' );

		$has_details = $time || $location || $c_name || $c_email || $c_phone;

		ob_start();
		?>
		<article class="scbl-ann-card">
			<?php if ( $image_id ) : ?>
				<div class="scbl-ann-card__image">
					<?php echo wp_get_attachment_image( $image_id, 'medium', false, [ 'class' => 'scbl-ann-card__img' ] ); ?>
				</div>
			<?php endif; ?>
			<div class="scbl-ann-card__body">
				<?php if ( $title ) : ?>
					<h3 class="scbl-ann-card__title"><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
				<?php if ( $body ) : ?>
					<div class="scbl-ann-card__desc"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
				<?php endif; ?>
				<?php if ( $has_details ) : ?>
					<ul class="scbl-ann-card__details">
						<?php if ( $time ) : ?>
							<li class="scbl-ann-card__detail scbl-ann-card__detail--time">
								<span class="scbl-ann-card__icon" aria-hidden="true">⏱</span>
								<span><?php echo esc_html( $time ); ?></span>
							</li>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<li class="scbl-ann-card__detail scbl-ann-card__detail--location">
								<span class="scbl-ann-card__icon" aria-hidden="true">📍</span>
								<span><?php echo esc_html( $location ); ?></span>
							</li>
						<?php endif; ?>
						<?php if ( $c_name || $c_email || $c_phone ) : ?>
							<li class="scbl-ann-card__detail scbl-ann-card__detail--contact">
								<span class="scbl-ann-card__icon" aria-hidden="true">👤</span>
								<span>
									<?php if ( $c_name ) echo esc_html( $c_name ); ?>
									<?php if ( $c_email ) : ?>
										<?php echo esc_html( $c_name ? ' · ' : '' ); ?>
										<a href="mailto:<?php echo esc_attr( $c_email ); ?>"><?php echo esc_html( $c_email ); ?></a>
									<?php endif; ?>
									<?php if ( $c_phone ) : ?>
										<?php echo ( $c_name || $c_email ) ? ' · ' : ''; ?>
										<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $c_phone ) ); ?>"><?php echo esc_html( $c_phone ); ?></a>
									<?php endif; ?>
								</span>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $link ) : ?>
					<p class="scbl-ann-card__link-wrap">
						<a class="scbl-ann-card__link" href="<?php echo esc_url( $link ); ?>">
							<?php esc_html_e( 'Event link', 'seedcast-bulletin-library' ); ?> <span aria-hidden="true">→</span>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Normalizes a live Announcement post into the copy-shape array that
	 * render_card() expects. Used by the upcoming-activities shortcode
	 * which draws from live announcements rather than service copies.
	 */
	public static function post_to_shape( \WP_Post $p ): array {
		$contact  = get_post_meta( $p->ID, AnnouncementEditor::META_CONTACT, true );
		$image_id = (int) get_post_thumbnail_id( $p->ID );
		return [
			'source_id' => $p->ID,
			'title'     => get_the_title( $p ),
			'body'      => $p->post_content,
			'link'      => (string) get_post_meta( $p->ID, AnnouncementEditor::META_LINK,     true ),
			'time'      => (string) get_post_meta( $p->ID, AnnouncementEditor::META_TIME,     true ),
			'location'  => (string) get_post_meta( $p->ID, AnnouncementEditor::META_LOC,      true ),
			'contact'   => is_array( $contact ) ? $contact : [],
			'image_id'  => $image_id,
			'image_url' => $image_id ? (string) wp_get_attachment_image_url( $image_id, [ 60, 60 ] ) : '',
			'start'     => (string) get_post_meta( $p->ID, AnnouncementEditor::META_START,    true ),
			'end'       => (string) get_post_meta( $p->ID, AnnouncementEditor::META_END,      true ),
		];
	}
}
