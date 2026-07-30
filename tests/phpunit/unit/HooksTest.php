<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService;
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
		parent::setUpBeforeClass();

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
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testOnMediaWikiPerformActionDelegatesToService() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$request = $this->createMock( self::$webRequestClassName );
		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkPerformAction' )
			->with( $output, $user, $request )
			->willReturn( false );

		$hooks = new Hooks( $service );
		$result = $hooks->onMediaWikiPerformAction(
			$output, $article, $title, $user, $request, $wiki
		);

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testOnMediaWikiPerformActionPassesThroughTrue() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$request = $this->createMock( self::$webRequestClassName );
		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkPerformAction' )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$result = $hooks->onMediaWikiPerformAction(
			$output, $article, $title, $user, $request, $wiki
		);

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 */
	public function testOnSpecialPageBeforeExecuteDelegatesToService() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$request = $this->createMock( self::$webRequestClassName );

		$context = $this->createMockContext( $user, $output, $request );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'WhatLinksHere' );
		$special->method( 'getContext' )->willReturn( $context );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkSpecialPage' )
			->with( 'WhatLinksHere', $output, $user, $request )
			->willReturn( false );

		$hooks = new Hooks( $service );
		$result = $hooks->onSpecialPageBeforeExecute( $special, null );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 */
	public function testOnSpecialPageBeforeExecutePassesThroughTrue() {
		$output = $this->createMock( self::$outputPageClassName );
		$user = $this->createMock( self::$userClassName );
		$request = $this->createMock( self::$webRequestClassName );

		$context = $this->createMockContext( $user, $output, $request );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'Search' );
		$special->method( 'getContext' )->willReturn( $context );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkSpecialPage' )
			->with( 'Search', $output, $user, $request )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$result = $hooks->onSpecialPageBeforeExecute( $special, null );

		$this->assertTrue( $result );
	}

	/**
	 * Create a mock context object.
	 *
	 * @param \PHPUnit\Framework\MockObject\MockObject $user Mock user object
	 * @param \PHPUnit\Framework\MockObject\MockObject $output Mock output object
	 * @param \PHPUnit\Framework\MockObject\MockObject $request Mock request object
	 * @return \stdClass Mock context
	 */
	private function createMockContext( $user, $output, $request ) {
		return new class( $user, $output, $request ) {
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $user;
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $output;
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $request;

			/**
			 * @param \PHPUnit\Framework\MockObject\MockObject $user
			 * @param \PHPUnit\Framework\MockObject\MockObject $output
			 * @param \PHPUnit\Framework\MockObject\MockObject $request
			 */
			public function __construct( $user, $output, $request ) {
				$this->user = $user;
				$this->output = $output;
				$this->request = $request;
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

			/**
			 * @return \PHPUnit\Framework\MockObject\MockObject
			 */
			public function getRequest() {
				return $this->request;
			}
		};
	}

	/**
	 * Build a stub API module exposing a module name and request parameters.
	 *
	 * @param string $moduleName
	 * @param array $params
	 * @return \stdClass Stub API module
	 */
	private function makeApiModule( string $moduleName, array $params = [] ) {
		$request = new class( $params ) {
			/** @var array */
			private $params;

			public function __construct( array $params ) {
				$this->params = $params;
			}

			public function getVal( $name ) {
				return $this->params[$name] ?? null;
			}
		};

		$main = new class( $request ) {
			/** @var \stdClass */
			private $request;

			public function __construct( $request ) {
				$this->request = $request;
			}

			public function getRequest() {
				return $this->request;
			}
		};

		return new class( $moduleName, $main ) {
			/** @var string */
			private $moduleName;
			/** @var \stdClass */
			private $main;

			public function __construct( string $moduleName, $main ) {
				$this->moduleName = $moduleName;
				$this->main = $main;
			}

			public function getModuleName(): string {
				return $this->moduleName;
			}

			public function getMain() {
				return $this->main;
			}
		};
	}

	/**
	 * @covers ::onApiCheckCanExecute
	 */
	public function testOnApiCheckCanExecuteDelegatesToService() {
		$user = $this->createMock( self::$userClassName );
		$module = $this->makeApiModule( 'compare' );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkApiModules' )
			->with( [ 'compare' ], $user )
			->willReturn( false );

		$hooks = new Hooks( $service );
		$message = null;
		$result = $hooks->onApiCheckCanExecute( $module, $user, $message );

		$this->assertFalse( $result );
		$this->assertNotNull( $message );
	}

	/**
	 * @covers ::onApiCheckCanExecute
	 */
	public function testOnApiCheckCanExecutePassesThroughWhenAllowed() {
		$user = $this->createMock( self::$userClassName );
		$module = $this->makeApiModule( 'query' );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkApiModules' )
			->with( [ 'query' ], $user )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$message = null;
		$result = $hooks->onApiCheckCanExecute( $module, $user, $message );

		// Returns null (no explicit false) when allowed
		$this->assertNotFalse( $result );
		$this->assertNull( $message );
	}

	/**
	 * @covers ::onApiCheckCanExecute
	 */
	public function testOnApiCheckCanExecuteCollectsQuerySubModules() {
		$user = $this->createMock( self::$userClassName );
		$module = $this->makeApiModule( 'query', [
			'prop' => 'revisions|links',
			'list' => 'recentchanges',
			'meta' => '',
			'generator' => 'allpages',
		] );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkApiModules' )
			->with(
				[ 'query', 'revisions', 'links', 'recentchanges', 'allpages' ],
				$user
			)
			->willReturn( true );

		$hooks = new Hooks( $service );
		$message = null;
		$hooks->onApiCheckCanExecute( $module, $user, $message );
	}

	/**
	 * @covers ::onApiCheckCanExecute
	 */
	public function testOnApiCheckCanExecuteHandlesUnitSeparatorMultiValues() {
		$user = $this->createMock( self::$userClassName );
		$module = $this->makeApiModule( 'query', [
			'prop' => "\x1frevisions\x1flinks",
		] );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkApiModules' )
			->with( [ 'query', 'revisions', 'links' ], $user )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$message = null;
		$hooks->onApiCheckCanExecute( $module, $user, $message );
	}

	/**
	 * @covers ::onApiCheckCanExecute
	 */
	public function testOnApiCheckCanExecuteIgnoresSubModulesForOtherActions() {
		$user = $this->createMock( self::$userClassName );
		$module = $this->makeApiModule( 'parse', [ 'prop' => 'links|templates' ] );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkApiModules' )
			->with( [ 'parse' ], $user )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$message = null;
		$hooks->onApiCheckCanExecute( $module, $user, $message );
	}

	/**
	 * Build a stub REST handler exposing an authority for the given user.
	 *
	 * @param \PHPUnit\Framework\MockObject\MockObject $user
	 * @return \stdClass Stub REST handler
	 */
	private function makeRestHandler( $user ) {
		$authority = new class( $user ) {
			/** @var \PHPUnit\Framework\MockObject\MockObject */
			private $user;

			/**
			 * @param \PHPUnit\Framework\MockObject\MockObject $user
			 */
			public function __construct( $user ) {
				$this->user = $user;
			}

			/**
			 * @return \PHPUnit\Framework\MockObject\MockObject
			 */
			public function getUser() {
				return $this->user;
			}
		};

		return new class( $authority ) {
			/** @var \stdClass */
			private $authority;

			/**
			 * @param \stdClass $authority
			 */
			public function __construct( $authority ) {
				$this->authority = $authority;
			}

			/**
			 * @return \stdClass
			 */
			public function getAuthority() {
				return $this->authority;
			}
		};
	}

	/**
	 * @covers ::onRestCheckCanExecute
	 */
	public function testOnRestCheckCanExecuteDelegatesToService() {
		$user = $this->createMock( self::$userClassName );
		$handler = $this->makeRestHandler( $user );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkRestPath' )
			->with( '/page/Main_Page/history', $user )
			->willReturn( false );

		$hooks = new Hooks( $service );
		$error = null;
		$result = $hooks->onRestCheckCanExecute( null, $handler, '/page/Main_Page/history', null, $error );

		$this->assertFalse( $result );
		$this->assertNotNull( $error );
	}

	/**
	 * @covers ::onRestCheckCanExecute
	 */
	public function testOnRestCheckCanExecutePassesThroughWhenAllowed() {
		$user = $this->createMock( self::$userClassName );
		$handler = $this->makeRestHandler( $user );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkRestPath' )
			->with( '/search', $user )
			->willReturn( true );

		$hooks = new Hooks( $service );
		$error = null;
		$result = $hooks->onRestCheckCanExecute( null, $handler, '/search', null, $error );

		$this->assertNotFalse( $result );
		$this->assertNull( $error );
	}
}
