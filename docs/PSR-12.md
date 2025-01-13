# PSR-12 compliance

This document describes what happens when strict [PSR-12] / [PER Coding Style]
compliance is enabled via the `--psr12` option or the `pretty-php` API (e.g.
`Formatter::withPsr12()`).

It also explains how (and why) `pretty-php`'s default style is not quite 100%
compliant.

## Options

The equivalent of the following command-line options are applied when strict
PSR-12 compliance is enabled:

- `--space=4`
- `--eol lf`
- `--heredoc-indent hanging`

And these are ignored:

- `--one-true-brace-style`
- `--no-sort-imports`

Internally, values assigned to `Formatter` properties are as follows:

- `InsertSpaces`: `true`
- `TabSize`: `4`
- `PreferredEol`: `"\n"`
- `PreserveEol`: `false`
- `HeredocIndent`: `HeredocIndent::HANGING`
- `OneTrueBraceStyle`: `false`
- `ExpandHeaders`: `true`
- `Psr12`: `true`
- `NewlineBeforeFnDoubleArrow`: `true`

## Rules

In strict PSR-12 mode, these rules are enabled and cannot be disabled:

- `sort-imports` | `SortImports` (sort order is not specified, but import
  statements must be grouped by class, then function, then constant as per
  [PSR-12, section 3])
- `strict-expressions` | `StrictExpressions`
- `strict-lists` | `StrictLists`
- `declaration-spacing` | `DeclarationSpacing`

And these cannot be enabled:

- `preserve-one-line` | `PreserveOneLineStatements`
- `semi-strict-expressions` | `SemiStrictExpressions`
- `align-lists` | `AlignLists`

## Behaviours

The following behaviours apply only when strict PSR-12 compliance is enabled.

### Declare statements

**Rule:** `StandardSpacing`

With or without a semicolon after the closing parenthesis, the following is
collapsed to one line as per [PSR-12, section 3]:

```php
<?php declare(strict_types=1) ?>
```

Otherwise, header blocks are formatted as prescribed:

```php
<?php

/**
 * Header
 */

declare(strict_types=1);

namespace Vendor\Package;
```

> `pretty-php`'s default style departs from the standard to collapse opening
> `declare` statements as below. This is to prevent wasteful use of vertical
> space at the beginning of every file in projects with headers like this:
>
> ```php
> <?php declare(strict_types=1);
>
> /**
>  * Header
>  */
>
> namespace Vendor\Package;
> ```

### Control structure expressions

**Rule:** `StrictExpressions`

Control structure expressions that break over multiple lines are moved to the
start of a line, as per [PSR-12, section 5]:

```php
<?php
// Before
if ($foo || $bar) {
    baz();
}
if ($foo ||
        $bar) {
    baz();
}

// After
if ($foo || $bar) {
    baz();
}
if (
    $foo ||
    $bar
) {
    baz();
}
```

> Because `pretty-php` uses [hanging indentation][] instead of vertical space
> for visual separation between adjacent code, neither `StrictExpressions` nor
> `SemiStrictExpressions` are mandatory by default.

### Arrow functions

Arrow functions that break over multiple lines are arranged as per [PER Coding
Style 2.0, section 7.1]:

```php
<?php
$foo = fn()
    => bar();
```

> `pretty-php`'s default style departs from the standard to minimise the
> horizontal space required for arrow function expressions:
>
> ```php
> <?php
> $foo = fn() =>
>     bar();
> ```

### Heredocs and nowdocs

**Rule:** `StandardSpacing`

Newlines before heredocs and nowdocs are suppressed, and unconditional heredoc
indentation is enforced as per [PER Coding Style 2.0, section 10]:

```php
<?php
$foo = [
    'bar',
    <<<EOF
        Content
        EOF,
];
$baz = <<<EOF
    Content
    EOF;
```

> `pretty-php`'s default style does not apply indentation to heredocs in
> contexts where there is no ambiguity without it:
>
> ```php
> $foo = <<<EOF
>     Content
>     EOF;
>
> $baz =
>     <<<EOF
>     Content
>     EOF;
> ```

### Exceptions

**Rule:** `OperatorSpacing`

Spaces are added between exceptions and `|` in `catch` blocks.

> `pretty-php`'s default style collapses whitespace in this context for
> consistency with union type formatting.

### Comments

**Rule:** `PlaceComments`

Comments beside the closing brace of a `class` / `interface` / `trait` / `enum`
are moved to the next line.

> `pretty-php`'s default style departs from the standard for consistency with
> its approach to comments beside code in other contexts. Varying comment
> placement beside some close braces but not others makes formatter behaviour
> seem arbitrary and reduces readability.

[hanging indentation]: Indentation.md#hanging-indentation
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12, section 3]:
  https://www.php-fig.org/psr/psr-12/#3-declare-statements-namespace-and-import-statements
[PSR-12, section 5]: https://www.php-fig.org/psr/psr-12/#5-control-structures
[PER Coding Style]: https://www.php-fig.org/per/coding-style/
[PER Coding Style 2.0, section 7.1]:
  https://www.php-fig.org/per/coding-style/#71-short-closures
[PER Coding Style 2.0, section 10]:
  https://www.php-fig.org/per/coding-style/#10-heredoc-and-nowdoc
