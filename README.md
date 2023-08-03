# PrettyPHP

## The opinionated formatter for modern PHP

*PrettyPHP* is a code formatter inspired by [Black], the "uncompromising Python
formatter".

Like Black, *PrettyPHP* has sensible defaults and doesn't need to be configured.
It's also deterministic (with some [pragmatic exceptions]), so no matter how
your code is formatted, it produces the same output.

## Requirements

- Linux, macOS or Windows
- PHP 7.4, 8.0, 8.1 or 8.2 with a CLI runtime and the following extensions:
  - `mbstring`
  - `json`
  - `tokenizer`

### A note about PHP versions

Your PHP runtime must be able to parse the code you're formatting, e.g. if
*PrettyPHP* is running on PHP 7.4, it won't be able to format code that requires
PHP 8.0. Similarly, to format PHP 8.1 code, *PrettyPHP* must be running on PHP
8.1 or above. If it fails with `"<file> cannot be parsed"` and your syntax is
valid, check your PHP version.

## Installation

### PHP archive (PHAR)

You can download the latest version of *PrettyPHP* packaged as a PHP archive and
use it immediately:

```shell
curl -Lo pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
```

```shell
php pretty-php.phar --version
pretty-php v0.4.15-b26ee688
```

The PHAR can be made executable for convenience:

```shell
chmod +x pretty-php.phar
```

```shell
./pretty-php.phar --version
pretty-php v0.4.15-b26ee688
```

It can also be installed to a location on your `PATH`, and the `.phar` extension
is optional. For example:

```shell
mv pretty-php.phar /usr/local/bin/pretty-php
```

### Composer

Alternatively, you can add *PrettyPHP* to your project using [Composer]:

```shell
composer require --dev lkrms/pretty-php
```

And run it like this:

```shell
./vendor/bin/pretty-php --version
pretty-php v0.4.15-b26ee688
```

Until *PrettyPHP* version 1.0 is released, locking `lkrms/pretty-php` to a
specific version is recommended if you're using it in production workflows. For
example:

```shell
composer require --dev lkrms/pretty-php=0.4.15
```

## Editor integrations

- **PrettyPHP for Visual Studio Code** \
  Official VS Code extension \
  [Visual Studio Marketplace] | [Open VSX Registry] | [GitHub][vscode]

Please create a pull request or [open an issue][new-issue] if an integration
isn't listed above.

## License

MIT

## FAQ

### How is *PrettyPHP* different to other formatters?

#### It's opinionated

- No configuration is required
- Formatting options are deliberately limited
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

## Support

Please [submit an issue][new-issue] to report a bug, request a feature or ask
for help.

## Pragmatism

In theory, *PrettyPHP* completely ignores previous formatting and doesn't change
anything that isn't whitespace.

In practice, strict adherence to these rules would make it difficult to work
with, so the following pragmatic exceptions have been made. Most of them can be
disabled for strictly deterministic behaviour.

### Newline placement is preserved

Unless suppressed by other rules, line breaks adjacent to most operators,
separators and brackets are copied from the input to the output.

> Use `-N/--ignore-newlines` to disable this behaviour.

### Scalar strings are normalised

Single-quoted strings are preferred unless one or more characters require a
backslash escape, or the double-quoted equivalent is shorter.

> Use `-S/--no-simplify-strings` to disable this behaviour.

### Alias/import statements are grouped and sorted alphabetically

> Use `-m/--sort-imports-by` or `-M/--no-sort-imports` to modify or disable this
> behaviour.

### Comments beside code are never moved to the next line

It might seem obvious, but it wouldn't be possible if *PrettyPHP* completely
ignored previous formatting.

> This behaviour cannot be disabled.

### Comments are trimmed and aligned

> This behaviour cannot be disabled.


[Black]: https://github.com/psf/black
[Composer]: https://getcomposer.org/
[direct download]: https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
[new-issue]: https://github.com/lkrms/pretty-php/issues/new
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[php-cs-fixer]: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
[phpcbf]: https://github.com/squizlabs/PHP_CodeSniffer
[pragmatic exceptions]: #pragmatism
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12 issue]: https://github.com/lkrms/pretty-php/issues/4
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
