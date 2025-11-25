# CrawlerProtection
Protect wikis against crawler bots

# Configuration

* `$wgCrawlerProtectedSpecialPages` - array of special pages to protect (default: `[ 'recentchangeslinked', 'whatlinkshere' ]`). Supported values are lowercase special page names, titled spacial page names and prefixed special page names.
* `$wgCrawlerProtectionUse418` - drop denied requests in a quick way via `die();` with [418 I'm a teapot](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/418) code (default: `false`)
