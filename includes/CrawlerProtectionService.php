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
use MediaWiki\Language\Language;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPageFactory;
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
		'CrawlerProtectionTrustXForwardedFor',
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

	/** @var SpecialPageFactory|null */
	private ?SpecialPageFactory $specialPageFactory;

	/** @var Language|null */
	private ?Language $contentLanguage;

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
	 * @param SpecialPageFactory|null $specialPageFactory
	 * @param Language|null $contentLanguage
	 */
	public function __construct(
		ServiceOptions $options,
		ResponseFactory $responseFactory,
		CrawlerProtectionShouldDenyHook $hookRunner,
		bool $cliMode,
		LoggerInterface $logger,
		?SpecialPageFactory $specialPageFactory = null,
		?Language $contentLanguage = null
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->responseFactory = $responseFactory;
		$this->hookRunner = $hookRunner;
		$this->cliMode = $cliMode;
		$this->logger = $logger;
		$this->specialPageFactory = $specialPageFactory;
		$this->contentLanguage = $contentLanguage;

		// Pre-normalise all array-valued configs at construction time so that a
		// misconfigured scalar never fatals on array_map / foreach, and so that
		// any warning is logged exactly once per request rather than per call.
		$this->normalizedProtectedActions = $this->normalizeArrayConfig( 'CrawlerProtectedActions' );
		$this->normalizedProtectedApiModules = $this->normalizeArrayConfig( 'CrawlerProtectedApiModules' );
		$this->normalizedProtectedRestPaths = $this->normalizeArrayConfig( 'CrawlerProtectedRestPaths' );
		$this->normalizedProtectedQueryParams = $this->normalizeArrayConfig( 'CrawlerProtectedQueryParams' );
		$this->normalizedProtectedSpecialPages = $this->normalizeProtectedSpecialPages(
			$this->normalizeArrayConfig( 'CrawlerProtectedSpecialPages' )
		);
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
			CrawlerProtectionShouldDenyHook::ENTRY_POINT_INDEX,
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
		$canonicalSpecialPageName = $this->getCanonicalSpecialPageNameFromRequest(
			$request,
			$specialPageName
		);

		if ( $this->cliMode ) {
			return true;
		}

		$shouldDeny = !$this->isUserAllowed( $user )
			&& !$this->isRequestIPAllowed( $request )
			&& $this->isProtectedSpecialPage( $canonicalSpecialPageName );

		$this->hookRunner->onCrawlerProtectionShouldDeny(
			$user,
			$request,
			CrawlerProtectionShouldDenyHook::ENTRY_POINT_INDEX,
			$canonicalSpecialPageName,
			$shouldDeny
		);

		if ( $shouldDeny ) {
			$this->responseFactory->markSpecialPageAliases(
				$request->response(),
				$this->getSpecialPageAliasesForHeader( $canonicalSpecialPageName )
			);
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
	 * @param WebRequest|null $request Request the check applies to, used to
	 *  resolve the canonical client IP for the allowlist
	 * @return bool
	 */
	public function checkApiModule( string $moduleName, $user, $request = null ): bool {
		return $this->checkApiModules( [ $moduleName ], $user, $request );
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
	 * @param WebRequest|null $request Request the check applies to, used to
	 *  resolve the canonical client IP for the allowlist
	 * @return bool
	 */
	public function checkApiModules( array $moduleNames, $user, $request = null ): bool {
		if ( $this->cliMode ) {
			return true;
		}

		$shouldDeny = false;

		if ( !$this->isUserAllowed( $user ) && !$this->isRequestIPAllowed( $request ) ) {
			foreach ( $moduleNames as $moduleName ) {
				if ( $this->isProtectedApiModule( $moduleName ) ) {
					$shouldDeny = true;
					break;
				}
			}
		}

		$this->hookRunner->onCrawlerProtectionShouldDeny(
			$user,
			$request,
			CrawlerProtectionShouldDenyHook::ENTRY_POINT_API,
			null,
			$shouldDeny
		);

		if ( $shouldDeny ) {
			// Core denies an ApiCheckCanExecute veto with dieWithError() and no
			// HTTP code, which would answer "200 OK" with an error body, so the
			// status and the robot directive are set here.
			$this->responseFactory->markDenied(
				$request !== null ? $request->response() : null,
				403
			);
			return false;
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
	 * @param WebRequest|null $request Request the check applies to, used to
	 *  resolve the canonical client IP for the allowlist
	 * @return bool
	 */
	public function checkRestPath( string $path, $user, $request = null ): bool {
		if ( $this->cliMode ) {
			return true;
		}

		$shouldDeny = !$this->isUserAllowed( $user )
			&& !$this->isRequestIPAllowed( $request )
			&& $this->isProtectedRestPath( $path );

		$this->hookRunner->onCrawlerProtectionShouldDeny(
			$user,
			$request,
			CrawlerProtectionShouldDenyHook::ENTRY_POINT_REST,
			null,
			$shouldDeny
		);

		if ( $shouldDeny ) {
			// The 403 status comes from the LocalizedHttpException raised by the
			// hook handler; only the robot directive is added here.
			$this->responseFactory->markDenied(
				$request !== null ? $request->response() : null
			);
			return false;
		}

		return true;
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
	 * The path passed by the RestCheckCanExecute hook is module-relative:
	 * core's Router strips the rest.php root and the module prefix, so
	 * "/w/rest.php/v1/page/Main_Page/history" arrives as
	 * "/page/Main_Page/history" and patterns must omit the prefix.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isProtectedRestPath( string $path ): bool {
		$patterns = $this->normalizedProtectedRestPaths;
		if ( $patterns === [] ) {
			return false;
		}

		// fnmatch() is unavailable on a handful of exotic PHP builds; without it
		// no pattern can be evaluated, so nothing is treated as protected.
		if ( !function_exists( 'fnmatch' ) ) {
			$this->logger->warning(
				'CrawlerProtection: fnmatch() is unavailable, so ' .
					'CrawlerProtectedRestPaths cannot be evaluated.'
			);
			return false;
		}

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
	 * Configured values and request-derived names are normalized by stripping any
	 * namespace-like prefix and resolving the remainder through
	 * SpecialPageFactory when it is available, so content-language aliases match
	 * the canonical special-page name.
	 *
	 * @param string $specialPageName
	 * @return bool
	 */
	public function isProtectedSpecialPage( string $specialPageName ): bool {
		return in_array(
			$this->normalizeSpecialPageName( $specialPageName ),
			$this->normalizedProtectedSpecialPages,
			true
		);
	}

	/**
	 * Canonicalize configured special-page names for stable matching.
	 *
	 * @param string[] $specialPages
	 * @return string[]
	 */
	private function normalizeProtectedSpecialPages( array $specialPages ): array {
		return array_values( array_unique( array_map(
			function ( string $specialPageName ): string {
				return $this->normalizeSpecialPageName( $specialPageName );
			},
			$specialPages
		) ) );
	}

	/**
	 * Normalize a special-page identifier for comparisons.
	 *
	 * Values are canonicalized through SpecialPageFactory when available so that
	 * configured aliases in any supported language resolve to the same
	 * canonical special-page name.
	 *
	 * @param string $specialPageName
	 * @return string
	 */
	private function normalizeSpecialPageName( string $specialPageName ): string {
		$unprefixedName = $this->stripSpecialPagePrefix( trim( $specialPageName ) );
		$canonicalName = $this->resolveSpecialPageAlias( $unprefixedName );

		if ( $canonicalName !== null ) {
			return strtolower( $canonicalName );
		}

		return strtolower( str_replace( ' ', '_', $unprefixedName ) );
	}

	/**
	 * Remove the special-page namespace prefix from a title-like string.
	 *
	 * @param string $specialPageName
	 * @return string
	 */
	private function stripSpecialPagePrefix( string $specialPageName ): string {
		$colonPos = strpos( $specialPageName, ':' );

		if ( $colonPos === false ) {
			return $specialPageName;
		}

		return substr( $specialPageName, $colonPos + 1 );
	}

	/**
	 * Resolve a special-page alias to its canonical name.
	 *
	 * @param string $specialPageName
	 * @return string|null
	 */
	private function resolveSpecialPageAlias( string $specialPageName ): ?string {
		if ( $this->specialPageFactory === null
			|| !method_exists( $this->specialPageFactory, 'resolveAlias' )
		) {
			return null;
		}

		[ $canonicalName, ] = $this->specialPageFactory->resolveAlias(
			str_replace( ' ', '_', $specialPageName )
		);

		return is_string( $canonicalName ) && $canonicalName !== ''
			? $canonicalName
			: null;
	}

	/**
	 * Canonicalize the requested special page from the web request when possible.
	 *
	 * @param WebRequest $request
	 * @param string $fallback
	 * @return string
	 */
	private function getCanonicalSpecialPageNameFromRequest( $request, string $fallback ): string {
		$title = $request->getVal( 'title' );

		if ( !is_string( $title ) || trim( $title ) === '' ) {
			return $fallback;
		}

		$canonicalName = $this->resolveSpecialPageAlias(
			$this->stripSpecialPagePrefix( trim( $title ) )
		);

		return $canonicalName !== null ? $canonicalName : $fallback;
	}

	/**
	 * Build the canonical-language aliases emitted for denied special pages.
	 *
	 * @param string $canonicalSpecialPageName
	 * @return string[]
	 */
	private function getSpecialPageAliasesForHeader( string $canonicalSpecialPageName ): array {
		$aliases = [ 'Special:' . $canonicalSpecialPageName ];

		if ( $this->contentLanguage === null
			|| !defined( 'NS_SPECIAL' )
			|| !method_exists( $this->contentLanguage, 'getNsText' )
			|| !method_exists( $this->contentLanguage, 'getSpecialPageAliases' )
		) {
			return $aliases;
		}

		$specialNamespace = $this->contentLanguage->getNsText( NS_SPECIAL );
		$specialPageAliases = $this->contentLanguage->getSpecialPageAliases();

		if ( !is_string( $specialNamespace ) || $specialNamespace === ''
			|| !is_array( $specialPageAliases )
			|| !isset( $specialPageAliases[$canonicalSpecialPageName] )
			|| !is_array( $specialPageAliases[$canonicalSpecialPageName] )
		) {
			return $aliases;
		}

		foreach ( $specialPageAliases[$canonicalSpecialPageName] as $alias ) {
			if ( !is_string( $alias ) || $alias === '' ) {
				continue;
			}
			$aliases[] = $specialNamespace . ':' . $alias;
		}

		return array_values( array_unique( $aliases ) );
	}

	/**
	 * Checks whether the request originates from an allowed IP range.
	 *
	 * The canonical client IP is taken from WebRequest (which correctly applies
	 * trusted-proxy / X-Forwarded-For handling) rather than the username.
	 *
	 * When CrawlerProtectionTrustXForwardedFor is enabled, the address reported
	 * by the reverse proxy in X-Forwarded-For is consulted as well, for wikis
	 * whose proxy is not registered in $wgCdnServersNoPurge.
	 *
	 * A null request (for example when an entry point cannot supply one) is
	 * never allowed through, so the regular protection logic applies.
	 *
	 * @param WebRequest|null $request
	 * @return bool
	 */
	private function isRequestIPAllowed( $request ): bool {
		if ( $request === null || $this->normalizedAllowedIPs === [] ) {
			return false;
		}

		if ( $this->isIPAllowed( $request->getIP() ) ) {
			return true;
		}

		$forwardedIP = $this->getForwardedIP( $request );

		return $forwardedIP !== null && $this->isIPAllowed( $forwardedIP );
	}

	/**
	 * Resolve the client address reported by the immediate reverse proxy in the
	 * X-Forwarded-For header, or null when it is unavailable or not trusted.
	 *
	 * MediaWiki only follows X-Forwarded-For for proxies that are registered as
	 * trusted (see $wgCdnServers / $wgCdnServersNoPurge), so behind an
	 * unregistered proxy such as HAProxy WebRequest::getIP() returns the proxy's
	 * own address. Registering the proxy remains the correct fix; this opt-in
	 * fallback covers wikis that cannot do so.
	 *
	 * Only the *last* entry of the header is used. A reverse proxy appends the
	 * address it observed to the end of the chain, so earlier entries may have
	 * been supplied by the client and must never be trusted. This still assumes
	 * that every request reaches the wiki through exactly one such proxy, which
	 * is why the behaviour is disabled by default.
	 *
	 * @param WebRequest $request
	 * @return string|null Sanitized IP address, or null if none can be trusted
	 */
	private function getForwardedIP( $request ): ?string {
		if ( !$this->options->get( 'CrawlerProtectionTrustXForwardedFor' ) ) {
			return null;
		}

		$forwardedFor = $request->getHeader( 'X-Forwarded-For' );
		if ( !is_string( $forwardedFor ) || trim( $forwardedFor ) === '' ) {
			return null;
		}

		$chain = explode( ',', $forwardedFor );
		$candidate = trim( (string)end( $chain ) );
		if ( $candidate === '' ) {
			return null;
		}

		// canonicalize() rejects anything that is not a single IP address,
		// returning null (older releases returned false).
		$canonical = IPUtils::canonicalize( $candidate );
		if ( !is_string( $canonical ) || $canonical === '' ) {
			return null;
		}

		return IPUtils::sanitizeIP( $canonical );
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

		$wasScalar = !is_array( $value );

		if ( $wasScalar ) {
			$this->logger->warning(
				'CrawlerProtection: Config {configKey} should be an array; got a scalar. ' .
					'Treating it as a single-element array.',
				[ 'configKey' => $configKey ]
			);
			$value = [ $value ];
		}

		$filtered = array_values( array_filter( $value, 'is_string' ) );

		// A non-string scalar has already been reported above; warning again
		// about the same value would be noise.
		if ( !$wasScalar && count( $filtered ) !== count( $value ) ) {
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
