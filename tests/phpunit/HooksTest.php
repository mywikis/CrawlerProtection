<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Extension\CrawlerProtection\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use User;
use WebRequest;

/**
 * @coversDefaultClass \MediaWiki\Extension\CrawlerProtection\Hooks
 */
class HooksTest extends TestCase {
	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testRevisionTypeBlocksAnonymous() {
		$output = $this->createMock( OutputPage::class );
		$output->expects( $this->once() )->method( 'setPageTitle' );
		$output->expects( $this->once() )->method( 'addWikiTextAsInterface' );
		$output->expects( $this->once() )->method( 'setStatusCode' )->with( 403 );

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )->with( 'type' )->willReturn( 'revision' );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( false );

		$article = $this->createMock( Article::class );
		$title = $this->createMock( Title::class );
		$wiki = $this->createMock( ActionEntryPoint::class );

		$runner = new Hooks();
		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testRevisionTypeAllowsLoggedIn() {
		$output = $this->createMock( OutputPage::class );

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )->with( 'type' )->willReturn( 'revision' );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( true );

		$article = $this->createMock( Article::class );
		$title = $this->createMock( Title::class );
		$wiki = $this->createMock( ActionEntryPoint::class );

		$runner = new Hooks();
		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertTrue( $result );
	}

	/**
	 * @covers ::onMediaWikiPerformAction
	 */
	public function testNonRevisionTypeAlwaysAllowed() {
		$output = $this->createMock( OutputPage::class );

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getVal' )->with( 'type' )->willReturn( 'view' );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( false );

		$article = $this->createMock( Article::class );
		$title = $this->createMock( Title::class );
		$wiki = $this->createMock( ActionEntryPoint::class );

		$runner = new Hooks();
		$result = $runner->onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
		$this->assertTrue( $result );
	}
}
