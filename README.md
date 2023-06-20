# PrettyPHP

## The opinionated formatter for modern, expressive PHP

*PrettyPHP* is a code formatter inspired by [Black], the "uncompromising Python
formatter".

Like Black, *PrettyPHP* runs with sensible defaults and doesn't need to be
configured. It's also deterministic (with some [pragmatic exceptions]), so no
matter how the input is formatted, it produces the same output.

> *PrettyPHP*'s default output is unlikely to change significantly between now
> and version 1.0, but if you're already using it in production, locking
> `lkrms/pretty-php` to a specific version is recommended.

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
- Formatting options are [deliberately limited][why-so-many-options]
- Readable code, small diffs, and fast batch processing are the main priorities

#### It's a formatter, not a fixer<sup>\*</sup>

- Previous formatting is ignored<sup>\*\*</sup>
- Whitespace is changed, code is not<sup>\*\*</sup>
- Entire files are formatted in place

<sup>\*</sup> No disrespect is intended to excellent tools like [phpcbf] and
[php-cs-fixer]. *PrettyPHP* augments these tools, much like Black augments
`pycodestyle`.

<sup>\*\*</sup> Some [pragmatic exceptions] are made.

#### It's CI-friendly

- Installs via `composer require --dev lkrms/pretty-php` or [direct download]
- Runs on Linux, macOS and Windows
- MIT-licensed

#### It's safe

- Written in PHP
- Uses PHP's tokenizer to parse input and validate output
- Checks formatted and original code for equivalence

#### It's (almost) PSR-12 compliant

Progress towards full compliance with the formatting-related requirements of
[PSR-12] can be followed [here][PSR-12 issue].

### If it's so opinionated, why does it have so many options?

*PrettyPHP*'s formatting options will be simplified before version 1.0 is
released. Until then, feel free to experiment and provide [feedback][issues] on
what you think should be enabled by default and/or remain available as an option
in the stable release.

## Support

Bug reports, feature requests and support questions are always welcome and
should be submitted [here][issues].

## Pragmatism

In theory, *PrettyPHP* completely ignores previous formatting and doesn't change
anything that isn't whitespace.

In practice, strict adherence to these rules would make it difficult to work
with, so the following pragmatic exceptions have been made. Most of them can be
disabled for strictly deterministic behaviour.

### Newline placement is preserved

Unless suppressed by other rules, line breaks adjacent to most operators,
separators and brackets are copied from the input to the output.

> Use `-N, --ignore-newlines` to disable this behaviour.

### Scalar strings are normalised

Single-quoted strings are preferred unless one or more characters require a
backslash escape, or the double-quoted equivalent is shorter.

> Use `-S, --no-simplify-strings` to disable this behaviour.

### Alias/import statements are grouped and sorted alphabetically

> Use `-M, --no-sort-imports` to disable this behaviour.

### Comments beside code are never moved to the next line

It might seem obvious, but it wouldn't be possible if *PrettyPHP* completely
ignored previous formatting.

> This behaviour cannot be disabled.

### Comments are trimmed and aligned

> This behaviour cannot be disabled.


[Black]: https://github.com/psf/black
[direct download]: https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
[issues]: https://github.com/lkrms/pretty-php/issues
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[php-cs-fixer]: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
[phpcbf]: https://github.com/squizlabs/PHP_CodeSniffer
[pragmatic exceptions]: #pragmatism
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12 issue]: https://github.com/lkrms/pretty-php/issues/4
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
[why-so-many-options]: #if-its-so-opinionated-why-does-it-have-so-many-options
