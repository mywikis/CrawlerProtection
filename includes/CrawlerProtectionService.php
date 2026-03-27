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

	/** @var string Prefix for special page names */
	private const SPECIAL_PAGE_PREFIX = 'Special:';

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
	 * @param string $specialPageName
	 * @return bool
	 */
	public function isProtectedSpecialPage( string $specialPageName ): bool {
		$protectedSpecialPages = $this->options->get( 'CrawlerProtectedSpecialPages' );

		// Normalize protected special pages: lowercase and strip 'Special:' prefix
		$normalizedProtectedPages = array_map(
			static function ( string $p ): string {
				$lower = strtolower( $p );
				if ( strpos( $lower, strtolower( self::SPECIAL_PAGE_PREFIX ) ) === 0 ) {
					return substr( $lower, strlen( self::SPECIAL_PAGE_PREFIX ) );
				}
				return $lower;
			},
			$protectedSpecialPages
		);

		$name = strtolower( $specialPageName );

		return in_array( $name, $normalizedProtectedPages, true );
	}
}
