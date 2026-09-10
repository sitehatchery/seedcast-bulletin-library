<?php
/**
 * Plugin Name: Seedcast Bulletin Library
 * Plugin URI:  https://seedcast.ai/bulletin-library/
 * Description: A searchable archive of every week's service bulletin. Bulletin Library assembles programs, announcements, and handouts into a single dated page and preserves the story of each Sunday over time.
 * Version:     3.8.0
 * Author:      Seedcast
 * Author URI:  https://seedcast.ai
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seedcast-bulletin-library
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package SeedcastBulletinLibrary
 */

namespace SeedcastBulletinLibrary;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SCBL_VERSION',         '3.8.0' );
define( 'SCBL_PLUGIN_FILE',     __FILE__ );
define( 'SCBL_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'SCBL_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'SCBL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The core version this copy bundles, and the minimum it needs to run all of
 * its features. They are the same today; they diverge as soon as another
 * plugin ships a newer core and this one has not caught up yet.
 */
define( 'SCBL_CORE_VERSION',     '1.26.2' );
define( 'SCBL_CORE_MIN_VERSION', '1.24.4' );

/*
 * Declare this copy of the shared library. Runs immediately rather than on a
 * hook: every plugin main file executes before plugins_loaded, so by the time
 * the registry loads at priority 0 all copies have declared themselves and the
 * highest version wins. Nothing here loads code; it only registers a candidate.
 */
require_once SCBL_PLUGIN_DIR . 'lib/seedcast-core/loader.php';
\Seedcast_Core_Registry::register(
	SCBL_CORE_VERSION,
	SCBL_PLUGIN_DIR . 'lib/seedcast-core/bootstrap.php'
);

require_once SCBL_PLUGIN_DIR . 'includes/class-autoloader.php';
require_once SCBL_PLUGIN_DIR . 'includes/helpers.php';
Autoloader::register();

final class Sunday {

	private static ?Sunday $instance = null;

	public static function instance(): Sunday {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks(): void {
		add_action( 'init', [ $this, 'register_with_core' ], 0 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 99 );
		add_action( 'upgrader_process_complete', [ $this, 'on_upgrade' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		// Front-end CSS is enqueued conditionally from the Frontend module
		// (class-frontend.php) rather than unconditionally here, so pages
		// with no Bulletin Library content don't carry a stylesheet.
		add_action( 'elementor/preview/enqueue_styles', [ $this, 'elementor_assets' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'elementor_assets' ] );

		( new Bulletin\Bulletin() )->init();
	}

	/**
	 * Declare Bulletin Library to the shared library, so core knows to load
	 * shared admin assets on Bulletin Library's screens and can report a
	 * version mismatch rather than letting a missing feature fail silently.
	 *
	 * Runs on init (priority 0) rather than during construction because
	 * core registration is the kind of thing WordPress expects to happen
	 * on init - and it keeps the door closed to accidentally introducing
	 * translated strings here that would trigger the "translations loaded
	 * too early" notice on WordPress 6.7+.
	 */
	public function register_with_core(): void {
		if ( ! class_exists( '\\Seedcast_Core' ) ) return;

		\Seedcast_Core::register_plugin( 'seedcast-bulletin-library', [
			'name'        => 'Bulletin Library',
			'tagline'     => 'The bulletin, alive online. Weekly service pages that gather programs, announcements, and handouts.',
			'icon'        => 'dashicons-calendar-alt',
			'admin'       => 'admin.php?page=seedcast-bulletin-library',
			'channel'     => 'free',
			'file'        => 'seedcast-bulletin-library/seedcast-bulletin-library.php',
			'version'     => SCBL_VERSION,
			'admin_menu'  => 'seedcast-bulletin-library',
			'post_types'  => [ 'scbl_service', 'scbl_announcement', 'scbl_program' ],
			'submissions' => false, // No front-end submission forms, so the shared engine stays unhooked.
			'min_core'    => SCBL_CORE_MIN_VERSION,
		] );
	}

	public function maybe_flush_rewrite_rules(): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
		if ( get_option( 'scbl_flush_rewrite_rules' ) ) {
			delete_option( 'scbl_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}

	public function on_upgrade( $upgrader, array $options ): void {
		if (
			isset( $options['action'], $options['type'], $options['plugins'] ) &&
			$options['action'] === 'update' &&
			$options['type']   === 'plugin' &&
			in_array( SCBL_PLUGIN_BASENAME, (array) $options['plugins'], true )
		) {
			update_option( 'scbl_flush_rewrite_rules', '1' );
		}
	}

	/**
	 * Bulletin Library's own admin assets. The shared chrome (settings page,
	 * suite grid, media field, moderation queue) is enqueued by core on any
	 * screen a registered plugin owns, so nothing here duplicates it. Living
	 * Bulletin's admin CSS declares the core handle as a dependency so load
	 * order is guaranteed.
	 */
	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) return;

		$owned    = [ 'scbl_service', 'scbl_announcement', 'scbl_program' ];
		$is_bl    = false !== strpos( $hook, 'seedcast-bulletin-library' ) || in_array( $screen->post_type, $owned, true );
		$is_scset = false !== strpos( $hook, 'seedcast-settings' );
		if ( ! $is_bl && ! $is_scset ) return;

		wp_enqueue_style(
			'scbl-admin',
			SCBL_PLUGIN_URL . 'assets/css/scbl-admin.css',
			[ 'seedcast-core-admin' ],
			scbl_asset_version( 'assets/css/scbl-admin.css' )
		);

		// Service editor only.
		if ( $screen->post_type === 'scbl_service' && in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
			wp_enqueue_script(
				'scbl-service-admin',
				SCBL_PLUGIN_URL . 'assets/js/scbl-service-admin.js',
				[],
				scbl_asset_version( 'assets/js/scbl-service-admin.js' ),
				true
			);
			wp_enqueue_script(
				'scbl-handouts',
				SCBL_PLUGIN_URL . 'assets/js/scbl-handouts.js',
				[ 'media-editor' ],
				scbl_asset_version( 'assets/js/scbl-handouts.js' ),
				true
			);
			// The handouts picker uses wp.media, which is not loaded by default
			// on custom post editors and must be enqueued explicitly.
			wp_enqueue_media();
		}

		// Seedcast settings page: enqueue the fallback-image picker and
		// the media library it uses.
		if ( $is_scset ) {
			wp_enqueue_script(
				'scbl-settings',
				SCBL_PLUGIN_URL . 'assets/js/scbl-settings.js',
				[ 'media-editor' ],
				scbl_asset_version( 'assets/js/scbl-settings.js' ),
				true
			);
			wp_localize_script(
				'scbl-settings',
				'scblDefaultImageL10n',
				[
					'pickTitle'  => __( 'Choose fallback image', 'seedcast-bulletin-library' ),
					'pickButton' => __( 'Use this image', 'seedcast-bulletin-library' ),
				]
			);
			wp_enqueue_media();
		}
	}

	public function elementor_assets(): void {
		if ( get_option( 'scbl_disable_frontend_css', false ) ) return;
		wp_enqueue_style( 'scbl-main', SCBL_PLUGIN_URL . 'assets/css/scbl-main.css', [], scbl_asset_version( 'assets/css/scbl-main.css' ) );
	}
}

register_activation_hook(   SCBL_PLUGIN_FILE, [ Installer::class, 'activate'   ] );
register_deactivation_hook( SCBL_PLUGIN_FILE, [ Installer::class, 'deactivate' ] );

// Priority 5: after the core registry loads at 0, so core is present.
add_action( 'plugins_loaded', function() {
	Sunday::instance();
}, 5 );
