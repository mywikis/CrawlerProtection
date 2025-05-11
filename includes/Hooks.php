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
     *
     * Special pages are handled separately in onSpecialPageBeforeExecute().
     *
     * @return bool False to abort further action
     */
    public static function onMediaWikiPerformAction(
        $output,
        $article,
        $title,
        $user,
        $request,
        $wiki
    ) {
        $type   = $request->getVal( 'type' );
        $action = $request->getVal( 'action' );

        if ( !$user->isRegistered() && ( $type === 'revision' || $action === 'history' ) ) {
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
     * @return bool False to abort execution
     */
    public static function onSpecialPageBeforeExecute( $specialPage, $subPage ) {
        $user = $specialPage->getContext()->getUser();
        if ( $user->isRegistered() ) {
            return true; // logged‑in users: allow
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
