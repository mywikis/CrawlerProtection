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

	private $protectWhatLinksHere;
	private $protectRecentChangesLinked;
	private $protectHistory;
	private $protectDiff;
	private $protectRevision;

	function __construct() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->protectWhatLinksHere = $config->get('CrawlerProtectWhatLinksHere');
		$this->protectRecentChangesLinked = $config->get('CrawlerProtectRecentChangesLinked');
		$this->protectHistory = $config->get('CrawlerProtectHistory');
		$this->protectDiff = $config->get('CrawlerProtectDiff');
		$this->protectRevision = $config->get('CrawlerProtectRevision');
		$this->protectWhatLinksHere = $config->get('CrawlerProtectWhatLinksHere');
		$this->protectRecentChangesLinked = $config->get('CrawlerProtectRecentChangesLinked');
		$this->protectHistory = $config->get('CrawlerProtectHistory');
		$this->protectDiff = $config->get('CrawlerProtectDiff');
		$this->protectRevision = $config->get('CrawlerProtectRevision');
	}

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
				($type === 'revision' && $this->protectRevision)
				|| ($action === 'history' && $this->protectHistory)
				|| ($diffId > 0 && $this->protectDiff)
				|| ($oldId > 0 && $this->protectHistory)
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

		$name = strtolower( $special->getName() );
		if (
			($this->protectRecentChangesLinked && $name == 'recentchangeslinked')
			|| ($this->protectWhatLinksHere && $name == 'whatlinkshere')
		) {
			$out = $special->getContext()->getOutput();
			$this->denyAccess( $out );
			return false;
		}

		return true;
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
