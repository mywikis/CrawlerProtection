#!/usr/bin/env bash
# Check that i18n/en.json and i18n/qqq.json describe the same set of message
# keys: every message must be documented, and qqq must not document messages
# that no longer exist.  Exits with code 1 when the two sets differ so that CI
# can enforce the MediaWiki "MUST" requirement.

set -euo pipefail

EXTENSION_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

php -- "$EXTENSION_ROOT/i18n/en.json" "$EXTENSION_ROOT/i18n/qqq.json" << 'PHP'
<?php
/**
 * @param string $path
 * @return string[]
 */
function crawlerProtectionMessageKeys( string $path ): array {
	$contents = file_get_contents( $path );
	if ( $contents === false ) {
		fwrite( STDERR, "ERROR: Unable to read $path\n" );
		exit( 1 );
	}

	$data = json_decode( $contents, true );
	if ( !is_array( $data ) ) {
		fwrite( STDERR, "ERROR: $path is not valid JSON\n" );
		exit( 1 );
	}

	$keys = array_keys( $data );
	return array_values( array_filter(
		$keys,
		static function ( $key ) {
			return $key !== '@metadata';
		}
	) );
}

[ , $enPath, $qqqPath ] = $argv;

$enKeys = crawlerProtectionMessageKeys( $enPath );
$qqqKeys = crawlerProtectionMessageKeys( $qqqPath );

$status = 0;

$missing = array_diff( $enKeys, $qqqKeys );
if ( $missing ) {
	sort( $missing );
	echo "ERROR: Keys present in en.json but missing from qqq.json:\n";
	foreach ( $missing as $key ) {
		echo "  - $key\n";
	}
	$status = 1;
}

$orphaned = array_diff( $qqqKeys, $enKeys );
if ( $orphaned ) {
	sort( $orphaned );
	echo "ERROR: Keys documented in qqq.json but absent from en.json:\n";
	foreach ( $orphaned as $key ) {
		echo "  - $key\n";
	}
	$status = 1;
}

if ( $status === 0 ) {
	printf( "OK: All %d message key(s) from en.json are documented in qqq.json.\n", count( $enKeys ) );
}

exit( $status );
PHP
