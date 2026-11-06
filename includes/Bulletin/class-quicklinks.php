<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Quick Links: the few pages a visitor asks for by name.
 *
 * A printed bulletin carries them on the back page. What we believe, who
 * leads, what happens if you turn up on a Sunday for the first time. The
 * links belong to the church rather than to any one service, so they are
 * entered once in settings, show on every service page, and go anywhere else
 * on the site through [scbl_quick_links].
 *
 * Storage is one option holding an ordered list of { label, url }. Only rows
 * with an address are kept, which is what lets the settings screen offer the
 * usual labels as blank prompts without inventing links the church has not
 * got.
 */
class QuickLinks {

	/** Ordered list of { label, url }. */
	const OPTION = 'scbl_quick_links';

	/** Whether the list shows on service pages. */
	const OPTION_ON_SERVICES = 'scbl_quick_links_on_services';

	/** The church's own name for the list, if they want one. */
	const OPTION_HEADING = 'scbl_quick_links_heading';

	/** Enough for any bulletin, and a ceiling on what a bad POST can store. */
	const MAX = 25;

	public function init(): void {
		add_filter( 'scbl/service/sections', [ $this, 'section' ], 60, 3 );
	}

	/**
	 * The labels offered as blank rows on the settings screen: the pages
	 * churches point visitors to most. Blank rows are never stored, so these
	 * stay on offer until they are filled in.
	 *
	 * @return string[]
	 */
	public static function suggested(): array {
		return [
			__( 'Statement of Faith', 'seedcast-bulletin-library' ),
			__( 'Mission', 'seedcast-bulletin-library' ),
			__( 'Vision', 'seedcast-bulletin-library' ),
			__( 'Leadership', 'seedcast-bulletin-library' ),
			__( 'Ministries', 'seedcast-bulletin-library' ),
			__( 'What to Expect on Your Visit', 'seedcast-bulletin-library' ),
		];
	}

	/**
	 * The saved links, in order.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$url   = trim( (string) ( $row['url'] ?? '' ) );
			if ( '' === $label || '' === $url ) {
				continue;
			}
			$out[] = [ 'label' => $label, 'url' => $url ];
		}
		return $out;
	}

	/** Whether anything is filled in. */
	public static function has_links(): bool {
		return [] !== self::all();
	}

	/** The heading, the church's own if they set one. */
	public static function heading(): string {
		$heading = trim( (string) get_option( self::OPTION_HEADING, '' ) );
		return '' !== $heading ? $heading : __( 'Quick Links', 'seedcast-bulletin-library' );
	}

	/** Whether the list belongs on service pages. On unless turned off. */
	public static function show_on_services(): bool {
		return (bool) get_option( self::OPTION_ON_SERVICES, 1 );
	}

	/**
	 * The rows the settings screen shows: what is saved, then whichever
	 * suggested labels are still going spare, so the prompt to fill one in
	 * does not disappear the moment the first link is saved.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function rows_for_editing(): array {
		$rows  = self::all();
		$taken = [];
		foreach ( $rows as $row ) {
			$taken[] = strtolower( $row['label'] );
		}

		foreach ( self::suggested() as $label ) {
			if ( ! in_array( strtolower( $label ), $taken, true ) ) {
				$rows[] = [ 'label' => $label, 'url' => '' ];
			}
		}
		return $rows;
	}

	/**
	 * Settings API sanitizer. A row with no address is a prompt rather than a
	 * link and is dropped. A row with an address but no text is named after
	 * the page it points at, so a link is never lost to an empty label.
	 *
	 * @param mixed $raw Rows as posted.
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function sanitize( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX ) {
				continue;
			}

			$url = esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) );
			if ( '' === $url ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label ) {
				$label = (string) get_the_title( url_to_postid( $url ) );
			}
			if ( '' === $label ) {
				continue;
			}

			$out[] = [ 'label' => $label, 'url' => $url ];
		}
		return $out;
	}

	/**
	 * The list, as it renders on a service page and from the shortcode.
	 *
	 * @param array $args {
	 *   @type string $heading Heading above the list. Pass an empty string for none.
	 *   @type string $class   Extra class on the section.
	 * }
	 * @return string Empty when no links are filled in.
	 */
	public static function render_panel( array $args = [] ): string {
		$links = self::all();
		if ( [] === $links ) {
			return '';
		}

		$heading = array_key_exists( 'heading', $args ) ? trim( (string) $args['heading'] ) : self::heading();
		$extra   = isset( $args['class'] ) ? sanitize_html_class( (string) $args['class'] ) : '';
		$classes = trim( 'scbl-section scbl-section--links ' . $extra );

		ob_start();
		?>
		<section class="<?php echo esc_attr( $classes ); ?>"<?php if ( '' === $heading ) : ?> aria-label="<?php esc_attr_e( 'Quick Links', 'seedcast-bulletin-library' ); ?>"<?php endif; ?>>
			<?php if ( '' !== $heading ) : ?>
				<h2 class="scbl-section__title"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<ul class="scbl-links">
				<?php foreach ( $links as $link ) : ?>
					<li class="scbl-links__item">
						<a class="scbl-links__link" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The list in the service page sidebar, under the visitor card. Weight 60
	 * keeps it below anything a suite plugin contributes there.
	 *
	 * @param array    $sections     Sections so far.
	 * @param \WP_Post $service      The service being rendered.
	 * @param string   $service_date Y-m-d of the service.
	 * @return array
	 */
	public function section( array $sections, \WP_Post $service, string $service_date ): array {
		if ( ! self::show_on_services() ) {
			return $sections;
		}

		$html = self::render_panel();
		if ( '' === $html ) {
			return $sections;
		}

		$sections[] = [
			'key'    => 'quick-links',
			'weight' => 60,
			'column' => 'sidebar',
			'html'   => $html,
		];
		return $sections;
	}

	/** The Quick Links card on the shared settings page. */
	public static function settings_card(): void {
		$rows = self::rows_for_editing();
		?>
		<div class="sc-settings-card">
			<h2><?php esc_html_e( 'Quick Links', 'seedcast-bulletin-library' ); ?></h2>
			<p class="description scbl-settings-desc">
				<?php esc_html_e( 'The pages a visitor asks for by name. Fill in the ones you have and leave the rest blank. Anything with an address shows on every service page, and the same list goes anywhere else with the Quick Links shortcode.', 'seedcast-bulletin-library' ); ?>
			</p>

			<div class="scbl-links-rows" data-scbl-links-rows>
				<?php foreach ( $rows as $i => $row ) : ?>
					<?php self::settings_row( (string) $i, $row ); ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button button-small" data-scbl-links-add><?php esc_html_e( 'Add a link', 'seedcast-bulletin-library' ); ?></button>
			</p>
			<template id="scbl-links-tpl"><?php self::settings_row( '__i__', [] ); ?></template>
			<?php self::pages_datalist(); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scbl_quick_links_heading"><?php esc_html_e( 'Heading', 'seedcast-bulletin-library' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="scbl_quick_links_heading" name="<?php echo esc_attr( self::OPTION_HEADING ); ?>"
							value="<?php echo esc_attr( (string) get_option( self::OPTION_HEADING, '' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Quick Links', 'seedcast-bulletin-library' ); ?>" />
						<p class="description"><?php esc_html_e( 'Shown above the list. Leave blank for Quick Links.', 'seedcast-bulletin-library' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service pages', 'seedcast-bulletin-library' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_ON_SERVICES ); ?>" value="1" <?php checked( self::show_on_services() ); ?> />
							<?php esc_html_e( 'Show Quick Links on service pages', 'seedcast-bulletin-library' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'They sit in the sidebar, under the visitor card. Turn this off to keep them for the pages you place the shortcode on.', 'seedcast-bulletin-library' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * One row: the text a visitor reads, and where it goes.
	 *
	 * @param string $index Row index, or __i__ in the template the script clones.
	 * @param array  $row   Saved row, if any.
	 */
	private static function settings_row( string $index, array $row ): void {
		$name = self::OPTION . '[' . $index . ']';
		?>
		<div class="scbl-links-row">
			<input type="text" class="scbl-links-row__label" name="<?php echo esc_attr( $name . '[label]' ); ?>"
				value="<?php echo esc_attr( (string) ( $row['label'] ?? '' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Link text', 'seedcast-bulletin-library' ); ?>"
				placeholder="<?php esc_attr_e( 'Link text', 'seedcast-bulletin-library' ); ?>" />
			<input type="url" class="scbl-links-row__url" name="<?php echo esc_attr( $name . '[url]' ); ?>"
				value="<?php echo esc_attr( (string) ( $row['url'] ?? '' ) ); ?>"
				list="scbl-links-pages"
				aria-label="<?php esc_attr_e( 'Address', 'seedcast-bulletin-library' ); ?>"
				placeholder="<?php esc_attr_e( 'Start typing a page name, or paste an address', 'seedcast-bulletin-library' ); ?>" />
			<button type="button" class="button-link scbl-links-row__remove" aria-label="<?php esc_attr_e( 'Remove this link', 'seedcast-bulletin-library' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * The site's published pages, so the address field completes as the admin
	 * types a page name rather than sending them off to copy a URL.
	 */
	private static function pages_datalist(): void {
		$pages = get_pages(
			[
				'post_status' => 'publish',
				'number'      => 200,
				'sort_column' => 'post_title',
			]
		);
		if ( ! is_array( $pages ) || [] === $pages ) {
			return;
		}
		?>
		<datalist id="scbl-links-pages">
			<?php foreach ( $pages as $page ) : ?>
				<option value="<?php echo esc_url( (string) get_permalink( $page ) ); ?>" label="<?php echo esc_attr( (string) get_the_title( $page ) ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<?php
	}
}
