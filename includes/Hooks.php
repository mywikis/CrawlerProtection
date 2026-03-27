<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

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
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
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
