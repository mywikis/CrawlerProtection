<?php

namespace MediaWiki\Extension\CrawlerProtection;

use OutputPage;
use Article;
use Title;
use User;
use WebRequest;
use MediaWiki\MediaWiki;
use MediaWiki\SpecialPage\SpecialPage;
use RequestContext;

class Hooks {
    /**
     * Block sensitive page views for anonymous users via MediaWikiPerformAction.
     * Handles:
     *  - ?type=revision
     *  - ?action=history
     *  - ?diff=1234
     *  - ?oldid=1234
     *
     * Special pages (e.g. Special:WhatLinksHere) are handled separately.
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

        if (
            !$user->isRegistered()
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
        if ( $user->isRegistered() ) {
            return true; // logged-in users: allow
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
        $output->setPageTitle( wfMessage( 'crawlerprotection-accessdenied-title' )->text() );
        $output->addWikiTextAsInterface( wfMessage( 'crawlerprotection-accessdenied-text' )->text() );
    }
}
