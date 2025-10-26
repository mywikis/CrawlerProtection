---
applyTo: "*"
---

# MediaWiki Extension Best Practices

## Code architecture

* **MUST:** Use structured logging, with a channel name specific to your extension and with appropriate use of message severity levels. This aids debugging. For Wikimedia Foundation deployment, this also helps your team in monitoring the extension. See also: [Logstash on Wikitech](https://wikitech.wikimedia.org/wiki/OpenSearch_Dashboards).
* **SHOULD:** Provide the same functionality through the API (Action API or REST API) and the graphical interface (`index.php`).
  * **OPTIONAL:** The functionality should be implemented without significant code duplication (e.g. shared backend service class with light API and SpecialPage bindings.)
* **SHOULD:** Use Dependency Injection ("DI") and service classes registered in service wiring.
* **SHOULD:** Avoid global state, especially singletons or other hidden state via static class variable (unless for debug logs and other state intrinsically about the current process and not about any particular wiki or caller). Avoid static calls for anything other than stateless utility methods.
* **SHOULD:** Avoid use of older unrefactored global or static functions from unrelated classes when writing new DI-based service classes.
* **SHOULD:** Only throw exceptions for situations that callers should not handle and thus are meant to bubble up.
* **SHOULD:** Don't hardcode wikitext and assumptions about templates, especially in a way that's not configurable for each website.
* **SHOULD:** Code should be readable by someone who is familiar in that area.
* **SHOULD:** Have a clear separation of concerns between what it actually does, and how it is presented to the user.
* **SHOULD:** Think twice before adding new public APIs that must remain for content compatibility, such as new wikitext syntax functionality.
* **SHOULD:** Not tightly integrate skin functionality with extension functionality.
* **SHOULD:** Not add new user preferences, unless you have a really good reason for doing so.
* **OPTIONAL:** Expose JavaScript methods for use by user scripts and gadgets, and to enable easy debugging from the console.

## File structure

Overall, the extension's file layout should be organized: consistent naming, directory structure that is logical and not messy.

* **MUST:** Using the following standard directory layout:
  * `src/` (when using PSR4 name-spacing, preferred) or `includes/`: Contains all (and only) PHP classes.
    * i18n files for special page alias, magic words or namespaces located in root folder.
    * **SHOULD:** Use [PSR-4](https://www.php-fig.org/psr/psr-4/) structure for classes and files, using AutoloadNamespaces in `extension.json` instead of listing all classes.
    * **SHOULD:** Classes in `MediaWiki\Extension\ExtensionName` namespace (or `MediaWiki\Skin\SkinName` if a skin).
    * **SHOULD:** One class per file.
  * `modules/` (or `resources`) - Contains JavaScript and CSS for ResourceLoader.
  * `maintenance/` command-line maintenance scripts
  * `i18n/` - Contains localised messages in JSON files.
  * `sql/` - SQL files for database modifications (e.g. called by LoadExtensionSchemaUpdates hook)
  * `tests/`:
    * `tests/parser/` - Contains parser test files.
    * `tests/phpunit/` - Contains PHPUnit test cases.
      * `tests/phpunit/unit/` - Contains test cases extending `MediaWikiUnitTestCase`
      * `tests/phpunit/integration/` - Contains test cases extending `MediaWikiIntegrationTestCase`
    * `tests/qunit/` - Contains QUnit test cases.
    * `tests/selenium/` - Contains Selenium browser test files.
  * `COPYING` or `LICENSE` - Contains full copy of the relevant license the code is released under.
* **SHOULD:** Avoid having many files in the root level directory.
* **SHOULD:** Avoid having dozens of nested directories that all only contain one or two things.
* **SHOULD:** Avoid having very large files, or very many tiny files (but keep following one class per file pattern – many tiny classes may be a sign of something else going wrong).
* **SHOULD:** Write a README file that summarizes the docs and gives detailed installation instructions.
* **SHOULD:** Declare foreign resources in a `foreign-resources.yaml` file.

## Database

* **MUST:** If adding database tables, use the LoadExtensionSchemaUpdates hook to ensure update.php works.
* **MUST:** Uses the Wikimedia-Rdbms library for all database access. Doing so avoids most SQL injection attack vectors, takes care of ensuring transactional correctness, and follows performance best practices.
* **SHOULD:** Work well in a distributed environment (concurrency, multiple databases, clustering).
* **SHOULD:** If it needs persistence, create nice SQL (primary keys, indexes where needed) and uses some caching mechanism where/if necessary.
* **SHOULD:** Never add fields to the core tables nor alter them in any way. To persist data associated with core tables, create a dedicated table for the extension and reference the core table's primary key. This makes it easier to remove an extension.
* **SHOULD:** It should use abstract schema.
* **OPTIONAL:** If the extension persists data and supports uninstalling, provide a maintenance script that automates this (e.g. drop tables, prune relevant log entries and page properties).

## Coding conventions

Overall, follow the MediaWiki coding conventions for PHP, JavaScript, CSS, and any other languages that are in-use and have applicable code conventions.

* **SHOULD:** Run MediaWiki-CodeSniffer to enforce PHP conventions (check CI Entry points).
* **SHOULD:** Run Phan for PHP static analysis (check CI Entry points).
* **SHOULD:** Run ESLint for JavaScript conventions and static analysis (check CI Entry points).
* **SHOULD:** Run Stylelint for CSS conventions (check CI Entry points).
* **SHOULD:** Avoid writing all code into one large function (in JavaScript especially).
* **OPTIONAL:** Use code comments generally to document why the code exists, not what the code does. In long blocks of code, adding comments stating what each paragraph does is nice for easy parsing, but generally, comments should focus on the questions that can't be answered by just reading the code.

## Testing

* **SHOULD:** Have and run PHPUnit and QUnit tests.
  * **OPTIONAL:** Split out integration and unit tests (see T87781).
* **SHOULD:** If there are parser functions or tags, have and run parser tests.
* **OPTIONAL:** Have and run browser tests.
* **OPTIONAL:** Test against right-to-left (RTL) languages! (how to verify?).
* **OPTIONAL:** Test against language converter languages! (how to verify?).

## Language

Various aspects of language support are also known as Localisation (L10n), internationalization (i18n), multilingualization, and globalization.
Overall, your extension should be fully usable and compatible with non-English and non-left-to-right languages.

* **MUST:** Use the proper Localisation functions (wfMessage), and not have hardcoded non-translatable strings in your code.
* **MUST:** Use the standard internationalization systems in MediaWiki.
* **MUST:** Use a clear and unique prefix named after the extension for all interface messages.
* **MUST:** Add `qqq.json` message documentation for all messages that exist in `en.json`
* **SHOULD:** Escape parameters to localisation messages as close to output as possible. Document whether functions take/accept wikitext vs. HTML.
* **OPTIONAL:** If an extension uses particular terms, write a glossary of these terms, and link to it from the message documentation. Example: Abstract Wikipedia/Glossary.

## Security

See also Security for developers.

* **MUST:** Shelling out should escape arguments.
* **MUST:** All write actions must be protected against cross-site request forgery (CSRF).
* **MUST:** Make sure privacy related issues (checkuser, revision and log suppression and deletion) are still covered when refactoring or writing new code.
* **SHOULD:** Use the standard MediaWiki CSRF token system.
* **SHOULD:** Don't modify HTML after it has been sanitized (common pattern is to use regex, but that's bad).
* **SHOULD:** Don't load any resources from external domains. This is also needed for privacy and improves performance.

## Don't reinvent / abuse MediaWiki

As a general principle, do not re-implement or compete with functionality already provided by MediaWiki core.

* **MUST:** Use MediaWiki functionality/wrappers for things like WebRequest vs. `$_GET`, etc.
* **MUST:** Use hooks where possible as opposed to workarounds or novel ways of modifying, injecting, or extending functionality.
* **MUST:** Use MediaWiki's validation/sanitization methods e.g. those in the Html and Sanitizer classes.
* **MUST:** Don't disable parser cache unless you have a really good reason.
* **MUST:** Use Composer for 3rd party PHP library management.
* **SHOULD:** Don't reimplement the wheel. Prefer stable and well-maintained libraries when they exist.
* **SHOULD:** Don't disable OutputPage. (T140664)
* **SHOULD:** If an abstraction exists (e.g. ContentHandler), use that instead of hooks.
* **SHOULD:** Don't make things harder for yourself – use standard functionality like extension.json's tests/PHPUnit auto-discovery stuff.
* **SHOULD:** Use global MediaWiki configuration such as read-only mode.