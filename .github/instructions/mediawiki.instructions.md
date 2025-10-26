---
applyTo: "**/*"
---

# MediaWiki Coding Conventions
This lists **general** conventions that apply to all MediaWiki code, whatever language it is written in.

## Code structure

### File formatting

#### Tab size

Lines should be indented with **a single tab character per indenting level**. You should make no assumptions about the number of spaces per tab. Most MediaWiki developers find 4 spaces per tab to be best for readability, but many systems are configured to use 8 spaces per tab and some developers might use 2 spaces per tab.

For vim users, one way to establish these settings is to add the following to `$HOME/.vimrc`:

```
autocmd Filetype php setlocal ts=4 sw=4
```

with similar lines for CSS, HTML, and JavaScript.

However, for Python, instead follow the whitespace guidelines from [PEP 8](http://www.python.org/dev/peps/pep-0008/), which recommends spaces for new projects.

#### Newlines

All files should use Unix-style newlines (single LF character, not a CR+LF combination).

* git on Windows will (by default) convert CR+LF newlines to LF during committing.

All files should have a newline at the end.

* It makes sense since all other lines have a newline character at the end.
* It makes passing data around in non-binary formats (like diffs) easier.
* Command-line tools like cat and wc don't handle files without one well (or at least, not in the way that one would like or expect).

#### Encoding

All text files **must** be encoded with UTF-8 without a Byte Order Mark.

Do not use Microsoft Notepad to edit files, as it always inserts a BOM. A BOM will stop PHP files from working since it is a special character at the very top of the file and will be output by the web browser to the client.

In short, make sure your editor supports UTF-8 without BOM.

#### Trailing whitespace

When using an IDE, pressing the Home and End keys (among other keyboard shortcuts) usually ignores trailing whitespace and instead jumps to the end of the code, which is intended. In non-IDE text editors, though, pressing End will jump to the very end of the line, which means the developer must backspace through the trailing whitespace to get to the spot where they actually want to type.

Removing trailing whitespace is a trivial operation in most text editors. Developers should avoid adding trailing whitespace, primarily on lines that contain other visible code.

Some tools make it easier:

* nano: GNU nano 3.2;
* Komodo Edit: in the Save Options from menu "Edit > Preferences", enable "Clean trailing whitespace and EOL markers" and "Only clean changed lines";
* Kate: you can see trailing spaces by enabling the option "Highlight trailing spaces". This option can be found in "Settings > Configure Kate > Appearance". You can also tell Kate to cleanup trailing spaces on save in "Settings > Configure Kate > Open/Save".
* vim: various automatic cleanup plugins;
* Sublime Text: [TrailingSpaces plugin](https://github.com/SublimeText/TrailingSpaces).

#### Keywords

Do not use parentheses with keywords (e.g. `require_once`, `require`) where they are not necessary.

### Indenting and alignment

#### General style

MediaWiki's indenting style is similar to the so-called "One True Brace Style". Braces are placed on the same line as the start of the function, conditional, loop, etc. The else/elseif is placed on the same line as the previous closing brace.

```php
function wfTimestampOrNull( $outputtype = TS_UNIX, $ts = null ) {
    if ( $ts === null ) {
        return null;
    } else {
        return wfTimestamp( $outputtype, $ts );
    }
}
```

Multi-line statements are written with the second and subsequent lines being indented by one extra level:

Use indenting and line breaks to clarify the logical structure of your code. Expressions which nest multiple levels of parentheses or similar structures may begin a new indenting level with each nesting level:

```php
$wgAutopromote = [
    'autoconfirmed' => [ '&',
        [ APCOND_EDITCOUNT, &$wgAutoConfirmCount ],
        [ APCOND_AGE, &$wgAutoConfirmAge ],
    ],
];
```

#### Vertical alignment

Avoid vertical alignment. It tends to create diffs which are hard to interpret, since the width allowed for the left column constantly has to be increased as more items are added.

> **Note:** Most diff tools provide options to ignore whitespace changes.  
> Git: `git diff -w`

When needed, create mid-line vertical alignment with spaces rather than tabs. For instance this:

```php
$namespaceNames = [
    NS_MEDIA            => 'Media',
    NS_SPECIAL          => 'Special',
    NS_MAIN             => '',
];
```

Is achieved as follows with spaces rendered as dots:

```
$namespaceNames·=·[
 →  NS_MEDIA············=>·'Media',
 →  NS_SPECIAL··········=>·'Special',
 →  NS_MAIN·············=>·'',
];
```

(If you use the [tabular vim add-on](https://github.com/godlygeek/tabular), entering `:Tabularize /=` will align the '=' signs.)

#### Line width

Lines should be broken with a line break at between 80 and 100 columns. There are some rare exceptions to this. Functions which take lots of parameters are not exceptions. The idea is that code should not overflow off the screen when word wrap is turned off.

The operator separating the two lines should be placed consistently (always at the end or always at the start of the line). Individual languages might have more specific rules.

```php
return strtolower( $val ) === 'on'
    || strtolower( $val ) === 'true'
    || strtolower( $val ) === 'yes'
    || preg_match( '/^\s*[+-]?0*[1-9]/', $val );
```

```php
$foo->dobar(
    Xml::fieldset( wfMessage( 'importinterwiki' )->text() ) .
        Xml::openElement( 'form', [ 'method' => 'post', 'action' => $action, 'id' => 'mw-import-interwiki-form' ] ) .
        wfMessage( 'import-interwiki-text' )->parse() .
        Xml::hidden( 'action', 'submit' ) .
        Xml::hidden( 'source', 'interwiki' ) .
        Xml::hidden( 'editToken', $wgUser->editToken() ),
    'secondArgument'
);
```

The method operator should always be put at the beginning of the next line.

```php
$this->getMockBuilder( Message::class )->setMethods( [ 'fetchMessage' ] )
    ->disableOriginalConstructor()
    ->getMock();
```

When continuing "if" statements, a switch to Allman-style braces makes the separation between the condition and the body clear:

```javascript
if ( $.inArray( mw.config.get( 'wgNamespaceNumber' ), whitelistedNamespaces ) !== -1 &&
    mw.config.get( 'wgArticleId' ) > 0 &&
    ( mw.config.get( 'wgAction' ) == 'view' || mw.config.get( 'wgAction' ) == 'purge' ) &&
    mw.util.getParamValue( 'redirect' ) !== 'no' &&
    mw.util.getParamValue( 'printable' ) !== 'yes'
) {
    …
}
```

Opinions differ on the amount of indentation that should be used for the conditional part. Using an amount of indentation different to that used by the body makes it more clear that the conditional part is not the body, but this is not universally observed.

Continuation of conditionals and very long expressions tend to be ugly whichever way you do them. So it's sometimes best to break them up by means of temporary variables.

#### Braceless control structures

Do not write "blocks" as a single-line. They reduce the readability of the code by moving important statements away from the left margin, where the reader is looking for them. Remember that making code shorter doesn't make it simpler. The goal of coding style is to communicate effectively with humans, not to fit computer-readable text into a small space.

```php
// No:
if ( $done ) return;

// No:
if ( $done ) { return; }

// Yes:
if ( $done ) {
    return;
}
```

This avoids a common logic error, which is especially prevalent when the developer is using a text editor which does not have a "smart indenting" feature. The error occurs when a single-line block is later extended to two lines:

```php
if ( $done )
    return;
```

Later changed to:

```php
if ( $done )
    $this->cleanup();
    return;
```

This has the potential to create subtle bugs.

#### emacs style

In emacs, using `php-mode.el` from [nXHTML mode](https://web.archive.org/web/20121213222615/http://ourcomments.org/Emacs/nXhtml/doc/nxhtml.html), you can set up a MediaWiki minor mode in your `.emacs` file:

```elisp
(defconst mw-style
  '((indent-tabs-mode . t)
    (tab-width . 4)
    (c-basic-offset . 4)
    (c-offsets-alist . ((case-label . +)
                        (arglist-cont-nonempty . +)
                        (arglist-close . 0)
                        (cpp-macro . (lambda(x) (cdr x)))
                        (comment-intro . 0)))
    (c-hanging-braces-alist
        (defun-open after)
        (block-open after)
        (defun-close))))

(c-add-style "MediaWiki" mw-style)

(define-minor-mode mah/mw-mode
  "tweak style for mediawiki"
  nil " MW" nil
  (delete-trailing-whitespace)
  (tabify (point-min) (point-max))
  (subword-mode 1)) ;; If this gives an error, try (c-subword-mode 1)), which is the earlier name for it

;; Add other sniffers as needed
(defun mah/sniff-php-style (filename)
  "Given a filename, provide a cons cell of
   (style-name . function)
where style-name is the style to use and function
sets the minor-mode"
  (cond ((string-match "/\\(mw[^/]*\\|mediawiki\\)/"
                       filename)
         (cons "MediaWiki" 'mah/mw-mode))
        (t
         (cons "cc-mode" (lambda (n) t)))))

(add-hook 'php-mode-hook (lambda () (let ((ans (when (buffer-file-name)
                                                 (mah/sniff-php-style (buffer-file-name)))))
                                      (c-set-style (car ans))
                                      (funcall (cdr ans) 1))))
```

The above `mah/sniff-php-style` function will check your path when `php-mode` is invoked to see if it contains "mw" or "mediawiki" and set the buffer to use the `mw-mode` minor mode for editing MediaWiki source. You will know that the buffer is using `mw-mode` because you'll see something like "PHP MW" or "PHP/lw MW" in the mode line.

### Data manipulation

#### Constructing URLs

Never build URLs manually with string concatenation or similar. Always use the full URL format for requests made by your code (especially POST and background requests).

You can use the appropriate Linker or Title method in PHP, the fullurl magic word in wikitext, the `mw.util.getUrl()` method in JavaScript, and similar methods in other languages. You'll avoid issues with unexpected short URL configuration and more.

## File naming

Files which contain server-side code should be named in *UpperCamelCase*. This is also our naming convention for extensions. Name the file after the most important class it contains; most files will contain only one class, or a base class and a number of descendants. For example, `Title.php` contains only the `Title` class; `WebRequest.php` contains the `WebRequest` class, and also its descendants `FauxRequest` and `DerivativeRequest`.

### Access point files

Name "access point" files, such as SQL, and PHP entry points such as `index.php` and `foobar.sql`, in *lowercase*. Maintenance scripts are generally in *lowerCamelCase*, although this varies somewhat. Files intended for the site administrator, such as readmes, licenses and changelogs, are usually in *UPPERCASE*.

Never include spaces in file names or directories, and never use non-ASCII characters. For lowercase titles, hyphens are preferred to underscores.

### JS, CSS, and media files

For JavaScript, CSS and other frontend files (usually registered via ResourceLoader) should be placed in directory named after the module bundle in which they are registered. For example, module `mediawiki.foo` might have files `mediawiki.foo/Bar.js` and `mediawiki.foo/baz.css`

JavaScript files that define classes should match exactly the name of the class they define. The class `TitleWidget` should be in a file named as, or ending with, `TitleWidget.js`. This allows for rapid navigation in text editors by navigating to files named after a selected class name (such as "Goto Anything [P]" in Sublime, or "Find File [P]" in Atom).

Large projects may have classes in a hierarchy with names that would overlap or be ambiguous without some additional way of organizing files. We generally approach this with subdirectories like `ext.foo/bar/TitleWidget.js` (for Package files), or longer class and file names like `mw.foo.bar.TitleWidget` in `ext.foo/bar.TitleWidget.js`.

Modules bundles registered by extensions should follow names like `ext.myExtension`, for example `MyExtension/modules/ext.myExtension/index.js`. This makes it easy to get started with working on a module in a text editor, by directly finding the source code files from only the public module name.

## Documentation

The language-specific subpages have more information on the exact syntax for code comments in files, e.g. comments in PHP for doxygen. Using precise syntax allows us to generate documentation from source code at [doc.wikimedia.org](https://doc.wikimedia.org).

High level concepts, subsystems, and data flows should be documented in the `/docs` folder.

### Source file headers

In order to be compliant with most licenses you should have something similar to the following (specific to GPLv2 PHP applications) at the top of every source file.

```php
<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * 
 * @file
 */
```

### Licenses

Licenses are generally referred to by their full name or acronym as per [SPDX standard](https://spdx.org/licenses/).

### Dynamic identifiers

It is generally recommended to avoid dynamically constructing identifiers such as interface message keys, CSS class names, or file names. When possible, write them out and select between them (e.g. using a conditional, ternary, or switch). This improves code stability and developer productivity through: easier code review, higher confidence during debugging, usage discovery, git-grep, Codesearch, etc.

If code is considered to be a better reflection of the logical structure, or if required to be fully variable, then you may concatenate the identifier with a variable instead. In that case, you must leave a comment nearby with the possible (or most common) values to demonstrate behaviour and to aid search and discovery.

**Negative example:**
```php
// No: Avoid composing message keys
$context->msg( 'templatesused-' . ( $section ? 'section' : 'page' ) );
```

**Positive example:**
```php
// Yes: Prefer full message keys
$context->msg( $section ? 'templatesused-section' : 'templatesused-page' );
```

```javascript
// If needed, concatenate and write explicit references in a comment

// Messages:
// * myextension-connect-success
// * myextension-connect-warning
// * myextension-connect-error
const text = mw.msg( 'myextension-connect-' + status );
```

```javascript
// The following classes are used here:
// * mw-editfont-monospace
// * mw-editfont-sans-serif
// * mw-editfont-serif
$texarea.addClass( 'mw-editfont-' + mw.user.options.get( 'editfont' ) );
```

```php
// Load example/foo.json, or example/foo.php
$thing->load( "$path/foo.$ext" );
```

## Release notes

You must document all significant changes (including all fixed bug reports) to the core software which might affect wiki users, server administrators, or extension authors in the `RELEASE-NOTES-N.NN` file.

`RELEASE-NOTES-N.NN` is in development; on every release we move the past release notes into the `HISTORY` file and start afresh. `RELEASE-NOTES-N.NN` is generally divided into three sections:

* **Configuration changes** is the place to put changes to accepted default behavior, backwards-incompatible changes, or other things which need a server administrator to look at and decide "is this change right for my wiki?". Try to include a brief explanation of how the previous functionality can be recovered if desired.
* **Bug fixes** is the place to note changes which fix behavior which is accepted to be problematic or undesirable. These will often be issues reported in Phabricator, but needn't necessarily.
* **New features** is, unsurprisingly, to note the addition of new functionality.

There may be additional sections for specific components (e.g. the Action API) or for miscellaneous changes that don't fall into one of the above categories.

In all cases, if your change is in response to one or more issues reported in Phabricator, include the task ID(s) at the start of the entry. Add new entries in chronological order at the end of the section.

## System messages

When creating a new system message, use hyphens (-) where possible instead of CamelCase or snake_case. So for example, `some-new-message` is a good name, while `someNewMessage` and `some_new_message` are not.

If the message is going to be used as a label which can have a colon (:) after it, don't hardcode the colon; instead, put the colon inside the message text. Some languages (such as French which require a space before) need to handle colons in a different way, which is impossible if the colon is hardcoded. The same holds for several other types of interpunctuation.

Try to use message keys "whole" in code, rather than building them on the fly; as this makes it easier to search for them in the codebase. For instance, the following shows how a search for `templatesused-section` will not find this use of the message key if they are not used as a whole.

```php
// No:
return wfMessage( 'templatesused-' . ( $section ? 'section' : 'page' ) );

// Yes:
$msgKey = $section ? 'templatesused-section' : 'templatesused-page';
return wfMessage( $msgKey );
```

If you feel that you have to build messages on the fly, put a comment with all possible whole messages nearby:

```php
// Messages that can be used here:
// * myextension-connection-success
// * myextension-connection-warning
// * myextension-connection-error
$text = wfMessage( 'myextension-connection-' . $status )->parse();
```

See Localisation for more conventions about creating, using, documenting and maintaining message keys.

### Preferred spelling

It is just as important to have consistent spelling in the UI and codebase as it is to have consistent UI. By long standing history, 'American English' is the preferred spelling for English language messages, comments, and documentation.

### Abbreviations in message keys

* **ph**: placeholder (text in input fields)
* **tip**: tooltip text
* **tog-xx**: toggle options in user preferences

### Punctuation

Non-title error messages are considered as sentences and should have punctuation.

## Improve the core

If you need some additional functionality from a MediaWiki core component (PHP class, JS module etc.), or you need a function that does something similar but slightly different, prefer to improve the core component. Avoid duplicating the code to an extension or elsewhere in core and modifying it there.

## Refactoring

Refactor code as changes are made: don't let the code keep getting worse with each change.

However, use separate commits if the refactoring is large. See also Architecture guidelines (draft).

## HTML

MediaWiki HTTP responses output HTML that can be generated by one of two sources. The MediaWiki PHP code is a trusted source for the user interface, it can output any arbitrary HTML. The Parser converts user-generated wikitext into HTML, this is an untrusted source. Complex HTML created by users via wikitext is often found in the "Template" namespace. HTML produced by the Parser is subject to sanitization before output.

Most `data-*` attributes are allowed to be used by users in wikitext and templates. But, the following prefixes have been restricted and are not allowed in wikitext and will be removed from the output HTML. This enables client JavaScript code to determine whether a DOM element came from a trusted source:

* `data-ooui` – This attribute is present in HTML generated by OOUI widgets.
* `data-parsoid` – reserved attribute for internal use by Parsoid.
* `data-mw` and `data-mw-...` – reserved attribute for internal use by MediaWiki core, skins and extensions. The `data-mw` attribute is used by Parsoid; other core code should use `data-mw-*`.

When selecting elements in JavaScript, one can specify an attribute key/value to ensure only DOM elements from the intended trusted source are considered. Example: Only trigger 'wikipage.diff' hook
