# PrettyPHP

## The opinionated formatter for modern, expressive PHP

*PrettyPHP* is a code formatter inspired by *Black*, the [uncompromising Python
formatter][Black].

Like *Black*, *PrettyPHP* runs with sensible defaults and doesn't need to be
configured. It's also deterministic (with some [pragmatic exceptions]), so no
matter how the input is formatted, it produces the same output.

> *PrettyPHP*'s default output is unlikely to change significantly before
> [version 1.0.0] is released, but if you're already using it in production,
> pinning `lkrms/pretty-php` to a specific version is recommended. For example:
>
> ```shell
> composer create-project --no-interaction --no-progress --no-dev lkrms/pretty-php=0.4.6 build/pretty-php
>
> build/pretty-php/bin/pretty-php --diff
> ```

## Requirements

- Linux, macOS or Windows
- PHP 7.4, 8.0, 8.1 or 8.2 with a CLI runtime and the following extensions:
  - `mbstring`
  - `json`
  - `tokenizer`

## Editor integrations

### Visual Studio Code

- **PrettyPHP for Visual Studio Code** — VS Code extension by the same author.  
  [Visual Studio Marketplace] | [Open VSX Registry] | [GitHub][vscode]

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

Over time, *PrettyPHP* will become more opinionated and have fewer options, so
reliance on formatting options is discouraged.

## Pragmatism

In theory, *PrettyPHP* completely ignores previous formatting and doesn't change
anything that isn't whitespace.

In practice, strict adherence to these rules would make it difficult to work
with, so the following pragmatic exceptions have been made. They can be disabled
for strictly deterministic behaviour.

### Newlines are preserved

> Use `-N, --ignore-newlines` to disable this behaviour.

Unless suppressed by other rules, line breaks at the following locations in the
input are applied to the output.

- **Before:** `!`, `.`, `??`, `->`, `?->`, `)`, `]`, `?>`, arithmetic operators,
  bitwise operators, ternary operators

- **After:** `(`, `[`, `{`, `,`, `;`, `=>`, `}`, `<?php`, `extends`,
  `implements`, `return`, `yield`, `yield from`, `:` (if not a ternary
  operator), assignment operators, comparison operators, logical operators,
  comments

### Scalar strings are normalised

> Use `-S, --no-simplify-strings` to disable this behaviour.

Single-quoted strings are preferred unless one or more characters require a
backslash escape, or the double-quoted equivalent is shorter.

### Alias/import statements are grouped and sorted alphabetically

> Use `-M, --no-sort-imports` to disable this behaviour.

### Comments are trimmed and aligned

> This behaviour cannot be disabled.


[Black]: https://github.com/psf/black
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[pragmatic exceptions]: #Pragmatism
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[tokenize]: https://www.php.net/manual/en/phptoken.tokenize.php
[version 1.0.0]: https://semver.org/#spec-item-5
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php

