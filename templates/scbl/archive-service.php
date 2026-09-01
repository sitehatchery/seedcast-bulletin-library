<?php
/**
 * Archive: Sunday Services.
 *
 * Grid of service cards, ordered by service date (newest first). Uses
 * the main WP_Query so WordPress's rewrite-based pagination
 * (/sunday/page/2/) works naturally.
 *
 * Heading and intro text come from Sunday Settings so admins can
 * change them without editing templates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use SeedcastBulletinLibrary\Bulletin\Frontend;
use SeedcastBulletinLibrary\Bulletin\ServiceEditor;

get_header();

// Template-local variables, not globals. See single-scbl-service.php.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$scbl_heading = (string) get_option( 'scbl_archive_heading', __( 'Sunday Services', 'seedcast-bulletin-library' ) );
$scbl_intro   = (string) get_option( 'scbl_archive_intro', '' );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<main id="scbl-main" class="sc-wrap scbl-archive">
	<header class="scbl-archive__header">
		<h1 class="scbl-archive__heading"><?php echo esc_html( $scbl_heading ); ?></h1>
		<?php if ( $scbl_intro !== '' ) : ?>
			<p class="scbl-archive__intro"><?php echo esc_html( $scbl_intro ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="scbl-service-grid scbl-service-grid--cols-2" role="list">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php echo wp_kses_post( Frontend::render_service_card( get_post() ) ); ?>
			<?php endwhile; ?>
		</div>

		<?php
		global $wp_query;
		if ( $wp_query->max_num_pages > 1 ) :
		?>
			<nav class="sc-pagination sc-pagination" aria-label="<?php esc_attr_e( 'Services pagination', 'seedcast-bulletin-library' ); ?>">
				<?php
				echo wp_kses_post( paginate_links( [
					'total'   => $wp_query->max_num_pages,
					'current' => max( 1, get_query_var( 'paged' ) ),
				] ) );
				?>
			</nav>
		<?php endif; ?>
	<?php else : ?>
		<p class="sc-empty"><?php esc_html_e( 'No services yet.', 'seedcast-bulletin-library' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
