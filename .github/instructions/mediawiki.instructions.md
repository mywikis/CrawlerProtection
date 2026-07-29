---
applyTo: "**/*"
---

# MediaWiki baseline guidance (condensed)

Use this file for broad MediaWiki conventions. Keep repository-specific decisions in
`copilot-instructions.md` and `crawlerprotection-*.instructions.md`.

- Follow MediaWiki coding conventions and extension architecture guidance.
- Prefer PHPCS/Phan/tooling output over manual formatting judgments.
- For PHP: run auto-fixers (`composer phpcbf`) instead of hand-formatting large blocks.
- For JS/CSS/JSON/Markdown: keep existing file style; avoid unrelated reformatting.
- Avoid dynamic message-key construction when a finite set of full keys is known.
  Prefer selecting a full key value (e.g. ternary/if) and passing the full key to `wfMessage`.
- Keep security/i18n as first-class concerns: sanitize/escape output, avoid raw superglobals,
  and keep translatable text in i18n files.

For full upstream references:
- https://www.mediawiki.org/wiki/Manual:Coding_conventions
- https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP
