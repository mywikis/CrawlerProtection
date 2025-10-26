<?php

// Basic stubs for MediaWiki testing

// Stub constant - set to newer version to avoid class_alias issues
if ( !defined('MW_VERSION') ) {
    define('MW_VERSION', '1.45.0');
}

// Stub function for wfMessage
if ( !function_exists( 'wfMessage' ) ) {
    function wfMessage( $key ) {
        return new class {
            public function plain() { return 'Mock message'; }
        };
    }
}
