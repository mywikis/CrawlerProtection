<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Extension\CrawlerProtection\Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\Hooks
 */
class HooksTest extends TestCase {
	/** @var string */
	private static string $actionEntryPointClassName;

	/** @var string */
	private static string $articleClassName;

	/** @var string */
	private static string $outputPageClassName;

	/** @var string */
	private static string $specialPageClassName;

	/** @var string */
	private static string $titleClassName;

	/** @var string */
	private static string $userClassName;

	/** @var string */
	private static string $webRequestClassName;

	public static function setUpBeforeClass(): void {
		self::$actionEntryPointClassName = class_exists( '\MediaWiki\Actions\ActionEntryPoint' )
			? '\MediaWiki\Actions\ActionEntryPoint'
			: '\MediaWiki';

		self::$articleClassName = class_exists( '\MediaWiki\Page\Article' )
			? '\MediaWiki\Page\Article'
			: '\Article';

		self::$outputPageClassName = class_exists( '\MediaWiki\Output\OutputPage' )
			? '\MediaWiki\Output\OutputPage'
			: '\OutputPage';

		self::$specialPageClassName = class_exists( '\MediaWiki\SpecialPage\SpecialPage' )
			? '\MediaWiki\SpecialPage\SpecialPage'
			: '\SpecialPage';

		self::$titleClassName = class_exists( '\MediaWiki\Title\Title' )
			? '\MediaWiki\Title\Title'
			: '\Title';

		self::$userClassName = class_exists( '\MediaWiki\User\User' )
			? '\MediaWiki\User\User'
			: '\User';

		self::$webRequestClassName = class_exists( '\MediaWiki\Request\WebRequest' )
			? '\MediaWiki\Request\WebRequest'
			: '\WebRequest';
	}

	/**
	 * Reset MediaWikiServices singleton after each test to prevent test pollution
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();
		// Reset the test config flag
		if ( property_exists( '\MediaWiki\MediaWikiServices', 'testUse418' ) ) {
			\MediaWiki\MediaWikiServices::$testUse418 = false;
		}
		// Only reset if the method exists (in our test stubs)
		if ( method_exists( '\MediaWiki\MediaWikiServices', 'resetForTesting' ) ) {
			\MediaWiki\MediaWikiServices::resetForTesting();
		}
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testRevisionTypeBlocksAnonymous() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->once() )->method( 'denyAccess' );

		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testRevisionTypeAllowsLoggedIn() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->never() )->method( 'denyAccess' );

		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertTrue( $result );
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testNonRevisionTypeAlwaysAllowed() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'view' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->never() )->method( 'denyAccess' );

		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertTrue( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 * @dataProvider provideBlockedSpecialPages
	 * @param string $specialPageName
	 */
	public function testSpecialPageBlocksAnonymous( $specialPageName ) {
		$output = $this->createMock( self::$outputPageClassName );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( $specialPageName );
		$special->method( 'getContext' )->willReturn( $context );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess', 'denyAccessWith418' ] )
			->getMock();
		$runner->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 * @dataProvider provideBlockedSpecialPages
	 * @param string $specialPageName
	 */
	public function testSpecialPageAllowsLoggedIn( $specialPageName ) {
		$output = $this->createMock( self::$outputPageClassName );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( $specialPageName );
		$special->method( 'getContext' )->willReturn( $context );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->never() )->method( 'denyAccess' );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertTrue( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 */
	public function testUnblockedSpecialPageAllowsAnonymous() {
		$output = $this->createMock( self::$outputPageClassName );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'Search' );
		$special->method( 'getContext' )->willReturn( $context );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->never() )->method( 'denyAccess' );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertTrue( $result );
	}

	/**
	 * Create a mock context object.
	 *
	 * @param \PHPUnit\Framework\MockObject\MockObject $user Mock user object
	 * @param \PHPUnit\Framework\MockObject\MockObject $output Mock output object
	 * @return \stdClass Mock context
	 */
	private function createMockContext( $user, $output ) {
		$context = new class( $user, $output ) {
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $user;
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $output;

			/**
			 * @param \PHPUnit\Framework\MockObject\MockObject $user
			 * @param \PHPUnit\Framework\MockObject\MockObject $output
			 */
			public function __construct( $user, $output ) {
				$this->user = $user;
				$this->output = $output;
			}

			/**
			 * @return \PHPUnit\Framework\MockObject\MockObject
			 */
			public function getUser() {
				return $this->user;
			}

			/**
			 * @return \PHPUnit\Framework\MockObject\MockObject
			 */
			public function getOutput() {
				return $this->output;
			}
		};
		return $context;
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 * @covers ::denyAccessWith418
	 */
	public function testSpecialPageCallsDenyAccessWith418WhenConfigured() {
		// Skip this test when running in MediaWiki environment where we can't mock the config
		// This test only works with our stubs where we can control MediaWikiServices
		if ( !property_exists( '\MediaWiki\MediaWikiServices', 'testUse418' ) ) {
			$this->markTestSkipped(
				'Test requires stub MediaWikiServices with testUse418 property. ' .
				'Run via MediaWiki test runner for full integration testing.'
			);
		}

		// Enable 418 response in the test stub config
		\MediaWiki\MediaWikiServices::$testUse418 = true;

		$output = $this->createMock( self::$outputPageClassName );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'WhatLinksHere' );
		$special->method( 'getContext' )->willReturn( $context );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccessWith418' ] )
			->getMock();
		// When denyFast is true, only denyAccessWith418 is called (it dies before denyAccess)
		$runner->expects( $this->once() )->method( 'denyAccessWith418' );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertFalse( $result );
	}

	/**
	 * Data provider for blocked special pages.
	 *
	 * @return array
	 */
	public function provideBlockedSpecialPages() {
		return [
			'RecentChangesLinked' => [ 'RecentChangesLinked' ],
			'WhatLinksHere' => [ 'WhatLinksHere' ],
			'MobileDiff' => [ 'MobileDiff' ],
			// Test case sensitivity
			'RecentChangesLinked lowercase' => [ 'recentchangeslinked' ],
			'WhatLinksHere lowercase' => [ 'whatlinkshere' ],
			'MobileDiff lowercase' => [ 'mobilediff' ],
			// Test mixed case
			'MobileDiff mixed case' => [ 'MoBiLeDiFf' ],
		];
	}
}
