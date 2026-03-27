<?php
/**
 * Copyright (c) 2025 MyWikis
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
 * @file CrawlerProtectionService.php
 */

namespace MediaWiki\Extension\CrawlerProtection;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;

/**
 * Core business logic for CrawlerProtection.
 *
 * Determines whether a given request should be blocked for anonymous
 * users and delegates the actual denial to ResponseFactory.
 */
class CrawlerProtectionService {

	/** @var string[] List of constructor options this class accepts */
	public const CONSTRUCTOR_OPTIONS = [
		'CrawlerProtectedSpecialPages',
	];

	/** @var ServiceOptions */
	private ServiceOptions $options;

	/** @var ResponseFactory */
	private ResponseFactory $responseFactory;

	/**
	 * @param ServiceOptions $options
	 * @param ResponseFactory $responseFactory
	 */
	public function __construct(
		ServiceOptions $options,
		ResponseFactory $responseFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->responseFactory = $responseFactory;
	}

	/**
	 * Check whether a regular page request should be blocked.
	 *
	 * Returns false (= abort further processing) when the request is
	 * blocked, true otherwise.
	 *
	 * @param OutputPage $output
	 * @param User $user
	 * @param WebRequest $request
	 * @return bool
	 */
	public function checkPerformAction(
		$output,
		$user,
		$request
	): bool {
		if ( $user->isRegistered() ) {
			return true;
		}

		$type = $request->getVal( 'type' );
		$action = $request->getVal( 'action' );
		$diffId = (int)$request->getVal( 'diff' );
		$oldId = (int)$request->getVal( 'oldid' );

		if (
			$type === 'revision'
			|| $action === 'history'
			|| $diffId > 0
			|| $oldId > 0
		) {
			$this->responseFactory->denyAccess( $output );
			return false;
		}

		return true;
	}

	/**
	 * Check whether a special page request should be blocked.
	 *
	 * Returns false (= abort further processing) when the request is
	 * blocked, true otherwise.
	 *
	 * @param string $specialPageName The canonical special page name
	 * @param OutputPage $output
	 * @param User $user
	 * @return bool
	 */
	public function checkSpecialPage(
		string $specialPageName,
		$output,
		$user
	): bool {
		if ( $user->isRegistered() ) {
			return true;
		}

		if ( $this->isProtectedSpecialPage( $specialPageName ) ) {
			$this->responseFactory->denyAccess( $output );
			return false;
		}

		return true;
	}

	/**
	 * Determine whether the given special page name is in the
	 * configured list of protected special pages.
	 *
	 * Because this method is only called from the SpecialPageBeforeExecute
	 * hook, any "Foo:" prefix on a configured value is necessarily the
	 * "Special" namespace in English or its localized equivalent (e.g.
	 * "Spezial:" in German). We therefore simply strip everything up to
	 * and including the first colon rather than checking for a specific
	 * namespace name, which keeps the logic language-agnostic.
	 *
	 * @param string $specialPageName
	 * @return bool
	 */
	public function isProtectedSpecialPage( string $specialPageName ): bool {
		$protectedSpecialPages = $this->options->get( 'CrawlerProtectedSpecialPages' );

		// Normalize protected special pages: lowercase and strip any
		// namespace prefix (everything up to and including the first ':').
		$normalizedProtectedPages = array_map(
			static function ( string $p ): string {
				$lower = strtolower( $p );
				$colonPos = strpos( $lower, ':' );
				if ( $colonPos !== false ) {
					return substr( $lower, $colonPos + 1 );
				}
				return $lower;
			},
			$protectedSpecialPages
		);

		$name = strtolower( $specialPageName );

		return in_array( $name, $normalizedProtectedPages, true );
	}
}
