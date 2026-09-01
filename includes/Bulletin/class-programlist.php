<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin list customization for Programs. Adds a brief note above the
 * list explaining what Programs are for, since it's a new concept in
 * the plugin and the CPT name alone doesn't explain the intent.
 */
class ProgramList {

	public function init(): void {
		add_action( 'admin_notices', [ $this, 'intro_note' ] );
	}

	public function intro_note(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) return;
		if ( $screen->post_type !== ProgramCPT::POST_TYPE ) return;
		if ( $screen->base !== 'edit' ) return;
		?>
		<div class="notice notice-info inline scbl-notice-inline">
			<p><strong><?php esc_html_e( 'What are Programs?', 'seedcast-bulletin-library' ); ?></strong> <?php esc_html_e( 'The parallel experiences happening at your gathering - for example: Sunday School, Youth Program, Main Service, Kids Church.', 'seedcast-bulletin-library' ); ?></p>
		</div>
		<?php
	}
}
