<?php

namespace MediaWiki\Extension\CrawlerProtection\Tests;

use MediaWiki\Extension\CrawlerProtection\Hooks;
use OutputPage;
use Title;
use User;
use WebRequest;
use MediaWiki\MediaWiki;
use Article;
use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase {
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
        $wiki = $this->createMock( MediaWiki::class );

        $result = Hooks::onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
        $this->assertFalse( $result );
    }

    public function testRevisionTypeAllowsLoggedIn() {
        $output = $this->createMock( OutputPage::class );

        $request = $this->createMock( WebRequest::class );
        $request->method( 'getVal' )->with( 'type' )->willReturn( 'revision' );

        $user = $this->createMock( User::class );
        $user->method( 'isRegistered' )->willReturn( true );

        $article = $this->createMock( Article::class );
        $title = $this->createMock( Title::class );
        $wiki = $this->createMock( MediaWiki::class );

        $result = Hooks::onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
        $this->assertTrue( $result );
    }

    public function testNonRevisionTypeAlwaysAllowed() {
        $output = $this->createMock( OutputPage::class );

        $request = $this->createMock( WebRequest::class );
        $request->method( 'getVal' )->with( 'type' )->willReturn( 'view' );

        $user = $this->createMock( User::class );
        $user->method( 'isRegistered' )->willReturn( false );

        $article = $this->createMock( Article::class );
        $title = $this->createMock( Title::class );
        $wiki = $this->createMock( MediaWiki::class );

        $result = Hooks::onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki );
        $this->assertTrue( $result );
    }
}