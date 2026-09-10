<?php
namespace SeedcastBulletinLibrary\Bulletin;

use Seedcast\Core\Frontend\ChurchDetails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode generator.
 *
 * Builds a Bulletin Library shortcode by picking options rather than by
 * remembering attribute syntax, in the same shape as the Sermon Library and
 * Visitor Card generators, so somebody running the suite meets one screen
 * three times rather than three different screens.
 *
 * Every control carries the shortcode's own default in data-default, and the
 * script leaves an attribute out while it still matches, so what gets copied
 * is as short as the choices allow.
 */
class ShortcodeGenerator {

	const PAGE_SLUG = 'seedcast-bulletin-library-shortcodes';

	/** Church detail fields shown when the shortcode names none, mirroring ChurchDetails. */
	const CHURCH_DEFAULT_FIELDS = [ 'name', 'address', 'times', 'phone', 'email', 'directions' ];

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 15 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	public function add_page(): void {
		add_submenu_page(
			AdminMenu::MENU_SLUG,
			__( 'Shortcode Generator', 'seedcast-bulletin-library' ),
			__( 'Shortcodes', 'seedcast-bulletin-library' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * The builder script, on this screen only. The admin stylesheet it needs
	 * is already loaded on every Bulletin Library screen.
	 *
	 * @param string $hook Current screen.
	 */
	public function assets( $hook ): void {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_script( 'scbl-shortcodes', SCBL_PLUGIN_URL . 'assets/js/scbl-shortcodes.js', [], scbl_asset_version( 'assets/js/scbl-shortcodes.js' ), true );
		wp_localize_script(
			'scbl-shortcodes',
			'scblSc',
			[
				'copied' => __( 'Copied', 'seedcast-bulletin-library' ),
				'copy'   => __( 'Copy shortcode', 'seedcast-bulletin-library' ),
			]
		);
	}

	/**
	 * What can be built. The key names the options panel; tag is the
	 * shortcode it produces.
	 *
	 * @return array<string, array{tag: string, icon: string, label: string, desc: string}>
	 */
	private static function displays(): array {
		return [
			'announcements' => [
				'tag'   => 'scbl_announcements',
				'icon'  => '📣',
				'label' => __( 'Announcements', 'seedcast-bulletin-library' ),
				'desc'  => __( 'Current and upcoming announcements, as one grid or grouped for an events page', 'seedcast-bulletin-library' ),
			],
			'services'      => [
				'tag'   => 'scbl_services',
				'icon'  => '⊞',
				'label' => __( 'Services', 'seedcast-bulletin-library' ),
				'desc'  => __( 'A grid of your service cards', 'seedcast-bulletin-library' ),
			],
			'next_service'  => [
				'tag'   => 'scbl_next_service',
				'icon'  => '⚡',
				'label' => __( 'Next service', 'seedcast-bulletin-library' ),
				'desc'  => __( 'A featured card for the next upcoming service', 'seedcast-bulletin-library' ),
			],
			'church'        => [
				'tag'   => ChurchDetails::SHORTCODE,
				'icon'  => '⛪',
				'label' => __( 'Church details', 'seedcast-bulletin-library' ),
				'desc'  => __( 'Name, address, service times and contact details, kept in one place', 'seedcast-bulletin-library' ),
			],
		];
	}

	/**
	 * Labels for the church detail fields, in the order the shortcode lists
	 * them. Built from ChurchDetails::FIELDS so a field added there shows up
	 * here, even before it has a friendlier label.
	 *
	 * @return array<string, string>
	 */
	private static function church_fields(): array {
		$labels = [
			'name'       => __( 'Church name', 'seedcast-bulletin-library' ),
			'address'    => __( 'Address', 'seedcast-bulletin-library' ),
			'times'      => __( 'Service times', 'seedcast-bulletin-library' ),
			'phone'      => __( 'Phone', 'seedcast-bulletin-library' ),
			'email'      => __( 'Email', 'seedcast-bulletin-library' ),
			'events'     => __( 'Link to what is coming up', 'seedcast-bulletin-library' ),
			'note'       => __( 'Note for visitors', 'seedcast-bulletin-library' ),
			'directions' => __( 'Directions link', 'seedcast-bulletin-library' ),
		];

		$fields = [];
		foreach ( ChurchDetails::FIELDS as $field ) {
			$fields[ $field ] = $labels[ $field ] ?? ucfirst( $field );
		}
		return $fields;
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$displays = self::displays();
		$first    = (string) array_key_first( $displays );
		?>
		<div class="wrap scbl-sc-wrap">
			<h1><?php esc_html_e( 'Shortcode Generator', 'seedcast-bulletin-library' ); ?></h1>
			<p class="scbl-sc-intro">
				<?php esc_html_e( 'Choose what you want to display, set the options, then copy the shortcode into any page or post.', 'seedcast-bulletin-library' ); ?>
			</p>

			<div class="scbl-sc-layout">
				<div class="scbl-sc-builder">

					<div class="scbl-sc-card">
						<h2 class="scbl-sc-card__title">
							<span class="scbl-sc-step">1</span>
							<?php esc_html_e( 'What do you want to display?', 'seedcast-bulletin-library' ); ?>
						</h2>
						<div class="scbl-sc-options">
							<?php foreach ( $displays as $key => $display ) : ?>
								<label class="scbl-sc-option<?php echo $key === $first ? ' is-selected' : ''; ?>">
									<input type="radio" name="scbl_sc_display" class="scbl-sc-radio"
										value="<?php echo esc_attr( $key ); ?>"
										data-tag="<?php echo esc_attr( $display['tag'] ); ?>"
										<?php checked( $key, $first ); ?> />
									<span class="scbl-sc-option__icon" aria-hidden="true"><?php echo esc_html( $display['icon'] ); ?></span>
									<span class="scbl-sc-option__body">
										<strong><?php echo esc_html( $display['label'] ); ?></strong>
										<span><?php echo esc_html( $display['desc'] ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="scbl-sc-card">
						<h2 class="scbl-sc-card__title">
							<span class="scbl-sc-step">2</span>
							<?php esc_html_e( 'Options', 'seedcast-bulletin-library' ); ?>
						</h2>

						<div class="scbl-sc-panel" data-for="announcements">
							<table class="scbl-sc-table">
								<tr>
									<th><label for="scbl-sc-grouped"><?php esc_html_e( 'Layout', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<select id="scbl-sc-grouped" data-param="grouped" data-default="false">
											<option value="false"><?php esc_html_e( 'One grid', 'seedcast-bulletin-library' ); ?></option>
											<option value="true"><?php esc_html_e( 'Grouped: Upcoming and Ongoing', 'seedcast-bulletin-library' ); ?></option>
										</select>
										<p class="description">
											<?php esc_html_e( 'Grouped suits a page that replaces an events calendar. Upcoming holds One Time, Consecutive and Staggered announcements, soonest first; Ongoing holds the rest.', 'seedcast-bulletin-library' ); ?>
										</p>
									</td>
								</tr>
								<tr data-show-when="grouped=true">
									<th><label for="scbl-sc-first"><?php esc_html_e( 'Which comes first', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<select id="scbl-sc-first" data-param="first" data-default="upcoming">
											<option value="upcoming"><?php esc_html_e( 'Upcoming', 'seedcast-bulletin-library' ); ?></option>
											<option value="ongoing"><?php esc_html_e( 'Ongoing', 'seedcast-bulletin-library' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="scbl-sc-ann-columns"><?php esc_html_e( 'Columns', 'seedcast-bulletin-library' ); ?></label></th>
									<td><?php $this->columns_select( 'scbl-sc-ann-columns' ); ?></td>
								</tr>
							</table>
						</div>

						<div class="scbl-sc-panel" data-for="services" hidden>
							<table class="scbl-sc-table">
								<tr>
									<th><label for="scbl-sc-limit"><?php esc_html_e( 'How many', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<input type="number" id="scbl-sc-limit" class="small-text" min="1" step="1"
											data-param="limit" data-default="10" placeholder="10" />
									</td>
								</tr>
								<tr>
									<th><label for="scbl-sc-svc-columns"><?php esc_html_e( 'Columns', 'seedcast-bulletin-library' ); ?></label></th>
									<td><?php $this->columns_select( 'scbl-sc-svc-columns' ); ?></td>
								</tr>
								<tr>
									<th><label for="scbl-sc-start"><?php esc_html_e( 'Leave out services before', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<input type="date" id="scbl-sc-start" data-param="start_date" data-default="" />
										<p class="description"><?php esc_html_e( 'Optional.', 'seedcast-bulletin-library' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="scbl-sc-panel" data-for="next_service" hidden>
							<p class="scbl-sc-note">
								<?php esc_html_e( 'Nothing to set. It shows the next service by date as a featured card, and moves on by itself each week.', 'seedcast-bulletin-library' ); ?>
							</p>
						</div>

						<div class="scbl-sc-panel" data-for="church" hidden>
							<table class="scbl-sc-table">
								<tr>
									<th><?php esc_html_e( 'Show', 'seedcast-bulletin-library' ); ?></th>
									<td>
										<fieldset class="scbl-sc-checks" data-param="show" data-default="<?php echo esc_attr( implode( ',', self::CHURCH_DEFAULT_FIELDS ) ); ?>">
											<legend class="screen-reader-text"><?php esc_html_e( 'Details to show', 'seedcast-bulletin-library' ); ?></legend>
											<?php foreach ( self::church_fields() as $field => $label ) : ?>
												<label>
													<input type="checkbox" value="<?php echo esc_attr( $field ); ?>"
														<?php checked( in_array( $field, self::CHURCH_DEFAULT_FIELDS, true ) ); ?> />
													<?php echo esc_html( $label ); ?>
												</label>
											<?php endforeach; ?>
										</fieldset>
										<p class="description">
											<?php esc_html_e( 'The details come from Seedcast > Church, so a change there reaches every place this appears.', 'seedcast-bulletin-library' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><label for="scbl-sc-heading"><?php esc_html_e( 'Heading', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<input type="text" id="scbl-sc-heading" class="regular-text" data-param="heading" data-default=""
											placeholder="<?php esc_attr_e( 'Optional', 'seedcast-bulletin-library' ); ?>" />
									</td>
								</tr>
								<tr>
									<th><label for="scbl-sc-layout"><?php esc_html_e( 'Layout', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<select id="scbl-sc-layout" data-param="layout" data-default="stacked">
											<option value="stacked"><?php esc_html_e( 'Stacked', 'seedcast-bulletin-library' ); ?></option>
											<option value="inline"><?php esc_html_e( 'Inline', 'seedcast-bulletin-library' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th><label for="scbl-sc-class"><?php esc_html_e( 'Extra CSS class', 'seedcast-bulletin-library' ); ?></label></th>
									<td>
										<input type="text" id="scbl-sc-class" class="regular-text" data-param="class" data-default=""
											placeholder="<?php esc_attr_e( 'Optional', 'seedcast-bulletin-library' ); ?>" />
									</td>
								</tr>
							</table>
						</div>
					</div>

				</div>

				<div class="scbl-sc-output-card">
					<h2><?php esc_html_e( 'Your shortcode', 'seedcast-bulletin-library' ); ?></h2>
					<div class="scbl-sc-output-code-wrap">
						<code class="scbl-sc-code" id="scbl-sc-code">[<?php echo esc_html( $displays[ $first ]['tag'] ); ?>]</code>
					</div>
					<button type="button" class="button button-primary scbl-sc-copy-btn" id="scbl-sc-copy">
						<?php esc_html_e( 'Copy shortcode', 'seedcast-bulletin-library' ); ?>
					</button>

					<hr class="scbl-sc-divider" />

					<h3><?php esc_html_e( 'How to use it', 'seedcast-bulletin-library' ); ?></h3>
					<ol class="scbl-sc-instructions">
						<li><?php esc_html_e( 'Copy the shortcode above', 'seedcast-bulletin-library' ); ?></li>
						<li><?php esc_html_e( 'Open the page or post it belongs on', 'seedcast-bulletin-library' ); ?></li>
						<li><?php esc_html_e( 'Add a Shortcode block, or paste it straight into the editor', 'seedcast-bulletin-library' ); ?></li>
						<li><?php esc_html_e( 'Update the page', 'seedcast-bulletin-library' ); ?></li>
					</ol>

					<hr class="scbl-sc-divider" />

					<h3><?php esc_html_e( 'All shortcodes', 'seedcast-bulletin-library' ); ?></h3>
					<table class="scbl-sc-ref-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Shortcode', 'seedcast-bulletin-library' ); ?></th>
								<th><?php esc_html_e( 'What it displays', 'seedcast-bulletin-library' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $displays as $display ) : ?>
								<tr>
									<td><code>[<?php echo esc_html( $display['tag'] ); ?>]</code></td>
									<td><?php echo esc_html( $display['label'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<hr class="scbl-sc-divider" />

					<h3><?php esc_html_e( 'Using Elementor?', 'seedcast-bulletin-library' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Drop a Shortcode widget where it should go and paste the shortcode in. Church details also has its own Church Details widget.', 'seedcast-bulletin-library' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/** A 1 to 4 columns select, defaulting to the shortcodes' own 3. */
	private function columns_select( string $id ): void {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" data-param="columns" data-default="3">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( 3, $i ); ?>><?php echo esc_html( (string) $i ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
	}
}
