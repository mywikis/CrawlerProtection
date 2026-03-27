<?php
/**
 * Copyright (c) 2025 MyWikis
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
 * @file ServiceWiring.php
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CrawlerProtection\CrawlerProtectionService;
use MediaWiki\Extension\CrawlerProtection\ResponseFactory;
use MediaWiki\MediaWikiServices;

return [
	'CrawlerProtection.ResponseFactory' =>
		static function ( MediaWikiServices $services ): ResponseFactory {
			return new ResponseFactory(
				new ServiceOptions(
					ResponseFactory::CONSTRUCTOR_OPTIONS,
					$services->getMainConfig()
				)
			);
		},
	'CrawlerProtection.CrawlerProtectionService' =>
		static function ( MediaWikiServices $services ): CrawlerProtectionService {
			return new CrawlerProtectionService(
				new ServiceOptions(
					CrawlerProtectionService::CONSTRUCTOR_OPTIONS,
					$services->getMainConfig()
				),
				$services->get( 'CrawlerProtection.ResponseFactory' )
			);
		},
];
