<?php
namespace SeedcastBulletinLibrary\Bulletin;


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta boxes for the source Announcement.
 *
 * Display period (start / end / ongoing) controls which Sundays this
 * announcement shows on. Event details (time, location, contact, link)
 * are optional and render on the card only when populated. Contact
 * fields autocomplete from the shared Contacts store and upsert back
 * to it on save.
 */
class AnnouncementEditor {

	const NONCE        = 'scbl_ann_meta';
	const META_START   = '_scbl_ann_start';
	const META_END     = '_scbl_ann_end';
	const META_LINK    = '_scbl_ann_link';
	const META_TIME    = '_scbl_ann_time';
	const META_LOC     = '_scbl_ann_location';
	const META_CONTACT = '_scbl_ann_contact';

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_boxes' ], 1 );
		add_action( 'save_post_' . AnnouncementCPT::POST_TYPE,         [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts',                           [ $this, 'autocomplete_data' ] );
	}

	public function register_boxes(): void {
		add_meta_box(
			'scbl_ann_display',
			__( 'Display period', 'seedcast-bulletin-library' ),
			[ $this, 'render_display' ],
			AnnouncementCPT::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'scbl_ann_event',
			__( 'Event details (optional)', 'seedcast-bulletin-library' ),
			[ $this, 'render_event' ],
			AnnouncementCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Sidebar box #1: display period. */
	public function render_display( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$start   = (string) get_post_meta( $post->ID, self::META_START, true );
		$end     = (string) get_post_meta( $post->ID, self::META_END,   true );
		$ongoing = ( $end === '' && $start !== '' );
		if ( ! $start && $post->post_status === 'auto-draft' ) {
			$start = gmdate( 'Y-m-d' );
		}
		?>
		<p>
			<label for="scbl_ann_start"><strong><?php esc_html_e( 'Start date', 'seedcast-bulletin-library' ); ?></strong></label><br />
			<input type="date" id="scbl_ann_start" name="scbl_ann_start" value="<?php echo esc_attr( $start ); ?>" class="scbl-ann-date-input" />
		</p>
		<p>
			<label>
				<input type="checkbox" id="scbl_ann_ongoing" name="scbl_ann_ongoing" value="1" <?php checked( $ongoing ); ?> />
				<?php esc_html_e( 'Ongoing (no end date)', 'seedcast-bulletin-library' ); ?>
			</label>
		</p>
		<p id="scbl_ann_end_wrap" class="scbl-ann-end-wrap<?php echo $ongoing ? ' is-hidden' : ''; ?>">
			<label for="scbl_ann_end"><strong><?php esc_html_e( 'End date', 'seedcast-bulletin-library' ); ?></strong></label><br />
			<input type="date" id="scbl_ann_end" name="scbl_ann_end" value="<?php echo esc_attr( $end ); ?>" class="scbl-ann-date-input" />
		</p>
		<p class="description">
			<?php esc_html_e( 'Controls which Sunday services show this announcement - any Sunday whose week overlaps this range.', 'seedcast-bulletin-library' ); ?>
		</p>
		<?php
	}

	/** Sidebar box #2: event details. Collapsed by default via WP's postbox toggle. */
	public function render_event( \WP_Post $post ): void {
		$time    = (string) get_post_meta( $post->ID, self::META_TIME,    true );
		$loc     = (string) get_post_meta( $post->ID, self::META_LOC,     true );
		$link    = (string) get_post_meta( $post->ID, self::META_LINK,    true );
		$contact = get_post_meta( $post->ID, self::META_CONTACT, true );
		if ( ! is_array( $contact ) ) $contact = [];
		$c_name  = (string) ( $contact['name']  ?? '' );
		$c_email = (string) ( $contact['email'] ?? '' );
		$c_phone = (string) ( $contact['phone'] ?? '' );
		?>
		<p class="description scbl-metabox-desc">
			<?php esc_html_e( 'Fill in what applies. Empty fields are hidden on the card.', 'seedcast-bulletin-library' ); ?>
		</p>
		<p>
			<label for="scbl_ann_time"><strong><?php esc_html_e( 'Time', 'seedcast-bulletin-library' ); ?></strong></label><br />
			<input type="text" id="scbl_ann_time" name="scbl_ann_time" value="<?php echo esc_attr( $time ); ?>" placeholder="e.g. 6:30pm or 9am – noon" class="scbl-ann-input" />
		</p>
		<p>
			<label for="scbl_ann_location"><strong><?php esc_html_e( 'Location', 'seedcast-bulletin-library' ); ?></strong></label><br />
			<input type="text" id="scbl_ann_location" name="scbl_ann_location" value="<?php echo esc_attr( $loc ); ?>" placeholder="e.g. Fellowship Hall" class="scbl-ann-input" />
		</p>

		<div class="scbl-ann-block">
			<p class="scbl-ann-block__title"><strong><?php esc_html_e( 'Contact', 'seedcast-bulletin-library' ); ?></strong></p>
			<p class="scbl-ann-field scbl-ann-field--relative">
				<input type="text" id="scbl_ann_contact_name" name="scbl_ann_contact_name" value="<?php echo esc_attr( $c_name ); ?>" placeholder="<?php esc_attr_e( 'Name', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="scbl_ann_contact_suggestions" />
				<span id="scbl_ann_contact_suggestions" class="scbl-ann-contact-suggestions" role="listbox" aria-label="<?php esc_attr_e( 'Contact suggestions', 'seedcast-bulletin-library' ); ?>"></span>
			</p>
			<p class="scbl-ann-field">
				<input type="email" id="scbl_ann_contact_email" name="scbl_ann_contact_email" value="<?php echo esc_attr( $c_email ); ?>" placeholder="<?php esc_attr_e( 'Email', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" />
			</p>
			<p class="scbl-ann-field">
				<input type="text" id="scbl_ann_contact_phone" name="scbl_ann_contact_phone" value="<?php echo esc_attr( $c_phone ); ?>" placeholder="<?php esc_attr_e( 'Phone', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" />
			</p>
			<p class="description scbl-ann-field-desc">
				<?php
				printf(
					/* translators: %s is a link to the Contacts admin page */
					esc_html__( 'Names autocomplete from your saved %s.', 'seedcast-bulletin-library' ),
					'<a href="' . esc_url( ContactsPage::url() ) . '">' . esc_html__( 'contacts list', 'seedcast-bulletin-library' ) . '</a>'
				);
				?>
			</p>
		</div>

		<p class="scbl-ann-block">
			<label for="scbl_ann_link"><strong><?php esc_html_e( 'Event link', 'seedcast-bulletin-library' ); ?></strong></label><br />
			<input type="url" id="scbl_ann_link" name="scbl_ann_link" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" class="scbl-ann-input" />
			<span class="description"><?php esc_html_e( 'Optional. Adds an "Event link" button to the card.', 'seedcast-bulletin-library' ); ?></span>
		</p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Display period.
		if ( isset( $_POST['scbl_ann_start'] ) ) {
			$start = sanitize_text_field( wp_unslash( $_POST['scbl_ann_start'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) {
				update_post_meta( $post_id, self::META_START, $start );
			}
		}
		$ongoing = ! empty( $_POST['scbl_ann_ongoing'] );
		if ( $ongoing ) {
			update_post_meta( $post_id, self::META_END, '' );
		} elseif ( isset( $_POST['scbl_ann_end'] ) ) {
			$end = sanitize_text_field( wp_unslash( $_POST['scbl_ann_end'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
				update_post_meta( $post_id, self::META_END, $end );
			}
		}

		// Event fields.
		if ( isset( $_POST['scbl_ann_time'] ) ) {
			update_post_meta( $post_id, self::META_TIME, sanitize_text_field( wp_unslash( $_POST['scbl_ann_time'] ) ) );
		}
		if ( isset( $_POST['scbl_ann_location'] ) ) {
			update_post_meta( $post_id, self::META_LOC, sanitize_text_field( wp_unslash( $_POST['scbl_ann_location'] ) ) );
		}
		if ( isset( $_POST['scbl_ann_link'] ) ) {
			update_post_meta( $post_id, self::META_LINK, esc_url_raw( wp_unslash( $_POST['scbl_ann_link'] ) ) );
		}

		// Contact - save as one array; also upsert back into the shared store
		// so future autocomplete suggestions include this contact.
		$c_name  = isset( $_POST['scbl_ann_contact_name'] )  ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_contact_name'] ) )  : '';
		$c_email = isset( $_POST['scbl_ann_contact_email'] ) ? sanitize_email(      wp_unslash( $_POST['scbl_ann_contact_email'] ) ) : '';
		$c_phone = isset( $_POST['scbl_ann_contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_contact_phone'] ) ) : '';
		if ( $c_name || $c_email || $c_phone ) {
			update_post_meta( $post_id, self::META_CONTACT, [
				'name'  => $c_name,
				'email' => $c_email,
				'phone' => $c_phone,
			] );
			if ( $c_name ) {
				Contacts::upsert( $c_name, $c_email, $c_phone );
			}
		} else {
			delete_post_meta( $post_id, self::META_CONTACT );
		}

		// Defensive: WP core normally saves _thumbnail_id in edit_post()
		// before save_post fires, but if something in the request chain
		// suppresses that we make sure the selection sticks.
		if ( isset( $_POST['_thumbnail_id'] ) ) {
			$thumb_id = (int) $_POST['_thumbnail_id'];
			if ( $thumb_id > 0 && 'attachment' === get_post_type( $thumb_id ) ) {
				set_post_thumbnail( $post_id, $thumb_id );
			} elseif ( -1 === $thumb_id || 0 === $thumb_id ) {
				delete_post_thumbnail( $post_id );
			}
		}
	}

	/**
	 * Enqueues the contact autocomplete behavior on the announcement editor
	 * and localizes the contacts list as JSON. For a small church's contact
	 * list (<100 entries) delivering the list once at page load is more
	 * efficient than an AJAX call on each keystroke.
	 */
	public function autocomplete_data(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== AnnouncementCPT::POST_TYPE ) return;

		wp_enqueue_script(
			'scbl-announcement-admin',
			SCBL_PLUGIN_URL . 'assets/js/scbl-announcement-admin.js',
			[],
			SCBL_VERSION,
			true
		);
		wp_localize_script(
			'scbl-announcement-admin',
			'scblContacts',
			(array) Contacts::all()
		);
	}
}
