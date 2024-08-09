# Rules

> This document is provided as a reference for contributors. Code style
> documentation for users is a separate concern.

Formatting rules applied by `pretty-php` are as follows.

Use the [list-rules][list-rules.php] script to generate an up-to-date list if
needed.

| Rule                        | Mandatory? | Default? | Pass | Method            | Priority |
| --------------------------- | ---------- | -------- | ---- | ----------------- | -------- |
| `ProtectStrings`            | Y          | -        | 1    | `processTokens()` | 40       |
| `SimplifyNumbers`           | -          | Y        | 1    | `processTokens()` | 60       |
| `SimplifyStrings`           | -          | Y        | 1    | `processTokens()` | 60       |
| `NormaliseComments`         | Y          | -        | 1    | `processTokens()` | 70       |
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
| `AlignChains` (2)           | -          | -        | 3    | `callback()`      | 710      |
| `AlignArrowFunctions` (2)   | -          | -        | 3    | `callback()`      | 710      |
| `AlignTernaryOperators` (2) | -          | -        | 3    | `callback()`      | 710      |
| `AlignLists` (2)            | -          | -        | 3    | `callback()`      | 710      |
| `AlignData` (2)             | -          | -        | 3    | `callback()`      | 720      |
| `HangingIndentation` (2)    | Y          | -        | 3    | `callback()`      | 800      |
| `StandardWhitespace` (2)    | Y          | -        | 3    | `callback()`      | 820      |
| `PlaceBraces` (2)           | Y          | -        | 4    | `beforeRender()`  | 400      |
| `HeredocIndentation` (2)    | Y          | -        | 4    | `beforeRender()`  | 900      |
| `PlaceComments` (2)         | Y          | -        | 4    | `beforeRender()`  | 997      |
| `AlignComments` (2)         | -          | -        | 4    | `beforeRender()`  | 998      |
| `EssentialWhitespace`       | Y          | -        | 4    | `beforeRender()`  | 999      |

[list-rules.php]: ../scripts/list-rules.php
