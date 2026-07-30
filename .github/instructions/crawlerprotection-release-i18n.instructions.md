---
applyTo: "**/*"
---

# CrawlerProtection release, i18n, and JSON formatting rules

## Release and CI gates

- This repository enforces a version-bump gate via `.github/scripts/check-version-bump.sh`.
- Any non-hidden-path content change must bump `extension.json` `version`.
- Any `i18n/` change must include at least a patch version bump.
- Admin/maintain collaborators may override the gate in CI; do not rely on override for routine work.
- Prefer semantic intent:
  - patch: fixes/documentation clarifications
  - minor: new config keys/behavior additions

## i18n checklist (when adding/changing user-facing text)

- Update `i18n/en.json` and `i18n/qqq.json` together.
- Use `crawlerprotection-` prefixed, hyphenated message keys.
- Keep punctuation/colon inside the message text, not hardcoded in PHP.
- Avoid dynamically concatenated message keys when finite full keys are known.
- Run the banana checker when available in your MediaWiki test environment.

## JSON formatting conventions

- Keep JSON files (`extension.json`, `composer.json`, `i18n/*.json`) with 4-space indentation.
- Preserve key order style already used by surrounding file unless a functional reason requires reordering.
- Do not reformat unrelated JSON sections in behavior-focused changes.
