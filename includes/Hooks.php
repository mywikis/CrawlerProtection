<?php

namespace MediaWiki\Extension\CrawlerProtection;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class Hooks implements MediaWikiPerformActionHook, SpecialPageBeforeExecuteHook {
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
     * @param OutputPage        $output
     * @param Article           $article
     * @param Title             $title
     * @param User              $user
     * @param WebRequest        $request
     * @param ActionEntryPoint  $mediaWiki
     * @return bool        False to abort further action
     */
    public function onMediaWikiPerformAction(
        $output,
        $article,
        $title,
        $user,
        $request,
        $mediaWiki
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
     * @param SpecialPage $special
     * @param string|null $subPage
     * @return bool       False to abort execution
     */
    public function onSpecialPageBeforeExecute( $special, $subPage ) {
        $user = $special->getContext()->getUser();
        if ( $user->isRegistered() ) {
            return true; // logged-in users: allow
        }

        $name = strtolower( $special->getName() );
        if ( in_array( $name, [ 'recentchangeslinked', 'whatlinkshere' ], true ) ) {
            $out = $special->getContext()->getOutput();
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
