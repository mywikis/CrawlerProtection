<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

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
	 * @return \stdClass Mock message object
	 */
	function wfMessage( $key ) {
		return new class() {
			/**
			 * Return self for chained calls
			 *
			 * @return static
			 */
			public function inContentLanguage() {
				return $this;
			}

			/**
			 * Return plain text version of message
			 *
			 * @return string
			 */
			public function plain() {
				return 'Mock message';
			}

			/**
			 * Return text version of message
			 *
			 * @return string
			 */
			public function text() {
				return 'Mock message';
			}
		};
	}
}
