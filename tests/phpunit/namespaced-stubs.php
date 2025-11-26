<?php

// Hook interfaces
namespace MediaWiki\Hook {
	interface MediaWikiPerformActionHook {
		public function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $mediaWiki );
	}
}

namespace MediaWiki\SpecialPage\Hook {
	interface SpecialPageBeforeExecuteHook {
		public function onSpecialPageBeforeExecute( $special, $subPage );
	}
}

// Core classes in their proper namespaces
namespace MediaWiki\Output {
	class OutputPage {
		public function setStatusCode( $code ) {
		}

		public function addWikiTextAsInterface( $text ) {
		}

		public function setPageTitle( $title ) {
		}

		public function setPageTitleMsg( $msg ) {
		}
	}
}

namespace MediaWiki\SpecialPage {
	class SpecialPage {
		public function getName() {
			return '';
		}

		public function getContext() {
			return null;
		}
	}
}

namespace MediaWiki\User {
	class User {
		public function isRegistered() {
			return false;
		}
	}
}

namespace MediaWiki\Request {
	class WebRequest {
		public function getVal( $name, $default = null ) {
			return $default;
		}
	}
}

namespace MediaWiki\Title {
	class Title {
	}
}

namespace MediaWiki\Page {
	class Article {
	}
}

namespace MediaWiki\Actions {
	class ActionEntryPoint {
	}
}

namespace MediaWiki {
	class MediaWikiServices {
		/** @var MediaWikiServices|null */
		private static $instance = null;

		/**
		 * @return MediaWikiServices
		 */
		public static function getInstance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * @param MediaWikiServices|null $instance
		 */
		public static function setInstance( $instance ) {
			self::$instance = $instance;
		}

		/**
		 * Reset the singleton instance for testing
		 *
		 * @return void
		 */
		public static function resetForTesting() {
			self::$instance = null;
		}

		/**
		 * @return \Config
		 */
		public function getMainConfig() {
			return new class() {
				/**
				 * @param string $name
				 * @return mixed
				 */
				public function get( $name ) {
					if ( $name === 'CrawlerProtectedSpecialPages' ) {
						return [
							'RecentChangesLinked',
							'WhatLinksHere',
							'MobileDiff',
							'recentchangeslinked',
							'whatlinkshere',
							'mobilediff'
						];
					}
					if ( $name === 'CrawlerProtectionUse418' ) {
						return true;
					}
					return null;
				}
			};
		}
	}
}
