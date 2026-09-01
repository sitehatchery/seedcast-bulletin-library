<?php
namespace SeedcastBulletinLibrary;

if ( ! defined( 'ABSPATH' ) ) exit;

class Autoloader {

	public static function register(): void {
		spl_autoload_register( [ new self(), 'load' ] );
	}

	/**
	 * Maps class base names to their actual filenames where the name
	 * cannot be derived mechanically (e.g. acronyms, compound words).
	 *
	 * @var array<string, string>
	 */
	private const FILE_MAP = [
		'AdminMenu'          => 'class-adminmenu.php',
		// Bulletin Library feature module - compound class names collapsed for
		// consistency with the rest of the codebase's file naming.
		'ServiceCPT'          => 'class-servicecpt.php',
		'ServiceList'         => 'class-servicelist.php',
		'AnnouncementCPT'     => 'class-announcementcpt.php',
		'ServiceEditor'       => 'class-serviceeditor.php',
		'AnnouncementEditor'  => 'class-announcementeditor.php',
		'AnnouncementList'    => 'class-announcementlist.php',
		'AnnouncementSection' => 'class-announcementsection.php',
		'SettingsSection'     => 'class-settingssection.php',
		'Completeness'        => 'class-completeness.php',
		'Contacts'            => 'class-contacts.php',
		'ContactsPage'        => 'class-contactspage.php',
		'Handouts'            => 'class-handouts.php',
		'ProgramCPT'          => 'class-programcpt.php',
		'ProgramEditor'       => 'class-programeditor.php',
		'ProgramList'         => 'class-programlist.php',
		'ProgramSection'      => 'class-programsection.php',
		'Programs'            => 'class-programs.php',
	];

	public function load( string $class ): void {
		if ( strpos( $class, 'SeedcastBulletinLibrary\\' ) !== 0 ) return;

		$relative = substr( $class, strlen( 'SeedcastBulletinLibrary\\' ) );
		$parts    = explode( '\\', $relative );

		$dir = SCBL_PLUGIN_DIR . 'includes/';
		if ( count( $parts ) > 1 ) {
			$dir .= implode( '/', array_slice( $parts, 0, -1 ) ) . '/';
		}

		$class_name = end( $parts );

		if ( isset( self::FILE_MAP[ $class_name ] ) ) {
			$filename = self::FILE_MAP[ $class_name ];
		} else {
			$filename = 'class-' . strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) ) . '.php';
		}

		$file = $dir . $filename;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
