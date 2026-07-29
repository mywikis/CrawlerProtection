---
applyTo: "**/*"
---

# MediaWiki extension clean-code guidance (repo-oriented)

- Construct services in `includes/ServiceWiring.php`.
- Do not call `MediaWikiServices::getInstance()` in production classes.
- Use `ServiceOptions` + `CONSTRUCTOR_OPTIONS` for config dependencies.
- Inject dependencies through constructors (including optional logger dependencies).
- Keep hook classes as delegation layers only.
- Keep service names consistent and namespaced (e.g. `CrawlerProtection.*`).
- Prefer small, focused methods and explicit control flow.
- Preserve multi-version compatibility patterns already used by the extension.
