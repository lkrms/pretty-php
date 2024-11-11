# Rules

Formatting rules applied by `pretty-php` are as follows.

Use the [list-rules][list-rules.php] script to generate an up-to-date list if
needed.

| Rule                        | Mandatory? | Default? | Pass | Method            | Priority |
| --------------------------- | ---------- | -------- | ---- | ----------------- | -------- |
| `ProtectStrings`            | Y          | -        | 1    | `processTokens()` | 40       |
| `SimplifyNumbers`           | -          | Y        | 1    | `processTokens()` | 60       |
| `SimplifyStrings`           | -          | Y        | 1    | `processTokens()` | 60       |
| `NormaliseComments`         | Y          | -        | 1    | `processTokens()` | 70       |
| `IndexSpacing`              | Y          | -        | 1    | `processTokens()` | 78       |
| `StandardWhitespace` (1)    | Y          | -        | 1    | `processTokens()` | 80       |
| `StatementSpacing`          | Y          | -        | 1    | `processTokens()` | 80       |
| `OperatorSpacing`           | Y          | -        | 1    | `processTokens()` | 80       |
| `ControlStructureSpacing`   | Y          | -        | 1    | `processTokens()` | 83       |
| `PlaceComments` (1)         | Y          | -        | 1    | `processTokens()` | 90       |
| `PlaceBraces` (1)           | Y          | -        | 1    | `processTokens()` | 92       |
| `PreserveNewlines`          | -          | Y        | 1    | `processTokens()` | 93       |
| `PreserveOneLineStatements` | -          | -        | 1    | `processTokens()` | 95       |
| `BlankBeforeReturn`         | -          | -        | 1    | `processTokens()` | 97       |
| `VerticalWhitespace`        | Y          | -        | 1    | `processTokens()` | 98       |
| `ListSpacing`               | Y          | -        | 1    | `processList()`   | 98       |
| `StrictExpressions`         | -          | -        | 1    | `processTokens()` | 98       |
| `Drupal`                    | -          | -        | 1    | `processTokens()` | 100      |
| `Laravel`                   | -          | -        | 1    | `processTokens()` | 100      |
| `Symfony` (1)               | -          | -        | 1    | `processTokens()` | 100      |
| `Symfony` (2)               | -          | -        | 1    | `processList()`   | 100      |
| `WordPress`                 | -          | -        | 1    | `processTokens()` | 100      |
| `AlignChains` (1)           | -          | -        | 1    | `processTokens()` | 340      |
| `StrictLists`               | -          | -        | 1    | `processList()`   | 370      |
| `AlignArrowFunctions` (1)   | -          | -        | 1    | `processTokens()` | 380      |
| `AlignTernaryOperators` (1) | -          | -        | 1    | `processTokens()` | 380      |
| `AlignLists` (1)            | -          | -        | 1    | `processList()`   | 400      |
| `StandardIndentation`       | Y          | -        | 1    | `processTokens()` | 600      |
| `SwitchIndentation`         | Y          | -        | 1    | `processTokens()` | 600      |
| `DeclarationSpacing`        | -          | Y        | 1    | `processTokens()` | 620      |
| `HangingIndentation` (1)    | Y          | -        | 1    | `processTokens()` | 800      |
| `HeredocIndentation` (1)    | Y          | -        | 1    | `processTokens()` | 900      |
| `AlignData` (1)             | -          | -        | 2    | `processBlock()`  | 340      |
| `AlignComments` (1)         | -          | -        | 2    | `processBlock()`  | 340      |
| `AlignChains` (2)           | -          | -        | 3    | _`callback`_      | 710      |
| `AlignArrowFunctions` (2)   | -          | -        | 3    | _`callback`_      | 710      |
| `AlignTernaryOperators` (2) | -          | -        | 3    | _`callback`_      | 710      |
| `AlignLists` (2)            | -          | -        | 3    | _`callback`_      | 710      |
| `AlignData` (2)             | -          | -        | 3    | _`callback`_      | 720      |
| `HangingIndentation` (2)    | Y          | -        | 3    | _`callback`_      | 800      |
| `StandardWhitespace` (2)    | Y          | -        | 3    | _`callback`_      | 820      |
| `PlaceBraces` (2)           | Y          | -        | 4    | `beforeRender()`  | 400      |
| `HeredocIndentation` (2)    | Y          | -        | 4    | `beforeRender()`  | 900      |
| `PlaceComments` (2)         | Y          | -        | 4    | `beforeRender()`  | 997      |
| `AlignComments` (2)         | -          | -        | 4    | `beforeRender()`  | 998      |
| `EssentialWhitespace`       | Y          | -        | 4    | `beforeRender()`  | 999      |

## `ProtectStrings`

Whitespace is suppressed via critical masks applied to siblings in non-constant
strings, and to every token between square brackets in those strings.

## `SimplifyNumbers`

Integer literals are normalised by replacing hexadecimal, octal and binary
prefixes with `0x`, `0` and `0b` respectively, removing redundant zeroes, adding
`0` before hexadecimal and binary values with an odd number of digits (except
hexadecimal values with exactly 5 digits), and converting hexadecimal digits to
uppercase.

Float literals are normalised by removing redundant zeroes, adding `0` to empty
integer or fractional parts, replacing `E` with `e`, removing `+` from
exponents, and expressing them with mantissae between 1.0 and 10.

If present in the input, underscores are added to decimal values with no
exponent every 3 digits, to hexadecimal values with more than 5 digits every 4
digits, and to binary values every 4 digits.

## `IndexSpacing`

Leading and trailing spaces are added to tokens in the `AddSpace`,
`AddSpaceBefore` and `AddSpaceAfter` indexes, then suppressed, along with
adjacent blank lines, for tokens in the `SuppressSpaceBefore` and
`SuppressSpaceAfter` indexes, and inside brackets other than structural and
`match` braces. Blank lines are also suppressed after alternative syntax colons
and before their closing counterparts.

## `StandardWhitespace` (call 1: `processTokens()`)

If the indentation level of an open tag aligns with a tab stop, and a close tag
is found in the same scope (or the document has no close tag and the open tag is
in the global scope), a callback is registered to align nested tokens with it.
In the global scope, an additional level of indentation is applied unless
`MatchIndentBetweenGlobalTags` is enabled.

If a `<?php` tag is followed by a `declare` statement, they are collapsed to one
line. This is only applied in strict PSR-12 mode if the `declare` statement is
`declare(strict_types=1);` (semicolon optional), followed by a close tag.

Statements between open and close tags on the same line are preserved as
one-line statements, even if they contain constructs that would otherwise break
over multiple lines. Similarly, if a pair of open and close tags are both
adjacent to code on the same line, newlines between code and tags are
suppressed. Otherwise, inner newlines are added to open and close tags.

Whitespace is also applied to tokens as follows:

- **Commas:** leading whitespace suppressed, trailing space added.
- **`declare` statements:** whitespace suppressed between parentheses.
- **`match` expressions:** trailing line added to delimiters after arms.
- **Attributes:** trailing blank line suppressed, leading and trailing space
  added for parameters, leading and trailing line added for others.
- **Heredocs:** leading line suppressed in strict PSR-12 mode.

## `StandardWhitespace` (call 2: _`callback`_)

The indentation level of tokens between indented tags is increased if the first
token is not sufficiently indented after other rules have been applied.

[list-rules.php]: ../scripts/list-rules.php
