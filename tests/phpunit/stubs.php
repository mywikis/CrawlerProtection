<?php

// Basic stubs for MediaWiki testing

// Stub constant - set to newer version to avoid class_alias issues
if ( !defined( 'MW_VERSION' ) ) {
	define( 'MW_VERSION', '1.45.0' );
}

// Stub function for wfMessage - only define if not already defined
if ( !function_exists( 'wfMessage' ) ) {
	/**
	 * Stub for MediaWiki's wfMessage function
	 *
	 * @param string $key Message key
	 * @return object Mock message object
	 */
	function wfMessage( $key ) {
		return new class() {
			/**
			 * Return plain text version of message
			 *
			 * @return string
			 */
			public function plain() {
				return 'Mock message';
			}
		};
	}
}
