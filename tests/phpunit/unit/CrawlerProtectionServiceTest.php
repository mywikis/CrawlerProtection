<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService;
use MediaWiki\Extension\CrawlerProtection\Hook\CrawlerProtectionShouldDenyHook;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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

	/** @var string */
	private static string $webResponseClassName;

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

		self::$webResponseClassName = class_exists( '\MediaWiki\Request\WebResponse' )
			? '\MediaWiki\Request\WebResponse'
			: '\WebResponse';
	}

	/**
	 * Build a CrawlerProtectionService with the given protected pages and
	 * a mock ResponseFactory.
	 *
	 * @param array $protectedPages
	 * @param array $protectedActions
	 * @param string|array $allowedIPs
	 * @param ResponseFactory|\PHPUnit\Framework\MockObject\MockObject|null $responseFactory
	 * @param bool $protectRevisions
	 * @param array $protectedQueryParams
	 * @param bool $cliMode
	 * @param array $protectedApiModules
	 * @param array $protectedRestPaths
	 * @param bool $treatTempUsersAsAnon
	 * @param callable[] $shouldDenyHandlers Handlers for CrawlerProtectionShouldDeny
	 * @param bool $trustXForwardedFor
	 * @return CrawlerProtectionService
	 */
	private function buildService(
		array $protectedPages = [ 'recentchangeslinked', 'whatlinkshere', 'mobilediff' ],
		array $protectedActions = [ 'history' ],
		$allowedIPs = [],
		$responseFactory = null,
		bool $protectRevisions = true,
		array $protectedQueryParams = [ 'target' ],
		bool $cliMode = false,
		array $protectedApiModules = [],
		array $protectedRestPaths = [],
		bool $treatTempUsersAsAnon = false,
		array $shouldDenyHandlers = [],
		bool $trustXForwardedFor = false
	): CrawlerProtectionService {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[
				'CrawlerProtectedActions' => $protectedActions,
				'CrawlerProtectedApiModules' => $protectedApiModules,
				'CrawlerProtectedQueryParams' => $protectedQueryParams,
				'CrawlerProtectedRestPaths' => $protectedRestPaths,
				'CrawlerProtectedSpecialPages' => $protectedPages,
				'CrawlerProtectionAllowedIPs' => $allowedIPs,
				'CrawlerProtectionProtectRevisions' => $protectRevisions,
				'CrawlerProtectionTreatTempUsersAsAnon' => $treatTempUsersAsAnon,
				'CrawlerProtectionTrustXForwardedFor' => $trustXForwardedFor,
			]
		);

		$responseFactory ??= $this->createMock( ResponseFactory::class );

		$hookRunner = new HookRunnerFake( $shouldDenyHandlers );

		return new CrawlerProtectionService(
			$options,
			$responseFactory,
			$hookRunner,
			$cliMode,
			new NullLogger()
		);
	}

	/**
	 * Build a registered user mock whose isTemp() returns the given value.
	 *
	 * User::isTemp() only exists in MediaWiki 1.42 and later, so the method is
	 * added to the mock when the underlying class does not define it.
	 *
	 * @param bool $isTemp
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function newTempAwareUserMock( bool $isTemp ) {
		$builder = $this->getMockBuilder( self::$userClassName )
			->disableOriginalConstructor();

		if ( method_exists( self::$userClassName, 'isTemp' ) ) {
			$builder->onlyMethods( [ 'isRegistered', 'isTemp' ] );
		} else {
			$builder->onlyMethods( [ 'isRegistered' ] )->addMethods( [ 'isTemp' ] );
		}

		$user = $builder->getMock();
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'isTemp' )->willReturn( $isTemp );

		return $user;
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
	public function testCheckPerformActionBlocksProtectedQueryParamWithoutTitle() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, null ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsProtectedQueryParamWithTitle() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, 'Special:RecentChangesLinked' ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionBlocksProtectedQueryParamWithEmptyTitle() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, '' ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionBlocksProtectedQueryParamFromCustomList() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, null ],
			[ 'feedformat', null, 'atom' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' );

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'feedformat', 'curid' ]
		);
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsDefaultParamWhenNotInCustomList() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, null ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'feedformat', 'curid' ]
		);
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsUnconfiguredQueryParam() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, null ],
			[ 'curid', null, '1234' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsProtectedQueryParamWhenListEmpty() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, null ],
			[ 'action', null, null ],
			[ 'diff', null, null ],
			[ 'oldid', null, null ],
			[ 'title', null, null ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory, true, [] );
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
	 * When CrawlerProtectionProtectRevisions is false and 'history' is not in
	 * CrawlerProtectedActions, the revision-shaped parameters (type=revision,
	 * diff, oldid) should be allowed. The action=history allowed-case is
	 * covered separately by testCheckPerformActionAllowsActionNotInConfig().
	 *
	 * @covers ::checkPerformAction
	 * @dataProvider provideRevisionOnlyRequestParams
	 *
	 * @param array $getValMap
	 * @param string $msg
	 */
	public function testCheckPerformActionAllowsRevisionsWhenNotConfigured(
		array $getValMap, string $msg
	) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( $getValMap );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [], [], $responseFactory, false );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ), $msg );
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

	/**
	 * When CrawlerProtectionProtectRevisions is true and 'history' is NOT in
	 * CrawlerProtectedActions, revision/diff requests should still be blocked.
	 * This allows operators to protect individual revisions without protecting
	 * the history listing page.
	 *
	 * @covers ::checkPerformAction
	 * @dataProvider provideRevisionOnlyRequestParams
	 *
	 * @param array $getValMap
	 * @param string $msg
	 */
	public function testCheckPerformActionBlocksRevisionsWhenProtectRevisionsTrueHistoryUnconfigured(
		array $getValMap, string $msg
	) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( $getValMap );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		// history not in protected actions, but protectRevisions = true
		$service = $this->buildService( [], [], [], $responseFactory, true );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ), $msg );
	}

	/**
	 * Data provider for revision/diff request parameters (excludes action=history
	 * since that is controlled independently by CrawlerProtectedActions).
	 *
	 * @return array
	 */
	public function provideRevisionOnlyRequestParams(): array {
		return [
			'type=revision' => [
				[
					[ 'type', null, 'revision' ],
					[ 'action', null, null ],
					[ 'diff', null, null ],
					[ 'oldid', null, null ],
				],
				'type=revision should be blocked when CrawlerProtectionProtectRevisions is true',
			],
			'diff=42' => [
				[
					[ 'type', null, null ],
					[ 'action', null, null ],
					[ 'diff', null, '42' ],
					[ 'oldid', null, null ],
				],
				'diff=42 should be blocked when CrawlerProtectionProtectRevisions is true',
			],
			'oldid=99' => [
				[
					[ 'type', null, null ],
					[ 'action', null, null ],
					[ 'diff', null, null ],
					[ 'oldid', null, '99' ],
				],
				'oldid=99 should be blocked when CrawlerProtectionProtectRevisions is true',
			],
		];
	}

	/**
	 * When CrawlerProtectionProtectRevisions is false, revision/diff requests
	 * should be allowed even when 'history' is in CrawlerProtectedActions.
	 *
	 * @covers ::checkPerformAction
	 * @dataProvider provideRevisionOnlyRequestParams
	 *
	 * @param array $getValMap
	 * @param string $msg
	 */
	public function testCheckPerformActionAllowsRevisionsWhenProtectRevisionsFalse(
		array $getValMap, string $msg
	) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( $getValMap );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		// history is protected, but protectRevisions = false
		$service = $this->buildService( [], [ 'history' ], [], $responseFactory, false );
		$this->assertTrue(
			$service->checkPerformAction( $output, $user, $request ),
			$msg
		);
	}

	/**
	 * action=history is controlled solely by CrawlerProtectedActions. It must
	 * remain blocked even when CrawlerProtectionProtectRevisions is false.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionBlocksHistoryListingEvenWhenProtectRevisionsFalse() {
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
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		// history is protected, but protectRevisions = false
		$service = $this->buildService( [], [ 'history' ], [], $responseFactory, false );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsProtectedActionOnCommandLine() {
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

		$service = $this->buildService( [], [ 'history' ], [], $responseFactory, true, [ 'target' ], true );
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
	// hasProtectedQueryParam tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamReturnsTrueWithoutTitle() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, null ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$service = $this->buildService();
		$this->assertTrue( $service->hasProtectedQueryParam( $request ) );
	}

	/**
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamTreatsEmptyTitleAsMissing() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, '' ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$service = $this->buildService();
		$this->assertTrue( $service->hasProtectedQueryParam( $request ) );
	}

	/**
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamTreatsWhitespaceTitleAsMissing() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, '   ' ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$service = $this->buildService();
		$this->assertTrue( $service->hasProtectedQueryParam( $request ) );
	}

	/**
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamReturnsFalseWithTitle() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, 'Special:RecentChangesLinked' ],
			[ 'target', null, 'Project:Some_Page' ],
		] );

		$service = $this->buildService();
		$this->assertFalse( $service->hasProtectedQueryParam( $request ) );
	}

	/**
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamReturnsFalseForUnconfiguredParam() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, null ],
			[ 'curid', null, '1234' ],
		] );

		$service = $this->buildService();
		$this->assertFalse( $service->hasProtectedQueryParam( $request ) );
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

		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertFalse( $service->checkSpecialPage( $specialPageName, $output, $user, $request ) );
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

		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertTrue( $service->checkSpecialPage( $specialPageName, $output, $user, $request ) );
	}

	/**
	 * @covers ::checkSpecialPage
	 */
	public function testCheckSpecialPageAllowsUnprotected() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory
		);
		$this->assertTrue( $service->checkSpecialPage( 'Search', $output, $user, $request ) );
	}

	/**
	 * @covers ::checkSpecialPage
	 */
	public function testCheckSpecialPageAllowsProtectedPageOnCommandLine() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'RecentChangesLinked', 'WhatLinksHere', 'MobileDiff' ],
			[],
			[],
			$responseFactory,
			true,
			[ 'target' ],
			true
		);
		$this->assertTrue( $service->checkSpecialPage( 'WhatLinksHere', $output, $user, $request ) );
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

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( $ip );
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

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( $ip );
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

	// ---------------------------------------------------------------
	// IP read from WebRequest::getIP() tests
	// ---------------------------------------------------------------

	/**
	 * Verify that the IP used for allowlist matching comes from $request->getIP()
	 * and not from the (now irrelevant) username.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionUsesRequestIPNotUsername() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		// Username does NOT match the allowlist; request IP DOES.
		// getName() must never be called for IP resolution.
		$user->expects( $this->never() )->method( 'getName' );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService( [], [ 'history' ], [ '1.2.3.4' ], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * Verify that the IP used for allowlist matching in checkSpecialPage comes
	 * from $request->getIP().
	 *
	 * @covers ::checkSpecialPage
	 */
	public function testCheckSpecialPageUsesRequestIPNotUsername() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->expects( $this->never() )->method( 'getName' );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[ 'WhatLinksHere' ], [], [ '1.2.3.4' ], $responseFactory
		);
		$this->assertTrue( $service->checkSpecialPage( 'WhatLinksHere', $output, $user, $request ) );
	}

	// ---------------------------------------------------------------
	// X-Forwarded-For allowlist tests
	// ---------------------------------------------------------------

	/**
	 * Build an anonymous request whose canonical IP is the reverse proxy and
	 * whose X-Forwarded-For header carries the given value.
	 *
	 * @param string|false $forwardedFor Header value, or false when absent
	 * @param string $proxyIP Address WebRequest::getIP() resolves to
	 * @return \PHPUnit\Framework\MockObject\MockObject WebRequest mock
	 */
	private function makeProxiedRequest( $forwardedFor, string $proxyIP = '10.0.0.1' ) {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( $proxyIP );
		$request->method( 'getHeader' )->willReturn( $forwardedFor );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		return $request;
	}

	/**
	 * Behind a reverse proxy that MediaWiki does not know about, the canonical
	 * IP is the proxy's own address, so an allowlisted client is blocked unless
	 * CrawlerProtectionTrustXForwardedFor is enabled.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPIsIgnoredWhenTrustDisabled() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->makeProxiedRequest( '1.2.3.4' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService( [], [ 'history' ], [ '1.2.3.4' ], $responseFactory );
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPIsAllowedWhenTrustEnabled() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->makeProxiedRequest( '1.2.3.4' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[], [ 'history' ], [ '1.2.3.4' ], $responseFactory, true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * CIDR and explicit ranges must work for forwarded addresses too.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPMatchesAllowedRange() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->makeProxiedRequest( '2001:0db8:85a3::7344' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[], [ 'history' ], [ '2001:0db8:85a3::/96' ], $responseFactory,
			true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * Only the address appended by the reverse proxy (the last entry) counts.
	 * A client that prepends an allowlisted address to spoof the header must
	 * still be blocked.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPIgnoresClientSuppliedChainEntries() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		// The client claimed to be 1.2.3.4; the proxy appended the address it saw.
		$request = $this->makeProxiedRequest( '1.2.3.4, 203.0.113.9' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService(
			[], [ 'history' ], [ '1.2.3.4' ], $responseFactory, true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * @covers ::checkPerformAction
	 * @dataProvider provideUnusableForwardedForHeaders
	 *
	 * @param string|false $forwardedFor
	 */
	public function testUnusableForwardedForHeaderDenies( $forwardedFor ) {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->makeProxiedRequest( $forwardedFor );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService(
			[], [ 'history' ], [ '1.2.3.4' ], $responseFactory, true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	public function provideUnusableForwardedForHeaders(): array {
		return [
			'header absent' => [ false ],
			'empty header' => [ '' ],
			'whitespace only' => [ '   ' ],
			'not an IP address' => [ 'unknown' ],
			'range instead of address' => [ '1.2.3.0/24' ],
			'trailing separator' => [ '1.2.3.4,' ],
		];
	}

	/**
	 * The forwarded address is only consulted when the canonical IP does not
	 * already match, and never allows a request the allowlist does not cover.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPDoesNotOverrideAllowedCanonicalIP() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		// Canonical IP is allowlisted, forwarded address is not.
		$request = $this->makeProxiedRequest( '203.0.113.9', '1.2.3.4' );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$service = $this->buildService(
			[], [ 'history' ], [ '1.2.3.4' ], $responseFactory, true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * An empty allowlist short-circuits before the header is read, so enabling
	 * the toggle alone can never let a request through.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testForwardedIPIsNotConsultedWithEmptyAllowlist() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '10.0.0.1' );
		$request->expects( $this->never() )->method( 'getHeader' );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], false, [], true
		);
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * The REST entry point resolves the allowlist through the same request, so
	 * the forwarded address applies there as well.
	 *
	 * @covers ::checkRestPath
	 */
	public function testForwardedIPAppliesToRestPaths() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->makeProxiedRequest( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [ '1.2.3.4' ], null, true, [], false, [], [ '/page/*/history' ], false, [], true
		);
		$this->assertTrue( $service->checkRestPath( '/page/Main_Page/history', $user, $request ) );
	}

	// ---------------------------------------------------------------
	// Temporary-account user tests
	// ---------------------------------------------------------------

	/**
	 * With CrawlerProtectionTreatTempUsersAsAnon = false (default), a
	 * temporary-account user (isRegistered() = true, isTemp() = true) should
	 * be allowed through just like any other registered user.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsTempUserWhenFlagIsFalse() {
		$output = $this->createMock( self::$outputPageClassName );

		// Simulate a temporary-account user: registered but isTemp() = true.
		$user = $this->newTempAwareUserMock( true );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		// treatTempUsersAsAnon = false (default)
		$service = $this->buildService( [], [ 'history' ], [], $responseFactory );
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * With CrawlerProtectionTreatTempUsersAsAnon = true, a user whose
	 * isRegistered() returns true but isTemp() returns true should be treated
	 * as anonymous and therefore blocked on a protected action.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionBlocksTempUserWhenFlagIsTrue() {
		$output = $this->createMock( self::$outputPageClassName );

		// Simulate a temporary-account user: registered but isTemp() = true.
		$user = $this->newTempAwareUserMock( true );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		// treatTempUsersAsAnon = true
		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], true
		);
		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * With CrawlerProtectionTreatTempUsersAsAnon = true, a fully registered
	 * (non-temp) user should still be allowed through.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testCheckPerformActionAllowsRegisteredNonTempWhenFlagIsTrue() {
		$output = $this->createMock( self::$outputPageClassName );

		$user = $this->newTempAwareUserMock( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		// treatTempUsersAsAnon = true, but user is not a temp account
		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], true
		);
		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
	}

	// ---------------------------------------------------------------
	// Scalar misconfiguration tests
	// ---------------------------------------------------------------

	/**
	 * When CrawlerProtectedActions is set to a scalar string instead of an
	 * array, the service should still function (no fatal) and treat the scalar
	 * as a single-element list.
	 *
	 * @covers ::isProtectedAction
	 */
	public function testIsProtectedActionToleratesScalarConfig() {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[
				'CrawlerProtectedActions' => 'history',
				'CrawlerProtectedApiModules' => [],
				'CrawlerProtectedQueryParams' => [ 'target' ],
				'CrawlerProtectedRestPaths' => [],
				'CrawlerProtectedSpecialPages' => [],
				'CrawlerProtectionAllowedIPs' => [],
				'CrawlerProtectionProtectRevisions' => true,
				'CrawlerProtectionTreatTempUsersAsAnon' => false,
				'CrawlerProtectionTrustXForwardedFor' => false,
			]
		);
		$service = new CrawlerProtectionService(
			$options,
			$this->createMock( ResponseFactory::class ),
			new HookRunnerFake(),
			false,
			new NullLogger()
		);

		$this->assertTrue( $service->isProtectedAction( 'history' ) );
		$this->assertFalse( $service->isProtectedAction( 'edit' ) );
	}

	/**
	 * When CrawlerProtectedQueryParams is set to a scalar string instead of an
	 * array, the service should still function and treat the scalar as a
	 * single-element list.
	 *
	 * @covers ::hasProtectedQueryParam
	 */
	public function testHasProtectedQueryParamToleratesScalarConfig() {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[
				'CrawlerProtectedActions' => [],
				'CrawlerProtectedApiModules' => [],
				'CrawlerProtectedQueryParams' => 'target',
				'CrawlerProtectedRestPaths' => [],
				'CrawlerProtectedSpecialPages' => [],
				'CrawlerProtectionAllowedIPs' => [],
				'CrawlerProtectionProtectRevisions' => true,
				'CrawlerProtectionTreatTempUsersAsAnon' => false,
				'CrawlerProtectionTrustXForwardedFor' => false,
			]
		);
		$service = new CrawlerProtectionService(
			$options,
			$this->createMock( ResponseFactory::class ),
			new HookRunnerFake(),
			false,
			new NullLogger()
		);

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'title', null, null ],
			[ 'target', null, 'Project:Foo' ],
		] );

		$this->assertTrue( $service->hasProtectedQueryParam( $request ) );
	}

	/**
	 * When CrawlerProtectedSpecialPages is set to a scalar string instead of an
	 * array, the service should still function and treat the scalar as a
	 * single-element list.
	 *
	 * @covers ::isProtectedSpecialPage
	 */
	public function testIsProtectedSpecialPageToleratesScalarConfig() {
		$options = new ServiceOptions(
			CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
			[
				'CrawlerProtectedActions' => [],
				'CrawlerProtectedApiModules' => [],
				'CrawlerProtectedQueryParams' => [],
				'CrawlerProtectedRestPaths' => [],
				'CrawlerProtectedSpecialPages' => 'WhatLinksHere',
				'CrawlerProtectionAllowedIPs' => [],
				'CrawlerProtectionProtectRevisions' => true,
				'CrawlerProtectionTreatTempUsersAsAnon' => false,
				'CrawlerProtectionTrustXForwardedFor' => false,
			]
		);
		$service = new CrawlerProtectionService(
			$options,
			$this->createMock( ResponseFactory::class ),
			new HookRunnerFake(),
			false,
			new NullLogger()
		);

		$this->assertTrue( $service->isProtectedSpecialPage( 'WhatLinksHere' ) );
		$this->assertFalse( $service->isProtectedSpecialPage( 'Search' ) );
	}

	// ---------------------------------------------------------------
	// isProtectedApiModule tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::isProtectedApiModule
	 */
	public function testIsProtectedApiModuleReturnsTrueForConfiguredModule() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions', 'compare' ]
		);
		$this->assertTrue( $service->isProtectedApiModule( 'revisions' ) );
		$this->assertTrue( $service->isProtectedApiModule( 'compare' ) );
	}

	/**
	 * @covers ::isProtectedApiModule
	 */
	public function testIsProtectedApiModuleReturnsFalseForUnconfiguredModule() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertFalse( $service->isProtectedApiModule( 'query' ) );
		$this->assertFalse( $service->isProtectedApiModule( 'parse' ) );
	}

	/**
	 * @covers ::isProtectedApiModule
	 */
	public function testIsProtectedApiModuleReturnsFalseWhenListEmpty() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, []
		);
		$this->assertFalse( $service->isProtectedApiModule( 'revisions' ) );
	}

	/**
	 * @covers ::isProtectedApiModule
	 */
	public function testIsProtectedApiModuleIsCaseInsensitive() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'Revisions' ]
		);
		$this->assertTrue( $service->isProtectedApiModule( 'revisions' ) );
		$this->assertTrue( $service->isProtectedApiModule( 'REVISIONS' ) );
		$this->assertTrue( $service->isProtectedApiModule( 'Revisions' ) );
	}

	// ---------------------------------------------------------------
	// checkApiModule tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleAllowsRegisteredUser() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertTrue( $service->checkApiModule( 'revisions', $user ) );
	}

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleBlocksAnonymousForProtectedModule() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertFalse( $service->checkApiModule( 'revisions', $user ) );
	}

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleAllowsAnonymousForUnprotectedModule() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertTrue( $service->checkApiModule( 'query', $user ) );
	}

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleAllowsWhenListEmpty() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, []
		);
		$this->assertTrue( $service->checkApiModule( 'revisions', $user ) );
	}

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleAllowsOnCommandLine() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$service = $this->buildService(
			[], [], [], null, true, [], true, [ 'revisions' ]
		);
		$this->assertTrue( $service->checkApiModule( 'revisions', $user ) );
	}

	/**
	 * @covers ::checkApiModules
	 */
	public function testCheckApiModulesBlocksWhenAnySubModuleIsProtected() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertFalse(
			$service->checkApiModules( [ 'query', 'links', 'revisions' ], $user )
		);
	}

	/**
	 * @covers ::checkApiModules
	 */
	public function testCheckApiModulesAllowsWhenNoModuleIsProtected() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertTrue(
			$service->checkApiModules( [ 'query', 'links', 'categories' ], $user )
		);
	}

	/**
	 * @covers ::checkApiModules
	 */
	public function testCheckApiModulesAllowsRegisteredUser() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ]
		);
		$this->assertTrue( $service->checkApiModules( [ 'query', 'revisions' ], $user ) );
	}

	/**
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleAllowsAllowedIP() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		// The username must never be used to resolve the client IP.
		$user->expects( $this->never() )->method( 'getName' );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [ '1.2.3.4' ], null, true, [], false, [ 'revisions' ]
		);
		$this->assertTrue( $service->checkApiModule( 'revisions', $user, $request ) );
	}

	/**
	 * Without a request the allowlist cannot be evaluated, so a protected
	 * module stays protected rather than being allowed by a username that
	 * happens to look like an allowlisted IP.
	 *
	 * @covers ::checkApiModule
	 */
	public function testCheckApiModuleDeniesWhenNoRequestGiven() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [ '1.2.3.4' ], null, true, [], false, [ 'revisions' ]
		);
		$this->assertFalse( $service->checkApiModule( 'revisions', $user ) );
	}

	// ---------------------------------------------------------------
	// isProtectedRestPath tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::isProtectedRestPath
	 */
	public function testIsProtectedRestPathReturnsFalseWhenListEmpty() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [], []
		);
		$this->assertFalse( $service->isProtectedRestPath( '/page/Main_Page/history' ) );
	}

	/**
	 * @covers ::isProtectedRestPath
	 */
	public function testIsProtectedRestPathMatchesExactPath() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/Main_Page/history' ]
		);
		$this->assertTrue( $service->isProtectedRestPath( '/page/Main_Page/history' ) );
	}

	/**
	 * @covers ::isProtectedRestPath
	 */
	public function testIsProtectedRestPathMatchesGlobPattern() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertTrue( $service->isProtectedRestPath( '/page/Main_Page/history' ) );
		$this->assertTrue( $service->isProtectedRestPath( '/page/Talk:Foo/history' ) );
	}

	/**
	 * @covers ::isProtectedRestPath
	 */
	public function testIsProtectedRestPathDoesNotMatchUnrelatedPath() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertFalse( $service->isProtectedRestPath( '/page/Main_Page' ) );
		$this->assertFalse( $service->isProtectedRestPath( '/search' ) );
	}

	/**
	 * @covers ::isProtectedRestPath
	 */
	public function testIsProtectedRestPathWildcardDoesNotSpanSlash() {
		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertFalse( $service->isProtectedRestPath( '/page/Foo/Bar/history' ) );
	}

	// ---------------------------------------------------------------
	// checkRestPath tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathAllowsRegisteredUser() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertTrue( $service->checkRestPath( '/page/Main_Page/history', $user ) );
	}

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathBlocksAnonymousForProtectedPath() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertFalse( $service->checkRestPath( '/page/Main_Page/history', $user ) );
	}

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathAllowsAnonymousForUnprotectedPath() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertTrue( $service->checkRestPath( '/search', $user ) );
	}

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathAllowsWhenListEmpty() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [], null, true, [], false, [], []
		);
		$this->assertTrue( $service->checkRestPath( '/page/Main_Page/history', $user ) );
	}

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathAllowsOnCommandLine() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$service = $this->buildService(
			[], [], [], null, true, [], true, [], [ '/page/*/history' ]
		);
		$this->assertTrue( $service->checkRestPath( '/page/Main_Page/history', $user ) );
	}

	/**
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathAllowsAllowedIP() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		// The username must never be used to resolve the client IP.
		$user->expects( $this->never() )->method( 'getName' );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [ '1.2.3.4' ], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertTrue( $service->checkRestPath( '/page/Main_Page/history', $user, $request ) );
	}

	/**
	 * Without a request the allowlist cannot be evaluated, so a protected
	 * path stays protected rather than being allowed by a username that
	 * happens to look like an allowlisted IP.
	 *
	 * @covers ::checkRestPath
	 */
	public function testCheckRestPathDeniesWhenNoRequestGiven() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$user->method( 'getName' )->willReturn( '1.2.3.4' );

		$service = $this->buildService(
			[], [], [ '1.2.3.4' ], null, true, [], false, [], [ '/page/*/history' ]
		);
		$this->assertFalse( $service->checkRestPath( '/page/Main_Page/history', $user ) );
	}

	// ---------------------------------------------------------------
	// CrawlerProtectionShouldDeny hook tests
	// ---------------------------------------------------------------

	/**
	 * @covers ::checkPerformAction
	 */
	public function testHookCanAllowOtherwiseDeniedPerformAction() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'action', null, 'history' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$seen = [];
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$seen ) {
			$seen = [ $user2, $request2, $entryPoint, $specialPageName, $shouldDeny ];
			$shouldDeny = false;
		};

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], false, [ $handler ]
		);

		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
		$this->assertSame( [ $user, $request, 'index', null, true ], $seen );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testHookCanDenyOtherwiseAllowedPerformAction() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) {
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], false, [ $handler ]
		);

		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
	}

	/**
	 * The allowlisted-IP short circuit must not skip the hook, so handlers can
	 * still deny a request coming from an allowed IP.
	 *
	 * @covers ::checkPerformAction
	 */
	public function testHookRunsForAllowlistedIP() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getIP' )->willReturn( '1.2.3.4' );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'action', null, 'history' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$seen = [];
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$seen ) {
			$seen = [ $user2, $request2, $entryPoint, $specialPageName, $shouldDeny ];
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[], [ 'history' ], [ '1.2.3.4' ], $responseFactory, true, [ 'target' ], false, [], [], false,
			[ $handler ]
		);

		$this->assertFalse( $service->checkPerformAction( $output, $user, $request ) );
		$this->assertSame( [ $user, $request, 'index', null, false ], $seen );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testHookIsNotRunInCliMode() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$request = $this->createMock( self::$webRequestClassName );

		$called = false;
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$called ) {
			$called = true;
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[], [ 'history' ], [], null, true, [ 'target' ], true, [], [], false, [ $handler ]
		);

		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
		$this->assertFalse( $called );
	}

	/**
	 * @covers ::checkPerformAction
	 */
	public function testHookAbortReturnValueKeepsDecision() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'action', null, 'history' ],
		] );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$secondCalled = false;
		$first = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) {
			$shouldDeny = false;
			return false;
		};
		$second = static function (
			$user2, $request2, $entryPoint, $specialPageName, &$shouldDeny
		) use ( &$secondCalled ) {
			$secondCalled = true;
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[], [ 'history' ], [], $responseFactory, true, [ 'target' ], false, [], [], false, [ $first, $second ]
		);

		$this->assertTrue( $service->checkPerformAction( $output, $user, $request ) );
		$this->assertFalse( $secondCalled );
	}

	/**
	 * @covers ::checkSpecialPage
	 */
	public function testHookCanAllowOtherwiseDeniedSpecialPage() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'denyAccess' );

		$seen = [];
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$seen ) {
			$seen = [ $user2, $request2, $entryPoint, $specialPageName, $shouldDeny ];
			$shouldDeny = false;
		};

		$service = $this->buildService(
			[ 'whatlinkshere' ], [], [], $responseFactory, true, [ 'target' ], false, [], [], false, [ $handler ]
		);

		$this->assertTrue(
			$service->checkSpecialPage( 'WhatLinksHere', $output, $user, $request )
		);
		$this->assertSame( [ $user, $request, 'index', 'WhatLinksHere', true ], $seen );
	}

	/**
	 * The hook must run for Action API requests as well, with the "api"
	 * entry point and no special page name.
	 *
	 * @covers ::checkApiModules
	 */
	public function testHookRunsForApiRequests() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$request = $this->createMock( self::$webRequestClassName );

		$seen = [];
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$seen ) {
			$seen = [ $user2, $request2, $entryPoint, $specialPageName, $shouldDeny ];
			$shouldDeny = false;
		};

		$service = $this->buildService(
			[], [], [], null, true, [], false, [ 'revisions' ], [], false, [ $handler ]
		);

		$this->assertTrue( $service->checkApiModules( [ 'query', 'revisions' ], $user, $request ) );
		$this->assertSame( [ $user, $request, 'api', null, true ], $seen );
	}

	/**
	 * The hook must run for REST requests as well, with the "rest" entry
	 * point and no special page name.
	 *
	 * @covers ::checkRestPath
	 */
	public function testHookRunsForRestRequests() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$request = $this->createMock( self::$webRequestClassName );

		$seen = [];
		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) use ( &$seen ) {
			$seen = [ $user2, $request2, $entryPoint, $specialPageName, $shouldDeny ];
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[], [], [], null, true, [], false, [], [ '/page/*/history' ], false, [ $handler ]
		);

		$this->assertFalse( $service->checkRestPath( '/search', $user, $request ) );
		$this->assertSame( [ $user, $request, 'rest', null, false ], $seen );
	}

	// ---------------------------------------------------------------
	// API and REST denial headers
	// ---------------------------------------------------------------

	/**
	 * A denied Action API request must be marked with HTTP 403, because core
	 * answers an ApiCheckCanExecute veto with "200 OK" otherwise.
	 *
	 * @covers ::checkApiModules
	 */
	public function testDeniedApiRequestIsMarkedWith403() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$response = $this->createMock( self::$webResponseClassName );
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'response' )->willReturn( $response );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )
			->method( 'markDenied' )
			->with( $response, 403 );

		$service = $this->buildService(
			[], [], [], $responseFactory, true, [], false, [ 'revisions' ]
		);

		$this->assertFalse( $service->checkApiModules( [ 'query', 'revisions' ], $user, $request ) );
	}

	/**
	 * An allowed Action API request must not touch the response headers.
	 *
	 * @covers ::checkApiModules
	 */
	public function testAllowedApiRequestIsNotMarked() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->never() )->method( 'markDenied' );

		$service = $this->buildService(
			[], [], [], $responseFactory, true, [], false, [ 'revisions' ]
		);

		$this->assertTrue( $service->checkApiModules( [ 'query', 'links' ], $user, $request ) );
	}

	/**
	 * A denied REST request gets its status from the LocalizedHttpException
	 * raised by the hook handler, so only the robot directive is added.
	 *
	 * @covers ::checkRestPath
	 */
	public function testDeniedRestRequestIsMarkedWithoutStatusCode() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$response = $this->createMock( self::$webResponseClassName );
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'response' )->willReturn( $response );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )
			->method( 'markDenied' )
			->with( $response );

		$service = $this->buildService(
			[], [], [], $responseFactory, true, [], false, [], [ '/page/*/history' ]
		);

		$this->assertFalse( $service->checkRestPath( '/page/Main_Page/history', $user, $request ) );
	}

	/**
	 * Without a request there is no response to mark, but the denial itself
	 * must still take effect.
	 *
	 * @covers ::checkApiModules
	 */
	public function testDeniedApiRequestWithoutRequestMarksNothing() {
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )
			->method( 'markDenied' )
			->with( null, 403 );

		$service = $this->buildService(
			[], [], [], $responseFactory, true, [], false, [ 'revisions' ]
		);

		$this->assertFalse( $service->checkApiModules( [ 'revisions' ], $user ) );
	}

	/**
	 * @covers ::checkSpecialPage
	 */
	public function testHookCanDenyOtherwiseAllowedSpecialPage() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );
		$request = $this->createMock( self::$webRequestClassName );

		$responseFactory = $this->createMock( ResponseFactory::class );
		$responseFactory->expects( $this->once() )->method( 'denyAccess' )->with( $output );

		$handler = static function ( $user2, $request2, $entryPoint, $specialPageName, &$shouldDeny ) {
			$shouldDeny = true;
		};

		$service = $this->buildService(
			[ 'whatlinkshere' ], [], [], $responseFactory, true, [ 'target' ], false, [], [], false, [ $handler ]
		);

		$this->assertFalse( $service->checkSpecialPage( 'Search', $output, $user, $request ) );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable MediaWiki.Files.ClassMatchesFilename.NotMatch
/**
 * Test double for HookRunner that dispatches to plain closures.
 *
 * The real HookRunner needs a HookContainer, which cannot be constructed
 * standalone, so tests use this fake to exercise the hook without one.
 */
class HookRunnerFake implements CrawlerProtectionShouldDenyHook {

	/** @var callable[] */
	private array $handlers;

	/**
	 * @param callable[] $handlers
	 */
	public function __construct( array $handlers = [] ) {
		$this->handlers = $handlers;
	}

	/**
	 * @inheritDoc
	 */
	public function onCrawlerProtectionShouldDeny(
		$user,
		$request,
		string $entryPoint,
		?string $specialPageName,
		bool &$shouldDeny
	) {
		foreach ( $this->handlers as $handler ) {
			if ( $handler( $user, $request, $entryPoint, $specialPageName, $shouldDeny ) === false ) {
				return false;
			}
		}

		return true;
	}
}
