# CrawlerProtection
Protect wikis against crawler bots

# Configuration

* `$wgCrawlerProtectedSpecialPages` - array of special pages to protect (default: `[ 'recentchangeslinked', 'whatlinkshere' ]`)
* `$wgCrawlerProtectionDenyFast` - drop denied requests in a quick way via `die();` with [418 I'm a teapot](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/418) code (default: `false`)
