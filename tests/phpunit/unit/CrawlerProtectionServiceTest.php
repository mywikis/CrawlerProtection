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
	 * @param ResponseFactory|\PHPUnit\Framework\MockObject\MockObject|null $responseFactory
	 * @return CrawlerProtectionService
	 */
	private function buildService(
		array $protectedPages = [ 'recentchangeslinked', 'whatlinkshere', 'mobilediff' ],
		$responseFactory = null
	): CrawlerProtectionService {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[ 'CrawlerProtectedSpecialPages' => $protectedPages ]
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

		$service = $this->buildService( [], $responseFactory );
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

		$service = $this->buildService( [], $responseFactory );
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

		$service = $this->buildService( [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
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
}
