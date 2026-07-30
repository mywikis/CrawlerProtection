---
applyTo: "**/*.php"
---

# CrawlerProtection architecture and security rules

## Architecture invariants

- This extension protects anonymous traffic at these hook points only:
  - `MediaWikiPerformAction` (index.php page views and actions)
  - `SpecialPageBeforeExecute` (index.php special pages)
  - `ApiCheckCanExecute` (api.php modules)
  - `RestCheckCanExecute` (rest.php paths, MediaWiki 1.44+)
- `includes/Hooks.php` must remain a thin adapter layer delegating to services.
- Business logic belongs in `CrawlerProtectionService` and response rendering in `ResponseFactory`.
- Instantiate services only in `includes/ServiceWiring.php`.
- Service names must use the existing namespace pattern:
  - `CrawlerProtection.ResponseFactory`
  - `CrawlerProtection.CrawlerProtectionService`
- New service config must use `CONSTRUCTOR_OPTIONS` + injected `ServiceOptions`.
- New config keys must follow `CrawlerProtection*` / `CrawlerProtected*` naming and be added to
  `extension.json` with `merge_strategy: "provide_default"` for arrays.

## Multi-version compatibility pattern

- Maintain the `class_alias` compatibility blocks in `includes/Hooks.php` global scope.
- Keep aliases before `use` statements so static analysis can resolve names.
- If adding newly namespaced core classes with older fallback equivalents, extend this pattern.

## Security-focused rules for this extension

- Never trust arbitrary forwarded IP headers directly; rely on MediaWiki request/user abstractions.
- Treat allowlist values as CIDR/IP ranges and validate via MediaWiki/Wikimedia helpers (current pattern: `IPUtils::isInRanges`).
- For denial responses, avoid reflecting unescaped user-controlled data in raw output.
- Changes that affect denial responses must preserve safe cache behavior and avoid cache-poisoning vectors
  (status codes, cache headers, and vary semantics should remain deliberate and explicit).

## Logging policy

- Current extension logic is intentionally minimal and may not emit operational logs.
- If logging is introduced for diagnostics/security events, inject `Psr\Log\LoggerInterface`
  from `ServiceWiring.php` using channel name `CrawlerProtection`.
- Do not call `LoggerFactory::getInstance()` outside wiring.
