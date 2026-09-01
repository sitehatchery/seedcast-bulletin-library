<?php
namespace SeedcastBulletinLibrary\Bulletin;


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta box for the source Program: time (freeform text) and link (optional
 * URL to an existing page on the church's site).
 *
 * Description lives in the post_content editor, title in the post_title.
 * Featured image via WordPress's standard thumbnail box. Everything else
 * is one small meta box.
 */
class ProgramEditor {

	const NONCE      = 'scbl_program_meta';
	const META_TIME  = '_scbl_program_time';
	const META_LINK  = '_scbl_program_link';

	public function init(): void {
		add_action( 'add_meta_boxes',                          [ $this, 'register_box' ], 1 );
		add_action( 'save_post_' . ProgramCPT::POST_TYPE,      [ $this, 'save' ], 10, 2 );
		add_action( 'edit_form_top',                           [ $this, 'examples_banner' ] );
	}

	/**
	 * Small helper banner above the title field on the Program edit screen.
	 * Keeps the "what are Programs for?" cue visible at the top of the
	 * editor rather than tucked below in the details meta box.
	 */
	public function examples_banner( \WP_Post $post ): void {
		if ( $post->post_type !== ProgramCPT::POST_TYPE ) return;
		?>
		<div class="notice notice-info inline scbl-notice-inline">
			<p><strong><?php esc_html_e( 'Examples:', 'seedcast-bulletin-library' ); ?></strong> <?php esc_html_e( 'Sunday School, Youth Program, Main Service, Kids Church.', 'seedcast-bulletin-library' ); ?></p>
		</div>
		<?php
	}

	public function register_box(): void {
		add_meta_box(
			'scbl_program_details',
			__( 'Program details', 'seedcast-bulletin-library' ),
			[ $this, 'render' ],
			ProgramCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$time = (string) get_post_meta( $post->ID, self::META_TIME, true );
		$link = (string) get_post_meta( $post->ID, self::META_LINK, true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="scbl_program_time"><?php esc_html_e( 'Time', 'seedcast-bulletin-library' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="scbl_program_time" name="scbl_program_time" value="<?php echo esc_attr( $time ); ?>" placeholder="<?php esc_attr_e( 'e.g. 9:00am, or "during main service"', 'seedcast-bulletin-library' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="scbl_program_link"><?php esc_html_e( 'Link', 'seedcast-bulletin-library' ); ?></label></th>
				<td>
					<input type="url" class="regular-text" id="scbl_program_link" name="scbl_program_link" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" />
					<p class="description"><?php esc_html_e( 'Optional. Link to an existing page on your site about this program.', 'seedcast-bulletin-library' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( isset( $_POST['scbl_program_time'] ) ) {
			update_post_meta( $post_id, self::META_TIME, sanitize_text_field( wp_unslash( $_POST['scbl_program_time'] ) ) );
		}
		if ( isset( $_POST['scbl_program_link'] ) ) {
			update_post_meta( $post_id, self::META_LINK, esc_url_raw( wp_unslash( $_POST['scbl_program_link'] ) ) );
		}
	}
}
