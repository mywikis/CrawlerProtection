# CrawlerProtection Copilot instructions

This repository is a MediaWiki extension that blocks high-cost crawler traffic for anonymous users.

## Extension scope

- Main logic: `includes/CrawlerProtectionService.php`
- Response rendering: `includes/ResponseFactory.php`
- Hook entry points only:
  - `MediaWikiPerformAction`
  - `SpecialPageBeforeExecute`

Keep hook handlers thin; put behavior in services.

## Compatibility and syntax floor

- Extension requirement: MediaWiki `>= 1.39.4`
- CI matrix currently tests:
  - REL1_39 on PHP 7.4, 8.1, and 8.2 (phpunit job)
  - REL1_43 on PHP 8.2, 8.3, 8.4
- Treat **PHP 7.4 syntax as mandatory** for production code and tests.

Do **not** introduce PHP 8-only syntax in extension code:

- constructor property promotion
- `match`
- union/intersection types
- enums
- `readonly`
- nullsafe operator `?->`
- named arguments
- first-class callable syntax (`foo(...)`)

## Hard CI invariants

- Non-hidden-path content changes must include a version bump in `extension.json`.
- Changes in `i18n/` must include at least a patch bump.
- CI validates: parallel-lint, PHPCS, Phan (`--minimum-target-php-version=7.4`), PHPUnit.

See `.github/scripts/check-version-bump.sh` and `.github/workflows/ci.yml`.

## Repository layout

- `includes/` extension classes and wiring
- `i18n/` localization JSON
- `tests/phpunit/unit/` unit tests
- `tests/phpunit/stubs.php` and `tests/phpunit/namespaced-stubs.php` test stubs

## Validation commands

Primary local workflow uses docker-compose-ci:

- `make ci`
- `MW_VERSION=1.39 PHP_VERSION=8.1 make ci`
- `MW_VERSION=1.43 PHP_VERSION=8.3 make ci`

Inside container (`make bash`):

- `composer phpcs`
- `composer phpcbf`
- `composer phpunit`

For setup details, see `TESTING.md`, `.github/CI-SETUP.md`, and `.github/DOCKER-CI-QUICKREF.md`.

## Instruction precedence

1. `.github/copilot-instructions.md` (repo-specific source of truth)
2. `.github/instructions/crawlerprotection-*.instructions.md`
3. `.github/instructions/php.instructions.md`
4. `.github/instructions/mediawiki-extensions*.instructions.md`
5. `.github/instructions/mediawiki.instructions.md`

If guidance conflicts, prefer the highest entry above and then CI/tooling output.

## Definition of done

Before finalizing a change:

- extension behavior and tests updated minimally for the task
- `extension.json` version bump done when required by CI gate
- PHP 7.4 syntax compatibility preserved
- PHPCS/Phan/PHPUnit checks run for affected areas
- i18n changes include both `en.json` and `qqq.json`
- README updated when configuration or behavior changes
