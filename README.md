# CrawlerProtection

Protect wikis against crawler bots. CrawlerProtection denies **anonymous** user
access to certain MediaWiki action URLs and SpecialPages which are resource
intensive.

# Entry-point coverage

| Entry point | Protected |
|---|---|
| `index.php` (page views, action=history, diffs, etc.) | ✅ Always |
| `index.php` (Special pages) | ✅ Always |
| `api.php` (Action API modules) | ✅ Configurable via `$wgCrawlerProtectedApiModules` |
| `rest.php` (REST API paths) | ✅ Configurable via `$wgCrawlerProtectedRestPaths` (MW 1.44+) |

Both `$wgCrawlerProtectedApiModules` and `$wgCrawlerProtectedRestPaths` default to
an empty list so that upgrades do not silently break existing anonymous API
consumers. Operators must opt in to API/REST protection by adding entries.

The same bypass semantics apply on every entry point: registered users and IP
addresses in `$wgCrawlerProtectionAllowedIPs` are always permitted.

# Configuration

* `$wgCrawlerProtectedSpecialPages` - array of special pages to protect
  (default: `[ 'mobilediff', 'recentchangeslinked', 'whatlinkshere' ]`).
  Supported values are special page names or their aliases regardless of case.
  You do not need to use the 'Special:' prefix. Note that you can fetch a full
  list of SpecialPages defined by your wiki using the API and jq with a simple
  bash one-liner like
  `curl -s "[YOURWIKI]api.php?action=query&meta=siteinfo&siprop=specialpagealiases&format=json" | jq -r '.query.specialpagealiases[].aliases[]' | sort`
  Of course certain Specials MUST be allowed like Special:Login so do not block
  everything.
* `$wgCrawlerProtectedActions` - array of MediaWiki action names to block for
  anonymous users (default: `[ 'history' ]`). Removing `'history'` from this
  list disables protection of the history-listing page but does not affect
  whether individual revisions and diffs are protected (see
  `$wgCrawlerProtectionProtectRevisions` below).
* `$wgCrawlerProtectionProtectRevisions` - when `true` (default), anonymous
  access to individual revisions and diffs (requests using `type=revision`,
  `diff=`, or `oldid=` query parameters) is denied. Set to `false` to allow
  anonymous access to those URLs. This setting is independent of
  `$wgCrawlerProtectedActions`, so you can protect revisions/diffs without
  protecting the history listing page, or vice versa.
* `$wgCrawlerProtectedQueryParams` - array of special-page query parameters
  that are denied for anonymous users when the request has no `title`
  parameter, or an empty one (default: `[ 'target' ]`). A request such as
  `index.php?target=Foo&days=365&limit=5000` carries the filter parameters of
  `Special:RecentChangesLinked` but names no special page, so
  `$wgCrawlerProtectedSpecialPages` does not apply to it: MediaWiki ignores
  the parameters and renders the main page instead. Because each such URL is
  unique it also defeats CDN and reverse-proxy caching, so every request
  reaches the application. MediaWiki always emits a `title` alongside these
  parameters, so their presence without one indicates a crawler-generated
  request. Set to `[]` to disable this check.
* `$wgCrawlerProtectedApiModules` - array of Action API module names to block
  for anonymous users (default: `[]`). Matches both top-level action names
  (e.g. `'compare'`, `'parse'`) and the `prop`, `list`, `meta` and `generator`
  sub-modules of `action=query` (e.g. `'revisions'`, `'recentchanges'`,
  `'backlinks'`). Matching is case-insensitive. Example that
  protects the most common crawler-attractive modules:
  ```php
  $wgCrawlerProtectedApiModules = [ 'compare', 'parse', 'revisions', 'recentchanges', 'backlinks' ];
  ```
* `$wgCrawlerProtectedRestPaths` - array of REST API path glob patterns to
  block for anonymous users (default: `[]`). Each pattern is tested with
  `fnmatch()` with the `FNM_PATHNAME` flag, so `*` matches any single path
  component (it never spans a `/`) and `**` is not supported. Example that
  protects history and compare endpoints:
  ```php
  $wgCrawlerProtectedRestPaths = [ '/page/*/history', '/revision/*/compare/*' ];
  ```
  REST protection requires MediaWiki 1.44 or later; the setting is silently
  ignored on older versions.
* `$wgCrawlerProtectionUse418` - drop denied requests in a quick way via
  `die();` with
  [418 I'm a teapot](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/418)
  code (default: `false`)
* `$wgCrawlerProtectionAllowedIPs` - array of IP addresses or ranges that are
  always allowed through, even for anonymous requests (default: `[]`). Supports
  single IPv4/IPv6 addresses (`'1.2.3.4'`, `'2001:db8::1'`), CIDR notation
  (`'1.2.3.0/24'`, `'2001:db8::/32'`), and explicit ranges
  (`'1.2.3.1 - 1.2.3.10'`). The client IP is resolved via `WebRequest::getIP()`,
  which correctly handles trusted-proxy and `X-Forwarded-For` headers consistent
  with the rest of MediaWiki.
* `$wgCrawlerProtectionTreatTempUsersAsAnon` - when `true`, users with
  [temporary accounts](https://www.mediawiki.org/wiki/Help:Temporary_accounts)
  (`$wgAutoCreateTempUser`, available since MediaWiki 1.42) are treated as
  anonymous and subject to protection like any other non-logged-in visitor.
  When `false` (default), temporary-account users are treated as registered
  users and bypass all protection checks. Set to `true` if you do not want
  crawlers that receive a temporary account to bypass protection.

The pretty denial page carries an `X-Robots-Tag: noindex,nofollow` header and
the same robot policy as a `<meta>` tag, so that well-behaved crawlers stop
re-requesting denied URLs.

# Hooks

## CrawlerProtectionShouldDeny

Runs after CrawlerProtection has decided whether to deny a request, but before
the denial is carried out. Handlers can implement bespoke policy (cookie
checks, proof-of-work, CAPTCHA integration, fingerprint heuristics, crawler
allowlists, ...) without patching this extension.

Parameters:

* `User $user` - the user making the request.
* `WebRequest $request` - the current request.
* `string|null $specialPageName` - canonical name of the special page being
  executed, or `null` if the request is not a special page view.
* `bool &$shouldDeny` - whether the request will be denied. Set it to `true` to
  deny a request that would otherwise be allowed, or to `false` to allow a
  request that would otherwise be denied.

Return `false` to stop other handlers from running; the value of `$shouldDeny`
at that point is still honoured. The hook runs for every web request that
reaches CrawlerProtection (but not on the command line), including requests by
registered users and requests that touch no protected resource, so handlers
must inspect `$shouldDeny` and the request themselves rather than assuming a
denial is pending. It does not run for Action API or REST API requests, which
are governed solely by `$wgCrawlerProtectedApiModules` and
`$wgCrawlerProtectedRestPaths`.

Example, allowing anonymous access when a request carries a secret header:

```php
$wgHooks['CrawlerProtectionShouldDeny'][] = static function (
	$user, $request, $specialPageName, &$shouldDeny
) {
	if ( $shouldDeny && $request->getHeader( 'X-My-Crawler-Token' ) === $secret ) {
		$shouldDeny = false;
	}
};
```

