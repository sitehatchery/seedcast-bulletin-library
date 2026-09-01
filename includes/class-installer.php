<?php
namespace SeedcastBulletinLibrary;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Activation, deactivation, and Bulletin Library's own option defaults.
 *
 * There is no migration code here. Bulletin Library has never been released, so there is
 * no site anywhere holding the old Bulletin Library's earlier shared options or the old
 * submissions table, and carrying migration code for a state that does not
 * exist is how a codebase accumulates permanent confusion about which keys are
 * actually current.
 */
class Installer {

	public static function activate(): void {
		self::set_defaults();

		flush_rewrite_rules();
		update_option( 'scbl_flush_rewrite_rules', '1' );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Bulletin Library's own defaults. The shared ones (theme, captcha, honeypot) belong
	 * to the core library and are seeded there.
	 */
	public static function set_defaults(): void {
		$defaults = [
			'scbl_services_per_page'     => '10',
			'scbl_service_slug'          => 'service',
			'scbl_service_block_editor'  => '0',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}
}
