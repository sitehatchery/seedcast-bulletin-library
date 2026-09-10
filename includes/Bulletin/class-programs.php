<?php
namespace SeedcastBulletinLibrary\Bulletin;


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Programs on the Service editor.
 *
 * Each service carries frozen copies of Program CPT records taken at save
 * time. Source changes surface an "Update?" affordance on diverged copies
 * rather than silently rewriting history. Storage is service post meta
 * (`_sunday_service_programs`), an ordered array whose sequence is the
 * front-end display order.
 *
 * On new services, all currently-published Programs are offered as copies,
 * since most churches run the same programs every week.
 */
class Programs {

	const NONCE    = 'scbl_programs';
	const META_KEY = '_scbl_service_programs';

	public function init(): void {
		add_action( 'add_meta_boxes',                              [ $this, 'register_box' ], 1 );
		add_action( 'save_post_' . ServiceCPT::POST_TYPE,          [ $this, 'save' ], 10, 2 );
	}

	public function register_box(): void {
		add_meta_box(
			'scbl_service_programs',
			__( "Today's Programs", 'seedcast-bulletin-library' ),
			[ $this, 'render' ],
			ServiceCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$copies = self::get_copies( $post->ID );
		$is_new = ( $post->post_status === 'auto-draft' ) || empty( $copies );

		// New service: start with everything currently published.
		if ( $is_new && empty( $copies ) ) {
			$copies = self::all_current_program_shapes();
		}

		// Anything published but not in copies is an Add candidate.
		$copied_source_ids = [];
		foreach ( $copies as $c ) {
			$sid = (int) ( $c['source_id'] ?? 0 );
			if ( $sid ) $copied_source_ids[] = $sid;
		}
		$suggestions = self::suggestions( $copied_source_ids );

		// Source data for Update buttons and Add buttons. Keyed by source_id.
		$source_data = [];
		foreach ( $copies as $c ) {
			$sid = (int) ( $c['source_id'] ?? 0 );
			if ( ! $sid || isset( $source_data[ $sid ] ) ) continue;
			$src = get_post( $sid );
			if ( ! $src || $src->post_type !== ProgramCPT::POST_TYPE ) continue;
			$source_data[ (string) $sid ] = self::program_to_shape( $src );
		}
		foreach ( $suggestions as $s ) {
			$sid = (int) ( $s['source_id'] ?? 0 );
			if ( $sid ) $source_data[ (string) $sid ] = $s;
		}
		?>

		<p class="description scbl-metabox-desc">
			<?php esc_html_e( 'Examples: Sunday School, Youth Program, Main Service.', 'seedcast-bulletin-library' ); ?>
		</p>

		<?php
		if ( ! empty( $source_data ) ) {
			wp_add_inline_script(
				'scbl-service-admin',
				'window.scblProgramSourceData = Object.assign( window.scblProgramSourceData || {}, ' . wp_json_encode( $source_data ) . ' );',
				'before'
			);
		}
		?>

		<?php if ( ! empty( $suggestions ) ) : ?>
			<div class="scbl-program-suggestions">
				<div class="scbl-program-suggestion-list">
					<?php foreach ( $suggestions as $s ) : ?>
						<div class="scbl-program-suggestion" data-source-id="<?php echo esc_attr( (string) $s['source_id'] ); ?>">
							<div class="scbl-program-suggestion__body">
								<strong><?php echo esc_html( $s['title'] ); ?></strong>
								<?php if ( ! empty( $s['time'] ) ) : ?>
									<span class="scbl-program-suggestion__time"><?php echo esc_html( $s['time'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $s['body'] ) ) : ?>
									<div class="scbl-program-suggestion__desc"><?php echo esc_html( wp_trim_words( $s['body'], 25 ) ); ?></div>
								<?php endif; ?>
							</div>
							<button type="button" class="button button-small scbl-program-suggestion-accept"><?php esc_html_e( 'Add', 'seedcast-bulletin-library' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="scbl-programs" id="scbl-programs">
			<?php foreach ( $copies as $i => $c ) : ?>
				<?php $this->render_row( $i, $c ); ?>
			<?php endforeach; ?>

			<?php if ( empty( $copies ) ) : ?>
				<p class="scbl-programs-empty">
					<?php esc_html_e( 'No programs configured yet. Add some from the Programs menu, then they\'ll appear here for each service.', 'seedcast-bulletin-library' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Published Programs not in the exclude list. Used to build the
	 * "Add these Programs?" suggestion strip on the service editor.
	 *
	 * @param array<int> $exclude Source IDs already represented on this service.
	 * @return array<int, array>
	 */
	public static function suggestions( array $exclude ): array {
		$posts = get_posts( [
			'post_type'    => ProgramCPT::POST_TYPE,
			'post_status'  => 'publish',
			'numberposts'  => -1,
			'orderby'      => 'menu_order title',
			'order'        => 'ASC',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			'post__not_in' => $exclude,
		] );
		self::prime_meta_cache( $posts );
		$out = [];
		foreach ( $posts as $p ) {
			$out[] = self::program_to_shape( $p );
		}
		return $out;
	}

	/**
	 * Prime the meta and attachment caches for a batch of program posts, so
	 * the per-post get_post_meta and thumbnail lookups downstream hit the
	 * object cache rather than each firing their own SELECT.
	 *
	 * @param array<int, \WP_Post> $posts
	 */
	private static function prime_meta_cache( array $posts ): void {
		if ( empty( $posts ) ) return;
		$ids = array_map( static fn( $p ) => (int) $p->ID, $posts );
		update_meta_cache( 'post', $ids );
		$thumb_ids = [];
		foreach ( $ids as $id ) {
			$t = (int) get_post_thumbnail_id( $id );
			if ( $t ) $thumb_ids[] = $t;
		}
		if ( ! empty( $thumb_ids ) ) {
			_prime_post_caches( $thumb_ids, false, true );
		}
	}

	private function render_row( int $index, array $c ): void {
		$title    = (string) ( $c['title']    ?? '' );
		$body     = (string) ( $c['body']     ?? '' );
		$preview  = ServiceEditor::preview_text( $body );
		$time     = (string) ( $c['time']     ?? '' );
		$link     = (string) ( $c['link']     ?? '' );
		$image_id = (int)    ( $c['image_id'] ?? 0 );
		$diff     = self::source_has_changed( $c );
		?>
		<div class="scbl-program-row" data-index="<?php echo esc_attr( (string) $index ); ?>" data-source-id="<?php echo esc_attr( (string) ( $c['source_id'] ?? 0 ) ); ?>">
			<div class="scbl-program-row__row">
				<?php if ( $image_id ) : ?>
					<div class="scbl-program-row__image">
						<?php echo wp_get_attachment_image( $image_id, [ 48, 48 ], false, [ 'class' => 'scbl-program-row__thumb' ] ); ?>
					</div>
				<?php endif; ?>
				<div class="scbl-program-row__body">
					<div class="scbl-program-row__title-row">
						<strong class="scbl-program-row__title"><?php echo esc_html( $title ); ?></strong>
						<?php if ( $time ) : ?>
							<span class="scbl-program-row__time"><?php echo esc_html( $time ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $preview ) : ?>
						<div class="scbl-program-row__desc"><?php echo esc_html( $preview ); ?></div>
					<?php endif; ?>
					<?php if ( $link ) : ?>
						<div class="scbl-program-row__meta"><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link ); ?></a></div>
					<?php endif; ?>

					<?php if ( $diff ) : ?>
						<div class="scbl-program-diff scbl-copy__update-hint">
							<span><?php esc_html_e( 'The program has changed since this was saved.', 'seedcast-bulletin-library' ); ?></span>
							<button type="button" class="button button-small scbl-program-update"><?php esc_html_e( 'Update', 'seedcast-bulletin-library' ); ?></button>
						</div>
					<?php endif; ?>

					<input type="hidden" class="scbl-program-title"     name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][title]"     value="<?php echo esc_attr( $title ); ?>" />
					<input type="hidden" class="scbl-program-body"      name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][body]"      value="<?php echo esc_attr( $body ); ?>" />
					<input type="hidden" class="scbl-program-time"      name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][time]"      value="<?php echo esc_attr( $time ); ?>" />
					<input type="hidden" class="scbl-program-link"      name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][link]"      value="<?php echo esc_attr( $link ); ?>" />
					<input type="hidden" class="scbl-program-image-id"  name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][image_id]"  value="<?php echo esc_attr( (string) $image_id ); ?>" />
					<input type="hidden" class="scbl-program-source-id" name="scbl_programs[<?php echo esc_attr( (string) $index ); ?>][source_id]" value="<?php echo esc_attr( (string) ( $c['source_id'] ?? 0 ) ); ?>" />
				</div>
				<button type="button" class="button-link scbl-program-remove" aria-label="<?php esc_attr_e( 'Remove', 'seedcast-bulletin-library' ); ?>">&times;</button>
			</div>
		</div>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Existing copies indexed by source_id → image_id, so we don't
		// re-duplicate an attachment we've already snapshotted.
		$existing = self::get_copies( $post_id );
		$existing_images_by_source = [];
		foreach ( $existing as $ex ) {
			$sid = (int) ( $ex['source_id'] ?? 0 );
			$img = (int) ( $ex['image_id']  ?? 0 );
			if ( $sid && $img ) $existing_images_by_source[ $sid ] = $img;
		}

		$out = [];
		if ( isset( $_POST['scbl_programs'] ) && is_array( $_POST['scbl_programs'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- individual fields sanitized below
			$rows = wp_unslash( $_POST['scbl_programs'] );
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				if ( $title === '' ) continue;

				$source_id       = isset( $row['source_id'] ) ? absint( $row['source_id'] ) : 0;
				$submitted_image = isset( $row['image_id'] )  ? absint( $row['image_id'] )  : 0;

				// Attachment duplication: same behavior as announcement copies.
				// If we've already snapshotted this source's image, keep the
				// existing copy; otherwise duplicate the source's attachment.
				$image_id = 0;
				if ( $submitted_image ) {
					if ( isset( $existing_images_by_source[ $source_id ] ) && $existing_images_by_source[ $source_id ] === $submitted_image ) {
						$image_id = $submitted_image;
					} else {
						$dup = self::duplicate_attachment( $submitted_image, $post_id );
						$image_id = $dup ? $dup : $submitted_image;
					}
				}

				$out[] = [
					'title'     => $title,
					'body'      => isset( $row['body'] ) ? wp_kses_post( $row['body'] ) : '',
					'time'      => isset( $row['time'] ) ? sanitize_text_field( $row['time'] ) : '',
					'link'      => isset( $row['link'] ) ? esc_url_raw( $row['link'] ) : '',
					'image_id'  => $image_id,
					'source_id' => $source_id,
				];
			}
		}
		update_post_meta( $post_id, self::META_KEY, $out );
	}

	/** @return array<int, array{title:string,body:string,time:string,link:string,image_id:int,source_id:int}> */
	public static function get_copies( int $service_id ): array {
		$copies = get_post_meta( $service_id, self::META_KEY, true );
		return is_array( $copies ) ? $copies : [];
	}

	/**
	 * Returns copy-shaped arrays for every currently-published Program.
	 * Used to pre-populate a new service's program list.
	 *
	 * @return array<int, array{title:string,body:string,time:string,link:string,image_id:int,source_id:int}>
	 */
	private static function all_current_program_shapes(): array {
		$posts = get_posts( [
			'post_type'   => ProgramCPT::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order title',
			'order'       => 'ASC',
		] );
		self::prime_meta_cache( $posts );
		$out = [];
		foreach ( $posts as $p ) {
			$out[] = self::program_to_shape( $p );
		}
		return $out;
	}

	/** Extracts a Program post into the copy shape used everywhere. */
	public static function program_to_shape( \WP_Post $p ): array {
		$image_id = (int) get_post_thumbnail_id( $p->ID );
		return [
			'title'     => (string) get_the_title( $p ),
			'body'      => (string) $p->post_content,
			'preview'   => ServiceEditor::preview_text( (string) $p->post_content ),
			'time'      => (string) get_post_meta( $p->ID, ProgramEditor::META_TIME, true ),
			'link'      => (string) get_post_meta( $p->ID, ProgramEditor::META_LINK, true ),
			'image_id'  => $image_id,
			'image_url' => $image_id ? (string) wp_get_attachment_image_url( $image_id, [ 48, 48 ] ) : '',
			'source_id' => (int) $p->ID,
		];
	}

	/**
	 * Whether the source Program has diverged from this copy. Returns false
	 * if the source no longer exists.
	 */
	private static function source_has_changed( array $c ): bool {
		$sid = (int) ( $c['source_id'] ?? 0 );
		if ( ! $sid ) return false;
		$src = get_post( $sid );
		if ( ! $src || $src->post_type !== ProgramCPT::POST_TYPE ) return false;

		// Compare bodies through wp_kses_post on both sides so a legacy
		// copy that was stored via sanitize_textarea_field (which strips
		// HTML) can be re-synced with a single Update click.
		return
			(string) get_the_title( $src )                                    !== (string) ( $c['title'] ?? '' ) ||
			(string) wp_kses_post( $src->post_content )                        !== (string) ( $c['body']  ?? '' ) ||
			(string) get_post_meta( $sid, ProgramEditor::META_TIME, true )    !== (string) ( $c['time']  ?? '' ) ||
			(string) get_post_meta( $sid, ProgramEditor::META_LINK, true )    !== (string) ( $c['link']  ?? '' );
	}

	/**
	 * Duplicate an attachment for snapshot preservation. Same pattern used
	 * for announcement featured images and handout files - the copy owns
	 * its own attachment record so historical services stay stable even
	 * if the source's media is later removed.
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
