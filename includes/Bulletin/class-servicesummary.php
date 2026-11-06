<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * "Generate overview" on the Service editor.
 *
 * Writes the service's overview from the sermon preached that week: not a
 * summary of the sermon, but something encouraging about what it was about,
 * as the emphasis of the service. The writing is not this plugin's. Sermon
 * Library knows the sermon and answers through two filters, so this plugin
 * goes on knowing nothing about sermons:
 *
 *   scbl/service/can_summarize  (bool $can)  whether anything will answer; the
 *                                            box is only shown when something will.
 *   scbl/service/summary        (null $result, int $service_id, string $date)
 *                                            plain text, paragraphs separated
 *                                            by a blank line, or a WP_Error
 *                                            whose message is shown as it is.
 *
 * The text goes into the editor rather than straight onto the post, so it is
 * read before it is saved, the same as anything else typed there.
 */
class ServiceSummary {

	const ACTION = 'scbl_service_summary';

	public function init(): void {
		add_action( 'add_meta_boxes_' . ServiceCPT::POST_TYPE, [ $this, 'add_box' ] );
		add_action( 'wp_ajax_' . self::ACTION,                 [ $this, 'ajax' ] );
		add_action( 'admin_enqueue_scripts',                   [ $this, 'assets' ] );
	}

	public static function available(): bool {
		return (bool) apply_filters( 'scbl/service/can_summarize', false );
	}

	public function add_box(): void {
		if ( ! self::available() ) return;

		add_meta_box(
			'scbl_service_summary',
			__( 'Overview from Sermon', 'seedcast-bulletin-library' ),
			[ $this, 'render_box' ],
			ServiceCPT::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_box( \WP_Post $post ): void {
		?>
		<p class="description scbl-metabox-desc">
			<?php esc_html_e( 'Write the overview from this week&#8217;s sermon: an encouraging thought on what it was about, for the service as a whole. It goes into the editor for you to review before saving.', 'seedcast-bulletin-library' ); ?>
		</p>
		<p class="scbl-summary-actions">
			<button type="button" class="button scbl-summary-generate" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php esc_html_e( 'Generate overview', 'seedcast-bulletin-library' ); ?>
			</button>
			<span class="spinner scbl-summary-spinner"></span>
		</p>
		<p class="scbl-summary-status" role="status" aria-live="polite"></p>
		<?php
	}

	public function assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

		$screen = get_current_screen();
		if ( ! $screen || ServiceCPT::POST_TYPE !== $screen->post_type ) return;
		if ( ! self::available() ) return;

		wp_enqueue_script(
			'scbl-service-summary',
			SCBL_PLUGIN_URL . 'assets/js/scbl-service-summary.js',
			[],
			scbl_asset_version( 'assets/js/scbl-service-summary.js' ),
			true
		);

		wp_localize_script( 'scbl-service-summary', 'scblServiceSummary', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::ACTION,
			'nonce'   => wp_create_nonce( self::ACTION ),
			'i18n'    => [
				'working' => __( 'Writing the overview. This can take up to a minute.', 'seedcast-bulletin-library' ),
				'done'    => __( 'Overview written. Review it, then save the service.', 'seedcast-bulletin-library' ),
				'replace' => __( 'Replace the current overview with one written from this week&#8217;s sermon?', 'seedcast-bulletin-library' ),
				'failed'  => __( 'The overview could not be written. Please try again.', 'seedcast-bulletin-library' ),
			],
		] );
	}

	/**
	 * Ask for the overview. Answers { text } or an error message.
	 */
	public function ajax(): void {
		check_ajax_referer( self::ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ServiceCPT::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot edit this service.', 'seedcast-bulletin-library' ) ], 403 );
		}

		// The date on the screen, which may not be saved yet; the stored one
		// otherwise, since the block editor does not draw the date field.
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = (string) get_post_meta( $post_id, ServiceEditor::META_DATE, true );
		}

		$result = apply_filters( 'scbl/service/summary', null, $post_id, $date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$text = is_string( $result ) ? trim( $result ) : '';
		if ( '' === $text ) {
			wp_send_json_error( [ 'message' => __( 'The overview could not be written. Please try again.', 'seedcast-bulletin-library' ) ] );
		}

		wp_send_json_success( [ 'text' => $text ] );
	}
}
