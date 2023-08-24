# PrettyPHP

## The opinionated code formatter for PHP

*PrettyPHP* is a code formatter for PHP in the tradition of [Black] for Python,
[Prettier] for JavaScript and [shfmt] for shell scripts. It aims to bring the
benefits of fast, deterministic, minimally configurable, automated code
formatting tools to PHP development.

You can use `pretty-php` as a standalone tool, run it from your [editor], add it
to your CI workflows, or pair it with a linter like [phpcbf] or [php-cs-fixer].

Or you could just give it a try 😉

## Features

- Previous formatting is ignored<sup>\*\*</sup>
- Whitespace is changed, code is not<sup>\*\*</sup>
- Output is the same no matter how input is formatted<sup>\*\*</sup>
- Code is formatted for:
  1. readability
  2. consistency
  3. small diffs
- Entire files are formatted in place
- Configuration is optional
- Formatting options are deliberately limited, workflow options are not
- Reports lines that would would change in `--diff` mode
- Exits with a meaningful status code
- Written in PHP
- Formats code written for PHP versions up to 8.2 (but see the [note about PHP
  versions][versions] below)
- Uses PHP's tokenizer to parse input and validate output
- Checks formatted and original code for equivalence
- Compliant with formatting-related [PSR-12] and [PER] requirements (when
  `--psr12` is given; details [here](docs/PSR-12.md) and [here][PSR-12 issue])

<sup>\*\*</sup> Some [pragmatic exceptions] are made.

## Requirements

- Linux, macOS or Windows
- PHP 7.4, 8.0, 8.1 or 8.2 with a CLI runtime and the following extensions
  (enabled by default on most platforms):
  - `mbstring`
  - `json`
  - `tokenizer`

### A note about PHP versions

If your PHP runtime can parse your code, *PrettyPHP* can format it, so if
formatting fails with `"<file> cannot be parsed"` even though your syntax is
valid, run `php -v` to check your PHP version.

## License

MIT

## Installation

### PHP archive (PHAR)

You can [download] the latest version of *PrettyPHP* packaged as a PHP archive
and use it straightaway:

```shell
curl -Lo pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
```

```shell
php pretty-php.phar --version
pretty-php v0.4.18-d62ba37b
```

The PHAR can be made executable for convenience:

```shell
chmod +x pretty-php.phar
```

```shell
./pretty-php.phar --version
pretty-php v0.4.18-d62ba37b
```

It can also be installed to a location on your `PATH`. For example:

```shell
mv pretty-php.phar /usr/local/bin/pretty-php
```

The `.phar` extension is optional.

### Composer

You can also add *PrettyPHP* to your project using [Composer]:

```shell
composer require --dev lkrms/pretty-php
```

And run it like this:

```shell
./vendor/bin/pretty-php --version
pretty-php v0.4.18-d62ba37b
```

Until *PrettyPHP* is stable, locking `lkrms/pretty-php` to a specific version is
recommended for production workflows. For example:

```shell
composer require --dev lkrms/pretty-php=0.4.18
```

## Editor integrations

- **PrettyPHP for Visual Studio Code** \
  Official VS Code extension \
  [Visual Studio Marketplace] | [Open VSX Registry] | [Repository][vscode]

## Pragmatism

In general, *PrettyPHP* ignores previous formatting and doesn't change anything
other than whitespace, but in cases where these rules are at odds with the
priorities mentioned above (readability, consistency, small diffs), an exception
is occasionally made and documented below.

### Exceptions

Pragmatic exceptions to *PrettyPHP*'s otherwise deterministic output are as
follows:

- **Newlines are (selectively) preserved** \
  Unless suppressed by other rules, line breaks adjacent to most operators,
  separators and brackets are copied from the input to the output. \
  *This behaviour is disabled by `-N/--ignore-newlines`*

- **Strings are normalised** \
  Single-quoted strings are preferred unless one or more characters require a
  backslash escape, or the double-quoted equivalent is shorter. \
  *This behaviour is disabled by `-S/--no-simplify-strings`*

- **Alias/import statements are grouped and sorted alphabetically** \
  *This behaviour is modified by `-m/--sort-imports-by` and disabled by
  `-M/--no-sort-imports`*

- **Comments beside code are not moved to the next line** \
  If previous formatting were ignored, detection of comments beside vs. above
  the code they describe would not be possible.

- **Comments are trimmed and aligned**

## Support

You can ask a question, report a bug or request a feature by opening a [new
issue][new-issue] in the official *PrettyPHP* GitHub repository.


[Black]: https://github.com/psf/black
[Composer]: https://getcomposer.org/
[download]: https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
[editor]: #editor-integrations
[new-issue]: https://github.com/lkrms/pretty-php/issues/new
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[PER]: https://www.php-fig.org/per/coding-style/
[php-cs-fixer]: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
[phpcbf]: https://github.com/squizlabs/PHP_CodeSniffer
[pragmatic exceptions]: #pragmatism
[Prettier]: https://prettier.io/
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12 issue]: https://github.com/lkrms/pretty-php/issues/4
[shfmt]: https://github.com/mvdan/sh#shfmt
[versions]: #a-note-about-php-versions
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
