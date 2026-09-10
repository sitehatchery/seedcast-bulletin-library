<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Service editor: meta boxes for the service date and the announcement
 * copies that appear on this service's page.
 *
 * Each service stores an array of announcement copies in post meta. A copy
 * is a snapshot: once saved, editing or deleting the source announcement
 * doesn't touch it. Any announcement whose date range overlaps this
 * service's week and isn't already copied here shows up as a suggestion
 * at the top of the editor.
 */
class ServiceEditor {

	const NONCE = 'scbl_service_meta';
	const META_DATE        = '_scbl_service_date';
	const META_COPIES      = '_scbl_service_ann_copies';
	const META_VIDEO       = '_scbl_service_video_url';
	const META_DESCRIPTION = '_scbl_service_description';

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_boxes' ], 1 );
		add_action( 'save_post_' . ServiceCPT::POST_TYPE,          [ $this, 'save' ], 10, 2 );
		add_action( 'edit_form_after_title',                       [ $this, 'editor_helper' ] );
		add_filter( 'enter_title_here',                            [ $this, 'title_placeholder' ], 10, 2 );
		add_filter( 'wp_insert_post_data',                         [ $this, 'default_title_from_date' ], 10, 2 );
		// Default layout for users with no stored meta-box order preference.
		add_filter( 'get_user_option_meta-box-order_' . ServiceCPT::POST_TYPE, [ $this, 'default_metabox_order' ] );
	}

	public function default_metabox_order( $order ) {
		if ( ! empty( $order ) && is_array( $order ) ) return $order;
		return [
			'normal'   => 'scbl_service_announcements,scbl_service_programs,scbl_service_handouts',
			'side'     => 'submitdiv,postimagediv,scbl_service_image_position,scbl_service_video',
			'advanced' => '',
		];
	}

	/**
	 * The title field's placeholder text - makes it discoverable that
	 * leaving it blank is fine (the service date will be used).
	 */
	public function title_placeholder( string $text, \WP_Post $post ): string {
		if ( $post->post_type !== ServiceCPT::POST_TYPE ) return $text;
		return __( 'Optional title (e.g. "Grace Week 2"). Leave blank to use the service date.', 'seedcast-bulletin-library' );
	}

	/**
	 * If the admin left the title empty, fill it from the service date
	 * on save so the admin list, browser tab, and permalink all read
	 * nicely. The service still works with a manually-typed title if
	 * the admin wants one.
	 */
	public function default_title_from_date( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== ServiceCPT::POST_TYPE ) return $data;
		$title = trim( (string) ( $data['post_title'] ?? '' ) );
		if ( $title !== '' ) return $data;

		// Prefer the just-submitted date from the meta box; fall back to
		// what's already stored. Nonce-gated so we don't read $_POST on
		// arbitrary saves.
		$date = '';
		$nonce_key = self::NONCE . '_nonce';
		$has_nonce = isset( $_POST[ $nonce_key ] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), self::NONCE );
		if ( $has_nonce && isset( $_POST['scbl_service_date'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_POST['scbl_service_date'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $candidate ) ) $date = $candidate;
		}
		if ( ! $date && ! empty( $postarr['ID'] ) ) {
			$date = (string) get_post_meta( (int) $postarr['ID'], self::META_DATE, true );
		}
		if ( $date ) {
			$ts = strtotime( $date );
			if ( $ts ) {
				$data['post_title'] = gmdate( 'l, F j, Y', $ts );
				$data['post_name']  = ''; // let WordPress regenerate the slug from the new title
			}
		}
		return $data;
	}

	/**
	 * Rendered directly below the title on the Service editor via the
	 * `edit_form_after_title` hook. Holds the Service Date field so it
	 * sits next to the title (a WP meta box can't be positioned between
	 * the title and the meta-box column below), plus the nonce for all
	 * meta box saves.
	 */
	public function editor_helper( \WP_Post $post ): void {
		if ( $post->post_type !== ServiceCPT::POST_TYPE ) return;
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$date = get_post_meta( $post->ID, self::META_DATE, true );
		if ( ! $date && $post->post_status === 'auto-draft' ) {
			$date = Week::anchor( strtotime( 'next sunday' ) );
		}
		?>
		<div class="scbl-service-date">
			<div class="scbl-service-date__row">
				<label for="scbl_service_date" class="scbl-service-date__label"><?php esc_html_e( 'Service Date', 'seedcast-bulletin-library' ); ?></label>
				<input type="date" id="scbl_service_date" name="scbl_service_date" value="<?php echo esc_attr( $date ); ?>" class="scbl-service-date__input" />
			</div>
		</div>
		<?php
	}

	public function register_boxes(): void {
		add_meta_box(
			'scbl_service_image_position',
			__( 'Featured Image Position', 'seedcast-bulletin-library' ),
			[ $this, 'render_image_position' ],
			ServiceCPT::POST_TYPE,
			'side',
			'low'
		);
		add_meta_box(
			'scbl_service_video',
			__( 'Service Video', 'seedcast-bulletin-library' ),
			[ $this, 'render_video' ],
			ServiceCPT::POST_TYPE,
			'side',
			'low'
		);
		add_meta_box(
			'scbl_service_announcements',
			__( "This Week's Announcements", 'seedcast-bulletin-library' ),
			[ $this, 'render_announcements' ],
			ServiceCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Sidebar box: optional video URL. When set, plays on the single
	 * service page in place of the featured image. The featured image
	 * still shows on the archive.
	 *
	 * Any URL the shared VideoEmbed helper understands works here:
	 * YouTube, Vimeo, or a direct MP4 URL. For an uploaded MP4, upload
	 * it via the Media Library and paste its URL.
	 */
	public function render_video( \WP_Post $post ): void {
		$url = (string) get_post_meta( $post->ID, self::META_VIDEO, true );
		?>
		<p class="description scbl-metabox-desc">
			<?php esc_html_e( 'Optional. YouTube, Vimeo, or direct MP4 URL. When set, plays on the service page in place of the featured image. Featured image still appears on the archive.', 'seedcast-bulletin-library' ); ?>
		</p>
		<p>
			<label for="scbl_service_video_url" class="scbl-video-label">
				<?php esc_html_e( 'Video URL', 'seedcast-bulletin-library' ); ?>
			</label>
			<input type="url"
				id="scbl_service_video_url"
				name="scbl_service_video_url"
				value="<?php echo esc_attr( $url ); ?>"
				placeholder="https://www.youtube.com/watch?v=..."
				class="scbl-video-input" />
		</p>
		<?php
	}

	/** Sidebar box directly under the standard Featured Image box, letting
	 *  admins choose where the image renders on the service page. */
	public function render_image_position( \WP_Post $post ): void {
		$pos = (string) get_post_meta( $post->ID, '_scbl_service_image_position', true );
		if ( ! in_array( $pos, [ 'above', 'below', 'none' ], true ) ) $pos = 'above';
		$options = [
			'above' => __( 'Above the overview', 'seedcast-bulletin-library' ),
			'below' => __( 'Below the overview', 'seedcast-bulletin-library' ),
			'none'  => __( 'Do not display', 'seedcast-bulletin-library' ),
		];
		?>
		<p class="description scbl-metabox-desc"><?php esc_html_e( 'Where the Featured Image appears on the service page.', 'seedcast-bulletin-library' ); ?></p>
		<?php foreach ( $options as $val => $label ) : ?>
			<label class="scbl-image-position__option">
				<input type="radio" name="scbl_service_image_position" value="<?php echo esc_attr( $val ); ?>" <?php checked( $pos, $val ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
		<?php
	}

	/** Main column: service date, placed right below the title so admins
	 *  can see the placeholder hint about "leave title blank to use the
	 *  service date" without hunting for the field. */
	/**
	 * Main content box: any active-for-this-week announcements not already
	 * in copies are shown as suggestions the admin can Add or leave off;
	 * copies are editable inline.
	 */
	public function render_announcements( \WP_Post $post ): void {
		$service_date = get_post_meta( $post->ID, self::META_DATE, true );
		$copies       = self::get_copies( $post->ID );
		$is_new       = ( $post->post_status === 'auto-draft' ) || ( empty( $copies ) && empty( $service_date ) );

		if ( ! $service_date ) {
			$service_date = Week::anchor( strtotime( 'next sunday' ) );
		}
		$sunday = Week::anchor( $service_date );

		// New service: pre-populate copies with everything active this week.
		if ( $is_new && empty( $copies ) ) {
			$copies = self::suggestions_for_week( $sunday, [] );
		}

		// Saved service: anything active this week but not in copies is a
		// suggestion the admin can Add individually.
		$suggestions = [];
		if ( ! $is_new ) {
			$copied_ids  = array_column( $copies, 'source_id' );
			$suggestions = self::suggestions_for_week( $sunday, $copied_ids );
		}
		?>

		<?php
		// Payload for the Add/Update buttons in the JS. Keyed by source_id;
		// contains both the suggestion candidates and the current-source data
		// for existing copies. Attached to the enqueued admin script via
		// wp_add_inline_script so no raw <script> tag is emitted here.
		$payload = [];
		if ( ! empty( $suggestions ) ) {
			foreach ( $suggestions as $r ) {
				$payload[ (string) $r['source_id'] ] = $r;
			}
		}
		foreach ( $copies as $c ) {
			$sid = (int) ( $c['source_id'] ?? 0 );
			if ( ! $sid || isset( $payload[ (string) $sid ] ) ) continue;
			$src_post = get_post( $sid );
			if ( ! $src_post || $src_post->post_type !== AnnouncementCPT::POST_TYPE ) continue;
			$payload[ (string) $sid ] = \SeedcastBulletinLibrary\Bulletin\AnnouncementSection::post_to_shape( $src_post );
		}
		if ( ! empty( $payload ) ) {
			wp_add_inline_script(
				'scbl-service-admin',
				'window.scblSuggestionData = Object.assign( window.scblSuggestionData || {}, ' . wp_json_encode( $payload ) . ' );',
				'before'
			);
		}
		?>

		<?php if ( ! empty( $suggestions ) ) : ?>
			<div class="scbl-review-notice">
				<div class="scbl-review-list">
					<?php foreach ( $suggestions as $r ) : ?>
						<div class="scbl-review-item" data-source-id="<?php echo esc_attr( $r['source_id'] ); ?>">
							<div class="scbl-review-item__body">
								<strong><?php echo esc_html( $r['title'] ); ?></strong>
								<?php if ( '' !== $r['schedule_text'] ) : ?>
									<div class="scbl-review-item__date"><?php echo esc_html( $r['schedule_text'] ); ?></div>
								<?php endif; ?>
							</div>
							<button type="button" class="button button-small scbl-review-accept"><?php esc_html_e( 'Add', 'seedcast-bulletin-library' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="scbl-copies" id="scbl-copies">
			<?php foreach ( $copies as $i => $c ) : ?>
				<?php $this->render_copy_row( $i, $c ); ?>
			<?php endforeach; ?>

			<?php if ( empty( $copies ) ) : ?>
				<p class="scbl-copies-empty">
					<?php esc_html_e( 'No announcements on this service yet. Add a suggested one above, or create a new one from the Announcements menu.', 'seedcast-bulletin-library' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Plain-text version of an announcement or program body, for the
	 * read-only rows in the service editor.
	 *
	 * Bodies are stored as HTML (the front end renders them through
	 * wpautop), so printing one through esc_html() shows the raw tags. A
	 * space goes in ahead of line breaks and block closers before the tags
	 * are stripped, so "Midday<br>12:00" reads as "Midday 12:00" rather
	 * than "Midday12:00".
	 */
	public static function preview_text( string $html ): string {
		$html = (string) preg_replace( '#<(?:br|/p|/h[1-6]|/li|/div|/blockquote|/tr|/td)\b[^>]*>#i', ' $0', $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Renders one copy row - read-only display of the frozen snapshot, with
	 * a Remove button and (if the source has changed since this was saved)
	 * an "Update" affordance that pulls current source values in.
	 *
	 * All the copy's fields are round-tripped as hidden inputs so save
	 * preserves the snapshot verbatim. Editing happens only on the source
	 * announcement, and copies get pulled forward via the Update button.
	 */
	private function render_copy_row( int $index, array $c ): void {
		$image_id = (int) ( $c['image_id'] ?? 0 );
		$contact  = is_array( $c['contact'] ?? null ) ? $c['contact'] : [];
		$c_name   = (string) ( $contact['name']  ?? '' );
		$c_email  = (string) ( $contact['email'] ?? '' );
		$c_phone  = (string) ( $contact['phone'] ?? '' );
		$time     = (string) ( $c['time']     ?? '' );
		$location = (string) ( $c['location'] ?? '' );
		$link     = (string) ( $c['link']     ?? '' );
		$preview  = self::preview_text( (string) ( $c['body'] ?? '' ) );
		$when     = isset( $c['schedule'] ) && is_array( $c['schedule'] ) ? Schedule::summary( $c['schedule'], $time ) : $time;

		$diff = self::source_has_changed( $c );
		?>
		<div class="scbl-copy" data-index="<?php echo esc_attr( $index ); ?>" data-source-id="<?php echo esc_attr( $c['source_id'] ?? 0 ); ?>">
			<div class="scbl-copy__row">
				<?php if ( $image_id ) : ?>
					<div class="scbl-copy__image">
						<?php echo wp_get_attachment_image( $image_id, [ 60, 60 ], false, [ 'class' => 'scbl-copy__thumb' ] ); ?>
					</div>
				<?php endif; ?>
				<div class="scbl-copy__body">
					<div class="scbl-copy__title"><?php echo esc_html( $c['title'] ?? '' ); ?></div>
					<?php if ( '' !== $preview ) : ?>
						<div class="scbl-copy__desc"><?php echo esc_html( $preview ); ?></div>
					<?php endif; ?>
					<?php if ( $when || $location ) : ?>
						<div class="scbl-copy__meta">
							<?php if ( $when ) : ?><span><?php echo esc_html( $when ); ?></span><?php endif; ?>
							<?php if ( $when && $location ) : ?> · <?php endif; ?>
							<?php if ( $location ) : ?><span><?php echo esc_html( $location ); ?></span><?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( $c_name || $c_email || $c_phone ) : ?>
						<div class="scbl-copy__meta">
							<?php echo esc_html( trim( $c_name . ' ' . ( $c_email ? '· ' . $c_email : '' ) . ' ' . ( $c_phone ? '· ' . $c_phone : '' ) ) ); ?>
						</div>
					<?php endif; ?>
					<?php if ( $link ) : ?>
						<div class="scbl-copy__meta"><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link ); ?></a></div>
					<?php endif; ?>

					<?php if ( $diff ) : ?>
						<div class="scbl-copy__update-hint">
							<span><?php esc_html_e( 'The announcement has changed since this was saved.', 'seedcast-bulletin-library' ); ?></span>
							<button type="button" class="button button-small scbl-copy-update"><?php esc_html_e( 'Update', 'seedcast-bulletin-library' ); ?></button>
						</div>
					<?php endif; ?>

					<?php // All fields round-trip as hidden inputs so save preserves the snapshot verbatim. ?>
					<input type="hidden" class="scbl-copy-title"          name="scbl_copies[<?php echo esc_attr( $index ); ?>][title]"         value="<?php echo esc_attr( $c['title'] ?? '' ); ?>" />
					<input type="hidden" class="scbl-copy-body"           name="scbl_copies[<?php echo esc_attr( $index ); ?>][body]"          value="<?php echo esc_attr( $c['body']  ?? '' ); ?>" />
					<input type="hidden" class="scbl-copy-time"           name="scbl_copies[<?php echo esc_attr( $index ); ?>][time]"          value="<?php echo esc_attr( $time ); ?>" />
					<input type="hidden" class="scbl-copy-location"       name="scbl_copies[<?php echo esc_attr( $index ); ?>][location]"      value="<?php echo esc_attr( $location ); ?>" />
					<input type="hidden" class="scbl-copy-contact-name"   name="scbl_copies[<?php echo esc_attr( $index ); ?>][contact_name]"  value="<?php echo esc_attr( $c_name ); ?>" />
					<input type="hidden" class="scbl-copy-contact-email"  name="scbl_copies[<?php echo esc_attr( $index ); ?>][contact_email]" value="<?php echo esc_attr( $c_email ); ?>" />
					<input type="hidden" class="scbl-copy-contact-phone"  name="scbl_copies[<?php echo esc_attr( $index ); ?>][contact_phone]" value="<?php echo esc_attr( $c_phone ); ?>" />
					<input type="hidden" class="scbl-copy-link"           name="scbl_copies[<?php echo esc_attr( $index ); ?>][link]"          value="<?php echo esc_attr( $link ); ?>" />
					<input type="hidden" class="scbl-copy-source-id"      name="scbl_copies[<?php echo esc_attr( $index ); ?>][source_id]"     value="<?php echo esc_attr( $c['source_id'] ?? 0 ); ?>" />
					<input type="hidden" class="scbl-copy-image-id"       name="scbl_copies[<?php echo esc_attr( $index ); ?>][image_id]"      value="<?php echo esc_attr( $image_id ); ?>" />
					<input type="hidden" class="scbl-copy-start"          name="scbl_copies[<?php echo esc_attr( $index ); ?>][start]"         value="<?php echo esc_attr( $c['start'] ?? '' ); ?>" />
					<input type="hidden" class="scbl-copy-end"            name="scbl_copies[<?php echo esc_attr( $index ); ?>][end]"           value="<?php echo esc_attr( $c['end']   ?? '' ); ?>" />
					<input type="hidden" class="scbl-copy-schedule"       name="scbl_copies[<?php echo esc_attr( $index ); ?>][schedule]"      value="<?php echo esc_attr( isset( $c['schedule'] ) ? (string) wp_json_encode( $c['schedule'] ) : '' ); ?>" />
				</div>
				<button type="button" class="button-link scbl-copy-remove" aria-label="<?php esc_attr_e( 'Remove', 'seedcast-bulletin-library' ); ?>">&times;</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether the source announcement has diverged from this copy. Returns
	 * false if the source no longer exists (orphaned copies just display
	 * as-is; we don't try to update from a deleted source).
	 */
	private static function source_has_changed( array $c ): bool {
		$source_id = (int) ( $c['source_id'] ?? 0 );
		if ( ! $source_id ) return false;
		$source = get_post( $source_id );
		if ( ! $source || $source->post_type !== AnnouncementCPT::POST_TYPE ) return false;

		$src_contact = get_post_meta( $source_id, AnnouncementEditor::META_CONTACT, true );
		if ( ! is_array( $src_contact ) ) $src_contact = [];

		$copy_contact = is_array( $c['contact'] ?? null ) ? $c['contact'] : [];

		// Compare the source's current featured-image ID against the ID
		// that was submitted at the time this copy was Added. When the
		// admin adds a featured image to the announcement after copying it
		// onto the service, this drives the "changed" notice so they can
		// click Update to bring the image in.
		$src_image = (int) get_post_thumbnail_id( $source_id );
		$copy_image_source = (int) ( $c['image_source_id'] ?? 0 );

		// Copies saved before scheduling existed carry no schedule. Comparing
		// them would flag every one of them as changed, so they are judged on
		// their time alone, as before.
		if ( isset( $c['schedule'] ) && is_array( $c['schedule'] ) && Schedule::get( $source_id ) !== Schedule::sanitize( $c['schedule'] ) ) {
			return true;
		}

		return
			(string) get_the_title( $source ) !== (string) ( $c['title'] ?? '' ) ||
			(string) $source->post_content !== (string) ( $c['body']  ?? '' ) ||
			(string) get_post_meta( $source_id, AnnouncementEditor::META_TIME,     true ) !== (string) ( $c['time']     ?? '' ) ||
			(string) get_post_meta( $source_id, AnnouncementEditor::META_LOC,      true ) !== (string) ( $c['location'] ?? '' ) ||
			(string) get_post_meta( $source_id, AnnouncementEditor::META_LINK,     true ) !== (string) ( $c['link']     ?? '' ) ||
			(string) ( $src_contact['name']  ?? '' ) !== (string) ( $copy_contact['name']  ?? '' ) ||
			(string) ( $src_contact['email'] ?? '' ) !== (string) ( $copy_contact['email'] ?? '' ) ||
			(string) ( $src_contact['phone'] ?? '' ) !== (string) ( $copy_contact['phone'] ?? '' ) ||
			$src_image !== $copy_image_source;
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Service date.
		if ( isset( $_POST['scbl_service_date'] ) ) {
			$date = sanitize_text_field( wp_unslash( $_POST['scbl_service_date'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				update_post_meta( $post_id, self::META_DATE, $date );
			}
		}

		// Featured image position.
		if ( isset( $_POST['scbl_service_image_position'] ) ) {
			$pos = sanitize_text_field( wp_unslash( $_POST['scbl_service_image_position'] ) );
			if ( in_array( $pos, [ 'above', 'below', 'none' ], true ) ) {
				update_post_meta( $post_id, '_scbl_service_image_position', $pos );
			}
		}

		// Video URL. Empty is meaningful (clears an existing URL), so this
		// runs whether the field is empty or not, as long as the field was
		// submitted at all. esc_url_raw strips anything not URL-shaped; the
		// VideoEmbed helper decides at render time whether it's a URL it
		// knows how to embed.
		if ( isset( $_POST['scbl_service_video_url'] ) ) {
			$video = esc_url_raw( wp_unslash( $_POST['scbl_service_video_url'] ) );
			if ( '' === $video ) {
				delete_post_meta( $post_id, self::META_VIDEO );
			} else {
				update_post_meta( $post_id, self::META_VIDEO, $video );
			}
		}

		// Announcement copies.
		$existing_copies = self::get_copies( $post_id );
		// Map existing copies by source_id → image_id so we can preserve
		// already-duplicated attachments rather than re-duplicating on save.
		$existing_by_source = [];
		foreach ( $existing_copies as $ex ) {
			if ( ! empty( $ex['source_id'] ) ) {
				$existing_by_source[ (int) $ex['source_id'] ] = $ex;
			}
		}

		$copies = [];
		if ( isset( $_POST['scbl_copies'] ) && is_array( $_POST['scbl_copies'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- individual fields sanitized below
			$rows = wp_unslash( $_POST['scbl_copies'] );
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) continue;
				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				if ( $title === '' ) continue; // Skip fully-empty rows.

				$source_id       = isset( $row['source_id'] ) ? absint( $row['source_id'] ) : 0;
				$submitted_image = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0;

				// image_id lifecycle:
				//   - Fresh Add: JS submits the source's current featured
				//     image ID. Duplicate it, store the duplicate's ID as
				//     image_id, store the source's ID as image_source_id.
				//   - Existing row unchanged: submitted image_id matches the
				//     duplicate we already have. Preserve everything.
				//   - Update clicked: JS overwrites the hidden image_id with
				//     the source's current featured image ID. That won't
				//     match the existing duplicate, so we re-duplicate here.
				$existing_row     = $existing_by_source[ $source_id ] ?? null;
				$existing_image   = (int) ( $existing_row['image_id']        ?? 0 );
				$existing_src_img = (int) ( $existing_row['image_source_id'] ?? 0 );

				if ( $existing_row && $submitted_image === $existing_image && $existing_image > 0 ) {
					$image_id        = $existing_image;
					$image_source_id = $existing_src_img;
				} elseif ( $submitted_image ) {
					$dup             = self::duplicate_attachment( $submitted_image, $post_id );
					$image_id        = $dup ? $dup : $submitted_image;
					$image_source_id = $submitted_image;
				} else {
					$image_id        = 0;
					$image_source_id = 0;
				}

				$copy = [
					'source_id'       => $source_id,
					'title'           => $title,
					'body'            => isset( $row['body'] )     ? wp_kses_post( $row['body'] ) : '',
					'link'            => isset( $row['link'] )     ? esc_url_raw( $row['link'] ) : '',
					'time'            => isset( $row['time'] )     ? sanitize_text_field( $row['time'] ) : '',
					'location'        => isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '',
					'contact'         => [
						'name'  => isset( $row['contact_name'] )  ? sanitize_text_field( $row['contact_name'] )  : '',
						'email' => isset( $row['contact_email'] ) ? sanitize_email( $row['contact_email'] ) : '',
						'phone' => isset( $row['contact_phone'] ) ? sanitize_text_field( $row['contact_phone'] ) : '',
					],
					'image_id'        => $image_id,
					'image_source_id' => $image_source_id,
					'start'           => isset( $row['start'] )    ? sanitize_text_field( $row['start'] ) : '',
					'end'             => isset( $row['end'] )      ? sanitize_text_field( $row['end'] ) : '',
				];

				// The schedule arrives as JSON in one hidden field; every value in
				// it is cleaned by Schedule::sanitize(). Legacy rows submit nothing
				// here and stay legacy until the admin clicks Update.
				$schedule = isset( $row['schedule'] ) && is_string( $row['schedule'] ) ? json_decode( $row['schedule'], true ) : null;
				if ( is_array( $schedule ) && isset( $schedule['frequency'] ) ) {
					$copy['schedule'] = Schedule::sanitize( $schedule );
				}
				$copies[] = $copy;
			}
		}
		update_post_meta( $post_id, self::META_COPIES, $copies );
	}

	/**
	 * Duplicates a source attachment (featured image on an announcement) into a
	 * new attachment record owned by the service. Same file, new post ID -
	 * so future edits or deletions of the source don't affect the service.
	 *
	 * Returns the new attachment ID, or 0 on failure. Marks the copy with
	 * meta so it can be hidden from the standard media library list.
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
			'post_excerpt'   => $source->post_excerpt, // alt-text convention
			'post_status'    => 'inherit',
			'post_parent'    => $service_post_id,
		], $file );

		if ( is_wp_error( $new_id ) || ! $new_id ) return 0;

		// Reuse the existing generated sizes rather than regenerate - same
		// file, so the metadata is valid for the copy too.
		$meta = wp_get_attachment_metadata( $source_id );
		if ( $meta ) {
			wp_update_attachment_metadata( $new_id, $meta );
		}

		// Preserve alt text (stored as a separate meta on the attachment).
		$alt = get_post_meta( $source_id, '_wp_attachment_image_alt', true );
		if ( $alt ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', $alt );
		}

		// Mark this attachment as a service-copy so we can hide it from
		// the standard Media Library view (see Sunday::hide_copies_from_media).
		update_post_meta( $new_id, '_scbl_service_copy', $service_post_id );

		return (int) $new_id;
	}

	// ─── Public accessors used by Frontend, AnnouncementSection ──────────

	public static function get_copies( int $service_id ): array {
		$c = get_post_meta( $service_id, self::META_COPIES, true );
		return is_array( $c ) ? $c : [];
	}

	/**
	 * All announcements whose date range overlaps the week of $sunday,
	 * minus IDs in $exclude. Returned as suggestion rows (same shape as
	 * copies) so they can be rendered by the same markup.
	 *
	 * @return array<int, array>
	 */
	public static function suggestions_for_week( string $sunday, array $exclude ): array {
		// Excluding already-copied announcements requires post__not_in; the
		// exclude list is bounded by the number of copies on the service.
		// Ordering happens below, once each schedule's first date is known.
		// phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		$posts = get_posts( [
			'post_type'      => AnnouncementCPT::POST_TYPE,
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'post__not_in'   => $exclude,
		] );
		// phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in

		// Prime meta and attachment caches so the per-announcement lookups
		// below hit cache rather than firing individual SELECTs.
		if ( ! empty( $posts ) ) {
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

		$out = [];
		foreach ( $posts as $p ) {
			$start = (string) get_post_meta( $p->ID, '_scbl_ann_start', true );
			$end   = (string) get_post_meta( $p->ID, '_scbl_ann_end',   true );
			// Offered from the week it was published through the week of its
			// last date. The admin approves every suggestion, so offering early
			// costs nothing; a start date in the future is no reason to wait.
			if ( ! Week::range_overlaps_week( substr( $p->post_date, 0, 10 ), $end, $sunday ) ) {
				continue;
			}
			$schedule = Schedule::get( $p->ID );
			$time     = (string) get_post_meta( $p->ID, '_scbl_ann_time', true );
			$contact = get_post_meta( $p->ID, '_scbl_ann_contact', true );
			$image_id = (int) get_post_thumbnail_id( $p->ID );
			$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, [ 60, 60 ] ) : '';
			$out[] = [
				'source_id' => $p->ID,
				'title'     => get_the_title( $p ),
				'body'      => $p->post_content,
				'preview'   => self::preview_text( (string) $p->post_content ),
				'link'      => (string) get_post_meta( $p->ID, '_scbl_ann_link',     true ),
				'time'      => $time,
				'location'  => (string) get_post_meta( $p->ID, '_scbl_ann_location', true ),
				'contact'   => is_array( $contact ) ? $contact : [],
				'image_id'  => $image_id,
				'image_url' => $image_url,
				'start'     => $start,
				'end'       => $end,
				'schedule'      => $schedule,
				'schedule_text' => Schedule::summary( $schedule, $time ),
			];
		}

		// Soonest first; announcements with no dates after the dated ones.
		usort(
			$out,
			static function ( array $a, array $b ): int {
				if ( ( '' === $a['start'] ) !== ( '' === $b['start'] ) ) {
					return '' === $a['start'] ? 1 : -1;
				}
				$by_start = strcmp( $a['start'], $b['start'] );
				return 0 !== $by_start ? $by_start : strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);
		return $out;
	}
}
