<?php
namespace SeedcastBulletinLibrary\Bulletin;

use Seedcast\Core\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bulletin Library's section on the shared Seedcast settings page.
 *
 * Bulletin Library used to host that page. It no longer does. Settings live in one
 * permanent location under the Settings menu regardless of which plugins are
 * installed, and Bulletin Library registers into it like every other plugin. What Bulletin Library
 * keeps is the dashboard, which has a genuinely different job: the service
 * object and completeness reporting, both of which require Bulletin Library to exist.
 *
 * Registration is guarded on the section API being present. If an older core
 * copy from another plugin happens to win negotiation, Bulletin Library degrades to
 * having no settings section rather than fataling.
 */
class SettingsSection {

	const GROUP   = 'scbl_settings';
	const SECTION = 'scbl_general';

	public function init(): void {
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'seedcast_core_register_settings', [ $this, 'register_section' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_link' ], 20 );
		add_filter( 'plugin_action_links_' . SCBL_PLUGIN_BASENAME, [ $this, 'action_link' ] );
		add_action( 'update_option_sunday_service_slug', [ $this, 'schedule_flush' ] );
	}

	public function register(): void {
		$fields = [
			'scbl_services_per_page',
			'scbl_label_service_singular',
			'scbl_label_service_plural',
			'scbl_service_slug',
			'scbl_service_block_editor',
			'scbl_archive_heading',
			'scbl_archive_intro',
		];
		foreach ( $fields as $field ) {
			register_setting( self::GROUP, $field, [ 'sanitize_callback' => 'sanitize_text_field' ] );
		}

		// Attachment ID for the fallback image on cards without a featured
		// image. Integer only; 0 means "use the plugin's shipped default."
		register_setting( self::GROUP, 'scbl_default_service_image', [
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );

		// Boolean opt-out for the plugin's front-end CSS. Used by designers
		// who prefer to style everything themselves.
		register_setting( self::GROUP, 'scbl_disable_frontend_css', [
			'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
			'default'           => 0,
		] );

		// When off, `scbl_service_sidebar_end` doesn't fire, so nothing
		// hooking it renders on service pages.
		register_setting( self::GROUP, 'scbl_show_visitor_card', [
			'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
			'default'           => 1,
		] );
	}

	/**
	 * @param Settings $settings Shared settings page.
	 */
	public function register_section( $settings ): void {
		if ( ! is_object( $settings ) || ! method_exists( $settings, 'add_section' ) ) return;

		$settings->add_section(
			self::SECTION,
			__( 'Bulletin Library', 'seedcast-bulletin-library' ),
			[ $this, 'render' ],
			self::GROUP,
			10
		);
	}

	/**
	 * A Settings entry under Bulletin Library's own menu that links to the shared page,
	 * opened on Bulletin Library's section.
	 *
	 * People look for settings where they work, not where they were filed.
	 * Passing a full relative URL as the menu slug produces a link rather than
	 * a page, so the item highlights under Bulletin Library and lands on the shared page.
	 */
	public function add_menu_link(): void {
		if ( ! class_exists( '\\Seedcast\\Core\\Admin\\Settings' ) ) return;

		add_submenu_page(
			AdminMenu::MENU_SLUG,
			__( 'Settings', 'seedcast-bulletin-library' ),
			__( 'Settings', 'seedcast-bulletin-library' ),
			'manage_options',
			Settings::menu_link( self::SECTION )
		);
	}

	public function action_link( array $links ): array {
		if ( ! class_exists( '\\Seedcast\\Core\\Admin\\Settings' ) ) return $links;

		array_unshift(
			$links,
			'<a href="' . esc_url( Settings::url( self::SECTION ) ) . '">' . esc_html__( 'Settings', 'seedcast-bulletin-library' ) . '</a>'
		);
		return $links;
	}

	public function render(): void {
		?>
		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Display', 'seedcast-bulletin-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scbl_services_per_page"><?php esc_html_e( 'Services per page', 'seedcast-bulletin-library' ); ?></label></th>
					<td>
						<input type="number" min="1" name="scbl_services_per_page" id="scbl_services_per_page" value="<?php echo esc_attr( get_option( 'scbl_services_per_page' ) ?: '10' ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'How many past services show on the archive page before paginating.', 'seedcast-bulletin-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scbl_archive_heading"><?php esc_html_e( 'Archive page heading', 'seedcast-bulletin-library' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="scbl_archive_heading" id="scbl_archive_heading" value="<?php echo esc_attr( get_option( 'scbl_archive_heading' ) ?: __( 'Sunday Services', 'seedcast-bulletin-library' ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Shown at the top of the Services archive page.', 'seedcast-bulletin-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="scbl_archive_intro"><?php esc_html_e( 'Archive page intro text', 'seedcast-bulletin-library' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" name="scbl_archive_intro" id="scbl_archive_intro"><?php echo esc_textarea( get_option( 'scbl_archive_intro', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional. A short paragraph under the archive heading, where you tell a visitor what they are looking at.', 'seedcast-bulletin-library' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Editor', 'seedcast-bulletin-library' ); ?></h2>
			<?php
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$classic_editor_active = is_plugin_active( 'classic-editor/classic-editor.php' );
			?>
			<?php if ( $classic_editor_active ) : ?>
				<p class="description">
					<?php esc_html_e( 'The block editor is disabled site wide by the Classic Editor plugin. Bulletin Library follows that setting, so services use the classic editor.', 'seedcast-bulletin-library' ); ?>
				</p>
			<?php else : ?>
				<p class="description scbl-settings-desc">
					<?php esc_html_e( 'Services use the classic editor by default, so the service editor stays visually consistent. If you need blocks for embeds, columns, or richer content in the service overview, you can opt into the block editor here.', 'seedcast-bulletin-library' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Service editor', 'seedcast-bulletin-library' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="scbl_service_block_editor" value="1" <?php checked( get_option( 'scbl_service_block_editor', '0' ), '1' ); ?> />
								<?php esc_html_e( 'Use the block editor for services', 'seedcast-bulletin-library' ); ?>
							</label>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Custom labels', 'seedcast-bulletin-library' ); ?></h2>
			<p class="description scbl-settings-desc"><?php esc_html_e( 'Rename "Service" to match your church\'s language. Leave blank to use the defaults.', 'seedcast-bulletin-library' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="scbl_label_service_singular"><?php esc_html_e( 'Service (singular)', 'seedcast-bulletin-library' ); ?></label></th>
					<td><input type="text" id="scbl_label_service_singular" name="scbl_label_service_singular" value="<?php echo esc_attr( get_option( 'scbl_label_service_singular', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Service', 'seedcast-bulletin-library' ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="scbl_label_service_plural"><?php esc_html_e( 'Services (plural)', 'seedcast-bulletin-library' ); ?></label></th>
					<td><input type="text" id="scbl_label_service_plural" name="scbl_label_service_plural" value="<?php echo esc_attr( get_option( 'scbl_label_service_plural', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Services', 'seedcast-bulletin-library' ); ?>" /></td>
				</tr>
			</table>
			<p class="description"><?php esc_html_e( 'For example: Service becomes Gathering, or Meeting.', 'seedcast-bulletin-library' ); ?></p>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'URL slug', 'seedcast-bulletin-library' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Change this before publishing. Changing it later breaks existing URLs, though rewrite rules flush automatically.', 'seedcast-bulletin-library' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="scbl_service_slug"><?php esc_html_e( 'Service slug', 'seedcast-bulletin-library' ); ?></label></th>
					<td><input type="text" id="scbl_service_slug" name="scbl_service_slug" value="<?php echo esc_attr( get_option( 'scbl_service_slug' ) ?: 'service' ); ?>" class="regular-text" /></td>
				</tr>
			</table>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Images', 'seedcast-bulletin-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="scbl_default_service_image_btn"><?php esc_html_e( 'Fallback image', 'seedcast-bulletin-library' ); ?></label></th>
					<td>
						<?php
						$fallback_id  = (int) get_option( 'scbl_default_service_image', 0 );
						$fallback_url = $fallback_id ? wp_get_attachment_image_url( $fallback_id, 'thumbnail' ) : '';
						?>
						<div class="scbl-default-image" data-current="<?php echo esc_attr( (string) $fallback_id ); ?>">
							<p>
								<button type="button" class="button" id="scbl_default_service_image_btn"><?php esc_html_e( 'Choose image', 'seedcast-bulletin-library' ); ?></button>
								<button type="button" class="button" id="scbl_default_service_image_clear"<?php echo $fallback_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Clear', 'seedcast-bulletin-library' ); ?></button>
								<input type="hidden" id="scbl_default_service_image" name="scbl_default_service_image" value="<?php echo esc_attr( (string) $fallback_id ); ?>" />
							</p>
							<div class="scbl-default-image__preview">
								<?php if ( $fallback_url ) : ?>
									<img src="<?php echo esc_url( $fallback_url ); ?>" alt="" class="scbl-settings-thumb" />
								<?php endif; ?>
							</div>
							<p class="description">
								<?php esc_html_e( 'Shown on service cards when a service has no featured image of its own. Leave empty to use the plugin default (a plain church icon).', 'seedcast-bulletin-library' ); ?>
							</p>
						</div>
					</td>
				</tr>
			</table>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Styles', 'seedcast-bulletin-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Plugin styles', 'seedcast-bulletin-library' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="scbl_disable_frontend_css" value="1" <?php checked( (bool) get_option( 'scbl_disable_frontend_css', false ) ); ?> />
							<?php esc_html_e( "Do not load this plugin's front-end CSS", 'seedcast-bulletin-library' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( "For designers who would rather style everything themselves. Cleaner than overriding rule by rule, and the markup and class names stay exactly the same.", 'seedcast-bulletin-library' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Sidebar', 'seedcast-bulletin-library' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Visitor Card', 'seedcast-bulletin-library' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="scbl_show_visitor_card" value="1" <?php checked( (bool) get_option( 'scbl_show_visitor_card', 1 ) ); ?> />
							<?php esc_html_e( 'Show the Visitor Card on service pages', 'seedcast-bulletin-library' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( "When the Seedcast Visitor Card plugin is active, its card appears in the sidebar of every service page. Turn this off to hide it.", 'seedcast-bulletin-library' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Shortcodes', 'seedcast-bulletin-library' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to the Shortcode Generator. */
					esc_html__( 'Pick the options in the %s and copy the result, rather than writing attributes by hand.', 'seedcast-bulletin-library' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=' . ShortcodeGenerator::PAGE_SLUG ) ) . '">' . esc_html__( 'Shortcode Generator', 'seedcast-bulletin-library' ) . '</a>'
				);
				?>
			</p>
			<p><code>[scbl_services]</code> <?php esc_html_e( 'grid of service cards.', 'seedcast-bulletin-library' ); ?><br />
				<span class="description"><?php esc_html_e( 'Attributes: limit (default 10), columns (1 to 4, default 3), start_date (YYYY-MM-DD, hides services before this date).', 'seedcast-bulletin-library' ); ?></span><br />
				<span class="description"><?php esc_html_e( 'Example:', 'seedcast-bulletin-library' ); ?> <code>[scbl_services limit="6" columns="3" start_date="2026-01-01"]</code></span>
			</p>
			<p><code>[scbl_next_service]</code> <?php esc_html_e( 'featured card for the next upcoming service.', 'seedcast-bulletin-library' ); ?></p>
			<p><code>[scbl_announcements]</code> <?php esc_html_e( 'current and upcoming announcements as a grid.', 'seedcast-bulletin-library' ); ?></p>
			<p><code>[scbl_announcements grouped="true"]</code> <?php esc_html_e( 'the same cards under Upcoming and Ongoing headings, for a page that replaces an events calendar. Use first="upcoming" (the default) or first="ongoing" to choose which comes first.', 'seedcast-bulletin-library' ); ?></p>
		</div>
		<?php
	}

	public function schedule_flush(): void {
		update_option( 'scbl_flush_rewrite_rules', '1' );
	}
}
