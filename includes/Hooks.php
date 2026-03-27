<?php

namespace MediaWiki\Extension\CrawlerProtection;

// Class aliases for multi-version compatibility.
// These need to be in global scope so phan can pick up on them,
// and before any use statements that make use of the namespaced names.
if ( version_compare( MW_VERSION, '1.39.4', '<' ) ) {
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

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class Hooks implements MediaWikiPerformActionHook, SpecialPageBeforeExecuteHook {
	/** @var string Prefix for special page names */
	private const SPECIAL_PAGE_PREFIX = 'Special:';

	/**
	 * Block sensitive page views for anonymous users via MediaWikiPerformAction.
	 * Handles:
	 *  - ?type=revision
	 *  - ?action=history
	 *  - ?diff=1234
	 *  - ?oldid=1234
	 *
	 * Special pages (e.g. Special:WhatLinksHere) are handled separately.
	 *
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @param ActionEntryPoint $mediaWiki
	 * @return bool False to abort further action
	 */
	public function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$mediaWiki
	) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$type = $request->getVal( 'type' );
		$action = $request->getVal( 'action' );
		$diffId = (int)$request->getVal( 'diff' );
		$oldId = (int)$request->getVal( 'oldid' );

		if (
			!$user->isRegistered()
			&& (
				$type === 'revision'
				|| $action === 'history'
				|| $diffId > 0
				|| $oldId > 0
			)
		) {
			$this->denyAccess( $output );
		}

		return true;
	}

	/**
	 * Block Special:RecentChangesLinked, Special:WhatLinksHere, and Special:MobileDiff for anonymous users.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 * @return bool False to abort execution
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$user = $special->getContext()->getUser();
		if ( $user->isRegistered() ) {
			// logged-in users: allow
			return true;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$protectedSpecialPages = $config->get( 'CrawlerProtectedSpecialPages' );
		$denyFast = $config->get( 'CrawlerProtectionUse418' );

		// Normalize protected special pages: lowercase and strip 'Special:' prefix
		$normalizedProtectedPages = array_map(
			fn ( $p ) => ( $p = strtolower( $p ) ) && strpos( $p, strtolower( self::SPECIAL_PAGE_PREFIX ) ) === 0
				? substr( $p, 8 )
				: $p,
			$protectedSpecialPages
		);

		$name = strtolower( $special->getName() );
		if ( in_array( $name, $normalizedProtectedPages, true ) ) {
			$outputPage = $special->getContext()->getOutput();
			$this->denyAccess( $outputPage );
			return false;
		}

		return true;
	}

	/**
	 * Helper: Triage denial method based on config
	 */
	protected function denyAccess( OutputPage $output ): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		
		$rawDenial = $config->get( 'CrawlerProtectionRawDenial' );
		$denyWith418 = $config->get( 'CrawlerProtectionUse418' );

		if ( $denyWith418 ) {
			$this->denyAccessWith418();
		} elseif ( $rawDenial ) {
			$this->denyAccessRaw(
				$config->get( 'CrawlerProtectionRawDenialHeader' ),
				$config->get( 'CrawlerProtectionRawDenialText' )
			);
		} else {
			$this->denyAccessPretty( $output );
		}
	}

	/**
	 * Helper: output 418 Teapot and halt the processing immediately
	 *
	 * @return void
	 * @suppress PhanPluginNeverReturnMethod
	 */
	protected function denyAccessWith418() {
		$this->denyAccessRaw( 'HTTP/1.0 I\'m a teapot', 'I\'m a teapot' );
	}

	/**
	 * Helper: output raw HTTP response and halt the processing immediately
	 *
	 * @param string $header
	 * @param string $message
	 * @return void
	 */
	protected function denyAccessRaw( string $header, string $message ): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		header( $header );
		die( $message );
	}

	/**
	 * Helper: output a pretty 403 Access Denied page using i18n messages.
	 *
	 * @param OutputPage $output
	 * @return void
	 */
	protected function denyAccessPretty( OutputPage $output ): void {
		$output->setStatusCode( 403 );
		$output->addWikiTextAsInterface( wfMessage( 'crawlerprotection-accessdenied-text' )->plain() );

		if ( version_compare( MW_VERSION, '1.41', '<' ) ) {
			$output->setPageTitle( wfMessage( 'crawlerprotection-accessdenied-title' ) );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod Exists in 1.41+
			$output->setPageTitleMsg( wfMessage( 'crawlerprotection-accessdenied-title' ) );
		}
	}
}
