# PrettyPHP

## The opinionated formatter for modern, expressive PHP

*PrettyPHP* is a code formatter made in the likeness of *Black*, the
[uncompromising Python formatter][Black].

Like *Black*, *PrettyPHP* runs with sensible defaults and doesn't need to be
configured. It's also deterministic (with some [pragmatic exceptions]), so no
matter how the input is formatted, it produces the same output.

> *PrettyPHP* is still in development and is yet to reach a stable release. Its
> code style is unlikely to change significantly before v1, and breaking changes
> to command line options are kept to a minimum. *PrettyPHP* v0.x releases are
> safe to use in production scenarios that accommodate these limitations.

## Editor integrations

- Visual Studio Code
  - [Visual Studio Marketplace]
  - [Open VSX]

## FAQ

### How is *PrettyPHP* different to other formatters?

#### It's opinionated

- No configuration is required
- Formatting options are deliberately limited
- Readable code, small diffs, and high throughput are the main priorities

#### It's a formatter, not a fixer

- Previous formatting is ignored
- Whitespace is changed, code is not
- Entire files are formatted in place

(Some [pragmatic exceptions] are made.)

#### It's CI-friendly

- Installs via `composer require --dev lkrms/pretty-php`
- Runs on Linux, macOS and Windows
- MIT-licensed

#### It's safe

- Written in PHP
- Uses PHP's tokenizer to parse input and validate output
- Checks formatted and original code for equivalence by comparing language
  tokens returned by [`PhpToken::tokenize()`][tokenize].

#### It's optionally compatible with coding standards

*PrettyPHP* has partial support for [PSR-12]. An upcoming release will offer
full support.

### Why are there so many options?

Because *PrettyPHP* is in initial development, PHP formatting is complicated,
and testing is easier when settings can be changed at runtime.

It may also have something to do with our collective resistance--as PHP
developers--to reaching a consensus about anything. There's a lot to juggle when
you're writing an opinionated formatter you hope will appeal to other PHP
developers!

Over time, *PrettyPHP* will become more opinionated and have fewer options, so
reliance on formatting options is discouraged.

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

> This behaviour cannot be disabled, but Markdown-style trailing spaces can be
> preserved with `-T, --preserve-trailing-spaces[=COUNT,...]`.


[Black]: https://github.com/psf/black
[Open VSX]: https://open-vsx.org/extension/lkrms/pretty-php
[pragmatic exceptions]: #Pragmatism
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[tokenize]: https://www.php.net/manual/en/phptoken.tokenize.php
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php

