<?php

namespace MediaWiki\Extension\CrawlerProtection;

class Compat {

    public static function init(): void {
        self::aliasCoreClasses();
    }

    private static function aliasCoreClasses(): void {

		if ( class_exists( \Title::class ) && !class_exists( \MediaWiki\Title\Title::class ) ) {
			class_alias( \Title::class, \MediaWiki\Title\Title::class ); /* < 1.40 */
		}
		if ( class_exists( \OutputPage::class ) && !class_exists( \MediaWiki\Output\OutputPage::class ) ) {
			class_alias( \OutputPage::class, \MediaWiki\Output\OutputPage::class ); /* < 1.41 */
		}
		if ( class_exists( \WebRequest::class ) && !class_exists( \MediaWiki\Request\WebRequest::class ) ) {
			class_alias( \WebRequest::class, \MediaWiki\Request\WebRequest::class ); /* < 1.41 */
		}
		if ( class_exists( \SpecialPage::class ) && !class_exists( \MediaWiki\SpecialPage\SpecialPage::class ) ) {
			class_alias( \SpecialPage::class, \MediaWiki\SpecialPage\SpecialPage::class ); /* < 1.41 */
		}
		if ( class_exists( \User::class ) && !class_exists( \MediaWiki\User\User::class ) ) {
			class_alias( \User::class, \MediaWiki\User\User::class ); /* < 1.41 */
		}
		if ( class_exists( \ActionEntryPoint::class ) && !class_exists( \MediaWiki\Actions\ActionEntryPoint::class ) ) {
			class_alias( \ActionEntryPoint::class, \MediaWiki\Actions\ActionEntryPoint::class ); /* < 1.42 */
		}
		if ( class_exists( \Article::class ) && !class_exists( \MediaWiki\Page\Article::class ) ) {
			class_alias( \Article::class, \MediaWiki\Page\Article::class ); /* < 1.42 */
		}
    }
}
