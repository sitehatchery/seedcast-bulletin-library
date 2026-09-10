<?php
/**
 * Global helpers.
 *
 * Kept out of the namespaced plugin file so they resolve from every
 * namespace in the plugin, the same arrangement as Sermon Library's
 * includes/helpers.php.
 *
 * @package SeedcastBulletinLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version string for one of this plugin's own assets: the plugin version plus
 * the file's modified time. Every edit to a stylesheet or script then gets a
 * new URL, so neither a browser nor a page cache can keep serving the copy
 * from before it, which a bare ?ver=3.8.0 lets them do. The same approach as
 * Sermon Library's scsl_asset_version().
 *
 * @param string $relative Path from the plugin root, e.g. 'assets/css/scbl-main.css'.
 * @return string
 */
function scbl_asset_version( string $relative ): string {
	$path = SCBL_PLUGIN_DIR . ltrim( $relative, '/' );
	if ( is_readable( $path ) ) {
		$mtime = filemtime( $path );
		if ( $mtime ) {
			return SCBL_VERSION . '.' . $mtime;
		}
	}
	return SCBL_VERSION;
}
