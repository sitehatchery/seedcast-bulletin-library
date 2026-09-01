<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders the Today's Programs section on a service page. Sits at weight 5
 * so it's the first section - Programs answers "what's happening today?"
 * which is the primary question a visitor is asking. Everything else
 * (praise reports, announcements, handouts) is secondary context.
 *
 * Reads copies from the service's meta rather than querying live Program
 * posts, so historical services show the frozen snapshot as saved.
 */
class ProgramSection {

	public function init(): void {
		add_filter( 'scbl/service/sections',   [ $this, 'section' ], 5, 3 );
		add_filter( 'scbl/service/card_lines', [ $this, 'card_line' ], 5, 3 );
	}

	public function section( array $sections, \WP_Post $service, string $service_date ): array {
		$copies = Programs::get_copies( $service->ID );

		// Bucket the service's handouts by the Program they attach to.
		// Any handout whose program_id doesn't map to a Program on this
		// service becomes an "orphan" and renders in its own section
		// below Programs so it doesn't disappear silently.
		$handouts_by_program = [];
		$orphan_handouts     = [];
		$valid_program_ids   = [];
		foreach ( $copies as $p ) {
			$sid = (int) ( $p['source_id'] ?? 0 );
			if ( $sid ) $valid_program_ids[ $sid ] = true;
		}
		foreach ( Handouts::get( $service->ID ) as $h ) {
			$pid = (int) ( $h['program_id'] ?? 0 );
			if ( $pid && isset( $valid_program_ids[ $pid ] ) ) {
				$handouts_by_program[ $pid ][] = $h;
			} else {
				$orphan_handouts[] = $h;
			}
		}

		if ( ! empty( $copies ) ) {
			ob_start();
			?>
			<section class="scbl-section scbl-section--programs">
				<h2 class="scbl-section__title"><?php esc_html_e( "Today's Programs", 'seedcast-bulletin-library' ); ?></h2>
				<ul class="scbl-program-list">
					<?php foreach ( $copies as $p ) :
						$pid = (int) ( $p['source_id'] ?? 0 );
						$program_handouts = $handouts_by_program[ $pid ] ?? [];
						?>
						<?php $this->render_item( $p, $program_handouts ); ?>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php
			$sections[] = [
				'key'    => 'programs',
				'weight' => 5,
				'html'   => (string) ob_get_clean(),
			];
		}

		if ( ! empty( $orphan_handouts ) ) {
			ob_start();
			?>
			<section class="scbl-section scbl-section--handouts">
				<h2 class="scbl-section__title"><?php esc_html_e( 'Handouts', 'seedcast-bulletin-library' ); ?></h2>
				<ul class="scbl-handout-list">
					<?php foreach ( $orphan_handouts as $h ) :
						$fid = (int) ( $h['file_id'] ?? 0 );
						if ( ! $fid ) continue;
						$url = wp_get_attachment_url( $fid );
						if ( ! $url ) continue;
						$label = (string) ( $h['title'] ?? '' );
						if ( $label === '' ) $label = (string) basename( (string) get_attached_file( $fid ) );
						?>
						<li class="scbl-handout-list__item">
							<a class="scbl-program__handout-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php
			$sections[] = [
				'key'    => 'handouts',
				'weight' => 6, // Right after Programs.
				'html'   => (string) ob_get_clean(),
			];
		}

		return $sections;
	}

	public function card_line( array $lines, \WP_Post $service, string $service_date ): array {
		$copies = Programs::get_copies( $service->ID );
		if ( empty( $copies ) ) return $lines;
		$lines[] = [
			'label' => __( 'Programs', 'seedcast-bulletin-library' ),
			'count' => count( $copies ),
		];
		return $lines;
	}

	/**
	 * @param array $p Program copy shape.
	 * @param array $handouts Handout rows attached to this Program.
	 */
	private function render_item( array $p, array $handouts = [] ): void {
		$title    = (string) ( $p['title']    ?? '' );
		$body     = (string) ( $p['body']     ?? '' );
		$time     = (string) ( $p['time']     ?? '' );
		$link     = (string) ( $p['link']     ?? '' );
		$image_id = (int)    ( $p['image_id'] ?? 0 );
		if ( ! $title ) return;
		?>
		<li class="scbl-program">
			<div class="scbl-program__inner">
				<?php if ( $image_id ) : ?>
					<div class="scbl-program__image">
						<?php echo wp_get_attachment_image( $image_id, [ 80, 80 ], false, [ 'loading' => 'lazy' ] ); ?>
					</div>
				<?php endif; ?>
				<div class="scbl-program__body">
					<div class="scbl-program__head">
						<h3 class="scbl-program__title">
							<?php if ( $link ) : ?>
								<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $title ); ?>
							<?php endif; ?>
						</h3>
						<?php if ( $time ) : ?>
							<span class="scbl-program__time"><?php echo esc_html( $time ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $body ) : ?>
						<div class="scbl-program__desc"><?php echo wp_kses_post( wpautop( do_shortcode( $body ) ) ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( ! empty( $handouts ) ) : ?>
				<div class="scbl-program__handouts">
					<div class="scbl-program__handouts-label"><?php esc_html_e( 'Handouts:', 'seedcast-bulletin-library' ); ?></div>
					<div class="scbl-program__handout-links">
						<?php
						foreach ( $handouts as $h ) {
							$fid = (int) ( $h['file_id'] ?? 0 );
							if ( ! $fid ) continue;
							$url = wp_get_attachment_url( $fid );
							if ( ! $url ) continue;
							$label = (string) ( $h['title'] ?? '' );
							if ( $label === '' ) $label = (string) basename( (string) get_attached_file( $fid ) );
							echo '<a class="scbl-program__handout-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>
		</li>
		<?php
	}
}
