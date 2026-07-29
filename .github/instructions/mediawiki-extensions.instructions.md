---
applyTo: "**/*"
---

# MediaWiki extension guidance (repo-oriented)

- Use dependency injection and service wiring for extension logic.
- Keep UI/hook glue thin; put business logic in reusable services.
- Use MediaWiki wrappers/APIs instead of direct superglobal access.
- Add or update tests for behavior changes; avoid untested logic changes.
- Keep localization complete for new messages (`en.json` + `qqq.json`).
- Avoid introducing extension-level global state.
- Keep changes backward-compatible with supported MediaWiki/PHP matrix in this repository.
- Prefer deterministic tool-based validation (PHPCS, Phan, PHPUnit) over style-only edits.
