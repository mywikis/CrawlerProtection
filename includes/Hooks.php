<?php

namespace MediaWiki\Extension\CrawlerProtection;

use OutputPage;
use Article;
use Title;
use User;
use WebRequest;
use MediaWiki\MediaWiki;

class Hooks {
    /**
     * Prevent viewing revisions, history, and Special:RecentChangesLinked by anonymous users.
     *
     * @param OutputPage $output
     * @param Article    $article
     * @param Title      $title
     * @param User       $user
     * @param WebRequest $request
     * @param MediaWiki  $wiki
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
        // Block revision view, history action, and RecentChangesLinked for anonymous users
        $type   = $request->getVal( 'type' );
        $action = $request->getVal( 'action' );

        if ( !$user->isRegistered()
            && (
                $type === 'revision'
                || $action === 'history'
                || $title->isSpecial( 'Recentchangeslinked' )
            )
        ) {
            // Access denied message
            $output->setPageTitle( wfMessage( 'crawlerprotection-accessdenied-title' )->text() );
            $output->addWikiTextAsInterface( wfMessage( 'crawlerprotection-accessdenied-text' )->text() );
            $output->setStatusCode( 403 );

            return false;
        }

        // Otherwise, allow normal processing
        return true;
    }
}