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
	public function testRevisionTypeBlocksAnonymous() {
		$output = $this->createMock( self::$outputPageClassName );
		$output->expects( $this->once() )->method( 'setPageTitle' );
		$output->expects( $this->once() )->method( 'addWikiTextAsInterface' );
		$output->expects( $this->once() )->method( 'setStatusCode' )->with( 403 );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'type', null, 'revision' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$article = $this->createMock( self::$articleClassName );
		$title = $this->createMock( self::$titleClassName );
		$wiki = $this->createMock( self::$actionEntryPointClassName );

		$runner = new Hooks();
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

		$runner = new Hooks();
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

		$runner = new Hooks();
		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertTrue( $result );
	}
}
