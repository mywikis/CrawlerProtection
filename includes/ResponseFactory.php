<?php
/**
 * Copyright (c) 2025-2026 MyWikis
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @file ResponseFactory.php
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
	 * When CrawlerProtectionRawDenial is enabled, a raw HTTP response is
	 * sent.  If CrawlerProtectionUse418 is *also* enabled the response
	 * uses the 418 "I'm a teapot" status; otherwise the configured
	 * header / body are used.
	 *
	 * When CrawlerProtectionRawDenial is disabled, a pretty 403 page is
	 * rendered through OutputPage regardless of the Use418 setting.
	 *
	 * @param OutputPage $output Used only for the "pretty" strategy
	 * @return void
	 */
	public function denyAccess( $output ): void {
		if ( $this->options->get( 'CrawlerProtectionRawDenial' ) ) {
			if ( $this->options->get( 'CrawlerProtectionUse418' ) ) {
				$this->denyAccessWith418();
			} else {
				$this->denyAccessRaw(
					$this->options->get( 'CrawlerProtectionRawDenialHeader' ),
					$this->options->get( 'CrawlerProtectionRawDenialText' )
				);
			}
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
