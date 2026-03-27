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

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'WhatLinksHere' );
		$special->method( 'getContext' )->willReturn( $context );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkSpecialPage' )
			->with( 'WhatLinksHere', $output, $user )
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

		$context = $this->createMockContext( $user, $output );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getName' )->willReturn( 'Search' );
		$special->method( 'getContext' )->willReturn( $context );

		$service = $this->createMock( CrawlerProtectionService::class );
		$service->expects( $this->once() )
			->method( 'checkSpecialPage' )
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
	 * @return \stdClass Mock context
	 */
	private function createMockContext( $user, $output ) {
		return new class( $user, $output ) {
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
	}
}
