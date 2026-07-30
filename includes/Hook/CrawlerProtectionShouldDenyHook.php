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
 * @file CrawlerProtectionShouldDenyHook.php
 */

namespace MediaWiki\Extension\CrawlerProtection\Hook;

/**
 * Hook interface for the CrawlerProtectionShouldDeny hook.
 *
 * This hook lets site operators and other extensions implement bespoke access
 * policy (cookie checks, proof-of-work, CAPTCHA integration, fingerprint
 * heuristics, crawler allowlists, ...) without patching this extension.
 *
 * @stable to implement
 */
interface CrawlerProtectionShouldDenyHook {

	/**
	 * Called after CrawlerProtection has decided whether to deny a request,
	 * but before the denial is carried out.
	 *
	 * Handlers may set $shouldDeny to true to deny a request that would
	 * otherwise be allowed, or to false to allow a request that would
	 * otherwise be denied. Return false to stop other handlers from running;
	 * the value of $shouldDeny at that point is still honoured.
	 *
	 * The hook runs for every web request that reaches CrawlerProtection,
	 * including requests by registered users and requests that touch no
	 * protected resource, so handlers must inspect $shouldDeny and the
	 * request themselves rather than assuming a denial is pending.
	 *
	 * @since 1.7.0
	 *
	 * @param \MediaWiki\User\User $user The user making the request
	 * @param \MediaWiki\Request\WebRequest $request The current request
	 * @param string|null $specialPageName Canonical name of the special page
	 *   being executed, or null if the request is not a special page view
	 * @param bool &$shouldDeny Whether the request will be denied; modify to
	 *   change the outcome
	 * @return bool|void True or no return value to continue, false to stop
	 *   other handlers from running
	 */
	public function onCrawlerProtectionShouldDeny(
		$user,
		$request,
		?string $specialPageName,
		bool &$shouldDeny
	);
}
