<?php
/**
 * Single: Sunday Service.
 *
 * Renders the service header (date + title + optional featured image +
 * service overview), assembles content sections contributed by suite
 * plugins via the `sunday/service/sections` filter, and prints a share
 * block at the end.
 *
 * Section weights by convention:
 *   Programs         5   (Today's Programs)
 *   Sermon           10  (Sermon Library, when installed)
 *   Praise Reports   20  (Praise Report plugin, when installed)
 *   Announcements    40  (This Week's Announcements)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use SeedcastBulletinLibrary\Bulletin\ServiceEditor;
use Seedcast\Core\Frontend\ShareButtons;
use Seedcast\Core\Frontend\Kses;

get_header();

while ( have_posts() ) :
	the_post();
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$scbl_service_date  = (string) get_post_meta( get_the_ID(), ServiceEditor::META_DATE, true );
	$scbl_default_title = $scbl_service_date ? gmdate( 'l, F j, Y', (int) strtotime( $scbl_service_date ) ) : '';
	$scbl_show_date_sub = $scbl_service_date !== '' && get_the_title() !== $scbl_default_title;
	$scbl_date_iso      = $scbl_service_date;
	$scbl_date_label    = $scbl_default_title;

	$scbl_image_pos = (string) get_post_meta( get_the_ID(), '_scbl_service_image_position', true );
	if ( ! in_array( $scbl_image_pos, [ 'above', 'below', 'none' ], true ) ) {
		$scbl_image_pos = 'above';
	}
	$scbl_has_image = has_post_thumbnail();
	$scbl_video_url = trim( (string) get_post_meta( get_the_ID(), ServiceEditor::META_VIDEO, true ) );
	$scbl_has_video = '' !== $scbl_video_url;

	/**
	 * Filter: scbl/service/sections.
	 *
	 * Public API for suite plugins to contribute their own sections to a
	 * service page. Sermon Library uses this to render sermon cards for
	 * sermons dated the same day as the service. Praise Report and Sing
	 * will use it similarly.
	 *
	 * Each returned entry is an associative array:
	 *   [
	 *     'html'   => (string) The section's rendered HTML, escaped.
	 *     'weight' => (int)    Sort order within its region. Lower renders
	 *                          first. Programs are 5, Announcements are 10.
	 *                          Sermons come in at 3.
	 *     'column' => (string) Optional. 'full' | 'main' | 'sidebar'.
	 *                          - 'full' spans the full page width, above
	 *                            the two-column area. Suited to primary
	 *                            content like the sermon.
	 *                          - 'main' (default) is the wider left column
	 *                            of the two-column area. Suited to bulletin
	 *                            content: programs, announcements.
	 *                          - 'sidebar' is the narrower right column.
	 *                            Suited to standalone widgets like a
	 *                            visitor card.
	 *                          On narrow viewports, sidebar collapses
	 *                          below main.
	 *   ]
	 *
	 * @param array   $scbl_sections     Existing section descriptors.
	 * @param WP_Post $post         The service post being rendered.
	 * @param string  $scbl_service_date Y-m-d date of the service.
	 */
	$scbl_sections = apply_filters( 'scbl/service/sections', [], get_post(), $scbl_service_date );
	usort( $scbl_sections, static fn( $a, $b ) => ( $a['weight'] ?? 0 ) <=> ( $b['weight'] ?? 0 ) );

	$scbl_full_sections    = [];
	$scbl_main_sections    = [];
	$scbl_sidebar_sections = [];
	foreach ( $scbl_sections as $s ) {
		$scbl_column = ( isset( $s['column'] ) && in_array( $s['column'], [ 'full', 'main', 'sidebar' ], true ) )
			? $s['column']
			: 'main';
		if ( 'full' === $scbl_column ) {
			$scbl_full_sections[] = $s;
		} elseif ( 'sidebar' === $scbl_column ) {
			$scbl_sidebar_sections[] = $s;
		} else {
			$scbl_main_sections[] = $s;
		}
	}

	// Post content. Legacy description meta is used as fallback so
	// services created before the switch back to post_content still
	// render their overview.
	$scbl_post_content = trim( (string) get_the_content() );
	$scbl_description  = $scbl_post_content;
	if ( $scbl_description === '' ) {
		$scbl_description = trim( (string) get_post_meta( get_the_ID(), ServiceEditor::META_DESCRIPTION, true ) );
	}

	$scbl_archive_url = get_post_type_archive_link( 'scbl_service' );
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	?>
	<main id="scbl-main" class="sc-wrap scbl-single scbl-single--image-<?php echo esc_attr( $scbl_image_pos ); ?>">
		<article <?php post_class( 'scbl-service' ); ?> aria-labelledby="scbl-service-title">
			<header class="scbl-single__header">
				<div class="scbl-single__title-row">
					<h1 id="scbl-service-title" class="scbl-single__title"><?php the_title(); ?></h1>
					<?php if ( $scbl_archive_url ) : ?>
						<a class="scbl-single__browse" href="<?php echo esc_url( $scbl_archive_url ); ?>">
							<?php esc_html_e( 'Browse Weekly Services', 'seedcast-bulletin-library' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<?php if ( $scbl_show_date_sub ) : ?>
					<p class="scbl-single__date">
						<time datetime="<?php echo esc_attr( $scbl_date_iso ); ?>"><?php echo esc_html( $scbl_date_label ); ?></time>
					</p>
				<?php elseif ( $scbl_service_date !== '' ) : ?>
					<?php // Machine-readable date is present even when the visible subhead is suppressed. ?>
					<time class="screen-reader-text" datetime="<?php echo esc_attr( $scbl_date_iso ); ?>"><?php echo esc_html( $scbl_date_label ); ?></time>
				<?php endif; ?>

				<?php if ( $scbl_has_video && $scbl_image_pos === 'above' ) : ?>
					<div class="scbl-single__video scbl-single__video--above">
						<?php \Seedcast\Core\Frontend\VideoEmbed::render( $scbl_video_url, [ 'title' => get_the_title(), 'class' => 'sc-video-wrap' ] ); ?>
					</div>
				<?php elseif ( $scbl_has_image && $scbl_image_pos === 'above' ) : ?>
					<figure class="scbl-single__image scbl-single__image--above">
						<?php the_post_thumbnail( 'large', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
					</figure>
				<?php endif; ?>

				<?php if ( $scbl_has_video && $scbl_image_pos === 'below' ) : ?>
					<div class="scbl-single__video scbl-single__video--below">
						<?php \Seedcast\Core\Frontend\VideoEmbed::render( $scbl_video_url, [ 'title' => get_the_title(), 'class' => 'sc-video-wrap' ] ); ?>
					</div>
				<?php elseif ( $scbl_has_image && $scbl_image_pos === 'below' ) : ?>
					<figure class="scbl-single__image scbl-single__image--below">
						<?php the_post_thumbnail( 'large', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
					</figure>
				<?php endif; ?>
			</header>

			<?php if ( '' !== $scbl_post_content ) : ?>
				<div class="scbl-single__intro"><?php the_content(); ?></div>
			<?php elseif ( '' !== $scbl_description ) : ?>
				<div class="scbl-single__intro"><?php echo wp_kses_post( wpautop( $scbl_description ) ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $scbl_full_sections ) ) : ?>
				<div class="scbl-single__full">
					<?php foreach ( $scbl_full_sections as $s ) : ?>
						<?php echo Kses::section( (string) $s['html'] ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			// Buffer the sidebar. If anything renders into it, add the
			// two-column body modifier; if not, main takes the full width.
			ob_start();
			if ( (bool) get_option( 'scbl_show_visitor_card', 1 ) ) {
				/**
				 * Fires in the sidebar column of a service page. Plugins that
				 * render into the service page sidebar (Visitor Card, etc.)
				 * hook this action. Suppressed when Show Visitor Card is off.
				 *
				 * @param int $post_id The service post ID.
				 */
				do_action( 'scbl_service_sidebar_end', get_the_ID() );
			}
			foreach ( $scbl_sidebar_sections as $s ) {
				echo Kses::section( (string) $s['html'] );
			}
			$scbl_sidebar_html = trim( (string) ob_get_clean() );
			$scbl_has_sidebar  = '' !== $scbl_sidebar_html;
			?>

			<div class="scbl-single__body <?php echo esc_attr( $scbl_has_sidebar ? 'scbl-single__body--has-sidebar' : '' ); ?>">
				<div class="scbl-single__main">
					<div class="scbl-single__sections">
						<?php foreach ( $scbl_main_sections as $s ) : ?>
							<?php echo Kses::section( (string) $s['html'] ); ?>
						<?php endforeach; ?>

						<?php if ( '' === $scbl_description && empty( $scbl_full_sections ) && empty( $scbl_main_sections ) && ! $scbl_has_sidebar ) : ?>
							<p class="sc-empty">
								<?php esc_html_e( "This service page doesn't have content yet.", 'seedcast-bulletin-library' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<footer class="scbl-single__footer">
						<?php ShareButtons::render( get_permalink(), get_the_title() ); ?>
					</footer>
				</div>

				<?php if ( $scbl_has_sidebar ) : ?>
					<aside class="scbl-single__sidebar" aria-label="<?php esc_attr_e( 'Service page sidebar', 'seedcast-bulletin-library' ); ?>">
						<?php
						// Escaped against Kses::section(), which allows the
						// form controls and inline SVG the Visitor Card needs
						// and wp_kses_post() would strip.
						echo Kses::section( $scbl_sidebar_html );
						?>
					</aside>
				<?php endif; ?>
			</div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
