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
		$type   = $request->getVal( 'type' );
		$action = $request->getVal( 'action' );
		$diffId = (int)$request->getVal( 'diff' );
		$oldId  = (int)$request->getVal( 'oldid' );

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
			return false;
		}

		return true;
	}

	/**
	 * Block Special:RecentChangesLinked and Special:WhatLinksHere for anonymous users.
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

		$protectedSpecialPages = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'CrawlerProtectedSpecialPages' );

		$denyFast = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'CrawlerProtectedSpecialPages' );

		$name = strtolower( $special->getName() );
		if (
			// allow forgiving entries in the setting array for Special pages names
			in_array( $special->getName(), $protectedSpecialPages, true )
			|| in_array( $name, $protectedSpecialPages, true )
			|| in_array( 'Special:' . $special->getName(), $protectedSpecialPages, true )
		) {
			$out = $special->getContext()->getOutput();
			if ( $denyFast ) {
				$this->denyAccessFast();
			}
			$this->denyAccess( $out );
			return false;
		}

		return true;
	}

	/**
	 * Helper: output 418 Teapot and halt the processing immediately
	 *
	 * @return never
	 */
	protected function denyAccessFast(): void {
		header( 'HTTP/1.0 418 Forbidden' );
		die( 'I am a teapot' );
	}

	/**
	 * Helper: output 403 Access Denied page using i18n messages.
	 *
	 * @param OutputPage $output
	 * @return void
	 */
	protected function denyAccess( OutputPage $output ): void {
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
