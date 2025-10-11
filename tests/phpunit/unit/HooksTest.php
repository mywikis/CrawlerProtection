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

	/** @var string */
	private static string $specialPageClassName;

	/** @var string */
	private static string $contextClassName;

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

		self::$specialPageClassName = class_exists( '\MediaWiki\SpecialPage\SpecialPage' )
			? '\MediaWiki\SpecialPage\SpecialPage'
			: '\SpecialPage';

		self::$contextClassName = class_exists( '\MediaWiki\Context\RequestContext' )
			? '\MediaWiki\Context\RequestContext'
			: '\RequestContext';
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
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testOldIdBlocksAnonymous() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'oldid', null, '1234' ],
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
	public function testOldIdAllowsLoggedIn() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'oldid', null, '1234' ],
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
	 * @covers ::onSpecialPageBeforeExecute
	 */
	public function testSpecialPageWithOldIdBlocksAnonymous() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'oldid', null, '4463' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$context = $this->createMock( self::$contextClassName );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getOutput' )->willReturn( $output );
		$context->method( 'getRequest' )->willReturn( $request );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getContext' )->willReturn( $context );
		$special->method( 'getName' )->willReturn( 'Login' );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->once() )->method( 'denyAccess' );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onSpecialPageBeforeExecute
	 */
	public function testSpecialPageWithOldIdAllowsLoggedIn() {
		$output = $this->createMock( self::$outputPageClassName );

		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'oldid', null, '4463' ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( true );

		$context = $this->createMock( self::$contextClassName );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getRequest' )->willReturn( $request );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getContext' )->willReturn( $context );
		$special->method( 'getName' )->willReturn( 'Login' );

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
	public function testSpecialPageWithoutOldIdAllowsAnonymous() {
		$request = $this->createMock( self::$webRequestClassName );
		$request->method( 'getVal' )->willReturnMap( [
			[ 'oldid', null, null ],
		] );

		$user = $this->createMock( self::$userClassName );
		$user->method( 'isRegistered' )->willReturn( false );

		$context = $this->createMock( self::$contextClassName );
		$context->method( 'getUser' )->willReturn( $user );
		$context->method( 'getRequest' )->willReturn( $request );

		$special = $this->createMock( self::$specialPageClassName );
		$special->method( 'getContext' )->willReturn( $context );
		$special->method( 'getName' )->willReturn( 'Login' );

		$runner = $this->getMockBuilder( Hooks::class )
			->onlyMethods( [ 'denyAccess' ] )
			->getMock();
		$runner->expects( $this->never() )->method( 'denyAccess' );

		$result = $runner->onSpecialPageBeforeExecute( $special, null );
		$this->assertTrue( $result );
	}
}
