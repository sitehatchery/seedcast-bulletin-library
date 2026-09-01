<?php
namespace SeedcastBulletinLibrary\Bulletin;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Saved contacts for announcements: { name, email, phone }.
 *
 * This started life in the shared library, which was wrong. Nothing else in
 * the suite uses it. It exists so that typing "Sarah" into an announcement
 * fills in her email and phone, which makes it Sunday's, and it stays here
 * until something else actually needs it.
 *
 * Design notes:
 *
 * - Storage is one WordPress option holding an array. Deliberate: this is a
 *   typing aid, not a first-class concept. A CPT would add a menu, columns,
 *   revisions and a permalink for what is a phone book entry.
 *
 * - Name is the index. Two records with the same name are considered
 *   the same person; typing an existing name pulls in the stored
 *   email/phone. This mirrors how a phone book works.
 *
 * - When an admin saves an announcement with a contact, the contact
 *   record on the announcement itself is stored independently (in the
 *   its own meta). Editing this store later doesn't touch
 *   past announcements. This store is ONLY the autocomplete source.
 *
 * - Deleting from this store removes the autocomplete suggestion but
 *   doesn't affect any announcements. Safe cleanup.
 */
class Contacts {

	const OPTION = 'scbl_contacts';

	/**
	 * @return array<int, array{name:string,email:string,phone:string}>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, [] );
		if ( ! is_array( $raw ) ) return [];
		$out = [];
		foreach ( $raw as $r ) {
			if ( ! is_array( $r ) ) continue;
			$out[] = [
				'name'  => (string) ( $r['name']  ?? '' ),
				'email' => (string) ( $r['email'] ?? '' ),
				'phone' => (string) ( $r['phone'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * Upsert by name. If a record with the same (case-insensitive) name
	 * already exists, its email/phone are updated. Otherwise a new one
	 * is appended. Empty names are ignored - silently, since this is
	 * called from announcement save and we don't want to error on empty
	 * contact sections.
	 */
	/**
	 * A saved contact by name, or null. Name is the index, so this is how you
	 * tell an add from an update.
	 *
	 * @param string $name Contact name.
	 * @return array|null
	 */
	public static function find( string $name ): ?array {
		foreach ( self::all() as $contact ) {
			if ( strcasecmp( $contact['name'], $name ) === 0 ) {
				return $contact;
			}
		}
		return null;
	}

	public static function upsert( string $name, string $email, string $phone ): void {
		$name = trim( $name );
		if ( $name === '' ) return;

		$all = self::all();
		$key = strtolower( $name );
		$found = false;
		foreach ( $all as &$r ) {
			if ( strtolower( $r['name'] ) === $key ) {
				$r['email'] = $email;
				$r['phone'] = $phone;
				$found = true;
				break;
			}
		}
		unset( $r );
		if ( ! $found ) {
			$all[] = [ 'name' => $name, 'email' => $email, 'phone' => $phone ];
		}
		update_option( self::OPTION, $all );
	}

	/**
	 * Replace one record identified by its (case-insensitive) name. Used
	 * by the Contact Lookup admin screen for edits.
	 */
	public static function update_by_name( string $original_name, array $updated ): void {
		$all = self::all();
		$key = strtolower( trim( $original_name ) );
		foreach ( $all as &$r ) {
			if ( strtolower( $r['name'] ) === $key ) {
				$r['name']  = (string) ( $updated['name']  ?? $r['name'] );
				$r['email'] = (string) ( $updated['email'] ?? $r['email'] );
				$r['phone'] = (string) ( $updated['phone'] ?? $r['phone'] );
				break;
			}
		}
		unset( $r );
		update_option( self::OPTION, $all );
	}

	public static function delete_by_name( string $name ): void {
		$key = strtolower( trim( $name ) );
		$out = array_values( array_filter( self::all(), static fn( $r ) => strtolower( $r['name'] ) !== $key ) );
		update_option( self::OPTION, $out );
	}
}
