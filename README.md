<div align="center">

<img src="images/logo-600x600-rounded.png" alt="PrettyPHP logo" width="300" height="300">

# PrettyPHP

## The opinionated code formatter for PHP

</div>

*PrettyPHP* is a code formatter for PHP in the tradition of [Black][] for
Python, [Prettier][] for JavaScript and [shfmt][] for shell scripts. It aims to
bring the benefits of fast, deterministic, minimally configurable, automated
code formatting tools to PHP development.

To that end, you can use *PrettyPHP* as a standalone tool, run it from your
[editor][], or pair it with a linter like [phpcbf][] or [php-cs-fixer][] and add
it to your CI workflows.

Or you could just give it a try and [let me know what you think][discuss]. 😉

## Requirements

- Linux, macOS or Windows
- PHP 7.4, 8.0, 8.1 or 8.2 with the standard `tokenizer`, `mbstring` and `json`
  extensions enabled

## Features

Code is formatted for

1. readability,
2. consistency, and
3. small diffs

and with a few [pragmatic exceptions][]:

- previous formatting is ignored
- whitespace is changed, code is not
- output is the same no matter how input is formatted

Also:

- Entire files are formatted in place
- Configuration is optional
- Formatting options are deliberately limited, workflow options are not
- Uses PHP's tokenizer to parse input and validate output
- Checks formatted and original code for equivalence
- Supports code written for PHP versions up to 8.2 (when running on a PHP
  version that can parse it)
- Compliant with [PSR-12][] and [PER][] if `--psr12` is given (details
  [here](docs/PSR-12.md) and [here][PSR-12 issue])

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

> Until *PrettyPHP* is stable, locking `lkrms/pretty-php` to a specific version
> is recommended for production workflows. For example:
>
> ```shell
> composer require --dev lkrms/pretty-php=0.4.18
> ```

## Editor integrations

- **PrettyPHP for Visual Studio Code** \
  Official VS Code extension \
  [Visual Studio Marketplace] | [Open VSX Registry] | [Repository][vscode]

## Pragmatism

*PrettyPHP* generally abides by its own rules ("previous formatting is ignored"
and "whitespace is changed, code is not"), but pragmatic exceptions are
occasionally made and added to the following list:

- **Newlines are preserved** \
  Line breaks adjacent to most operators, separators and brackets are copied
  from the input to the output. \
  *`-N/--ignore-newlines` disables this behaviour*

- **Strings are normalised** \
  Single-quoted strings are preferred unless the alternative is shorter or
  backslash escapes are required. \
  *`-S/--no-simplify-strings` disables this behaviour*

- **Alias/import statements are grouped and sorted alphabetically** \
  *`-M/--no-sort-imports` and `-m/--sort-imports-by` modify this behaviour*

- **Comments beside code are not moved to the next line**

- **Comments are trimmed and aligned**

## Support

You can ask a question, report a bug or request a feature by opening a [new
issue][new-issue] in the official *PrettyPHP* GitHub repository.


[Black]: https://github.com/psf/black
[Composer]: https://getcomposer.org/
[discuss]: https://github.com/lkrms/pretty-php/discussions
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
[Visual Studio Marketplace]: https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
