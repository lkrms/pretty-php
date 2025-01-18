<h1 align="center">pretty-php: the opinionated code formatter</h1>

<p align="center">
  <a href="https://github.com/lkrms/pretty-php">
    <img src="https://github.com/lkrms/pretty-php/raw/main/images/logo-600x600-rounded.png" alt="pretty-php logo" width="200">
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
PHP, written in PHP.

It looks after the whitespace in your code so you have more time and energy for
the content.

Inspired by [Black][], `pretty-php` aims to produce the smallest diffs possible
and for code to look the same regardless of the project you're working on,
eliminating visual dissonance and improving the effectiveness of code review.

You can run `pretty-php` from the command line, use it in your [editor][], add
it to your CI workflows, pair it with your preferred linter, and more.

It has sensible defaults and runs without configuration.

If you have questions or feedback, I'd love to [hear from you][discuss].

## Features

- Formats code written for **PHP 8.4** and below (when running on a compatible
  version of PHP), including [property hooks][] introduced in PHP 8.4
- Code is formatted for **readability**, **consistency**, and **small diffs**
- **Previous formatting is ignored**, and nothing other than whitespace is
  changed (see [Pragmatism](#pragmatism) for exceptions)
- Entire files are formatted in place
- Formatting options are deliberately limited (`pretty-php` is opinionated so
  you don't have to be)
- Configuration via a simple JSON file is supported but not required
- Formatted and original code are compared for equivalence
- Compliant with [PSR-12][] and [PER][] (see [PSR-12 compliance][] for details)
- Supports **Symfony**, **Drupal**, **Laravel** and **WordPress** code styles
  via presets

## Installation

### Requirements

- Linux, macOS or Windows
- PHP 8.4, 8.3, 8.2, 8.1, 8.0 or 7.4 with the standard `tokenizer`, `mbstring`
  and `json` extensions enabled

### PHP archive (PHAR)

`pretty-php` is distributed as a PHP archive you can download and run:

```shell
wget -O pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
```

```shell
php pretty-php.phar --version
```

The PHAR can be made executable:

```shell
chmod +x pretty-php.phar
```

```shell
./pretty-php.phar --version
```

Official releases distributed via GitHub are signed and can be verified as
follows:

```shell
wget -O pretty-php.phar https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar
wget -O pretty-php.phar.asc https://github.com/lkrms/pretty-php/releases/latest/download/pretty-php.phar.asc
gpg --recv-keys 0xE8CC5BC780B581F2
gpg --verify pretty-php.phar.asc pretty-php.phar
```

Installation with [PHIVE][], which verifies PHAR releases automatically, is also
supported:

```shell
phive install lkrms/pretty-php
```

```shell
./tools/pretty-php --version
```

Adding `lkrms/pretty-php` to your project as a Composer dependency is not
recommended. A separate API package will be provided in the future.

### Arch Linux

Arch Linux users can install `pretty-php` from the AUR. For example, if your
preferred AUR helper is `yay`:

```shell
yay -S pretty-php
```

### macOS

Homebrew users on macOS can install `pretty-php` using the following command,
which automatically taps `lkrms/misc` if necessary:

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

To see what would change without actually replacing any files, add the `--diff`
option:

```shell
pretty-php --diff bootstrap.php src
```

For detailed usage information, see [usage](docs/Usage.md) or run:

```shell
pretty-php --help
```

## Editor integrations

- **pretty-php for Visual Studio Code** \
  Official VS Code extension \
  [Visual Studio Marketplace][] | [Open VSX Registry][] | [Repository][vscode]

## Pragmatism

`pretty-php` generally abides by its own rules ("previous formatting is ignored,
and nothing other than whitespace is changed"), but exceptions are occasionally
made and documented here.

- **Some newlines are preserved** \
  Line breaks adjacent to most operators, delimiters and brackets are copied from
  the input to the output (see [Newlines][] for details).

  Use `-N/--ignore-newlines`, `-O/--operators-first` or `-L/--operators-last` to
  disable or modify this behaviour.

- **Strings and numbers are normalised** \
  Single-quoted strings are preferred unless the alternative is shorter or backslash
  escapes are required.

  Use `-S/--no-simplify-strings` and `-n/--no-simplify-numbers` to disable or
  modify this behaviour.

- **Imports are grouped and sorted by name, depth-first** \
  See [Import sorting][] for details.

  Use `-M/--no-sort-imports` or `-m/--sort-imports-by` to disable or modify this
  behaviour.

- **Comments are moved if necessary for correct placement of adjacent tokens** \
  Use `--disable=move-comments` to disable this behaviour.

- **Comments beside code are not moved to the next line**

- **Comments are trimmed and aligned**

- **Empty DocBlocks are removed**

## License

This project is licensed under the [MIT License][].

[Black]: https://github.com/psf/black
[discuss]: https://github.com/lkrms/pretty-php/discussions
[editor]: #editor-integrations
[Import sorting]: docs/Imports.md
[MIT License]: LICENSE
[Newlines]: docs/Newlines.md
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[PER]: https://www.php-fig.org/per/coding-style/
[PHIVE]: https://phar.io
[property hooks]: https://wiki.php.net/rfc/property-hooks
[PSR-12 compliance]: docs/PSR-12.md
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[Visual Studio Marketplace]:
  https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
