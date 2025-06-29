<?php

namespace MediaWiki\Extension\CrawlerProtection;

class Setup {
	public static function registerClassAliases() {
		if ( version_compare( MW_VERSION, '1.40', '<' ) ) {
			class_alias( '\Title', '\MediaWiki\Title\Title' );
		}

		if ( version_compare( MW_VERSION, '1.41', '<' ) ) {
			class_alias( '\OutputPage', '\MediaWiki\Output\OutputPage' );
			class_alias( '\SpecialPage', '\MediaWiki\SpecialPage\SpecialPage' );
			class_alias( '\User', '\MediaWiki\User\User' );
			class_alias( '\WebRequest', '\MediaWiki\Request\WebRequest' );
		}

		if ( version_compare( MW_VERSION, '1.42', '<' ) ) {
			class_alias( '\MediaWiki', '\MediaWiki\Actions\ActionEntryPoint' );
		}

		if ( version_compare( MW_VERSION, '1.44', '<' ) ) {
			class_alias( '\Article', '\MediaWiki\Page\Article' );
		}
	}
}
