<?php
/**
 * Copyright (c) 2025-2026 MyWikis
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @file Hooks.php
 */

namespace MediaWiki\Extension\CrawlerProtection;

use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Hook handler for the CrawlerProtection extension.
 *
 * This is a thin delegation layer. All business logic lives in
 * CrawlerProtectionService and ResponseFactory, which are injected
 * via the service container (see ServiceWiring.php and extension.json).
 */
class Hooks implements MediaWikiPerformActionHook, SpecialPageBeforeExecuteHook {

    public static function onRegistration() {
        Compat::init();
    }

	/** @var CrawlerProtectionService */
	private CrawlerProtectionService $crawlerProtectionService;

	/**
	 * @param CrawlerProtectionService $crawlerProtectionService
	 */
	public function __construct( CrawlerProtectionService $crawlerProtectionService ) {
		$this->crawlerProtectionService = $crawlerProtectionService;
	}

	/**
	 * Block sensitive page views for anonymous users via MediaWikiPerformAction.
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
		return $this->crawlerProtectionService->checkPerformAction(
			$output,
			$user,
			$request
		);
	}

	/**
	 * Block protected special pages for anonymous users.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 * @return bool False to abort execution
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		return $this->crawlerProtectionService->checkSpecialPage(
			$special->getName(),
			$special->getContext()->getOutput(),
			$special->getContext()->getUser()
		);
	}
}
