# PrettyPHP

## The opinionated formatter for modern, expressive PHP

*PrettyPHP* is a code formatter made in the likeness of [Black][].

Like *Black*, *PrettyPHP* runs with sensible defaults and doesn't need to be
configured. It's also deterministic ([with a few exceptions](#pragmatism)), so
no matter how the input is formatted, it should always produce the same output.

*PrettyPHP* is the culmination of many attempts to find a suitable alternative.
You could say it's something that happened while I was busy making other
plans...

## FAQ

<details>
<summary><strong>How is <em>PrettyPHP</em> different to other formatters?</strong></summary>

- It's opinionated

  - No configuration is required
  - Formatting options are deliberately limited
  - Readable code, small diffs, and fast processing are the main priorities

- It ignores previous formatting ([with some exceptions](#pragmatism))

  - Whitespace is discarded before formatting
  - Entire files are formatted in place

- It doesn't make any changes to code ([with some exceptions](#pragmatism))

- It's CI-friendly

  - Installs via `composer require --dev` or direct download
  - Runs on Linux, macOS and Windows
  - MIT-licensed

- It's written in PHP

  - Uses PHP to safely tokenize and validate code
  - Compares tokens before and after formatting for equivalence

- It's optionally compliant with PSR-12 and other coding standards

</details>

## Pragmatism

As a deterministic code formatter, *PrettyPHP* follows two simple rules:

1. Ignore previous formatting
2. Don't make any changes to code

Exceptions to these rules are made sparingly and are documented below.

<details>
<summary><strong>Newlines are preserved by default</strong></summary>

Tokens listed in [`TokenType::PRESERVE_NEWLINE_AFTER`][] and
[`TokenType::PRESERVE_NEWLINE_BEFORE`][] are checked for adjacent newlines.

> Use `--ignore-newlines` to disable this feature.

</details>

<details>
<summary><strong>Constant strings are simplified by default</strong></summary>

Single- and double-quoted strings are replaced with whichever is clearer and
more efficient.

> Use `--skip simplify-strings` to disable this feature.

</details>

<details>
<summary><strong>Multi-line comments are cleaned up and aligned</strong></summary>

The second and subsequent lines of PHPDoc comments, and standard multi-line
comments with a leading asterisk on each line, are aligned below the first
line's opening asterisk.

</details>


[Black]: https://github.com/psf/black
[`TokenType::PRESERVE_NEWLINE_AFTER`]: https://github.com/lkrms/pretty-php/blob/92cc5ae52eab9b88de122174f70c93ae6e58ba3a/src/Php/TokenType.php#L30
[`TokenType::PRESERVE_NEWLINE_BEFORE`]: https://github.com/lkrms/pretty-php/blob/92cc5ae52eab9b88de122174f70c93ae6e58ba3a/src/Php/TokenType.php#L50

