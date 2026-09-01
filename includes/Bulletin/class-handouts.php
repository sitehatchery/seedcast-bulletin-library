<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handouts on the Service editor.
 *
 * Handouts are per-service: title, file, and the Program they attach to.
 * They're stored in service post meta and render inline within their
 * Program's card on the front-end (there is no standalone Handouts
 * section). On save, the source attachment is duplicated into an
 * attachment owned by the service, so historical pages don't decay if
 * the source is later removed from the media library.
 */
class Handouts {

	const NONCE       = 'scbl_handouts';
	const META_KEY    = '_scbl_service_handouts';

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_box' ], 1 );
		add_action( 'save_post_' . ServiceCPT::POST_TYPE,          [ $this, 'save' ], 10, 2 );
	}

	public function register_box(): void {
		add_meta_box(
			'scbl_service_handouts',
			__( "Today's Handouts", 'seedcast-bulletin-library' ),
			[ $this, 'render' ],
			ServiceCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$handouts = self::get( $post->ID );

		// Program dropdown draws only from Programs already on this
		// service, so a handout can't reference a Program that isn't
		// rendered.
		$programs = Programs::get_copies( $post->ID );
		?>
		<p class="description scbl-metabox-desc">
			<?php esc_html_e( 'Printable materials for this service. Each handout attaches to a Program and renders alongside that Program on the service page.', 'seedcast-bulletin-library' ); ?>
		</p>

		<div class="scbl-handouts" id="scbl-handouts" data-index="<?php echo esc_attr( (string) count( $handouts ) ); ?>">
			<?php foreach ( $handouts as $i => $h ) : ?>
				<?php $this->render_row( $i, $h, $programs ); ?>
			<?php endforeach; ?>
		</div>

		<p class="scbl-handout__add">
			<button type="button" class="button" id="scbl-handout-add"><?php esc_html_e( '+ Add handout', 'seedcast-bulletin-library' ); ?></button>
		</p>

		<?php // Row template used by the JS to append new rows. ?>
		<template id="scbl-handout-template">
			<?php $this->render_row( '__INDEX__', [], $programs ); ?>
		</template>

		<?php
		// The behavior lives in assets/js/scbl-handouts.js, which is
		// enqueued by the main plugin file on the service editor screen.
		// i18n strings for the media picker are localized here so the JS
		// can pull them without inline PHP.
		wp_localize_script(
			'scbl-handouts',
			'scblHandoutsL10n',
			[
				'pickTitle'    => __( 'Choose handout file', 'seedcast-bulletin-library' ),
				'pickButton'   => __( 'Use this file', 'seedcast-bulletin-library' ),
				'mediaMissing' => __( 'Media library not loaded. Please refresh the page and try again.', 'seedcast-bulletin-library' ),
			]
		);
		?>
		<?php
	}

	/**
	 * @param array<int, array{title:string,source_id:int}> $programs Copies from Today's Programs on this service.
	 */
	private function render_row( $index, array $h, array $programs ): void {
		$title      = (string) ( $h['title']      ?? '' );
		$file_id    = (int)    ( $h['file_id']    ?? 0 );
		$file_nm    = $file_id ? basename( (string) get_attached_file( $file_id ) ) : '';
		$program_id = (int)    ( $h['program_id'] ?? 0 );

		// Saved rows render as read-only display with a delete button.
		// New rows still show the picker form.
		$is_saved = $file_id > 0;
		if ( $is_saved ) :
			// Prefer the frozen program_title on the row; fall back to
			// the current Program's title if the frozen one is empty.
			$program_name = (string) ( $h['program_title'] ?? '' );
			if ( $program_name === '' && $program_id ) {
				foreach ( $programs as $p ) {
					if ( (int) ( $p['source_id'] ?? 0 ) === $program_id ) {
						$program_name = (string) ( $p['title'] ?? '' );
						break;
					}
				}
			}
			?>
			<div class="scbl-handout scbl-handout--saved" data-index="<?php echo esc_attr( (string) $index ); ?>">
				<div class="scbl-handout__body">
					<div class="scbl-handout__title"><?php echo esc_html( $title ?: $file_nm ); ?></div>
					<div class="scbl-handout__meta">
						<span><?php echo esc_html( $file_nm ); ?></span>
						<?php if ( $program_name ) : ?> · <span><?php echo esc_html( $program_name ); ?></span><?php endif; ?>
					</div>
					<input type="hidden" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][title]"         value="<?php echo esc_attr( $title ); ?>" />
					<input type="hidden" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][file_id]"       value="<?php echo esc_attr( (string) $file_id ); ?>" />
					<input type="hidden" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][program_id]"    value="<?php echo esc_attr( (string) $program_id ); ?>" />
					<input type="hidden" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][program_title]" value="<?php echo esc_attr( $program_name ); ?>" />
				</div>
				<button type="button" class="button-link scbl-handout-remove" aria-label="<?php esc_attr_e( 'Remove', 'seedcast-bulletin-library' ); ?>">&times;</button>
			</div>
			<?php
			return;
		endif;
		?>
		<div class="scbl-handout scbl-handout--edit" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div>
				<label class="scbl-handout__label"><?php esc_html_e( 'Title', 'seedcast-bulletin-library' ); ?></label>
				<input type="text" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" class="scbl-handout__input" placeholder="<?php esc_attr_e( 'e.g. Sermon outline', 'seedcast-bulletin-library' ); ?>" />
				<div class="scbl-handout__file-row">
					<label class="scbl-handout__label"><?php esc_html_e( 'File', 'seedcast-bulletin-library' ); ?></label>
					<button type="button" class="button button-small scbl-handout-pick"><?php esc_html_e( 'Choose file', 'seedcast-bulletin-library' ); ?></button>
					<span class="scbl-handout-file-name"><?php echo esc_html( $file_nm ); ?></span>
					<input type="hidden" class="scbl-handout-file-id" name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][file_id]" value="<?php echo esc_attr( (string) $file_id ); ?>" />
				</div>
			</div>
			<div>
				<label class="scbl-handout__label"><?php esc_html_e( 'Program', 'seedcast-bulletin-library' ); ?></label>
				<select name="scbl_handouts[<?php echo esc_attr( (string) $index ); ?>][program_id]" class="scbl-handout__select">
					<option value="0"><?php esc_html_e( '- None -', 'seedcast-bulletin-library' ); ?></option>
					<?php foreach ( $programs as $p ) :
						$pid = (int) ( $p['source_id'] ?? 0 );
						if ( ! $pid ) continue;
						?>
						<option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( $program_id, $pid ); ?>>
							<?php echo esc_html( (string) ( $p['title'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $programs ) ) : ?>
					<p class="description scbl-handout__note">
						<?php esc_html_e( 'Add Programs above first, then link this handout to one of them.', 'seedcast-bulletin-library' ); ?>
					</p>
				<?php endif; ?>
			</div>
			<button type="button" class="button-link scbl-handout-remove scbl-handout-remove--start" aria-label="<?php esc_attr_e( 'Remove', 'seedcast-bulletin-library' ); ?>">&times;</button>
		</div>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Existing rows keyed by source_file_id, so we skip re-duplicating.
		$existing = self::get( $post_id );
		$existing_files_by_source = [];
		foreach ( $existing as $ex ) {
			$src = (int) ( $ex['source_file_id'] ?? 0 );
			if ( $src ) $existing_files_by_source[ $src ] = (int) ( $ex['file_id'] ?? 0 );
		}

		$out = [];
		if ( isset( $_POST['scbl_handouts'] ) && is_array( $_POST['scbl_handouts'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- individual fields sanitized below
			$rows = wp_unslash( $_POST['scbl_handouts'] );

			// Snapshot each Program's title so a later removal from
			// Today's Programs doesn't strip the handout's label.
			$program_titles = [];
			foreach ( Programs::get_copies( $post_id ) as $p ) {
				$sid = (int) ( $p['source_id'] ?? 0 );
				if ( $sid ) $program_titles[ $sid ] = (string) ( $p['title'] ?? '' );
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				$title       = isset( $row['title'] )      ? sanitize_text_field( $row['title'] ) : '';
				$source_file = isset( $row['file_id'] )    ? absint( $row['file_id'] )    : 0;
				$program_id  = isset( $row['program_id'] ) ? absint( $row['program_id'] ) : 0;

				// A handout without a file has nothing to download; skip.
				if ( ! $source_file ) continue;

				// Reuse an already-duplicated attachment if we have one.
				$file_id = 0;
				if ( $source_file ) {
					if ( isset( $existing_files_by_source[ $source_file ] ) ) {
						$file_id = $existing_files_by_source[ $source_file ];
					} else {
						$dup = self::duplicate_attachment( $source_file, $post_id );
						$file_id = $dup ? $dup : $source_file;
					}
				}

				$program_title = $program_titles[ $program_id ] ?? '';
				if ( $program_title === '' && isset( $row['program_title'] ) ) {
					$program_title = sanitize_text_field( $row['program_title'] );
				}

				$out[] = [
					'title'          => $title,
					'file_id'        => $file_id,
					'source_file_id' => $source_file,
					'program_id'     => $program_id,
					'program_title'  => $program_title,
				];
			}
		}
		update_post_meta( $post_id, self::META_KEY, $out );
	}

	/** @return array<int, array{title:string,file_id:int,source_file_id:int,program_id:int,program_title:string}> */
	public static function get( int $service_id ): array {
		$h = get_post_meta( $service_id, self::META_KEY, true );
		return is_array( $h ) ? $h : [];
	}

	/**
	 * Same duplication pattern as ServiceEditor::duplicate_attachment -
	 * copy the attachment record (same file, new post ID) so historical
	 * services keep their handouts if the source is later removed.
	 */
	private static function duplicate_attachment( int $source_id, int $service_post_id ): int {
		$source = get_post( $source_id );
		if ( ! $source || $source->post_type !== 'attachment' ) return 0;
		$file = get_attached_file( $source_id );
		if ( ! $file || ! file_exists( $file ) ) return 0;

		$new_id = wp_insert_attachment( [
			'post_mime_type' => $source->post_mime_type,
			'post_title'     => $source->post_title,
			'post_content'   => $source->post_content,
			'post_excerpt'   => $source->post_excerpt,
			'post_status'    => 'inherit',
			'post_parent'    => $service_post_id,
		], $file );

		if ( is_wp_error( $new_id ) || ! $new_id ) return 0;

		$meta = wp_get_attachment_metadata( $source_id );
		if ( $meta ) wp_update_attachment_metadata( $new_id, $meta );

		update_post_meta( $new_id, '_scbl_service_copy', $service_post_id );
		return (int) $new_id;
	}
}
