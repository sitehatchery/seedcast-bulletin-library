<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A service's photo gallery.
 *
 * A gallery could always be dropped into the overview with Add Media, but
 * nothing on Edit Service said so, and inside the overview it competes with
 * the words. This gives photos their own box on Edit Service, with an Add
 * photos button, and their own place on the service page: a grid of
 * thumbnails at the top of the right column, above the Visitor Card, that
 * opens in the lightbox.
 *
 * Stored as an ordered list of attachment IDs. The images themselves stay in
 * the Media Library.
 */
class ServiceGallery {

	const META  = '_scbl_service_gallery';
	const NONCE = 'scbl_service_gallery';

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_box' ], 1 );
		add_action( 'save_post_' . ServiceCPT::POST_TYPE, [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'scbl_service_sidebar_start', [ $this, 'render' ] );
	}

	/**
	 * A service's gallery, in order. A photo since deleted from the Media
	 * Library drops out here rather than leaving a hole in the grid.
	 *
	 * @return int[]
	 */
	public static function get( int $post_id ): array {
		$ids = get_post_meta( $post_id, self::META, true );
		return self::clean_ids( is_array( $ids ) ? $ids : [] );
	}

	/**
	 * Attachment IDs that are images, each once, in the order given.
	 *
	 * @param mixed $raw An array of IDs, or a comma separated string of them.
	 * @return int[]
	 */
	public static function clean_ids( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}

		$ids = [];
		foreach ( (array) $raw as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $id, $ids, true ) && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	public function register_box(): void {
		add_meta_box(
			'scbl_service_gallery',
			__( 'Service Gallery', 'seedcast-bulletin-library' ),
			[ $this, 'render_box' ],
			ServiceCPT::POST_TYPE,
			'side',
			'default'
		);
	}

	/** The Edit Service box: thumbnails in saved order, and the Add photos button. */
	public function render_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$ids = self::get( $post->ID );
		?>
		<div class="scbl-gallery-box">
			<p class="description scbl-gallery-box__intro">
				<?php esc_html_e( 'Photos from this service. They show beside the service page, above the visitor card, and open full size when clicked.', 'seedcast-bulletin-library' ); ?>
			</p>
			<ul class="scbl-gallery-box__list">
				<?php foreach ( $ids as $id ) : ?>
					<li class="scbl-gallery-box__item" data-id="<?php echo esc_attr( (string) $id ); ?>">
						<?php echo wp_get_attachment_image( $id, 'thumbnail', false, [ 'alt' => '' ] ); ?>
						<button type="button" class="scbl-gallery-box__remove" aria-label="<?php esc_attr_e( 'Remove photo', 'seedcast-bulletin-library' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" name="scbl_service_gallery" class="scbl-gallery-box__ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />
			<p class="scbl-gallery-box__actions">
				<button type="button" class="button scbl-gallery-box__add"><?php esc_html_e( 'Add photos', 'seedcast-bulletin-library' ); ?></button>
			</p>
			<p class="description scbl-gallery-box__hint"<?php echo count( $ids ) > 1 ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'Drag to reorder.', 'seedcast-bulletin-library' ); ?>
			</p>
		</div>
		<?php
	}

	/** The media picker and reordering, on the service editor only. */
	public function admin_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ServiceCPT::POST_TYPE !== $screen->post_type || ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'scbl-service-gallery',
			SCBL_PLUGIN_URL . 'assets/js/scbl-service-gallery.js',
			[ 'jquery', 'jquery-ui-sortable', 'media-editor' ],
			scbl_asset_version( 'assets/js/scbl-service-gallery.js' ),
			true
		);
		wp_localize_script(
			'scbl-service-gallery',
			'scblGallery',
			[
				'title'  => __( 'Add photos to the service gallery', 'seedcast-bulletin-library' ),
				'button' => __( 'Add to gallery', 'seedcast-bulletin-library' ),
				'remove' => __( 'Remove photo', 'seedcast-bulletin-library' ),
			]
		);
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids = isset( $_POST['scbl_service_gallery'] )
			? self::clean_ids( sanitize_text_field( wp_unslash( $_POST['scbl_service_gallery'] ) ) )
			: [];

		if ( $ids ) {
			update_post_meta( $post_id, self::META, $ids );
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}

	/**
	 * The gallery at the top of the service page sidebar.
	 *
	 * Uses the markup WordPress's own [gallery] prints, so the overview
	 * gallery's grid styles and the lightbox apply to it unchanged. Each link
	 * points at a large image for the lightbox, and the caption travels in
	 * data-caption to be shown there rather than under a small thumbnail.
	 *
	 * @param int $post_id Service ID.
	 * @return void
	 */
	public function render( $post_id ): void {
		$ids = self::get( (int) $post_id );
		if ( ! $ids ) {
			return;
		}
		?>
		<section class="scbl-gallery-panel" aria-labelledby="scbl-gallery-title">
			<h2 id="scbl-gallery-title" class="scbl-section__title"><?php esc_html_e( 'Photos', 'seedcast-bulletin-library' ); ?></h2>
			<div class="gallery gallery-columns-2 scbl-gallery-panel__grid">
				<?php foreach ( $ids as $id ) : ?>
					<figure class="gallery-item">
						<div class="gallery-icon">
							<a href="<?php echo esc_url( (string) wp_get_attachment_image_url( $id, '1536x1536' ) ); ?>" data-caption="<?php echo esc_attr( (string) wp_get_attachment_caption( $id ) ); ?>">
								<?php
								// Two thumbnails across a 35% column come to under 200px each.
								echo wp_get_attachment_image(
									$id,
									'medium',
									false,
									[
										'sizes'   => '(max-width: 900px) 33vw, 190px',
										'loading' => 'lazy',
									]
								);
								?>
							</a>
						</div>
					</figure>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}
}
