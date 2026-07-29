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

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable MediaWiki.Files.ClassMatchesFilename.NotMatch

// -----------------------------------------------------------------
// Backward-compatibility stubs for hook interfaces
// -----------------------------------------------------------------

// Provide a no-op ApiCheckCanExecuteHook for MW versions that pre-date
// the interface-based hook system for this hook so the Hooks class can
// implement it unconditionally.
namespace MediaWiki\Api\Hook {
	if ( !interface_exists( 'MediaWiki\Api\Hook\ApiCheckCanExecuteHook' ) ) {
		// phpcs:ignore MediaWiki.Commenting.MissingDocumentationPublic
		interface ApiCheckCanExecuteHook {
		}
	}
}

// Provide a no-op RestCheckCanExecuteHook for MW < 1.42 where the REST
// hook system is not yet present. On those versions the hook never fires;
// the stub only lets the class compile cleanly.
namespace MediaWiki\Rest\Hook {
	if ( !interface_exists( 'MediaWiki\Rest\Hook\RestCheckCanExecuteHook' ) ) {
		// phpcs:ignore MediaWiki.Commenting.MissingDocumentationPublic
		interface RestCheckCanExecuteHook {
		}
	}
}

// -----------------------------------------------------------------
// Extension namespace
// -----------------------------------------------------------------

namespace MediaWiki\Extension\CrawlerProtection {

	use MediaWiki\Actions\ActionEntryPoint;
	use MediaWiki\Api\Hook\ApiCheckCanExecuteHook;
	use MediaWiki\Hook\MediaWikiPerformActionHook;
	use MediaWiki\Output\OutputPage;
	use MediaWiki\Page\Article;
	use MediaWiki\Request\WebRequest;
	use MediaWiki\Rest\Hook\RestCheckCanExecuteHook;
	use MediaWiki\Rest\HttpException;
	use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
	use MediaWiki\SpecialPage\SpecialPage;
	use MediaWiki\Title\Title;
	use MediaWiki\User\User;

	// Class aliases for multi-version compatibility.
	// These must be set up before the Hooks class is first instantiated
	// so that the aliased names resolve correctly at runtime.

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

	/**
	 * Hook handler for the CrawlerProtection extension.
	 *
	 * This is a thin delegation layer. All business logic lives in
	 * CrawlerProtectionService and ResponseFactory, which are injected
	 * via the service container (see ServiceWiring.php and extension.json).
	 */
	class Hooks implements
		MediaWikiPerformActionHook,
		SpecialPageBeforeExecuteHook,
		ApiCheckCanExecuteHook,
		RestCheckCanExecuteHook
	{

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

		/**
		 * Block protected Action API modules for anonymous users.
		 *
		 * Fires for every API module (including query sub-modules) before it
		 * is executed. Returns false and sets an error message to deny the
		 * request when the module is in the configured protected list and the
		 * caller is anonymous.
		 *
		 * @param mixed $module ApiBase instance
		 * @param mixed $user User object
		 * @param string|mixed &$message Error message set on denial
		 * @return bool False to deny execution
		 */
		public function onApiCheckCanExecute( $module, $user, &$message ) {
			if ( !$this->crawlerProtectionService->checkApiModule(
				$module->getModuleName(),
				$user
			) ) {
				$message = 'crawlerprotection-accessdenied-text';
				return false;
			}
		}

		/**
		 * Block protected REST API paths for anonymous users.
		 *
		 * Available on MW 1.42+. On older versions the hook never fires and
		 * this method is never called, so the REST API is unprotected there.
		 *
		 * @param mixed $module REST Module instance
		 * @param mixed $handler REST Handler instance
		 * @param string $path The request path (e.g. "/page/Main_Page/history")
		 * @param mixed $request PSR-7 request
		 * @param HttpException|null &$error Set to an HttpException to deny the request
		 * @return bool|void False to deny execution
		 */
		public function onRestCheckCanExecute( $module, $handler, string $path, $request, &$error ) {
			$user = $handler->getAuthority()->getUser();
			if ( !$this->crawlerProtectionService->checkRestPath( $path, $user ) ) {
				$error = new HttpException(
					wfMessage( 'crawlerprotection-accessdenied-text' )->plain(),
					403
				);
				return false;
			}
		}
	}
}
