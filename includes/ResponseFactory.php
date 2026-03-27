<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Extension\CrawlerProtection;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Output\OutputPage;

/**
 * Responsible for producing the denial HTTP response.
 *
 * Supports three strategies:
 *  - 418 "I'm a teapot"
 *  - Raw HTTP response (configurable header and body)
 *  - Pretty 403 page rendered through MediaWiki's OutputPage
 */
class ResponseFactory {

	private const TEAPOT_HEADER = 'HTTP/1.0 418 I\'m a teapot';
	private const TEAPOT_BODY = 'I\'m a teapot';

	/** @var string[] List of constructor options this class accepts */
	public const CONSTRUCTOR_OPTIONS = [
		'CrawlerProtectionUse418',
		'CrawlerProtectionRawDenial',
		'CrawlerProtectionRawDenialHeader',
		'CrawlerProtectionRawDenialText',
	];

	/** @var ServiceOptions */
	private ServiceOptions $options;

	/**
	 * @param ServiceOptions $options
	 */
	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * Deny access using the configured strategy.
	 *
	 * @param OutputPage $output Used only for the "pretty" strategy
	 * @return void
	 */
	public function denyAccess( $output ): void {
		if ( $this->options->get( 'CrawlerProtectionUse418' ) ) {
			$this->denyAccessWith418();
		} elseif ( $this->options->get( 'CrawlerProtectionRawDenial' ) ) {
			$this->denyAccessRaw(
				$this->options->get( 'CrawlerProtectionRawDenialHeader' ),
				$this->options->get( 'CrawlerProtectionRawDenialText' )
			);
		} else {
			$this->denyAccessPretty( $output );
		}
	}

	/**
	 * Output a 418 "I'm a teapot" response and halt.
	 *
	 * @return void
	 * @suppress PhanPluginNeverReturnMethod
	 */
	protected function denyAccessWith418(): void {
		$this->denyAccessRaw( self::TEAPOT_HEADER, self::TEAPOT_BODY );
	}

	/**
	 * Output a raw HTTP response and halt.
	 *
	 * @param string $header
	 * @param string $message
	 * @return void
	 * @suppress PhanPluginNeverReturnMethod
	 */
	protected function denyAccessRaw( string $header, string $message ): void {
		header( $header );
		die( $message );
	}

	/**
	 * Output a pretty 403 Access Denied page using i18n messages.
	 *
	 * @param OutputPage $output
	 * @return void
	 */
	protected function denyAccessPretty( $output ): void {
		$output->setStatusCode( 403 );
		$output->addWikiTextAsInterface(
			wfMessage( 'crawlerprotection-accessdenied-text' )->plain()
		);

		if ( version_compare( MW_VERSION, '1.41', '<' ) ) {
			$output->setPageTitle( wfMessage( 'crawlerprotection-accessdenied-title' ) );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod Exists in 1.41+
			$output->setPageTitleMsg( wfMessage( 'crawlerprotection-accessdenied-title' ) );
		}
	}
}
