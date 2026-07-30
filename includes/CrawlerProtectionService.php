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
 * @file CrawlerProtectionService.php
 */

namespace MediaWiki\Extension\CrawlerProtection;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\Hook\CrawlerProtectionShouldDenyHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * Core business logic for CrawlerProtection.
 *
 * Determines whether a given request should be blocked for anonymous
 * users and delegates the actual denial to ResponseFactory.
 *
 * Only web requests are subject to protection. On the command line there is
 * no crawler to guard against, and denying access there aborts maintenance
 * scripts that re-parse pages transcluding a protected special page.
 */
class CrawlerProtectionService {

	/** @var string[] List of constructor options this class accepts */
	public const CONSTRUCTOR_OPTIONS = [
		'CrawlerProtectedActions',
		'CrawlerProtectedApiModules',
		'CrawlerProtectedQueryParams',
		'CrawlerProtectedRestPaths',
		'CrawlerProtectedSpecialPages',
		'CrawlerProtectionAllowedIPs',
		'CrawlerProtectionProtectRevisions',
		'CrawlerProtectionTreatTempUsersAsAnon',
	];

	/** @var ServiceOptions */
	private ServiceOptions $options;

	/** @var ResponseFactory */
	private ResponseFactory $responseFactory;

	/** @var CrawlerProtectionShouldDenyHook */
	private CrawlerProtectionShouldDenyHook $hookRunner;

	/** @var bool */
	private bool $cliMode;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var string[] Normalised CrawlerProtectedActions */
	private array $normalizedProtectedActions;

	/** @var string[] Normalised CrawlerProtectedApiModules */
	private array $normalizedProtectedApiModules;

	/** @var string[] Normalised CrawlerProtectedRestPaths */
	private array $normalizedProtectedRestPaths;

	/** @var string[] Normalised CrawlerProtectedQueryParams */
	private array $normalizedProtectedQueryParams;

	/** @var string[] Normalised CrawlerProtectedSpecialPages */
	private array $normalizedProtectedSpecialPages;

	/** @var string[] Normalised and validated CrawlerProtectionAllowedIPs */
	private array $normalizedAllowedIPs;

	/**
	 * @param ServiceOptions $options
	 * @param ResponseFactory $responseFactory
	 * @param CrawlerProtectionShouldDenyHook $hookRunner
	 * @param bool $cliMode
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $options,
		ResponseFactory $responseFactory,
		CrawlerProtectionShouldDenyHook $hookRunner,
		bool $cliMode,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->responseFactory = $responseFactory;
		$this->hookRunner = $hookRunner;
		$this->cliMode = $cliMode;
		$this->logger = $logger;

		// Pre-normalise all array-valued configs at construction time so that a
		// misconfigured scalar never fatals on array_map / foreach, and so that
		// any warning is logged exactly once per request rather than per call.
		$this->normalizedProtectedActions = $this->normalizeArrayConfig( 'CrawlerProtectedActions' );
		$this->normalizedProtectedApiModules = $this->normalizeArrayConfig( 'CrawlerProtectedApiModules' );
		$this->normalizedProtectedRestPaths = $this->normalizeArrayConfig( 'CrawlerProtectedRestPaths' );
		$this->normalizedProtectedQueryParams = $this->normalizeArrayConfig( 'CrawlerProtectedQueryParams' );
		$this->normalizedProtectedSpecialPages = $this->normalizeArrayConfig( 'CrawlerProtectedSpecialPages' );
		$this->normalizedAllowedIPs = $this->normalizeAndValidateIPs(
			$this->normalizeArrayConfig( 'CrawlerProtectionAllowedIPs' )
		);
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
		if ( $this->cliMode ) {
			return true;
		}

		$shouldDeny = false;

		if ( !$this->isUserAllowed( $user ) && !$this->isRequestIPAllowed( $request ) ) {
			$type = $request->getVal( 'type' );
			$action = $request->getVal( 'action' );
			$diffId = (int)$request->getVal( 'diff' );
			$oldId = (int)$request->getVal( 'oldid' );

			// $wgCrawlerProtectionProtectRevisions independently controls whether
			// type=revision, diff and oldid requests are blocked. This allows
			// operators to disable history-listing protection (by removing 'history'
			// from $wgCrawlerProtectedActions) while still blocking direct access
			// to individual revisions and diffs, or vice versa.
			$revisionsProtected = $this->options->get( 'CrawlerProtectionProtectRevisions' );

			$shouldDeny = $this->isProtectedAction( $action )
				|| $this->hasProtectedQueryParam( $request )
				|| ( $revisionsProtected && (
					$type === 'revision'
					|| $diffId > 0
					|| $oldId > 0
				) );
		}

		$this->hookRunner->onCrawlerProtectionShouldDeny(
			$user,
			$request,
			null,
			$shouldDeny
		);

		if ( $shouldDeny ) {
			$this->responseFactory->denyAccess( $output );
			return false;
		}

		return true;
	}

	/**
	 * Determine whether the request carries a protected special-page query
	 * parameter without a title naming the special page that consumes it.
	 *
	 * Such a request never resolves to a special page, so
	 * SpecialPageBeforeExecute does not fire and the request is served as an
	 * ordinary page view. MediaWiki always emits a title alongside these
	 * parameters, so their presence without one marks the request as
	 * crawler-generated rather than user-initiated.
	 *
	 * An empty or whitespace-only title (for example "title=") counts as
	 * missing, since it does not name a special page either and MediaWiki
	 * renders the main page for it.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	public function hasProtectedQueryParam( $request ): bool {
		$title = $request->getVal( 'title' );
		if ( $title !== null && trim( $title ) !== '' ) {
			return false;
		}

		foreach ( $this->normalizedProtectedQueryParams as $param ) {
			if ( $request->getVal( $param ) !== null ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the given action name is in the configured
	 * list of protected actions.
	 *
	 * The comparison is case-insensitive so that configured values
	 * like "History" or "HISTORY" match the action parameter.
	 *
	 * @param string|null $action
	 * @return bool
	 */
	public function isProtectedAction( ?string $action ): bool {
		if ( $action === null ) {
			return false;
		}

		$protectedActions = array_map(
			'strtolower',
			$this->normalizedProtectedActions
		);

		return in_array( strtolower( $action ), $protectedActions, true );
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
	 * @param WebRequest $request
	 * @return bool
	 */
	public function checkSpecialPage(
		string $specialPageName,
		$output,
		$user,
		$request
	): bool {
		if ( $this->cliMode ) {
			return true;
		}

		$shouldDeny = !$this->isUserAllowed( $user )
			&& !$this->isRequestIPAllowed( $request )
			&& $this->isProtectedSpecialPage( $specialPageName );

		$this->hookRunner->onCrawlerProtectionShouldDeny(
			$user,
			$request,
			$specialPageName,
			$shouldDeny
		);

		if ( $shouldDeny ) {
			$this->responseFactory->denyAccess( $output );
			return false;
		}

		return true;
	}

	/**
	 * Check whether an Action API module call should be blocked.
	 *
	 * Returns false (= deny) when the module is in the configured
	 * protected-modules list and the caller is anonymous.  Returns
	 * true otherwise.
	 *
	 * @param string $moduleName The canonical module name (e.g. "revisions", "compare")
	 * @param User $user
	 * @return bool
	 */
	public function checkApiModule( string $moduleName, $user ): bool {
		return $this->checkApiModules( [ $moduleName ], $user );
	}

	/**
	 * Check whether an Action API request involving the given modules
	 * should be blocked.
	 *
	 * Returns false (= deny) when any of the modules is in the configured
	 * protected-modules list and the caller is anonymous.  Returns true
	 * otherwise.
	 *
	 * @param string[] $moduleNames Module names involved in the request, i.e.
	 *  the requested action plus, for action=query, its sub-modules
	 * @param User $user
	 * @return bool
	 */
	public function checkApiModules( array $moduleNames, $user ): bool {
		if ( $this->cliMode ) {
			return true;
		}

		if ( $this->isUserAllowed( $user ) || $this->isIPAllowed( $user->getName() ) ) {
			return true;
		}

		foreach ( $moduleNames as $moduleName ) {
			if ( $this->isProtectedApiModule( $moduleName ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether the given API module name is in the
	 * configured list of protected modules.
	 *
	 * The comparison is case-insensitive.
	 *
	 * @param string $moduleName
	 * @return bool
	 */
	public function isProtectedApiModule( string $moduleName ): bool {
		$protected = array_map(
			'strtolower',
			$this->normalizedProtectedApiModules
		);
		return in_array( strtolower( $moduleName ), $protected, true );
	}

	/**
	 * Check whether a REST API request should be blocked.
	 *
	 * Returns false (= deny) when the path matches a configured
	 * protected pattern and the caller is anonymous.  Returns true
	 * otherwise.
	 *
	 * @param string $path The request path (e.g. "/page/Main_Page/history")
	 * @param User $user
	 * @return bool
	 */
	public function checkRestPath( string $path, $user ): bool {
		if ( $this->cliMode ) {
			return true;
		}

		if ( $this->isUserAllowed( $user ) || $this->isIPAllowed( $user->getName() ) ) {
			return true;
		}

		return !$this->isProtectedRestPath( $path );
	}

	/**
	 * Determine whether the given REST path matches any configured
	 * protected-path pattern.
	 *
	 * Each pattern is tested as a glob (fnmatch) with the FNM_PATHNAME
	 * flag, so a "*" wildcard matches a single path component and never
	 * spans a "/" separator. See $wgCrawlerProtectedRestPaths in the
	 * README for example patterns.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isProtectedRestPath( string $path ): bool {
		$patterns = $this->normalizedProtectedRestPaths;
		foreach ( $patterns as $pattern ) {
			if ( fnmatch( $pattern, $path, FNM_PATHNAME ) ) {
				return true;
			}
		}
		return false;
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
			$this->normalizedProtectedSpecialPages
		);

		$name = strtolower( $specialPageName );

		return in_array( $name, $normalizedProtectedPages, true );
	}

	/**
	 * Checks whether the request originates from an allowed IP range.
	 *
	 * The canonical client IP is taken from WebRequest (which correctly applies
	 * trusted-proxy / X-Forwarded-For handling) rather than the username.
	 *
	 * @param WebRequest $request
	 * @return bool
	 */
	private function isRequestIPAllowed( $request ): bool {
		return $this->normalizedAllowedIPs !== [] && $this->isIPAllowed( $request->getIP() );
	}

	/**
	 * Checks whether the given IP is in an allowed IP range.
	 *
	 * @param string $ip
	 * @return bool
	 */
	private function isIPAllowed( string $ip ): bool {
		return IPUtils::isInRanges( $ip, $this->normalizedAllowedIPs );
	}

	/**
	 * Determine whether the given user should be allowed through the protection
	 * without an IP check.
	 *
	 * Registered users are allowed unless CrawlerProtectionTreatTempUsersAsAnon
	 * is true and the user holds a temporary account.  Temporary accounts were
	 * introduced in MediaWiki 1.42 (User::isTemp()); on earlier versions the
	 * method does not exist and the guard below is a no-op.
	 *
	 * @param User $user
	 * @return bool
	 */
	private function isUserAllowed( $user ): bool {
		if ( !$user->isRegistered() ) {
			return false;
		}

		// When CrawlerProtectionTreatTempUsersAsAnon is true, a temporary-account
		// user is treated as anonymous so the rest of the protection logic applies.
		if ( $this->options->get( 'CrawlerProtectionTreatTempUsersAsAnon' )
			&& method_exists( $user, 'isTemp' )
			&& $user->isTemp()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Coerce a config value to a string array, logging a warning if the raw
	 * value is not already an array or contains non-string elements.
	 *
	 * @param string $configKey
	 * @return string[]
	 */
	private function normalizeArrayConfig( string $configKey ): array {
		$value = $this->options->get( $configKey );

		if ( !is_array( $value ) ) {
			$this->logger->warning(
				'CrawlerProtection: Config {configKey} should be an array; got a scalar. ' .
					'Treating it as a single-element array.',
				[ 'configKey' => $configKey ]
			);
			$value = [ $value ];
		}

		$filtered = array_values( array_filter( $value, 'is_string' ) );

		if ( count( $filtered ) !== count( $value ) ) {
			$this->logger->warning(
				'CrawlerProtection: Config {configKey} contains non-string entries; ' .
					'they have been ignored.',
				[ 'configKey' => $configKey ]
			);
		}

		return $filtered;
	}

	/**
	 * Validate IP allowlist entries and log a warning for any entry that does
	 * not look like a valid IP address, CIDR range, or explicit range.
	 *
	 * Invalid entries are kept in the returned array because
	 * IPUtils::isInRanges() handles them gracefully (returns false), so they
	 * do not cause errors; the warning simply makes typos discoverable.
	 *
	 * @param string[] $ips
	 * @return string[]
	 */
	private function normalizeAndValidateIPs( array $ips ): array {
		foreach ( $ips as $ip ) {
			// Accept single IPs, CIDR ranges, and explicit "a - b" ranges.
			if ( !IPUtils::isIPAddress( $ip ) && !IPUtils::isValidRange( $ip ) ) {
				$this->logger->warning(
					'CrawlerProtection: CrawlerProtectionAllowedIPs entry "{ip}" does not look like ' .
						'a valid IP address, CIDR range, or explicit IP range.',
					[ 'ip' => $ip ]
				);
			}
		}
		return $ips;
	}
}
