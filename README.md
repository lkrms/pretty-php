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

By taking responsibility for the whitespace in your code, `pretty-php` makes it
easier to focus on the content, providing time and mental energy savings that
accrue over time.

Code formatted by `pretty-php` produces the smallest diffs possible and looks
the same regardless of the project you're working on, eliminating visual
dissonance and improving the speed and effectiveness of code review.

You can use `pretty-php` as a standalone tool, run it from your [editor][], pair
it with a linter, or add it to your CI workflows. Configuration is optional in
each case.

If you have questions or feedback, I'd love to [hear from you][discuss].

> `pretty-php` isn't stable yet, so updates may introduce formatting changes
> that affect your code.

## Features

- Supports code written for **PHP 8.3** and below (when running on a PHP version
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

### Requirements

- Linux, macOS or Windows
- PHP 8.3, 8.2, 8.1, 8.0 or 7.4 with the standard `tokenizer`, `mbstring` and
  `json` extensions enabled

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
recommended.

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
and nothing in the original file other than whitespace is changed"), but
exceptions are occasionally made and documented here.

- **Newlines are preserved** \
  Line breaks adjacent to most operators, separators and brackets are copied from
  the input to the output. _Use **`-N/--ignore-newlines`** to disable this behaviour._

- **Strings and numbers are normalised** \
  Single-quoted strings are preferred unless the alternative is shorter or backslash
  escapes are required. _Use **`-S/--no-simplify-strings`** and **`-n/--no-simplify-numbers`**
  to disable or modify this behaviour._

- **Alias/import statements are grouped and sorted alphabetically** \
  _Use **`-M/--no-sort-imports`** or **`-m/--sort-imports-by`** to disable or modify
  this behaviour._

- **Comments are placed after adjacent delimiters** \
  Relocated DocBlocks are converted to standard C-style comments as a precaution.
  _Use **`--disable=move-comments`** to disable this behaviour._

- **Comments beside code are not moved to the next line**

- **Comments are trimmed and aligned**

- **Empty DocBlocks are removed**

## License

MIT

[discuss]: https://github.com/lkrms/pretty-php/discussions
[editor]: #editor-integrations
[Open VSX Registry]: https://open-vsx.org/extension/lkrms/pretty-php
[PER]: https://www.php-fig.org/per/coding-style/
[PHIVE]: https://phar.io
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12 issue]: https://github.com/lkrms/pretty-php/issues/4
[Visual Studio Marketplace]:
  https://marketplace.visualstudio.com/items?itemName=lkrms.pretty-php
[vscode]: https://github.com/lkrms/vscode-pretty-php
