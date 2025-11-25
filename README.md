# CrawlerProtection
Protect wikis against crawler bots. CrawlerProtection denies **anonymous** user
access to certain MediaWiki action URLs and SpecialPages which are resource
intensive.

# Configuration

* `$wgCrawlerProtectedSpecialPages` - array of special pages to protect (default:
`[ 'mobilediff', 'recentchangeslinked', 'whatlinkshere' ]`). Supported values are special page names or their aliases regardless of case. You do not need to 
use the 'Special:' prefix. Note that you can fetch a full list of SpecialPages
defined by your wiki using the API and jq with a simple bash one-liner like
`curl -s "[YOURWIKI]api.php?action=query&meta=siteinfo&siprop=specialpagealiases&format=json" | jq -r '.query.specialpagealiases[].aliases[]' | sort` Of course
certain Specials MUST be allowed like Special:Login so do not block everything.
* `$wgCrawlerProtectionUse418` - drop denied requests in a quick way via `die();` with [418 I'm a teapot](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/418) code (default: `false`)
