# PrettyPHP

## The opinionated formatter for modern, expressive PHP

*PrettyPHP* is a code formatter made in the likeness of [Black].

Like Black, *PrettyPHP* runs with sensible defaults and doesn't need to be
configured. It's also deterministic (with some [pragmatic exceptions]), so no
matter how the input is formatted, it produces the same output.

*PrettyPHP* is the culmination of many attempts to find a suitable alternative,
i.e. something that happened while its developer was busy making other plans.

## Editor integrations

- [Visual Studio Code] / [Open VSX]

## FAQ

### How is *PrettyPHP* different to other formatters?

> Features still under development are temporarily ~~crossed out~~.

#### It's opinionated

- No configuration is required
- Formatting options are deliberately limited
- Readable code, small diffs, and high throughput are the main priorities

#### It's a formatter, not a fixer

- Previous formatting is ignored[^1]
- Whitespace is changed, code is not[^1]
- Entire files are formatted in place

[^1]: Some [pragmatic exceptions] are made.

#### It's CI-friendly

- Installs via `composer require --dev lkrms/pretty-php` ~~or direct download~~
- Runs on Linux, macOS and Windows
- MIT-licensed

#### It's safe

- Written in PHP
- Uses PHP's tokenizer to parse input and validate output
- Checks formatted and original code for equivalence by comparing language
  tokens returned by [`PhpToken::tokenize()`][tokenize].

#### ~~It's optionally compliant with PSR-12 and other coding standards~~

*PrettyPHP* has partial support for [PSR-12]. An upcoming release will offer
full support.

## Pragmatism

In theory, *PrettyPHP* completely ignores previous formatting and doesn't change
anything that isn't whitespace.

In practice, strict adherence to these rules would make it difficult to work
with, so the following pragmatic exceptions have been made. They can be disabled
for strictly deterministic behaviour.

### Newlines are preserved

Unless suppressed by other rules, newlines in the input are applied to the
output if they appear:

- before `!`, `.`, `??`, `->`, `?->`, `)`, `]`, `?>`, arithmetic operators,
  bitwise operators, and ternary operators, or
- after `(`, `[`, `=>`, `return`, `yield from`, `yield`, `:` (if not a ternary
  operator), assignment operators, and comparison operators

> Use `-N, --ignore-newlines` to disable this behaviour.

### Scalar strings are normalised

Single-quoted strings are preferred unless one or more characters require a
backslash escape, or the double-quoted equivalent is shorter.

> Use `-S, --no-simplify-strings` to disable this behaviour.

### Alias/import statements are grouped and sorted alphabetically

> Use `-M, --no-sort-imports` to disable this behaviour.

### Comments are trimmed and aligned

This behaviour cannot be disabled.


[Black]: https://github.com/psf/black
[Open VSX]: https://open-vsx.org/extension/lkrms/pretty-php
[pragmatic exceptions]: #Pragmatism
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[tokenize]: https://www.php.net/manual/en/phptoken.tokenize.php
[Visual Studio Code]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php

