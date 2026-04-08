<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService
 */
class CrawlerProtectionServiceTest extends TestCase {
	/** @var string */
	private static string $outputPageClassName;

	/** @var string */
	private static string $userClassName;

	/** @var string */
	private static string $webRequestClassName;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$outputPageClassName = class_exists( '\MediaWiki\Output\OutputPage' )
			? '\MediaWiki\Output\OutputPage'
			: '\OutputPage';

		self::$userClassName = class_exists( '\MediaWiki\User\User' )
			? '\MediaWiki\User\User'
			: '\User';

		self::$webRequestClassName = class_exists( '\MediaWiki\Request\WebRequest' )
			? '\MediaWiki\Request\WebRequest'
			: '\WebRequest';
	}

	/**
	 * Build a CrawlerProtectionService with the given protected pages and
	 * a mock ResponseFactory.
	 *
	 * @param array $protectedPages
	 * @param array $protectedActions
	 * @param string|array $allowedIPs
	 * @param ResponseFactory|\PHPUnit\Framework\MockObject\MockObject|null $responseFactory
	 * @return CrawlerProtectionService
	 */
	private function buildService(
		array $protectedPages = [ 'recentchangeslinked', 'whatlinkshere', 'mobilediff' ],
		array $protectedActions = [ 'history' ],
		$allowedIPs = [],
		$responseFactory = null
	): CrawlerProtectionService {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[
				'CrawlerProtectedActions' => $protectedActions,
				'CrawlerProtectedSpecialPages' => $protectedPages,
				'CrawlerProtectionAllowedIPs' => $allowedIPs
			]
		);

		$responseFactory ??= $this->createMock( ResponseFactory::class );

		return new CrawlerProtectionService( $options, $responseFactory );
	}

	// ---------------------------------------------------------------
	// checkPerformAction tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsRegisteredUser() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 * @dataProvider provideBlockedRequestParams
	 *
	 * @param array $getValMap
	 * @param string $msg
	 */
	public function testCheckPerformActionBlocksAnonymous( array $getValMap, string $msg ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( $getValMap );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ), $msg );
	}

	/**
	 * Data provider for request parameters that should trigger a block.
	 *
	 * @return array
	 */
	public function provideBlockedRequestParams(): array {
		return [
			'type=revision' => [
				[
					[ 'type', null, 'revision' ],
					[ 'action', null, null ],
					[ 'diff', null, null ],
					[ 'oldid', null, null ],
				],
				'type=revision should be blocked',
			],
			'action=history' => [
				[
					[ 'type', null, null ],
					[ 'action', null, 'history' ],
					[ 'diff', null, null ],
					[ 'oldid', null, null ],
				],
				'action=history should be blocked',
			],
			'diff=42' => [
				[
					[ 'type', null, null ],
					[ 'action', null, null ],
					[ 'diff', null, '42' ],
					[ 'oldid', null, null ],
				],
				'diff=42 should be blocked',
			],
			'oldid=99' => [
				[
					[ 'type', null, null ],
					[ 'action', null, null ],
					[ 'diff', null, null ],
					[ 'oldid', null, '99' ],
				],
				'oldid=99 should be blocked',
			],
		];
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsNormalAnonymousView() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, 'view' ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionBlocksConfiguredAction() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, 'edit' ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService( [], [ 'edit', 'history' ], [], $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsActionNotInConfig() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, 'history' ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	// ---------------------------------------------------------------
	// isProtectedAction tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::isProtectedAction
	 */
	public function testIsProtectedActionReturnsTrueForConfiguredAction() {
		$service = $this->buildService( [], [ 'history', 'edit' ] );
		$this->assertTrue( $service->isProtectedAction( 'history' ) );
		$this->assertTrue( $service->isProtectedAction( 'edit' ) );
	}

	/**
	 * @covers ::isProtectedAction
	 */
	public function testIsProtectedActionReturnsFalseForUnconfiguredAction() {
		$service = $this->buildService( [], [ 'history' ] );
		$this->assertFalse( $service->isProtectedAction( 'view' ) );
		$this->assertFalse( $service->isProtectedAction( 'edit' ) );
	}

	/**
	 * @covers ::isProtectedAction
	 */
	public function testIsProtectedActionReturnsFalseForNull() {
		$service = $this->buildService( [], [ 'history' ] );
		$this->assertFalse( $service->isProtectedAction( null ) );
	}

	/**
	 * @covers ::isProtectedAction
	 */
	public function testIsProtectedActionIsCaseInsensitive() {
		$service = $this->buildService( [], [ 'History' ] );
		$this->assertTrue( $service->isProtectedAction( 'history' ) );
		$this->assertTrue( $service->isProtectedAction( 'HISTORY' ) );
		$this->assertTrue( $service->isProtectedAction( 'History' ) );
	}

	// ---------------------------------------------------------------
	// checkSpecialPage tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkSpecialPage
	 * @dataProvider provideBlockedSpecialPages
	 *
	 * @param string $specialPageName
	 */
	public function testCheckSpecialPageBlocksAnonymous( string $specialPageName ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertFalse( $service->checkSpecialPage( $specialPageName, $output, $user ) );
	}

	/**
	 * @covers ::checkSpecialPage
	 * @dataProvider provideBlockedSpecialPages
	 *
	 * @param string $specialPageName
	 */
	public function testCheckSpecialPageAllowsRegistered( string $specialPageName ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertTrue( $service->checkSpecialPage( $specialPageName, $output, $user ) );
	}

	/**
	 * @covers ::checkSpecialPage
	 */
	public function testCheckSpecialPageAllowsUnprotected() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertTrue( $service->checkSpecialPage( 'Search', $output, $user ) );
	}

	// ---------------------------------------------------------------
	// isProtectedSpecialPage tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageStripsPrefix() {
		$service = $this->buildService( [ 'Special:WhatLinksHere' ] );
		$this->assertTrue( $service->isProtectedSpecialPage( 'WhatLinksHere' ) );
	}

	/**
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageIsCaseInsensitive() {
		$service = $this->buildService( [ 'WhatLinksHere' ] );
		$this->assertTrue( $service->isProtectedSpecialPage( 'whatlinkshere' ) );
		$this->assertTrue( $service->isProtectedSpecialPage( 'WHATLINKSHERE' ) );
		$this->assertTrue( $service->isProtectedSpecialPage( 'WhAtLiNkShErE' ) );
	}

	/**
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageReturnsFalseForUnprotected() {
		$service = $this->buildService( [ 'WhatLinksHere' ] );
		$this->assertFalse( $service->isProtectedSpecialPage( 'Search' ) );
	}

	/**
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageStripsLocalizedPrefix() {
		$service = $this->buildService( [ 'Spezial:WhatLinksHere' ] );
		$this->assertTrue( $service->isProtectedSpecialPage( 'WhatLinksHere' ) );
	}

	/**
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageStripsAnyPrefix() {
		$service = $this->buildService( [ 'Especial:WhatLinksHere' ] );
		$this->assertTrue( $service->isProtectedSpecialPage( 'WhatLinksHere' ) );
	}

	/**
	 * Data provider for blocked special pages.
	 *
	 * @return array
	 */
	public function provideBlockedSpecialPages(): array {
		return [
			'RecentChangesLinked' => [ 'RecentChangesLinked' ],
			'WhatLinksHere' => [ 'WhatLinksHere' ],
			'MobileDiff' => [ 'MobileDiff' ],
			'RecentChangesLinked lowercase' => [ 'recentchangeslinked' ],
			'WhatLinksHere lowercase' => [ 'whatlinkshere' ],
			'MobileDiff lowercase' => [ 'mobilediff' ],
			'MobileDiff mixed case' => [ 'MoBiLeDiFf' ],
		];
	}

	// ---------------------------------------------------------------
	// isIPAllowed tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkPerformAction
	 * @dataProvider provideAllowedIPs
	 *
	 * @param array|string $allowedIPs
	 * @param string $ip
	 */
	public function testCheckPerformActionAllowsAllowedIPs( $allowedIPs, string $ip ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( $ip );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], $allowedIPs, $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 * @dataProvider provideBlockedIPs
	 *
	 * @param array $allowedIPs
	 * @param string $ip
	 */
	public function testCheckPerformActionBlocksNotAllowedIPs( array $allowedIPs, string $ip ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( $ip );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService( [], [ 'history' ], $allowedIPs, $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	public function provideBlockedIPs(): array {
		return [
			'IPv4 Single IP mismatch' => [ [ '1.2.3.4' ], '1.2.3.5' ],
			'IPv4 CIDR mismatch' => [ [ '1.2.3.0/24' ], '1.2.4.4' ],
			'IPv4 Explicit range mismatch' => [ [ '1.2.3.1 - 1.2.3.10' ], '1.2.3.11' ],
			'IPv6 Single IP mismatch' => [ [ '2001:0db8:85a3::7344' ], '2001:0db8:85a3::7345' ],
			'IPv6 CIDR mismatch' => [ [ '2001:0db8:85a3::/96' ], '2001:0db8:85a4::7344' ],
			'IPv6 Explicit range mismatch' => [
                [ '2001:0db8:85a3::7340 - 2001:0db8:85a3::7350' ], '2001:0db8:85a3::7351'
            ],
		];
	}

	public function provideAllowedIPs(): array {
		return [
			'IPv4 Single IP' => [ [ '1.2.3.4' ], '1.2.3.4' ],
			'IPv4 CIDR match' => [ [ '1.2.3.0/24' ], '1.2.3.4' ],
			'IPv4 Explicit range match' => [ [ '1.2.3.1 - 1.2.3.10' ], '1.2.3.4' ],
			'IPv6 Single IP' => [ [ '2001:0db8:85a3::7344' ], '2001:0db8:85a3::7344' ],
			'IPv6 CIDR match' => [ [ '2001:0db8:85a3::/96' ], '2001:0db8:85a3::7344' ],
			'IPv6 Explicit range match' => [
                [ '2001:0db8:85a3::7340 - 2001:0db8:85a3::7350' ], '2001:0db8:85a3::7344'
            ],
			'String instead of array' => [ '1.2.3.4', '1.2.3.4' ],
		];
	}
}
