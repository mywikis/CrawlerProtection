---
applyTo: "**/*.php"
---

# PHP Coding Conventions

This page describes the coding conventions used within files of the MediaWiki codebase written in PHP.

See also the general conventions that apply to all program languages, including PHP. If you would like a short checklist to help you review your commits, try using the Pre-commit checklist.

Most of the code style rules can be automatically fixed, or at least detected, by PHP_CodeSniffer (also known as PHPCS), using a custom ruleset for MediaWiki. For more information, see Continuous integration/PHP CodeSniffer.

## Code structure

### Spaces

MediaWiki favors a heavily-spaced style for optimum readability.

Indent with tabs, not spaces. Limit lines to 120 characters (given a tab-width of 4 characters).

Put spaces on either side of binary operators, for example:

```php
// No:
$a=$b+$c;

// Yes:
$a = $b + $c;
```

Put spaces next to parentheses on the inside, except where the parentheses are empty. Do not put a space following a function name.

```php
$a = getFoo( $b );
$c = getBar();
```

Put a space after the `:` in the function return type hint, but not before:

```php
function square( int $x ): int {
    return $x * $x;
}
```

Put spaces in brackets when declaring an array, except where the array is empty. Do not put spaces in brackets when accessing array elements.

```php
// Yes
$a = [ 'foo', 'bar' ];
$c = $a[0];
$x = [];

// No
$a = ['foo', 'bar'];
$c = $a[ 0 ];
$x = [ ];
```

Control structures such as `if`, `while`, `for`, `foreach`, `switch`, as well as the `catch` keyword, should be followed by a space:

```php
// Yes
if ( isFoo() ) {
    $a = 'foo';
}

// No
if( isFoo() ) {
    $a = 'foo';
}
```

When type casting, do not use a space within or after the cast operator:

```php
// Yes
(int)$foo;

// No
(int) $bar;
( int )$bar;
( int ) $bar;
```

In comments there should be one space between the `#` or `//` character and the comment.

```php
// Yes: Proper inline comment
//No: Missing space
/***** Do not comment like this ***/
```

### Ternary operator

The ternary operator can be used profitably if the expressions are very short and obvious:

```php
$title = $page ? $page->getTitle() : Title::newMainPage();
```

But if you're considering a multi-line expression with a ternary operator, please consider using an `if ()` block instead. Remember, disk space is cheap, code readability is everything, "if" is English and "?:" is not. If you are using a multi-line ternary expression, the question mark and colon should go at the beginning of the second and third lines and not the end of the first and second (in contrast to MediaWiki's JavaScript convention).

Since MediaWiki requires PHP 7.2 or later, use of the shorthand ternary operator (`?:`) also known as the elvis operator, introduced in PHP 5.3, is allowed.

Since PHP 7.0 the null coalescing operator is also available and can replace the ternary operator in some use cases. For example, instead of:
```php
$wiki = isset( $this->mParams['wiki'] ) ? $this->mParams['wiki'] : false;
```
you could instead write the following:
```php
$wiki = $this->mParams['wiki'] ?? false;
```

### String literals

Single quotes are preferred in all cases where they are equivalent to double quotes. Code using single quotes is less error-prone and easier to review, as it cannot accidentally contain escape sequences or variables. For example, the regular expression `"/\\n+/"` requires an extra backslash, making it slightly more confusing and error-prone than `'/\n+/'`. Also for people using US/UK qwerty keyboards, they are easier to type, since it avoids the need to press shift.

However, do not be afraid of using PHP's double-quoted string interpolation feature:
```php
$elementId = "myextension-$index";
```

This has slightly better performance characteristics than the equivalent using the concatenation (dot) operator, and it looks nicer too.

Heredoc-style strings are sometimes useful:

```php
$s = <<<EOT
<div class="mw-some-class">
$boxContents
</div>
EOT;
```

Some authors like to use END as the ending token, which is also the name of a PHP function.

### Functions and parameters

Avoid passing huge numbers of parameters to functions or constructors:

```php
// Constructor for Block.php from 1.17 to 1.26. DO NOT do this!
function __construct( $address = '', $user = 0, $by = 0, $reason = '',
    $timestamp = 0, $auto = 0, $expiry = '', $anonOnly = 0, $createAccount = 0, $enableAutoblock = 0,
    $hideName = 0, $blockEmail = 0, $allowUsertalk = 0
) {
    ...
}
```

It quickly becomes impossible to remember the order of parameters, and you will inevitably end up having to hardcode all the defaults in callers just to customise a parameter at the end of the list. If you are tempted to code a function like this, consider passing an associative array of named parameters instead.

In general, using boolean parameters is discouraged in functions. In `$object->getSomething( $input, true, true, false )`, without looking up the documentation for `MyClass::getSomething()`, it is impossible to know what those parameters are meant to indicate. Much better is to either use class constants, and make a generic flag parameter:

```php
$myResult = MyClass::getSomething( $input, MyClass::FROM_DB | MyClass::PUBLIC_ONLY );
```

Or to make your function accept an array of named parameters:

```php
$myResult = MyClass::getSomething( $input, [ 'fromDB', 'publicOnly' ] );
```

Try not to repurpose variables over the course of a function, and avoid modifying the parameters passed to a function (unless they're passed by reference and that's the whole point of the function, obviously).

### Assignment expressions

Using assignment as an expression is surprising to the reader and looks like an error. Do not write code like this:

```php
if ( $a = foo() ) {
    bar();
}
```

Space is cheap, and you're a fast typist, so instead use:

```php
$a = foo();
if ( $a ) {
    bar();
}
```

Using assignment in a `while()` clause used to be legitimate, for iteration:

```php
$res = $dbr->query( 'SELECT foo, bar FROM some_table' );
while ( $row = $dbr->fetchObject( $res ) ) {
    showRow( $row );
}
```

This is unnecessary in new code; instead use:

```php
$res = $dbr->query( 'SELECT foo, bar FROM some_table' );
foreach ( $res as $row ) {
    showRow( $row );
}
```

### C borrowings

The PHP language was designed by people who love C and wanted to bring souvenirs from that language into PHP. But PHP has some important differences from C.

In C, constants are implemented as preprocessor macros and are fast. In PHP, they are implemented by doing a runtime hashtable lookup for the constant name, and are slower than just using a string literal. In most places where you would use an enum or enum-like set of macros in C, you can use string literals in PHP.

PHP has three special literals for which upper-/lower-/mixed-case is insignificant in the language (since PHP 5.1.3), but for which our convention is always lowercase: `true`, `false` and `null`.

Use `elseif` not `else if`. They have subtly different meanings:

```php
// This:
if ( $foo === 'bar' ) {
    echo 'Hello world';
} else if ( $foo === 'Bar' ) {
    echo 'Hello world';
} else if ( $baz === $foo ) {
    echo 'Hello baz';
} else {
    echo 'Eh?';
}

// Is actually equivalent to:
if ( $foo === 'bar' ) {
    echo 'Hello world';
} else {
    if ( $foo == 'Bar' ) {
        echo 'Hello world';
    } else {
        if ( $baz == $foo ) {
            echo 'Hello baz';
        } else {
            echo 'Eh?';
        }
    }
}
```

And the latter has poorer performance.

### Alternative syntax for control structures

PHP offers an alternative syntax for control structures using colons and keywords such as `endif`, `endwhile`, etc.:

```php
if ( $foo == $bar ):
    echo "<div>Hello world</div>";
endif;
```

This syntax should be avoided, as it prevents many text editors from automatically matching and folding braces. Braces should be used instead:

```php
if ( $foo == $bar ) {
    echo "<div>Hello world</div>";
}
```

### Brace placement

See Manual:Coding conventions#Indenting and alignment.

For anonymous functions, prefer arrow functions when the anonymous function consists only of one line. Arrow functions are more concise and readable than regular anonymous functions and neatly side-steps formatting issues that arise with single-line anonymous functions.

### Type declarations for variables
Avoid using PHPDoc comments to declare types for local variables. Instead, use native type declarations for function parameters and return types, and use static analysis tools (like PHPStan or Psalm) to infer types of local variables.

Example:

```php
private static string $nameOfVariable = '';
```


### Type declarations in function parameters

Use native type declarations and return type declarations when applicable. (But see #Don't add type declarations for "big" legacy classes below.)

Scalar typehints are allowed as of MediaWiki 1.35, following the switch to PHP 7.2.

Use PHP 7.1 syntax for nullable parameters: choose

```php
public function foo ( ?MyClass $mc ) {}
```

instead of

```php
public function foo ( MyClass $mc = null ) {}
```

The former conveys precisely the nullability of a parameter, without risking any ambiguity with optional parameters. IDEs and static analysis tools will also recognize it as such, and will not complain if a non-nullable parameter follows a nullable one.

Do not add PHPDoc comments that only repeat the native types. Add PHPDoc comments if they document types that can't be expressed using native types (e.g. `string[]` where the native type is `array`), or if they document something useful about the value beyond what the type and parameter/function name already says.

## Naming

| Element | Convention | Notes |
|---------|------------|-------|
| Files | UpperCamelCase | PHP files should be named after the class they contain, which is UpperCamelCase. For instance, `WebRequest.php` contains the `WebRequest` class. See also Manual:Coding conventions#File naming |
| Namespaces | UpperCamelCase | |
| Classes | UpperCamelCase | Use UpperCamelCase when naming classes. For example: `class ImportantClass` |
| Constants | Uppercase with underscores | Use uppercase with underscores for global and class constants: `DB_PRIMARY`, `IDBAccessObject::READ_LATEST` |
| Functions | lowerCamelCase | Use lowerCamelCase when naming functions. For example: `private function doSomething( $userPrefs, $editSummary )` |
| Function variables | lowerCamelCase | Use lowerCamelCase when naming function variables. Avoid using underscores in variable names. |

### Prefixes

There are also some prefixes that can be used in different places:

#### Function names

* `wf` (wiki functions) – top-level functions, e.g. `function wfFuncname() { ... }`
* `ef` (extension functions) – global functions in extensions, although "in most cases modern style puts hook functions as static methods on a class, leaving few or no raw top-level functions to be so named."

Verb phrases are preferred: use `getReturnText()` instead of `returnText()`. When exposing functions for use in testing, mark these as `@internal` per the Stable interface policy. Misuse or unofficial reliance on these is more problematic than most internal methods, and as such we tend to make these throw if they run outside of a test environment.

```php
/**
 * Reset example data cache.
 *
 * @internal For testing only
 */
public static function clearCacheForTest(): void {
    if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
        throw new RuntimeException( 'Not allowed outside tests' );
    }
    self::$exampleDataCache = [];
}
```

#### Variable names

* `$wg` – global variables, e.g. `$wgTitle`. Always use this for new globals, so that it's easy to spot missing `global $wgFoo` declarations. In extensions, the extension name should be used as a namespace delimiter. For example, `$wgAbuseFilterConditionLimit`, **not** `$wgConditionLimit`.
* Global declarations should be at the beginning of a function so dependencies can be determined without having to read the whole function.

It is common to work with an instance of the `Database` class; we have a naming convention for these which helps keep track of the nature of the server to which we are connected. This is of particular importance in replicated environments, such as Wikimedia and other large wikis; in development environments, there is usually no difference between the two types, which can conceal subtle errors.

* `$dbw` – a `Database` object for writing (a primary connection)
* `$dbr` – a `Database` object for non-concurrency-sensitive reading (this may be a read-only replica, slightly behind primary state, so don't ever try to write to the database with it, or get an "authoritative" answer to important queries like permissions and block status)

The following may be seen in old code but are discouraged in new code:

* `$ws` – Session variables, e.g. `$_SESSION['wsSessionName']`
* `$wc` – Cookie variables, e.g. `$_COOKIE['wcCookieName']`
* `$wp` – Post variables (submitted via form fields), e.g. `$wgRequest->getText( 'wpLoginName' )`
* `$m` – object member variables: `$this->mPage`. This is **discouraged in new code**, but try to stay consistent within a class.

## Pitfalls

### `empty()`

The `empty()` function should only be used when you want to suppress errors. Otherwise just use `!` (boolean conversion).

* `empty( $var )` essentially does `!isset( $var ) || !$var`.
  Common use case: Optional boolean configuration keys that default to `false`. `$this->enableFoo = !empty( $options['foo'] );`
* Beware of boolean conversion pitfalls.
* It suppresses errors about undefined properties and variables. If only intending to test for undefined, use `!isset()`. If only intending to test for "empty" values (e.g. `false`, `0`, `[]`, etc.), use `!`.

### `isset()`

Do not use `isset()` to test for `null`. Using `isset()` in this situation could introduce errors by hiding misspelled variable names. Instead, use `$var === null`.

Testing whether a typed property that cannot be null but has no default value has been initialized is a valid use of `isset()`, but confuses the PHP static analysis tool Phan. You can often avoid this by using `??` / `??=`.

### Boolean conversion

```php
if ( !$var ) {
    …
}
```

* Do not use `!` or `empty()` to test if a string or array is empty, because PHP considers `'0'` to be falsy – but `'0'` is a valid title and valid user name in MediaWiki. Use `=== ''` or `=== []` instead.
* Study the rules for conversion to boolean. Be careful when converting strings to boolean.

### Other

* Array plus does not renumber the keys of numerically-indexed arrays, so `[ 'a' ] + [ 'b' ] === [ 'a' ]`. If you want keys to be renumbered, use `array_merge()`: `array_merge( [ 'a' ], [ 'b' ] ) === [ 'a', 'b' ]`
* Make sure you have `error_reporting()` set to `-1`. This will notify you of undefined variables and other subtle gotchas that stock PHP will ignore. See also Manual:How to debug.
* When working in a pure PHP file (e.g. not an HTML template), omit any trailing `?>` tags. These tags often cause issues with trailing white-space and "headers already sent" error messages. It is conventional in version control for files to have a new line at end-of-file (which editors may add automatically), which would then trigger this error.
* Do not use the `goto` syntax introduced in 5.3. PHP may have introduced the feature, but that does not mean we should use it.
* Do not pass by reference when traversing an array with `foreach` unless you *have to*. Even then, be aware of the consequences.
* PHP lets you declare static variables even within a non-static method of a class. This has led to subtle bugs in some cases, as the variables are shared between instances. Where you would not use a `private static` property, do not use a static variable either.

### Equality operators

Be careful with double-equals comparison operators. Triple-equals (`===`) is generally more intuitive and should be preferred unless you have a reason to use double-equals (`==`).

* `'000' == '0'` is `true` (!)
* `'000' === '0'` is `false`
* To check if two scalars that are supposed to be numeric are equal, use `==`, e.g. `5 == "5"` is true.
* To check if two variables are both of type 'string' and are the same sequence of characters, use `===`, e.g. `"1.e6" === "1.0e6"` is false.

Watch out for internal functions and constructs that use weak comparisons; for instance, provide the third parameter to `in_array`, and don't mix scalar types in `switch` constructs.

Do not use Yoda conditionals.

### JSON number precision

JSON uses JavaScript's type system, so all numbers are represented as 64bit IEEE floating point numbers. This means that numbers lose precision when getting bigger, to the point where some whole numbers become indistinguishable: Numbers beyond 2^52 will have a precision worse than ±0.5, so a large integer may end up changing to a different integer. To avoid this issue, represent potentially large integers as strings in JSON.

## Dos and don'ts

### Don't use built in serialization

PHP's built in serialization mechanism (the `serialize()` and `unserialize()` functions) should not be used for data stored (or read from) outside of the current process. Use JSON based serialization instead (however, beware the pitfalls). This is policy established by RFC T161647.

The reason is twofold: (1) data serialized with this mechanism cannot reliably be unserialized with a later version of the same class. And (2) crafted serialized data can be used to execute malicious code, posing a serious security risk.

Sometimes, your code will not control the serialization mechanism, but will be using some library or driver that uses it internally. In such cases, steps should be taken to mitigate risk. The first issue mentioned above can be mitigated by converting any data to arrays or plain anonymous objects before serialization. The second issue can perhaps be mitigated using the whitelisting feature PHP 7 introduces for unserialization.

Although for trivial classes PHP's JsonSerializable interface may suffice, more complex examples will likely find the wikimedia/json-codec package useful when serializing to/from JSON. It contains facilities to integrate with services and dependency injection, as well as to integrate with external classes which don't natively support serialization. The `JsonCodec` service in core extends the codec provided by `wikimedia/json-codec`.

### Don't add type declarations for "big" legacy classes

MediaWiki contains some big classes that are going to be split up or replaced sooner or later. This will be done in a way that keeps code compatible for a transition period, but it can break extension code that expects the legacy classes in parameter types, return types, property types, or similar. For instance, a hook handler's `$title` parameter may be passed some kind of `MockTitleCompat` class instead of a real `Title`.

Such big legacy classes should therefore not be used in type hints, only in PHPDoc. The classes include:

* `Title`
* `Article`
* `WikiPage`
* `User`
* `MediaWiki`
* `OutputPage`
* `WebRequest`
* `EditPage`

### Don't add type declarations for DOM classes

PHP 8.4 introduces a new `\Dom\Document` class which is not-quite-compatible with the older `\DOMDocument` class used in PHP <= 8.3. The `Wikimedia\Parsoid\Utils\DOMCompat` class in `wikimedia/parsoid` contains functions to bridge the gap between the two implementations, and to generally provide standards-compliant implementations of features missing in one or the other implementation. It is recommended to either omit explicit type declarations for DOM classes (allowing either `\Dom\Document` or `\DOMDocument` classes to be passed at runtime) or to use the `Wikimedia\Parsoid\DOM\Document` aliases provided by Parsoid in type hints, which will resolve to `\DOMDocument` before PHP 8.4 and `\Dom\Document` after.

## Comments and documentation

It is essential that your code be well documented so that other developers and bug fixers can easily navigate the logic of your code. New classes, methods, and member variables should include comments providing brief descriptions of their functionality (unless it is obvious), even if private. In addition, all new methods should document their parameters and return values.

We use the Doxygen documentation style (it is very similar to PHPDoc for the subset that we use) to produce auto-generated documentation from code comments (see Manual:mwdocgen.php). Begin a block of Doxygen comments with `/**`, instead of the Qt-style formatting `/*!`. Doxygen structural commands start with `@tagname`. (Use `@` rather than `\` as the escape character – both styles work in Doxygen, but for backwards and future compatibility MediaWiki has chosen the `@param` style.) They organize the generated documentation (using `@ingroup`) and identify authors (using `@author` tags).

They describe a function or method, the parameters it takes (using `@param`), and what the function returns (using `@return`). The format for parameters is:

```
@param type $paramName Description of parameter
```

If a parameter can be of multiple types, separate them with the pipe '|' character, for example:

```
@param string|Language|bool $lang Language for the ToC title, defaults to user language
```

Continue sentences belonging to an annotation on the next line, indented with one additional space.

For every public interface (method, class, variable, whatever) you add or change, provide a `@since VERSION` tag, so people extending the code via this interface know they are breaking compatibility with older versions of the code.

```php
class Foo {

    /**
     * @var array Description here
     * @example [ 'foo' => Bar, 'quux' => Bar, .. ]
     */
    protected $bar;

    /**
     * Description here, following by documentation of the parameters.
     *
     * Some example:
     * @code
     * ...
     * @endcode
     *
     * @since 1.24
     * @param FooContext $context context for decoding Foos
     * @param array|string $options Optionally pass extra options.
     *  Either a string or an array of strings.
     * @return Foo|null New instance of Foo or null if quuxification failed.
     */
    public function makeQuuxificatedFoo( FooContext $context = null, $options = [] ) {
        /* .. */
    }

}
```

FIXME usually means something is bad or broken. TODO means that improvements are needed; it does not necessarily mean that the person adding the comment is going to do it. HACK means that a quick but inelegant, awkward or otherwise suboptimal solution to an immediate problem was made, and that eventually a more thorough rewrite of the code should be done.

### Source file headers

In order to be compliant with most licenses you should have something similar to the following (specific to GPLv2 applications) at the top of every source file.

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

### Doxygen tags

We use the following annotations which Doxygen recognizes. Use them in this order, for consistency:

File level:
* @file

Class, class member, or global member:

* @todo
* @var
* @stable, @newable, @deprecated, @internal, @unstable, @private
* @see
* @since
* @ingroup
* @param
* @return
* @throws
* @author
* @copyright

### Test annotations

In tests, we use the following annotations among others. These aren't merely documentation, they mean something to PHPUnit and affect test execution.

* @depends
* @group
* @covers
* @dataProvider

## Integration

There are a few pieces of code in the MediaWiki codebase which are intended to be standalone and easily portable to other applications. While some of these now exist as separate libraries, others remain within the MediaWiki source tree (e.g. the files in `/includes/libs`). Apart from these, code should be integrated into the rest of the MediaWiki environment, and should allow other areas of the codebase to integrate with it in return.

## Visibility

Mark code as `private` unless there is a reason to make it more visible. Don't just make everything protected (= public to subclasses) or public.

### Global objects

Do not access the PHP superglobals `$_GET`, `$_POST`, etc, directly; use `$request->get*( 'param' )` instead; there are various functions depending on what type of value you want. You can get a `WebRequest` from the nearest `RequestContext`, or if absolutely necessary `RequestContext::getMain()`. Equally, do not access `$_SERVER` directly; use `$request->getIP()` if you want to get the IP address of the current user.

### Static methods

Code using static methods should be written so that all method calls inside a class use Late Static Bindings, which basically means that calls to overridable static methods are resolved in the same way as calls to overridable instance methods. Specifically:

* When calling static methods that may be overridden by subclasses from inside the class, use `static::func()`. This will call the override methods defined in subclasses if they exist, just like `$this->func()` does for instance methods.
* When calling static methods that may not be overridden (especially private methods), use `self::func()`. This will only call the methods of the class where it is used and its parent classes.
* When calling a parent method from an override of a static method, use `parent::func()`.
* If you ever think you need to call a grandparent class's version of a static method, or a child class's, think about it again, and use `forward_static_call()` if you don't come up with any better ideas.

Do not write out the class name like `ClassName::func()` in the above cases, as that will cause all method calls inside that method to ignore overrides of that class's members in subclasses. This is only a problem for static methods, it works like you'd expect in instance methods, but avoid that syntax in instance methods too to avoid confusion about what the call will do.

These complications are annoying. Best avoid static methods so that you don't have to think about this.

### Calling methods

For clarity, the method call syntax should match the method type:

* Calls to static methods should always use `::`, even though PHP lets you use `->` sometimes.
* Calls to instance methods should always use `->`, even though PHP lets you use `::` sometimes. (`self::` and `parent::` may be used when needed.)

### Classes

Encapsulate your code in an object-oriented class, or add functionality to existing classes; do not add new global functions or variables. Try to be mindful of the distinction between 'backend' classes, which represent entities in the database (e.g. `User`, `Block`, `RevisionRecord`, etc.), and 'frontend' classes, which represent pages or interfaces visible to the user (`SpecialPage`, `Article`, `ChangesList`, etc.). Even if your code is not obviously object-oriented, you can put it in a static class (e.g. `IP` or `Html`).

As a holdover from PHP 4's lack of private class members and methods, older code will be marked with comments such as `/** @private */` to indicate the intention; respect this as if it were enforced by the interpreter.

Mark new code with proper visibility modifiers, including `public` if appropriate, but **do not** add visibility to existing code without first checking, testing and refactoring as required. It's generally a good idea to avoid visibility changes unless you're making changes to the function which would break old uses of it anyway.

## Error handling

In general, you should not suppress PHP errors. The proper method of handling errors is to *actually handle the errors*.

For example, if you are thinking of using an error suppression operator to suppress an invalid array index warning, you should instead perform an `isset()` check on the array index before trying to access it. When possible, *always* catch or naturally prevent PHP errors.

Only if there is a situation where you are expecting an unavoidable PHP warning, you may use PHP's `@` operator. This is for cases where:

1. It is impossible to anticipate the error that is about to occur; and
2. You are planning on handling the error in an appropriate manner after it occurs.

We use PHPCS to warn against use of the at-operator. If you really need to use it, you'll also need to instruct PHPCS to make an exemption, like so:

```php
// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
$content = @file_get_contents( $path );
```

An example use case is opening a file with `fopen()`. You can try to predict the error by calling `file_exists()` and `is_readable()`, but unlike `isset()`, such file operations add significant overhead and make for unstable code. For example, the file may be deleted or changed between the check and the actual `fopen()` call (see TOC/TOU).

In this case, write the code to just try the main operation you need to do. Then handle the case of the file failing to open, by using the `@` operator to prevent PHP from being noisy, and then check the result afterwards. For `fopen()` and `filemtime()`, that means checking for a boolean false return, and then performing a fallback, or throw an exception.

### AtEase

For PHP 5 and earlier, MediaWiki developers discouraged use of the `@` operator due to it causing unlogged and unexplained fatal errors. Instead, we used custom `AtEase::suppressWarnings()` and `AtEase::restoreWarnings()` methods from the at-ease library. The reason is that the at-operator caused PHP to not provide error messages or stack traces upon fatal errors. While the at-operator is mainly intended for non-fatal errors (not exceptions or fatals), if a fatal were to happen it would make for a very poor developer experience.

```php
use Wikimedia\AtEase\AtEase;

AtEase::suppressWarnings();
$content = file_get_contents( $path );
AtEase::restoreWarnings();
```

In PHP 7, the exception handler was fixed to always provide such errors, including a stack trace, regardless of error suppression. In 2020, use of AtEase started a phase out, reinstating the at-operator.

## Exception handling

Exceptions can be checked (meaning callers are expected to catch them) or unchecked (meaning callers must not catch them).

Unchecked exceptions are commonly used for programming errors, such as invalid arguments passed to a function. These exceptions should generally use (either directly or by subclassing) the SPL exception classes, and must not be documented with `@throws` annotations. Nonetheless, the conditions that lead to these exceptions being thrown should be documented in prose in the doc comment when they're part of a method's contract (for example, a string argument that must not be empty, or an integer argument that must be non-negative).

Checked exceptions, on the other hand, must always be documented with `@throws` annotations. When calling a method that can throw a checked exception, said exception must either be caught, or documented in the caller's doc comment. Checked exceptions should generally use dedicated exception classes extending `Exception`. It's recommended not to use SPL exceptions as base classes for checked exceptions, so that correct usage of exception classes can be enforced with static code analyzers.

The base `Exception` class must never be thrown directly: use more specific exception classes instead. It can be used in a `catch` clause if the intention is to catch all possible exceptions, but `Throwable` is usually more correct for that purpose.

In legacy code it is relatively common to throw or subclass the `MWException` class. This class must be avoided in new code, as it does not provide any advantage, and could actually be confusing.

When creating a new exception class, consider implementing `INormalizedException` if the exception message contains variable parts, and `ILocalizedException` if the exception message is shown to users.

If you're not sure what exception class to use, you can throw a `LogicException` for problems that indicate bugs in the code (e.g. function called with wrong arguments, or an unreachable branch being reached), and `RuntimeException` for anything else (e.g. an external server being down).
