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
 * @file CrawlerProtectionIntegrationTest.php
 */

namespace MediaWiki\Extension\CrawlerProtection\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService;
use MediaWiki\Extension\CrawlerProtection\HookRunner;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWikiIntegrationTestCase;

/**
 * Integration tests for the CrawlerProtection extension.
 *
 * These tests run under the real MediaWiki service container and exercise the
 * parts of the extension that unit tests with hand-written stubs cannot reach:
 *
 * - Service-container wiring: confirms that the service names declared in
 *   extension.json resolve to the expected PHP objects.
 * - Hook registration: confirms that the hooks listed in extension.json are
 *   actually registered with MediaWiki's HookContainer.
 * - Real OutputPage state: confirms that the denial path sets HTTP 403 on a
 *   genuine OutputPage instance, exercising both the setPageTitle() (MW < 1.41)
 *   and setPageTitleMsg() (MW >= 1.41) branches through method_exists().
 * - End-to-end service behaviour: confirms that checkPerformAction() and
 *   checkSpecialPage() block anonymous users as configured.
 *
 * @group CrawlerProtection
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService
 */
class CrawlerProtectionIntegrationTest extends MediaWikiIntegrationTestCase {

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	/**
	 * Override every CrawlerProtection config key so that tests are not
	 * sensitive to wiki-local defaults.
	 *
	 * @param array $overrides Values that differ from the all-defaults baseline.
	 */
	private function overrideCrawlerProtectionConfig( array $overrides = [] ): void {
		$defaults = [
			'CrawlerProtectedActions'              => [ 'history' ],
			'CrawlerProtectedApiModules'           => [],
			'CrawlerProtectedRestPaths'            => [],
			'CrawlerProtectedSpecialPages'         => [ 'whatlinkshere', 'recentchangeslinked' ],
			'CrawlerProtectedQueryParams'          => [ 'target' ],
			'CrawlerProtectionAllowedIPs'          => [],
			'CrawlerProtectionProtectRevisions'    => true,
			'CrawlerProtectionTreatTempUsersAsAnon' => false,
			'CrawlerProtectionTrustXForwardedFor'  => false,
			'CrawlerProtectionRawDenial'           => false,
			'CrawlerProtectionUse418'              => false,
			'CrawlerProtectionRawDenialHeader'     => 'HTTP/1.0 403 Forbidden',
			'CrawlerProtectionRawDenialText'       => '',
		];

		$this->overrideConfigValues( array_merge( $defaults, $overrides ) );
	}

	/**
	 * Return a fresh OutputPage attached to a minimal RequestContext.
	 *
	 * A Title is required so that OutputPage::addWikiTextAsInterface() can
	 * invoke the parser without an "Invalid title" error.  TitleFactory is
	 * used instead of the Title class so that the test works both on releases
	 * that still provide the unnamespaced Title alias and on those that do not.
	 *
	 * @return \MediaWiki\Output\OutputPage|\OutputPage
	 */
	private function makeOutputPage() {
		$context = new \RequestContext();
		$context->setTitle(
			$this->getServiceContainer()->getTitleFactory()->makeTitle( NS_MAIN, 'Test' )
		);
		return $context->getOutput();
	}

	/**
	 * Build a FauxRequest carrying the given query parameters.
	 *
	 * FauxRequest lives in the MediaWiki\Request namespace on newer releases
	 * and in the global namespace on older ones, so the class is resolved at
	 * run time rather than referenced directly.
	 *
	 * @param array $params Query parameters
	 * @return \MediaWiki\Request\FauxRequest|\FauxRequest
	 */
	private function makeRequest( array $params ) {
		$class = class_exists( \MediaWiki\Request\FauxRequest::class )
			? \MediaWiki\Request\FauxRequest::class
			: 'FauxRequest';
		return new $class( $params );
	}

	/**
	 * Return a real anonymous user object for the given IP address.
	 *
	 * A real user is used rather than a mock because the User class has moved
	 * between namespaces across supported releases, and mocking it by name
	 * fails on releases where the chosen name is only an alias.
	 *
	 * @param string $ip
	 * @return \MediaWiki\User\User|\User
	 */
	private function makeAnonUser( string $ip = '1.2.3.4' ) {
		return $this->getServiceContainer()->getUserFactory()->newAnonymous( $ip );
	}

	/**
	 * Build a CrawlerProtectionService wired against the real container but
	 * with cliMode forced to false.
	 *
	 * PHPUnit runs PHP as a CLI process, so ServiceWiring.php detects
	 * MW_ENTRY_POINT === 'cli' and constructs the container service with
	 * cliMode = true, which bypasses all protection.  Tests that exercise
	 * the blocking path must use this helper instead of pulling the service
	 * directly from the container.
	 *
	 * @return CrawlerProtectionService
	 */
	private function makeWebModeService(): CrawlerProtectionService {
		return new CrawlerProtectionService(
			new ServiceOptions(
				CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			$this->getServiceContainer()->get( 'CrawlerProtection.ResponseFactory' ),
			new HookRunner( $this->getServiceContainer()->getHookContainer() ),
			// false = web-request mode — not CLI
			false,
			LoggerFactory::getInstance( 'CrawlerProtection' )
		);
	}

	// ---------------------------------------------------------------
	// Service-container wiring
	// ---------------------------------------------------------------

	/**
	 * Verify that CrawlerProtection.CrawlerProtectionService resolves from
	 * the real service container and is of the correct type.
	 *
	 * This catches typos in extension.json service names and constructor
	 * mis-wiring in ServiceWiring.php.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::__construct
	 */
	public function testCrawlerProtectionServiceResolvesFromContainer(): void {
		$service = $this->getServiceContainer()->get( 'CrawlerProtection.CrawlerProtectionService' );
		$this->assertInstanceOf( CrawlerProtectionService::class, $service );
	}

	/**
	 * Verify that CrawlerProtection.ResponseFactory resolves from the real
	 * service container and is of the correct type.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\ResponseFactory::__construct
	 */
	public function testResponseFactoryResolvesFromContainer(): void {
		$factory = $this->getServiceContainer()->get( 'CrawlerProtection.ResponseFactory' );
		$this->assertInstanceOf( ResponseFactory::class, $factory );
	}

	// ---------------------------------------------------------------
	// Hook registration
	// ---------------------------------------------------------------

	/**
	 * Verify that the MediaWikiPerformAction hook handler is registered.
	 *
	 * This catches mistakes in the "Hooks" section of extension.json.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\Hooks::__construct
	 */
	public function testMediaWikiPerformActionHookIsRegistered(): void {
		$this->assertTrue(
			$this->getServiceContainer()->getHookContainer()->isRegistered( 'MediaWikiPerformAction' ),
			'MediaWikiPerformAction hook must be registered by the extension'
		);
	}

	/**
	 * Verify that the SpecialPageBeforeExecute hook handler is registered.
	 *
	 * This catches mistakes in the "Hooks" section of extension.json.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\Hooks::__construct
	 */
	public function testSpecialPageBeforeExecuteHookIsRegistered(): void {
		$this->assertTrue(
			$this->getServiceContainer()->getHookContainer()->isRegistered( 'SpecialPageBeforeExecute' ),
			'SpecialPageBeforeExecute hook must be registered by the extension'
		);
	}

	// ---------------------------------------------------------------
	// ResponseFactory::denyAccessPretty() with real OutputPage
	// ---------------------------------------------------------------

	/**
	 * Confirm that the "pretty" denial path sets HTTP 403 on a real OutputPage.
	 *
	 * This exercises the method_exists()-based branch in denyAccessPretty():
	 * on MW < 1.41 the method uses setPageTitle(); on MW >= 1.41 it uses
	 * setPageTitleMsg().  Both paths must end with a 403 status code, and the
	 * CI matrix (REL1_39 and REL1_43+) naturally covers both branches.
	 *
	 * OutputPage::getStatusCode() was added in MW 1.45.  On earlier versions
	 * the test verifies the call does not throw; the status-code assertion is
	 * skipped when the getter is absent.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\ResponseFactory::denyAccessPretty
	 * @covers \MediaWiki\Extension\CrawlerProtection\ResponseFactory::denyAccess
	 */
	public function testDenyAccessPrettySetsStatusCode403OnRealOutputPage(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectionRawDenial' => false,
			'CrawlerProtectionUse418'    => false,
		] );

		/** @var ResponseFactory $factory */
		$factory = $this->getServiceContainer()->get( 'CrawlerProtection.ResponseFactory' );
		$output  = $this->makeOutputPage();

		$factory->denyAccess( $output );

		if ( method_exists( $output, 'getStatusCode' ) ) {
			$this->assertSame( 403, $output->getStatusCode() );
		} else {
			// On MW < 1.45, getStatusCode() does not exist; verify only that
			// denyAccess() completed without throwing.
			$this->addToAssertionCount( 1 );
		}
	}

	// ---------------------------------------------------------------
	// CrawlerProtectionService::checkPerformAction()
	// ---------------------------------------------------------------

	/**
	 * An anonymous user requesting a protected action must be blocked (return
	 * false) and the OutputPage must receive HTTP 403.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkPerformAction
	 */
	public function testAnonymousUserWithProtectedActionIsBlocked(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedActions'           => [ 'history' ],
			'CrawlerProtectionProtectRevisions' => false,
		] );

		$service = $this->makeWebModeService();

		$user = $this->makeAnonUser();

		$request = $this->makeRequest( [ 'action' => 'history' ] );
		$output  = $this->makeOutputPage();

		$result = $service->checkPerformAction( $output, $user, $request );

		$this->assertFalse( $result, 'checkPerformAction must return false to abort the request' );
		if ( method_exists( $output, 'getStatusCode' ) ) {
			$this->assertSame( 403, $output->getStatusCode() );
		}
	}

	/**
	 * A registered (logged-in) user requesting a protected action must not
	 * be blocked — the service must return true.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkPerformAction
	 */
	public function testRegisteredUserWithProtectedActionIsNotBlocked(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedActions' => [ 'history' ],
		] );

		$service = $this->makeWebModeService();

		// getMutableTestUser() returns a real registered user object.
		$user    = $this->getMutableTestUser()->getUser();
		$request = $this->makeRequest( [ 'action' => 'history' ] );
		$output  = $this->makeOutputPage();

		$result = $service->checkPerformAction( $output, $user, $request );

		$this->assertTrue( $result, 'checkPerformAction must return true for registered users' );
	}

	// ---------------------------------------------------------------
	// X-Forwarded-For allowlist
	// ---------------------------------------------------------------

	/**
	 * Build a request that reaches the wiki through a reverse proxy: the
	 * connecting address is the proxy, and the client address is reported in
	 * the X-Forwarded-For header.
	 *
	 * A real WebRequest is used so that the header lookup exercises
	 * MediaWiki's own getHeader() implementation.
	 *
	 * @param array $params Query parameters
	 * @param string $forwardedFor X-Forwarded-For header value
	 * @param string $proxyIP Connecting (proxy) address
	 * @return \MediaWiki\Request\FauxRequest|\FauxRequest
	 */
	private function makeProxiedRequest(
		array $params,
		string $forwardedFor,
		string $proxyIP = '10.0.0.1'
	) {
		$request = $this->makeRequest( $params );
		$request->setIP( $proxyIP );
		$request->setHeaders( [ 'X-Forwarded-For' => $forwardedFor ] );

		return $request;
	}

	/**
	 * Behind a reverse proxy that is not registered in $wgCdnServersNoPurge,
	 * WebRequest::getIP() reports the proxy address, so an allowlisted client
	 * is still blocked while CrawlerProtectionTrustXForwardedFor is off.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkPerformAction
	 */
	public function testForwardedIPIsIgnoredWhenTrustDisabled(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedActions'      => [ 'history' ],
			'CrawlerProtectionAllowedIPs'  => [ '1.2.3.4' ],
		] );

		$service = $this->makeWebModeService();
		$request = $this->makeProxiedRequest( [ 'action' => 'history' ], '1.2.3.4' );

		$this->assertFalse(
			$service->checkPerformAction( $this->makeOutputPage(), $this->makeAnonUser(), $request )
		);
	}

	/**
	 * With CrawlerProtectionTrustXForwardedFor enabled, the address reported
	 * by the proxy is matched against the allowlist.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkPerformAction
	 */
	public function testForwardedIPIsAllowedWhenTrustEnabled(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedActions'             => [ 'history' ],
			'CrawlerProtectionAllowedIPs'         => [ '1.2.3.4' ],
			'CrawlerProtectionTrustXForwardedFor' => true,
		] );

		$service = $this->makeWebModeService();
		$request = $this->makeProxiedRequest( [ 'action' => 'history' ], '1.2.3.4' );

		$this->assertTrue(
			$service->checkPerformAction( $this->makeOutputPage(), $this->makeAnonUser(), $request )
		);
	}

	/**
	 * Entries a client prepends to the header must never be trusted: only the
	 * address appended by the proxy (the last entry) is matched.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkPerformAction
	 */
	public function testSpoofedForwardedChainIsRejected(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedActions'             => [ 'history' ],
			'CrawlerProtectionAllowedIPs'         => [ '1.2.3.4' ],
			'CrawlerProtectionTrustXForwardedFor' => true,
		] );

		$service = $this->makeWebModeService();
		$request = $this->makeProxiedRequest( [ 'action' => 'history' ], '1.2.3.4, 203.0.113.9' );

		$this->assertFalse(
			$service->checkPerformAction( $this->makeOutputPage(), $this->makeAnonUser(), $request )
		);
	}

	// ---------------------------------------------------------------
	// CrawlerProtectionService::checkSpecialPage()
	// ---------------------------------------------------------------

	/**
	 * An anonymous user visiting a protected special page must be blocked.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkSpecialPage
	 */
	public function testAnonymousUserOnProtectedSpecialPageIsBlocked(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedSpecialPages' => [ 'WhatLinksHere' ],
		] );

		$service = $this->makeWebModeService();

		$user = $this->makeAnonUser();

		$output = $this->makeOutputPage();

		$request = $this->makeRequest( [] );

		$result = $service->checkSpecialPage( 'WhatLinksHere', $output, $user, $request );

		$this->assertFalse( $result, 'checkSpecialPage must return false to abort the request' );
		if ( method_exists( $output, 'getStatusCode' ) ) {
			$this->assertSame( 403, $output->getStatusCode() );
		}
	}

	/**
	 * A registered user visiting a protected special page must not be blocked.
	 *
	 * @covers \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService::checkSpecialPage
	 */
	public function testRegisteredUserOnProtectedSpecialPageIsNotBlocked(): void {
		$this->overrideCrawlerProtectionConfig( [
			'CrawlerProtectedSpecialPages' => [ 'WhatLinksHere' ],
		] );

		$service = $this->makeWebModeService();

		$user   = $this->getMutableTestUser()->getUser();
		$output = $this->makeOutputPage();

		$request = $this->makeRequest( [] );

		$result = $service->checkSpecialPage( 'WhatLinksHere', $output, $user, $request );

		$this->assertTrue( $result, 'checkSpecialPage must return true for registered users' );
	}
}
