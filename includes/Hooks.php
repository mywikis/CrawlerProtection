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

// Class aliases for multi-version compatibility.
// These need to be in global scope so phan can pick up on them,
// and before any use statements that make use of the namespaced names.

if ( version_compare( MW_VERSION, '1.41', '<' ) ) {
	class_alias( '\OutputPage', '\MediaWiki\Output\OutputPage' );
	class_alias( '\SpecialPage', '\MediaWiki\SpecialPage\SpecialPage' );
	class_alias( '\User', '\MediaWiki\User\User' );
	class_alias( '\WebRequest', '\MediaWiki\Request\WebRequest' );
}

if ( version_compare( MW_VERSION, '1.42', '<' ) ) {
	class_alias( '\MediaWiki', '\MediaWiki\Actions\ActionEntryPoint' );
	class_alias( '\RequestContext', '\MediaWiki\Context\RequestContext' );
}

if ( version_compare( MW_VERSION, '1.44', '<' ) ) {
	class_alias( '\Article', '\MediaWiki\Page\Article' );
}

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Message\MessageValue;

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
			$special->getContext()->getUser(),
			$special->getContext()->getRequest()
		);
	}

	/**
	 * Block protected Action API modules for anonymous users.
	 *
	 * Handler for the ApiCheckCanExecute hook, which fires for the requested
	 * action module before it is executed. Returns false and sets an error
	 * message key to deny the request when the module is in the configured
	 * protected list and the caller is anonymous.
	 *
	 * This class deliberately does not implement ApiCheckCanExecuteHook:
	 * hook handlers are dispatched by method name, and not implementing the
	 * interface keeps the handler usable across supported MediaWiki
	 * versions without redeclaring core interfaces.
	 *
	 * @param mixed $module ApiBase instance
	 * @param mixed $user User object
	 * @param string|mixed &$message Error message key set on denial
	 * @return bool|void False to deny execution
	 */
	public function onApiCheckCanExecute( $module, $user, &$message ) {
		if ( !$this->crawlerProtectionService->checkApiModules(
			$this->getApiModuleNames( $module ),
			$user,
			$module->getMain()->getRequest()
		) ) {
			$message = 'crawlerprotection-accessdenied-text';
			return false;
		}
	}

	/**
	 * Collect the API module names involved in a request.
	 *
	 * The ApiCheckCanExecute hook only fires for the requested action, so for
	 * action=query the requested sub-modules are read from the request
	 * parameters in order to make them protectable by name as well.
	 *
	 * @param mixed $module ApiBase instance
	 * @return string[]
	 */
	private function getApiModuleNames( $module ): array {
		$names = [ $module->getModuleName() ];

		if ( $names[0] !== 'query' ) {
			return $names;
		}

		$request = $module->getMain()->getRequest();
		foreach ( [ 'prop', 'list', 'meta', 'generator' ] as $param ) {
			$value = $request->getVal( $param );
			if ( $value === null || $value === '' ) {
				continue;
			}
			// MediaWiki uses "\x1f" as the separator when a multi-value
			// parameter starts with that character, and "|" otherwise.
			$separator = substr( $value, 0, 1 ) === "\x1f" ? "\x1f" : '|';
			foreach ( explode( $separator, $value ) as $subModule ) {
				$subModule = trim( $subModule );
				if ( $subModule !== '' ) {
					$names[] = $subModule;
				}
			}
		}

		return $names;
	}

	/**
	 * Block protected REST API paths for anonymous users.
	 *
	 * Handler for the RestCheckCanExecute hook, available on MW 1.44+. On
	 * older versions the hook never fires and this method is never called,
	 * so the REST API is unprotected there.
	 *
	 * As with onApiCheckCanExecute, RestCheckCanExecuteHook is deliberately
	 * not implemented so the class loads on MediaWiki versions that do not
	 * ship that interface.
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
		// The REST RequestInterface exposes no canonical client IP, so the
		// WebRequest of the current context is used instead: it applies the
		// wiki's trusted-proxy / X-Forwarded-For handling, matching the
		// behaviour of the index.php and api.php entry points.
		if ( !$this->crawlerProtectionService->checkRestPath(
			$path,
			$user,
			RequestContext::getMain()->getRequest()
		) ) {
			$error = new LocalizedHttpException(
				MessageValue::new( 'crawlerprotection-accessdenied-text' ),
				403
			);
			return false;
		}
	}
}
