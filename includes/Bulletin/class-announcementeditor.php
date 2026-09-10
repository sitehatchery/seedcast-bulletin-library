<?php
namespace SeedcastBulletinLibrary\Bulletin;


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta boxes for the source Announcement.
 *
 * Scheduling says when the announcement happens: a frequency, and only the
 * dates and times that frequency needs (see Schedule). An announcement is
 * offered to Sunday services from the week it is published through the week
 * of its last date. Event details (location, contact, link) are optional and
 * render on the card only when populated. Contact fields autocomplete from
 * the shared Contacts store and upsert back to it on save.
 */
class AnnouncementEditor {

	const NONCE        = 'scbl_ann_meta';
	/** First date the schedule covers, derived on save. '' when open-ended. */
	const META_START   = '_scbl_ann_start';
	/** Last date the schedule covers, derived on save. '' when it has no end. */
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
			'scbl_ann_schedule',
			__( 'Scheduling', 'seedcast-bulletin-library' ),
			[ $this, 'render_schedule' ],
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

	/**
	 * Scheduling box. Every field is rendered once; which ones show depends
	 * on the frequency, per Schedule::fields(). The same table travels to the
	 * JS in data-fields, so the initial state here and the live toggling there
	 * cannot drift apart.
	 */
	public function render_schedule( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$s         = Schedule::get( $post->ID );
		$kind      = Schedule::kind( $s );
		$use_dates = ! empty( $s['use_dates'] );
		$time      = (string) get_post_meta( $post->ID, self::META_TIME, true );
		$help      = Schedule::help();
		$hidden    = static function ( string $field ) use ( $kind, $use_dates ): string {
			return Schedule::is_visible( $field, $kind, $use_dates ) ? '' : ' is-hidden';
		};
		?>
		<div class="scbl-schedule" data-fields="<?php echo esc_attr( (string) wp_json_encode( Schedule::fields() ) ); ?>">
			<div class="scbl-schedule__field">
				<label for="scbl_ann_frequency"><strong><?php esc_html_e( 'Frequency', 'seedcast-bulletin-library' ); ?></strong></label><br />
				<select id="scbl_ann_frequency" name="scbl_ann_frequency" aria-describedby="scbl_ann_frequency_help">
					<?php foreach ( Schedule::frequencies() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" data-help="<?php echo esc_attr( $help[ $key ] ?? '' ); ?>" <?php selected( $s['frequency'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p id="scbl_ann_frequency_help" class="description scbl-schedule__help"><?php echo esc_html( $help[ $s['frequency'] ] ?? '' ); ?></p>
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'pattern' ) ); ?>" data-field="pattern">
				<label for="scbl_ann_pattern"><strong><?php esc_html_e( 'Repeats', 'seedcast-bulletin-library' ); ?></strong></label><br />
				<select id="scbl_ann_pattern" name="scbl_ann_pattern">
					<?php foreach ( Schedule::patterns() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['pattern'] ?? 'weekly', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'weekday' ) ); ?>" data-field="weekday">
				<label for="scbl_ann_weekday"><strong><?php esc_html_e( 'Weekday', 'seedcast-bulletin-library' ); ?></strong></label><br />
				<?php $this->weekday_select( 'scbl_ann_weekday', (int) ( $s['weekday'] ?? 0 ), 'scbl_ann_weekday' ); ?>
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'date' ) ); ?>" data-field="date">
				<label for="scbl_ann_date"><strong><?php esc_html_e( 'Date', 'seedcast-bulletin-library' ); ?></strong></label><br />
				<input type="date" id="scbl_ann_date" name="scbl_ann_date" value="<?php echo esc_attr( $s['date'] ?? '' ); ?>" class="scbl-ann-date-input" data-required />
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'use_dates' ) ); ?>" data-field="use_dates">
				<label>
					<input type="checkbox" id="scbl_ann_use_dates" name="scbl_ann_use_dates" value="1" <?php checked( $use_dates ); ?> />
					<?php esc_html_e( 'Set start and end dates', 'seedcast-bulletin-library' ); ?>
				</label>
			</div>

			<div class="scbl-schedule__pair">
				<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'start' ) ); ?>" data-field="start">
					<label for="scbl_ann_start"><strong><?php esc_html_e( 'Start date', 'seedcast-bulletin-library' ); ?></strong></label><br />
					<input type="date" id="scbl_ann_start" name="scbl_ann_start" value="<?php echo esc_attr( $s['start'] ?? '' ); ?>" class="scbl-ann-date-input" data-required />
				</div>
				<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'end' ) ); ?>" data-field="end">
					<label for="scbl_ann_end"><strong><?php esc_html_e( 'End date', 'seedcast-bulletin-library' ); ?></strong></label><br />
					<input type="date" id="scbl_ann_end" name="scbl_ann_end" value="<?php echo esc_attr( $s['end'] ?? '' ); ?>" class="scbl-ann-date-input" data-required />
				</div>
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'time' ) ); ?>" data-field="time">
				<label for="scbl_ann_time"><strong><?php esc_html_e( 'Time', 'seedcast-bulletin-library' ); ?></strong></label><br />
				<input type="text" id="scbl_ann_time" name="scbl_ann_time" value="<?php echo esc_attr( $time ); ?>" placeholder="<?php esc_attr_e( 'e.g. 6:30pm or 9am – noon', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" />
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'days' ) ); ?>" data-field="days">
				<p class="scbl-schedule__label"><strong><?php esc_html_e( 'Days and times', 'seedcast-bulletin-library' ); ?></strong></p>
				<div class="scbl-schedule__rows" data-rows="days">
					<?php foreach ( $s['days'] ?? [] as $i => $row ) : ?>
						<?php $this->day_row( (string) $i, $row ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-small" data-add="days"><?php esc_html_e( 'Add a day', 'seedcast-bulletin-library' ); ?></button>
				<template id="scbl-schedule-tpl-days"><?php $this->day_row( '__i__', [] ); ?></template>
			</div>

			<div class="scbl-schedule__field<?php echo esc_attr( $hidden( 'dates' ) ); ?>" data-field="dates">
				<p class="scbl-schedule__label"><strong><?php esc_html_e( 'Dates and times', 'seedcast-bulletin-library' ); ?></strong></p>
				<div class="scbl-schedule__rows" data-rows="dates">
					<?php foreach ( $s['dates'] ?? [] as $i => $row ) : ?>
						<?php $this->date_row( (string) $i, $row ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-small" data-add="dates"><?php esc_html_e( 'Add a date', 'seedcast-bulletin-library' ); ?></button>
				<template id="scbl-schedule-tpl-dates"><?php $this->date_row( '__i__', [] ); ?></template>
			</div>

			<p class="description scbl-schedule__note">
				<?php esc_html_e( 'Offered to Sunday services from the week this is published through the week of its last date. With no last date, it is offered until you unpublish it.', 'seedcast-bulletin-library' ); ?>
			</p>
		</div>
		<?php
	}

	/** One Multiday row: a weekday and its time. */
	private function day_row( string $index, array $row ): void {
		$name = 'scbl_ann_days[' . $index . ']';
		?>
		<div class="scbl-schedule__item">
			<?php $this->weekday_select( $name . '[weekday]', (int) ( $row['weekday'] ?? 0 ), '', __( 'Weekday', 'seedcast-bulletin-library' ) ); ?>
			<input type="text" name="<?php echo esc_attr( $name . '[time]' ); ?>" value="<?php echo esc_attr( $row['time'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Time, e.g. 12:00 PM – 1:15 PM', 'seedcast-bulletin-library' ); ?>" aria-label="<?php esc_attr_e( 'Time', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" />
			<button type="button" class="button-link scbl-schedule__remove" aria-label="<?php esc_attr_e( 'Remove this day', 'seedcast-bulletin-library' ); ?>">&times;</button>
		</div>
		<?php
	}

	/** One Staggered row: a date and its time. */
	private function date_row( string $index, array $row ): void {
		$name = 'scbl_ann_dates[' . $index . ']';
		?>
		<div class="scbl-schedule__item">
			<input type="date" name="<?php echo esc_attr( $name . '[date]' ); ?>" value="<?php echo esc_attr( $row['date'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'Date', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-date-input" data-required />
			<input type="text" name="<?php echo esc_attr( $name . '[time]' ); ?>" value="<?php echo esc_attr( $row['time'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Time, e.g. 7pm', 'seedcast-bulletin-library' ); ?>" aria-label="<?php esc_attr_e( 'Time', 'seedcast-bulletin-library' ); ?>" class="scbl-ann-input" />
			<button type="button" class="button-link scbl-schedule__remove" aria-label="<?php esc_attr_e( 'Remove this date', 'seedcast-bulletin-library' ); ?>">&times;</button>
		</div>
		<?php
	}

	private function weekday_select( string $name, int $selected, string $id = '', string $aria_label = '' ): void {
		?>
		<select name="<?php echo esc_attr( $name ); ?>"
			<?php if ( '' !== $id ) : ?>id="<?php echo esc_attr( $id ); ?>"<?php endif; ?>
			<?php if ( '' !== $aria_label ) : ?>aria-label="<?php echo esc_attr( $aria_label ); ?>"<?php endif; ?>>
			<?php for ( $day = 0; $day <= 6; $day++ ) : ?>
				<option value="<?php echo esc_attr( (string) $day ); ?>" <?php selected( $selected, $day ); ?>><?php echo esc_html( Schedule::weekday_name( $day ) ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
	}

	/** Event details box: where, who to ask, and a link. */
	public function render_event( \WP_Post $post ): void {
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

		// Scheduling. Fields the frequency does not use are disabled in the
		// form and dropped again by Schedule::sanitize(), so a stale value
		// from a frequency the admin switched away from never survives.
		if ( isset( $_POST['scbl_ann_frequency'] ) ) {
			$schedule = Schedule::sanitize(
				[
					'frequency' => sanitize_key( wp_unslash( $_POST['scbl_ann_frequency'] ) ),
					'pattern'   => isset( $_POST['scbl_ann_pattern'] ) ? sanitize_key( wp_unslash( $_POST['scbl_ann_pattern'] ) ) : '',
					'weekday'   => isset( $_POST['scbl_ann_weekday'] ) ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_weekday'] ) ) : '',
					'date'      => isset( $_POST['scbl_ann_date'] ) ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_date'] ) ) : '',
					'use_dates' => ! empty( $_POST['scbl_ann_use_dates'] ),
					'start'     => isset( $_POST['scbl_ann_start'] ) ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_start'] ) ) : '',
					'end'       => isset( $_POST['scbl_ann_end'] ) ? sanitize_text_field( wp_unslash( $_POST['scbl_ann_end'] ) ) : '',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each row field is cleaned in Schedule::sanitize()
					'days'      => isset( $_POST['scbl_ann_days'] ) && is_array( $_POST['scbl_ann_days'] ) ? wp_unslash( $_POST['scbl_ann_days'] ) : [],
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each row field is cleaned in Schedule::sanitize()
					'dates'     => isset( $_POST['scbl_ann_dates'] ) && is_array( $_POST['scbl_ann_dates'] ) ? wp_unslash( $_POST['scbl_ann_dates'] ) : [],
				]
			);
			update_post_meta( $post_id, Schedule::META, $schedule );

			// The flat first/last dates are what the list filters, the
			// shortcode and the service suggestions query. Written even when
			// empty so every announcement has the rows those queries join on.
			list( $first, $last ) = Schedule::bounds( $schedule );
			update_post_meta( $post_id, self::META_START, $first );
			update_post_meta( $post_id, self::META_END, $last );

			$time = '';
			if ( isset( $_POST['scbl_ann_time'] ) && Schedule::uses_time( Schedule::kind( $schedule ) ) ) {
				$time = sanitize_text_field( wp_unslash( $_POST['scbl_ann_time'] ) );
			}
			update_post_meta( $post_id, self::META_TIME, $time );
		}

		// Event fields.
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
	 * Enqueues the scheduling and contact autocomplete behavior on the
	 * announcement editor and localizes the contacts list as JSON. For a
	 * small church's contact list (<100 entries) delivering the list once at
	 * page load is more efficient than an AJAX call on each keystroke.
	 */
	public function autocomplete_data(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== AnnouncementCPT::POST_TYPE ) return;

		wp_enqueue_script(
			'scbl-announcement-admin',
			SCBL_PLUGIN_URL . 'assets/js/scbl-announcement-admin.js',
			[],
			scbl_asset_version( 'assets/js/scbl-announcement-admin.js' ),
			true
		);
		wp_localize_script(
			'scbl-announcement-admin',
			'scblContacts',
			(array) Contacts::all()
		);
	}
}
