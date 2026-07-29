<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
// phpcs:disable MediaWiki.Files.ClassMatchesFilename.NotMatch

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

namespace MediaWiki\Api\Hook {
	interface ApiCheckCanExecuteHook {
		public function onApiCheckCanExecute( $module, $user, &$message );
	}
}

namespace MediaWiki\Rest\Hook {
	interface RestCheckCanExecuteHook {
		public function onRestCheckCanExecute( $module, $handler, string $path, $request, &$error );
	}
}

// API/REST stubs
namespace MediaWiki\Rest {
	class HttpException extends \RuntimeException {
		/**
		 * @param string $message
		 * @param int $code HTTP status code
		 */
		public function __construct( string $message = '', int $code = 500 ) {
			parent::__construct( $message, $code );
		}
	}
}

// ServiceOptions stub
namespace MediaWiki\Config {
	class ServiceOptions {
		/** @var array */
		private array $options = [];

		/**
		 * @param string[] $keys
		 * @param mixed ...$sources
		 */
		public function __construct( array $keys, ...$sources ) {
			foreach ( $sources as $source ) {
				if ( $source instanceof Config ) {
					foreach ( $keys as $key ) {
						if ( !array_key_exists( $key, $this->options ) ) {
							$this->options[$key] = $source->get( $key );
						}
					}
				} elseif ( is_array( $source ) ) {
					foreach ( $keys as $key ) {
						if ( !array_key_exists( $key, $this->options ) && array_key_exists( $key, $source ) ) {
							$this->options[$key] = $source[$key];
						}
					}
				}
			}
		}

		/**
		 * @param string[] $expectedKeys
		 */
		public function assertRequiredOptions( array $expectedKeys ): void {
			// no-op in tests
		}

		/**
		 * @param string $key
		 * @return mixed
		 */
		public function get( string $key ) {
			return $this->options[$key] ?? null;
		}
	}

	interface Config {
		/**
		 * @param string $name
		 * @return mixed
		 */
		public function get( $name );
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
		public function isRegistered(): bool {
			return false;
		}

		public function getName(): string {
			return '';
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
		 * @return \MediaWiki\Config\Config
		 */
		public function getMainConfig() {
			return new class() implements \MediaWiki\Config\Config {
				/**
				 * @param string $name
				 * @return mixed
				 */
				public function get( $name ) {
					return null;
				}
			};
		}
	}
}
