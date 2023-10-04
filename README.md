<h1 align="center">pretty-php: the opinionated code formatter</h1>

<p align="center">
  <a href="https://github.com/lkrms/pretty-php">
    <img src="https://github.com/lkrms/pretty-php/raw/main/images/logo-600x600-rounded.png" alt="pretty-php logo" width="250">
  </a>
<p>

<p align="center">
  <a href="https://packagist.org/packages/lkrms/pretty-php"><img src="https://poser.pugx.org/lkrms/pretty-php/v" alt="Latest Stable Version" /></a>
  <a href="https://packagist.org/packages/lkrms/pretty-php"><img src="https://poser.pugx.org/lkrms/pretty-php/license" alt="License" /></a>
  <a href="https://github.com/lkrms/pretty-php/actions"><img src="https://github.com/lkrms/pretty-php/actions/workflows/ci.yml/badge.svg" alt="CI Status" /></a>
  <a href="https://codecov.io/gh/lkrms/pretty-php"><img src="https://codecov.io/gh/lkrms/pretty-php/graph/badge.svg?token=W0KVZU718K" alt="Code Coverage" /></a>
  <a href="https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php"><img src="https://img.shields.io/visual-studio-marketplace/i/lkrms.pretty-php?label=Marketplace%20installs&color=%230066b8" alt="Visual Studio Marketplace install count" /></a>
  <a href="https://open-vsx.org/extension/lkrms/pretty-php"><img src="https://img.shields.io/open-vsx/dt/lkrms/pretty-php?label=Open%20VSX%20downloads&color=%23a60ee5" alt="Open VSX Registry download count" /></a>
</p>

---

`pretty-php` is a fast, deterministic, minimally configurable code formatter for
PHP.

By taking responsibility for the minutiae of formatting your code, `pretty-php`
makes it easier to focus on the content and gives you more time and mental
energy to use elsewhere.

Code formatted by `pretty-php` produces the smallest diffs possible and looks
the same regardless of the project you're working on, eliminating visual
dissonance and improving the speed and effectiveness of code review.

You can use `pretty-php` as a standalone tool, run it from your [editor][], pair
it with a linter, or add it to your CI workflows. Configuration is optional in
each case.

If you have questions or feedback, I'd love to [hear from you][discuss].

> `pretty-php` isn't stable yet, so updates may introduce formatting changes
> that affect your code. Locking the `lkrms/pretty-php` package to a specific
> version is recommended for production workflows.

## Features

- Supports code written for **PHP 8.2** and below (when running on a PHP version
  that can parse it)
- Code is formatted for **readability**, **consistency** and **small diffs**
- With few [exceptions](#pragmatism), **previous formatting is ignored**, and
  nothing in the original file other than whitespace is changed
- Entire files are formatted in place
- Formatting options are deliberately limited (`pretty-php` is opinionated so
  you don't have to be)
- Configuration via a simple JSON file is supported but not required
- PHP's embedded tokenizer is used to parse input and validate output
- Formatted and original code are compared for equivalence
- Output is optionally compliant with [PSR-12][] and [PER][] (details
  [here](docs/PSR-12.md) and [here][PSR-12 issue])

## Installation

### Prerequisites

- Linux, macOS or Windows
- PHP 8.2, 8.1, 8.0 or 7.4 with `tokenizer`, `mbstring` and `json` extensions
  enabled

### PHP archive (PHAR)

You can [download][] the latest version of `pretty-php` packaged as a PHP
archive and use it straightaway:

```shell
wget -O pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
```

```shell
php pretty-php.phar --version
```

The PHAR can be made executable for convenience:

```shell
chmod +x pretty-php.phar
```

```shell
./pretty-php.phar --version
```

It can also be installed to a location on your `PATH`. For example:

```shell
mv pretty-php.phar /usr/local/bin/pretty-php
```

### Composer

To add `pretty-php` to a [Composer][] project:

```shell
composer require --dev lkrms/pretty-php
```

Then, assuming your project's `bin-dir` is `vendor/bin`:

```shell
vendor/bin/pretty-php --version
```

### Arch Linux

Arch Linux users can install the [`pretty-php` package][AUR] from the AUR. For
example, if your preferred AUR helper is `yay`:

```shell
yay -S pretty-php
```

### Homebrew on macOS

[Homebrew][] users on macOS can use [this formula][formula] to install
`pretty-php`. The following command automatically adds the `lkrms/misc` tap if
necessary:

```shell
brew install lkrms/misc/pretty-php
```

## Usage

Once installed, getting started with `pretty-php` is as simple as giving it
something to format. For example, to format `bootstrap.php` and any PHP files in
the `src` directory:

```shell
pretty-php bootstrap.php src
```

If you would prefer to see what would change without actually replacing any
files, add the `--diff` option:

```shell
pretty-php --diff bootstrap.php src
```

For detailed information about this and other options:

```shell
pretty-php --help
```

Usage information is also available [here](docs/Usage.md).

## Editor integrations

- **pretty-php for Visual Studio Code** \
  Official VS Code extension \
  [Visual Studio Marketplace] | [Open VSX Registry] | [Repository][vscode]

## Pragmatism

`pretty-php` generally abides by its own rules (e.g. "previous formatting is
ignored, and nothing in the original file other than whitespace is changed"),
but exceptions are occasionally made and documented here.

- **Newlines are preserved** \
  Line breaks adjacent to most operators, separators and brackets are copied from
  the input to the output. \
  _`-N/--ignore-newlines` disables this behaviour_

- **Strings are normalised** \
  Single-quoted strings are preferred unless the alternative is shorter or backslash
  escapes are required. \
  _`-S/--no-simplify-strings` disables this behaviour_

- **Alias/import statements are grouped and sorted alphabetically** \
  _`-M/--no-sort-imports` and `-m/--sort-imports-by` modify this behaviour_

- **Comments beside code are not moved to the next line**

- **Comments are trimmed and aligned**

## License

MIT

[AUR]: https://aur.archlinux.org/packages/pretty-php
[Composer]: https://getcomposer.org/
[discuss]: https://github.com/lkrms/pretty-php/discussions
[download]:
  https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
[editor]: #editor-integrations
[formula]:
  https://github.com/lkrms/homebrew-misc/blob/main/Formula/pretty-php.rb
[Homebrew]: https://brew.sh/
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[PER]: https://www.php-fig.org/per/coding-style/
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12 issue]: https://github.com/lkrms/pretty-php/issues/4
[Visual Studio Marketplace]:
  https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
