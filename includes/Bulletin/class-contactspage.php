<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The saved contacts screen, under the Sunday menu.
 *
 * Sits next to Announcements because that is the only thing that reads it.
 * Editing or deleting here changes what autocompletes next time; announcements
 * already published kept their own copy of the fields at save time and are
 * never touched from this screen.
 */
class ContactsPage {

	const PAGE_SLUG = 'scbl-contacts';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 15 );
		add_action( 'admin_post_sunday_contact_save', [ $this, 'handle_save' ] );
		add_action( 'admin_post_sunday_contact_delete', [ $this, 'handle_delete' ] );
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	public function add_page(): void {
		add_submenu_page(
			AdminMenu::MENU_SLUG,
			__( 'Contacts', 'seedcast-bulletin-library' ),
			__( 'Contacts', 'seedcast-bulletin-library' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) return;

		$contacts = Contacts::all();
		usort( $contacts, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contacts', 'seedcast-bulletin-library' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'People you can attach to an announcement. Start typing a name in the announcement editor and the rest fills in.', 'seedcast-bulletin-library' ); ?>
			</p>

			<?php $this->render_notice(); ?>

			<form class="sc-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'scbl_contact_save' ); ?>
				<input type="hidden" name="action" value="scbl_contact_save" />
				<input type="hidden" name="original_name" value="" />
				<p>
					<label for="scbl_contact_name"><strong><?php esc_html_e( 'Name', 'seedcast-bulletin-library' ); ?></strong></label><br />
					<input type="text" id="scbl_contact_name" name="name" required class="regular-text" />
				</p>
				<p>
					<label for="scbl_contact_email"><strong><?php esc_html_e( 'Email', 'seedcast-bulletin-library' ); ?></strong></label><br />
					<input type="email" id="scbl_contact_email" name="email" class="regular-text" />
				</p>
				<p>
					<label for="scbl_contact_phone"><strong><?php esc_html_e( 'Phone', 'seedcast-bulletin-library' ); ?></strong></label><br />
					<input type="text" id="scbl_contact_phone" name="phone" class="regular-text" />
				</p>
				<p>
					<button type="submit" class="button button-primary"
						data-add-label="<?php esc_attr_e( 'Add contact', 'seedcast-bulletin-library' ); ?>"
						data-update-label="<?php esc_attr_e( 'Update contact', 'seedcast-bulletin-library' ); ?>">
						<?php esc_html_e( 'Add contact', 'seedcast-bulletin-library' ); ?>
					</button>
				</p>
			</form>

			<?php if ( $contacts ) : ?>
				<table class="widefat striped sc-contact-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'seedcast-bulletin-library' ); ?></th>
							<th><?php esc_html_e( 'Email', 'seedcast-bulletin-library' ); ?></th>
							<th><?php esc_html_e( 'Phone', 'seedcast-bulletin-library' ); ?></th>
							<th class="scbl-contacts-actions-col"><?php esc_html_e( 'Actions', 'seedcast-bulletin-library' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $contacts as $contact ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $contact['name'] ); ?></strong></td>
								<td>
									<?php if ( $contact['email'] ) : ?>
										<a href="<?php echo esc_url( 'mailto:' . $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a>
									<?php else : ?>
										<span class="sc-muted">&ndash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $contact['phone'] ? $contact['phone'] : '-' ); ?></td>
								<td>
									<?php
									$delete_url = wp_nonce_url(
										add_query_arg(
											[
												'action' => 'scbl_contact_delete',
												'name'   => rawurlencode( $contact['name'] ),
											],
											admin_url( 'admin-post.php' )
										),
										'scbl_contact_delete_' . $contact['name']
									);
									?>
									<button type="button" class="button button-small sc-contact-edit"
										data-name="<?php echo esc_attr( $contact['name'] ); ?>"
										data-email="<?php echo esc_attr( $contact['email'] ); ?>"
										data-phone="<?php echo esc_attr( $contact['phone'] ); ?>">
										<?php esc_html_e( 'Edit', 'seedcast-bulletin-library' ); ?>
									</button>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small sc-contact-delete">
										<?php esc_html_e( 'Delete', 'seedcast-bulletin-library' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sc-muted">
					<?php esc_html_e( 'No saved contacts yet. They build up as you fill in contact fields on announcements, or you can add one here.', 'seedcast-bulletin-library' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Result of the last add, update or delete.
	 */
	private function render_notice(): void {
		// Read only, and the action it reports on was already nonce checked.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['scbl_msg'] ) ? sanitize_key( wp_unslash( $_GET['scbl_msg'] ) ) : '';
		if ( ! $msg ) return;

		$messages = [
			'added'   => [ 'success', __( 'Contact added.', 'seedcast-bulletin-library' ) ],
			'updated' => [ 'success', __( 'Contact updated.', 'seedcast-bulletin-library' ) ],
			'deleted' => [ 'success', __( 'Contact deleted.', 'seedcast-bulletin-library' ) ],
			'noname'  => [ 'error',   __( 'A name is required.', 'seedcast-bulletin-library' ) ],
		];
		if ( ! isset( $messages[ $msg ] ) ) return;

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $msg ][0] ),
			esc_html( $messages[ $msg ][1] )
		);
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'Not allowed.', 'seedcast-bulletin-library' ) );
		check_admin_referer( 'scbl_contact_save' );

		$name     = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone    = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$original = sanitize_text_field( wp_unslash( $_POST['original_name'] ?? '' ) );

		if ( '' === $name ) {
			$result = 'noname';
		} else {
			$existing = null !== Contacts::find( $name ) || '' !== $original;
			if ( '' !== $original && strtolower( $original ) !== strtolower( $name ) ) {
				Contacts::delete_by_name( $original );
			}
			Contacts::upsert( $name, $email, $phone );
			$result = $existing ? 'updated' : 'added';
		}

		wp_safe_redirect( add_query_arg( 'scbl_msg', $result, self::url() ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'Not allowed.', 'seedcast-bulletin-library' ) );
		// The nonce is bound to the name, so the name is read before it can be
		// verified. Nothing is written until check_admin_referer passes.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw  = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( $_GET['name'] ) ) : '';
		$name = rawurldecode( $raw );
		check_admin_referer( 'scbl_contact_delete_' . $name );

		Contacts::delete_by_name( $name );
		wp_safe_redirect( add_query_arg( 'scbl_msg', 'deleted', self::url() ) );
		exit;
	}
}
