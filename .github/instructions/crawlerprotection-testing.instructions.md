---
applyTo: "tests/phpunit/**/*.php"
---

# CrawlerProtection testing rules

- Unit tests in this repo use `PHPUnit\Framework\TestCase` with local stubs.
- Keep using `tests/phpunit/stubs.php` and `tests/phpunit/namespaced-stubs.php` for MW shims in unit tests.
- When production code consumes a new MediaWiki class/function that is unavailable in isolated unit tests,
  update stubs in the same change.
- Add regression tests for each bug fix or behavior change.
- Prefer data providers over duplicated case-by-case tests.
- Add `@covers` / `@coversDefaultClass` for changed production classes where practical.
- Prefer adding structure/registration checks (for example, extension.json/service wiring smoke coverage)
  whenever changing hooks, wiring, or constructor options.
- For wiring/registration changes, add or update smoke-test coverage strategy notes in the PR,
  and prefer introducing integration tests under `tests/phpunit/integration/` when CI support is added.
- Ensure tests remain compatible with supported MediaWiki/PHP versions (including PHP 7.4 syntax limits).
