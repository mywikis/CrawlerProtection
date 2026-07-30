# CrawlerProtection

Protect wikis against crawler bots. CrawlerProtection denies **anonymous** user
access to certain MediaWiki action URLs and SpecialPages which are resource
intensive.

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
* `$wgCrawlerProtectionUse418` - drop denied requests in a quick way via
  `die();` with
  [418 I'm a teapot](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/418)
  code (default: `false`)

The pretty denial page carries an `X-Robots-Tag: noindex,nofollow` header and
the same robot policy as a `<meta>` tag, so that well-behaved crawlers stop
re-requesting denied URLs.

