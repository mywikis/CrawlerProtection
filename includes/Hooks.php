<?php

namespace MediaWiki\Extension\CrawlerProtection;

use OutputPage;
use Article;
use Title;
use User;
use WebRequest;
use MediaWiki;
use SpecialPage;

class Hooks {
    /**
     * Block sensitive page views for anonymous users via MediaWikiPerformAction.
     * Handles:
     *  - ?type=revision
     *  - ?action=history
     *  - ?diff=1234
     *  - ?oldid=1234
     *
     * @param OutputPage   $output
     * @param Article      $article
     * @param Title        $title
     * @param User         $user
     * @param WebRequest   $request
     * @param MediaWiki    $wiki
     * @return bool        False to abort further action
     */
    public static function onMediaWikiPerformAction(
        OutputPage $output,
        Article $article,
        Title $title,
        User $user,
        WebRequest $request,
        MediaWiki $wiki
    ) {
        $type   = $request->getVal( 'type' );
        $action = $request->getVal( 'action' );
        $diffId = (int)$request->getVal( 'diff' );
        $oldId  = (int)$request->getVal( 'oldid' );

        // For MW 1.31, use isAnon() instead of isRegistered()
        if (
            $user->isAnon()
            && (
                $type === 'revision'
                || $action === 'history'
                || $diffId > 0
                || $oldId  > 0
            )
        ) {
            self::denyAccess( $output );
            return false;
        }

        return true;
    }

    /**
     * Block Special:RecentChangesLinked and Special:WhatLinksHere for anonymous users.
     *
     * @param SpecialPage $specialPage
     * @param string      $subPage
     * @return bool       False to abort execution
     */
    public static function onSpecialPageBeforeExecute( SpecialPage $specialPage, $subPage ) {
        $user = $specialPage->getContext()->getUser();
        // Allow only logged-in users
        if ( ! $user->isAnon() ) {
            return true;
        }

        $name = strtolower( $specialPage->getName() );
        if ( in_array( $name, [ 'recentchangeslinked', 'whatlinkshere' ], true ) ) {
            $out = $specialPage->getContext()->getOutput();
            self::denyAccess( $out );
            return false;
        }

        return true;
    }

    /**
     * Helper: output 403 Access Denied page using i18n messages.
     *
     * @param OutputPage $output
     * @return void
     */
    private static function denyAccess( OutputPage $output ): void {
        $output->setStatusCode( 403 );
        $output->setPageTitle(
            wfMessage( 'crawlerprotection-accessdenied-title' )->text()
        );
        // MW 1.31 does not have addWikiTextAsInterface(); use addWikiMsg()
        $output->addWikiMsg( 'crawlerprotection-accessdenied-text' );
    }
}
