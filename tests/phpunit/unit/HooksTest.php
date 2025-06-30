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
     */
    public function testSpecialPageBlocksAnonymous( $specialPageName ) {
        $output = $this->createMock( self::$outputPageClassName );

        $user = $this->createMock( self::$userClassName );
        $user->method( 'isRegistered' )->willReturn( false );

        $context = new class( $user, $output ) {
            private $user;
            private $output;

            public function __construct( $user, $output ) {
                $this->user = $user;
                $this->output = $output;
            }

            public function getUser() {
                return $this->user;
            }

            public function getOutput() {
                return $this->output;
            }
        };

        $special = $this->createMock( self::$specialPageClassName );
        $special->method( 'getName' )->willReturn( $specialPageName );
        $special->method( 'getContext' )->willReturn( $context );

        $runner = $this->getMockBuilder( Hooks::class )
            ->onlyMethods( [ 'denyAccess' ] )
            ->getMock();
        $runner->expects( $this->once() )->method( 'denyAccess' )->with( $output );

        $result = $runner->onSpecialPageBeforeExecute( $special, null );
        $this->assertFalse( $result );
    }

    /**
     * @covers ::onSpecialPageBeforeExecute
     * @dataProvider provideBlockedSpecialPages
     */
    public function testSpecialPageAllowsLoggedIn( $specialPageName ) {
        $output = $this->createMock( self::$outputPageClassName );

        $user = $this->createMock( self::$userClassName );
        $user->method( 'isRegistered' )->willReturn( true );

        $context = new class( $user, $output ) {
            private $user;
            private $output;

            public function __construct( $user, $output ) {
                $this->user = $user;
                $this->output = $output;
            }

            public function getUser() {
                return $this->user;
            }

            public function getOutput() {
                return $this->output;
            }
        };

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

        $context = new class( $user, $output ) {
            private $user;
            private $output;

            public function __construct( $user, $output ) {
                $this->user = $user;
                $this->output = $output;
            }

            public function getUser() {
                return $this->user;
            }

            public function getOutput() {
                return $this->output;
            }
        };

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
     * Data provider for blocked special pages
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
