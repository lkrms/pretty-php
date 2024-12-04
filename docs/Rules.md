# Rules

Formatting rules applied by `pretty-php` are as follows.

| Rule                        | Mandatory? | Default? | Pass | Method                  | Priority |
| --------------------------- | ---------- | -------- | ---- | ----------------------- | -------- |
| `ProtectStrings`            | Y          | -        | 1    | `processTokens()`       | 40       |
| `NormaliseNumbers`          | -          | Y        | 1    | `processTokens()`       | 60       |
| `NormaliseStrings`          | -          | Y        | 1    | `processTokens()`       | 60       |
| `NormaliseComments`         | Y          | -        | 1    | `processTokens()`       | 70       |
| `IndexSpacing`              | Y          | -        | 1    | `processTokens()`       | 78       |
| `StandardSpacing` (1)       | Y          | -        | 1    | `processTokens()`       | 80       |
| `StandardSpacing` (2)       | Y          | -        | 1    | `processDeclarations()` | 80       |
| `StatementSpacing`          | Y          | -        | 1    | `processTokens()`       | 80       |
| `OperatorSpacing`           | Y          | -        | 1    | `processTokens()`       | 80       |
| `ControlStructureSpacing`   | Y          | -        | 1    | `processTokens()`       | 83       |
| `PlaceComments` (1)         | Y          | -        | 1    | `processTokens()`       | 90       |
| `PlaceBraces` (1)           | Y          | -        | 1    | `processTokens()`       | 92       |
| `PreserveNewlines`          | -          | Y        | 1    | `processTokens()`       | 93       |
| `PreserveOneLineStatements` | -          | -        | 1    | `processStatements()`   | 95       |
| `BlankBeforeReturn`         | -          | -        | 1    | `processTokens()`       | 97       |
| `VerticalWhitespace`        | Y          | -        | 1    | `processTokens()`       | 98       |
| `ListSpacing` (1)           | Y          | -        | 1    | `processDeclarations()` | 98       |
| `ListSpacing` (2)           | Y          | -        | 1    | `processList()`         | 98       |
| `StrictExpressions`         | -          | -        | 1    | `processTokens()`       | 98       |
| `SemiStrictExpressions`     | -          | -        | 1    | `processTokens()`       | 98       |
| `Drupal` (1)                | -          | -        | 1    | `processTokens()`       | 100      |
| `Drupal` (2)                | -          | -        | 1    | `processDeclarations()` | 100      |
| `Laravel`                   | -          | -        | 1    | `processTokens()`       | 100      |
| `Symfony` (1)               | -          | -        | 1    | `processTokens()`       | 100      |
| `Symfony` (2)               | -          | -        | 1    | `processList()`         | 100      |
| `WordPress`                 | -          | -        | 1    | `processTokens()`       | 100      |
| `AlignChains` (1)           | -          | -        | 1    | `processTokens()`       | 340      |
| `StrictLists`               | -          | -        | 1    | `processList()`         | 370      |
| `AlignArrowFunctions` (1)   | -          | -        | 1    | `processTokens()`       | 380      |
| `AlignTernaryOperators` (1) | -          | -        | 1    | `processTokens()`       | 380      |
| `AlignLists` (1)            | -          | -        | 1    | `processList()`         | 400      |
| `StandardIndentation`       | Y          | -        | 1    | `processTokens()`       | 600      |
| `SwitchIndentation`         | Y          | -        | 1    | `processTokens()`       | 600      |
| `DeclarationSpacing`        | -          | Y        | 1    | `processDeclarations()` | 620      |
| `HangingIndentation` (1)    | Y          | -        | 1    | `processTokens()`       | 800      |
| `HeredocIndentation` (1)    | Y          | -        | 1    | `processTokens()`       | 900      |
| `AlignData` (1)             | -          | -        | 2    | `processBlock()`        | 340      |
| `AlignComments` (1)         | -          | -        | 2    | `processBlock()`        | 340      |
| `AlignChains` (2)           | -          | -        | 3    | _`callback`_            | 710      |
| `AlignArrowFunctions` (2)   | -          | -        | 3    | _`callback`_            | 710      |
| `AlignTernaryOperators` (2) | -          | -        | 3    | _`callback`_            | 710      |
| `AlignLists` (2)            | -          | -        | 3    | _`callback`_            | 710      |
| `AlignData` (2)             | -          | -        | 3    | _`callback`_            | 720      |
| `HangingIndentation` (2)    | Y          | -        | 3    | _`callback`_            | 800      |
| `StandardSpacing` (3)       | Y          | -        | 3    | _`callback`_            | 820      |
| `PlaceBraces` (2)           | Y          | -        | 4    | `beforeRender()`        | 400      |
| `HeredocIndentation` (2)    | Y          | -        | 4    | `beforeRender()`        | 900      |
| `PlaceComments` (2)         | Y          | -        | 4    | `beforeRender()`        | 997      |
| `AlignComments` (2)         | -          | -        | 4    | `beforeRender()`        | 998      |
| `EssentialWhitespace`       | Y          | -        | 4    | `beforeRender()`        | 999      |

## Descriptions

### `ProtectStrings`

Changes to whitespace in non-constant strings are suppressed for:

- nested siblings
- every descendant of square brackets that are nested siblings

The latter is necessary because strings like `"$foo[0]"` and `"$foo[$bar]"` are unparseable if there is any whitespace between the brackets.

### `NormaliseNumbers`, unless disabled

Integer literals are normalised by replacing hexadecimal, octal and binary prefixes with `0x`, `0` and `0b` respectively, removing redundant zeroes, adding `0` before hexadecimal and binary values with an odd number of digits (except hexadecimal values with exactly 5 digits), and converting hexadecimal digits to uppercase.

Float literals are normalised by removing redundant zeroes, adding `0` to empty integer or fractional parts, replacing `E` with `e`, removing `+` from exponents, and expressing them with mantissae between 1.0 and 10.

If present in the input, underscores are applied to decimal values with no exponent every 3 digits, to hexadecimal values with more than 5 digits every 4 digits, and to binary values every 4 digits.

### `NormaliseStrings`, unless disabled

Strings other than nowdocs are normalised as follows:

- Single- and double-quoted strings are replaced with the most readable and economical syntax. Single-quoted strings are preferred unless escaping is required or the double-quoted equivalent is shorter.
- Backslash escapes are added in contexts where they improve safety, consistency and readability, otherwise they are removed if possible.
- Aside from leading and continuation bytes in valid UTF-8 strings, control characters and non-ASCII characters are backslash-escaped using hexadecimal notation with lowercase digits. Invisible characters that don't belong to a recognised Unicode sequence are backslash-escaped using Unicode notation with uppercase digits.

### `NormaliseComments`

In one-line C-style comments, unnecessary asterisks are removed from both delimiters, and whitespace between delimiters and adjacent content is replaced with a space.

Shell-style comments (`#`) are converted to C++-style comments (`//`).

In C++-style comments, a space is added between the delimiter and adjacent content if horizontal whitespace is not already present.

DocBlocks are normalised for PSR-5 compliance as follows:

- An asterisk is added to the start of each line that doesn't have one. The indentation of undelimited lines relative to each other is maintained if possible.
- If every line starts with an asterisk and ends with `" *"` or `"\t*"`, trailing asterisks are removed.
- Trailing whitespace is removed from each line.
- The content of each DocBlock is applied to its token as `COMMENT_CONTENT` data.
- DocBlocks with one line of content are collapsed to a single line unless they appear to describe a file or have a subsequent named declaration. In the latter case, the `COLLAPSIBLE_COMMENT` flag is applied.

C-style comments where every line starts with an asterisk, or at least one delimiter appears on its own line, receive the same treatment as DocBlocks.

> Any C-style comments that remain are trimmed and reindented by the > renderer.

### `IndexSpacing`

Leading and trailing spaces are added to tokens in the `AddSpace`, `AddSpaceBefore` and `AddSpaceAfter` indexes, then suppressed, along with adjacent blank lines, for tokens in the `SuppressSpaceBefore` and `SuppressSpaceAfter` indexes, and inside brackets other than structural and `match` braces. Blank lines are also suppressed after alternative syntax colons and before their closing counterparts.

### `StandardSpacing` (call 1: `processTokens()`)

If the indentation level of an open tag aligns with a tab stop, and a close tag is found in the same scope (or the document has no close tag and the open tag is in the global scope), a callback is registered to align nested tokens with it. An additional level of indentation is applied if `IndentBetweenTags` is enabled.

If a `<?php` tag is followed by a `declare` statement, they are collapsed to one line. This is only applied in strict PSR-12 mode if the `declare` statement is `declare(strict_types=1);` (semicolon optional), followed by a close tag.

Statements between open and close tags on the same line are preserved as one-line statements, even if they contain constructs that would otherwise break over multiple lines. Similarly, if a pair of open and close tags are both adjacent to code on the same line, newlines between code and tags are suppressed. Otherwise, inner newlines are added to open and close tags.

Whitespace is also applied to tokens as follows:

- **Commas:** leading whitespace suppressed, trailing space added.
- **`declare` statements:** whitespace suppressed between parentheses.
- **`match` expressions:** trailing line added to delimiters after arms.
- **Attributes:** trailing blank line suppressed, leading and trailing space added for parameters, property hooks, anonymous functions and arrow functions, leading and trailing line added for others.
- **Heredocs:** leading line suppressed in strict PSR-12 mode.

### `StandardSpacing` (call 2: `processDeclarations()`)

If a constructor has one or more promoted parameters, a line is added before every parameter.

If a property has unimplemented hooks with no modifiers or attributes (e.g. `public $Foo { &get; set; }`), they are collapsed to one line, otherwise hooks with statements are formatted like anonymous functions, and hooks that use abbreviated syntax are formatted like arrow functions.

### `PlaceBraces` (call 1: `processTokens()`)

Whitespace is applied to structural and `match` expression braces as follows:

- Blank lines are suppressed after open braces and before close braces.
- Newlines are added after open braces.
- Newlines are added after close braces unless they belong to a `match` expression or a control structure that is immediately continued, e.g. `} else {`. In the latter case, trailing newlines are suppressed.
- Empty class, function and property hook bodies are collapsed to ` {}` on the same line as the declaration they belong to unless `CollapseEmptyDeclarationBodies` is disabled.
- Horizontal whitespace is suppressed between other empty braces.

> Open brace placement is left for a rule that runs after vertical > whitespace has been applied.

### `BlankBeforeReturn`, if enabled

Blank lines are added before non-consecutive `return`, `yield` and `yield from` statements.

### `ListSpacing` (call 1: `processDeclarations()`)

If a list of property hooks has one or more attributes with a trailing newline, every attribute is placed on its own line, and blank lines are added before and after annotated hooks to improve readability.

### `ListSpacing` (call 2: `processList()`)

Arrays and argument lists with trailing ("magic") commas are split into one item per line.

If parameter lists have one or more attributes with a trailing newline, every attribute is placed on its own line, and blank lines are added before and after annotated parameters to improve readability.

If interface lists break over multiple lines and neither `StrictLists` nor `AlignLists` are enabled, a newline is added before the first interface.

### `StrictExpressions`, if enabled

Newlines are added before and after control structure expressions that break over multiple lines.

### `SemiStrictExpressions`, if enabled

Newlines are added before and after control structure expressions with newlines between siblings.

> Unlike `StrictExpressions`, this rule does not apply leading and > trailing newlines to expressions that would not break over multiple > lines if tokens between brackets were removed.

### `Drupal`, if enabled (call 1: `processTokens()`)

Blank lines are added after DocBlocks with a `@file` tag.

Newlines are added after close braces with a subsequent `elseif`, `else`, `catch` or `finally`.

### `Drupal`, if enabled (call 2: `processDeclarations()`)

Blank lines are added inside non-empty `class`, `enum`, `interface` and `trait` braces.

### `Laravel`, if enabled

Trailing spaces are added to:

- `!` operators
- `fn` in arrow functions

Leading and trailing spaces are suppressed for `.` operators.

### `Symfony`, if enabled (call 1: `processTokens()`)

Trailing spaces are added to `fn` in arrow functions.

Leading and trailing spaces are suppressed for `.` operators.

### `Symfony`, if enabled (call 2: `processList()`)

Newlines are suppressed between parameters in function declarations that have no promoted constructor parameters.

### `WordPress`, if enabled

Suppression of blank lines after DocBlocks is disabled for the first DocBlock in each document.

Blank lines added before DocBlocks by other rules are removed.

Leading spaces are added to `:` in alternative syntax constructs.

Trailing spaces are added to `!` operators.

Suppression of blank lines inside braces is disabled.

Spaces are added inside non-empty:

- parentheses
- square brackets (except in strings or when they enclose one inner token that is not a variable)

### `AlignChains`, if enabled (call 1: `processTokens()`)

If there are no object operators with a leading newline in a chain of method calls, or if the first object operator in the chain has a leading newline and `AlignChainAfterNewline` is disabled, no action is taken.

Otherwise, if the first object operator in the chain has a leading newline, it is removed if horizontal space on subsequent lines would be saved. Then, a callback is registered to align object operators in the chain with:

- the first object operator (if it has no leading newline)
- the expression dereferenced by the first object operator (if it doesn't break over multiple lines), or
- the first token on the line before the first object operator

### `StrictLists`, if enabled

Items in lists are arranged horizontally or vertically by replicating the arrangement of the first and second items.

### `AlignArrowFunctions`, if enabled (call 1: `processTokens()`)

If an arrow function expression starts on a new line, a callback is registered to align it with the `fn` it's associated with, or with the first token on the previous line if its arguments break over multiple lines.

### `AlignArrowFunctions`, if enabled (call 2: _`callback`_)

Tokens in arrow function expressions are aligned with the `fn` they're associated with, or with the first token on the previous line if its arguments break over multiple lines.

This is achieved by copying the alignment target's indentation to each token after making a calculated adjustment to `LinePadding`.

### `StandardSpacing` (call 3: _`callback`_)

The `TagIndent` of tokens between indented tags is adjusted by the difference, if any, between the open tag's indent and the indentation level of the first token after the open tag.

### `PlaceBraces` (call 2: `beforeRender()`)

In function declarations where `)` and `{` appear at the start of consecutive lines, they are collapsed to the same line.

## `TokenRule` classes, by token

| Token                                       | Rules                                                                                         |
| ------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `*`                                         | `HangingIndentation`, `PreserveNewlines`, `StandardIndentation`                               |
| `T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG`     | `VerticalWhitespace`                                                                          |
| `T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG` | `VerticalWhitespace`                                                                          |
| `T_AND`                                     | `VerticalWhitespace`                                                                          |
| `T_ATTRIBUTE_COMMENT`                       | `StandardSpacing`                                                                             |
| `T_ATTRIBUTE`                               | `StandardSpacing`                                                                             |
| `T_BACKTICK`                                | `ProtectStrings`                                                                              |
| `T_BOOLEAN_AND`                             | `VerticalWhitespace`                                                                          |
| `T_BOOLEAN_OR`                              | `VerticalWhitespace`                                                                          |
| `T_CASE`                                    | `SwitchIndentation`                                                                           |
| `T_CATCH`                                   | `Drupal`                                                                                      |
| `T_CLOSE_TAG`                               | `StandardSpacing`                                                                             |
| `T_COALESCE`                                | `AlignTernaryOperators`                                                                       |
| `T_COLON`                                   | `StatementSpacing`, `WordPress`                                                               |
| `T_COMMA`                                   | `StandardSpacing`                                                                             |
| `T_COMMENT`                                 | `NormaliseComments`, `PlaceComments`, `WordPress`                                             |
| `T_CONCAT`                                  | `Laravel`, `Symfony`                                                                          |
| `T_CONSTANT_ENCAPSED_STRING`                | `NormaliseStrings`                                                                            |
| `T_DECLARE`                                 | `StandardSpacing`                                                                             |
| `T_DEFAULT`                                 | `SwitchIndentation`                                                                           |
| `T_DNUMBER`                                 | `NormaliseNumbers`                                                                            |
| `T_DOC_COMMENT`                             | `Drupal`, `NormaliseComments`, `PlaceComments`, `WordPress`                                   |
| `T_DOUBLE_QUOTE`                            | `ProtectStrings`                                                                              |
| `T_DO`                                      | `ControlStructureSpacing`                                                                     |
| `T_ELSEIF`                                  | `ControlStructureSpacing`, `Drupal`, `SemiStrictExpressions`, `StrictExpressions`             |
| `T_ELSE`                                    | `ControlStructureSpacing`, `Drupal`                                                           |
| `T_ENCAPSED_AND_WHITESPACE`                 | `NormaliseStrings`                                                                            |
| `T_FINALLY`                                 | `Drupal`                                                                                      |
| `T_FN`                                      | `AlignArrowFunctions`, `Laravel`, `Symfony`                                                   |
| `T_FOREACH`                                 | `ControlStructureSpacing`, `SemiStrictExpressions`, `StrictExpressions`                       |
| `T_FOR`                                     | `ControlStructureSpacing`, `SemiStrictExpressions`, `StrictExpressions`, `VerticalWhitespace` |
| `T_IF`                                      | `ControlStructureSpacing`, `SemiStrictExpressions`, `StrictExpressions`                       |
| `T_LNUMBER`                                 | `NormaliseNumbers`                                                                            |
| `T_LOGICAL_AND`                             | `VerticalWhitespace`                                                                          |
| `T_LOGICAL_NOT`                             | `Laravel`, `WordPress`                                                                        |
| `T_LOGICAL_OR`                              | `VerticalWhitespace`                                                                          |
| `T_LOGICAL_XOR`                             | `VerticalWhitespace`                                                                          |
| `T_MATCH`                                   | `StandardSpacing`                                                                             |
| `T_NULLSAFE_OBJECT_OPERATOR`                | `AlignChains`, `VerticalWhitespace`                                                           |
| `T_OBJECT_OPERATOR`                         | `AlignChains`, `VerticalWhitespace`                                                           |
| `T_OPEN_BRACE`                              | `PlaceBraces`, `VerticalWhitespace`, `WordPress`                                              |
| `T_OPEN_BRACKET`                            | `WordPress`                                                                                   |
| `T_OPEN_PARENTHESIS`                        | `WordPress`                                                                                   |
| `T_OPEN_TAG_WITH_ECHO`                      | `StandardSpacing`                                                                             |
| `T_OPEN_TAG`                                | `StandardSpacing`                                                                             |
| `T_OR`                                      | `VerticalWhitespace`                                                                          |
| `T_QUESTION`                                | `AlignTernaryOperators`, `VerticalWhitespace`                                                 |
| `T_RETURN`                                  | `BlankBeforeReturn`                                                                           |
| `T_SEMICOLON`                               | `StatementSpacing`                                                                            |
| `T_START_HEREDOC`                           | `HeredocIndentation`, `ProtectStrings`, `StandardSpacing`                                     |
| `T_SWITCH`                                  | `SemiStrictExpressions`, `StrictExpressions`, `SwitchIndentation`                             |
| `T_WHILE`                                   | `ControlStructureSpacing`, `SemiStrictExpressions`, `StrictExpressions`                       |
| `T_XOR`                                     | `VerticalWhitespace`                                                                          |
| `T_YIELD_FROM`                              | `BlankBeforeReturn`                                                                           |
| `T_YIELD`                                   | `BlankBeforeReturn`                                                                           |

## `DeclarationRule` classes, by declaration type

| Declaration    | Rules                                                  |
| -------------- | ------------------------------------------------------ |
| `CASE`         | `DeclarationSpacing`                                   |
| `CLASS`        | `DeclarationSpacing`, `Drupal`                         |
| `CONST`        | `DeclarationSpacing`                                   |
| `DECLARE`      | `DeclarationSpacing`                                   |
| `ENUM`         | `DeclarationSpacing`, `Drupal`                         |
| `FUNCTION`     | `DeclarationSpacing`                                   |
| `HOOK`         | `DeclarationSpacing`                                   |
| `INTERFACE`    | `DeclarationSpacing`, `Drupal`                         |
| `NAMESPACE`    | `DeclarationSpacing`                                   |
| `PARAM`        | `ListSpacing`, `StandardSpacing`                       |
| `PROPERTY`     | `DeclarationSpacing`, `ListSpacing`, `StandardSpacing` |
| `TRAIT`        | `DeclarationSpacing`, `Drupal`                         |
| `USE_CONST`    | `DeclarationSpacing`                                   |
| `USE_FUNCTION` | `DeclarationSpacing`                                   |
| `USE_TRAIT`    | `DeclarationSpacing`                                   |
| `USE`          | `DeclarationSpacing`                                   |
