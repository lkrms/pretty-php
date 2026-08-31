# Newlines

The token lists below are validated by the `TokenIndex` [unit test][], so any
changes to structure or syntax must be reflected there.

## Mixed

### After

By default, newlines are allowed after:

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (except in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_SEMICOLON`
- Assignment operators
- Boolean operators (except `T_LOGICAL_NOT`) - `&&`, `||`, `and`, `or`, `xor`
- Comparison operators (except `T_COALESCE`)

### Before

They are also allowed before:

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_COALESCE`
- `T_CONCAT`
- `T_DOUBLE_ARROW` (in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_LOGICAL_NOT`
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- `T_PIPE`
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Ternary operators

## Operators first

If **`--operators-first`** is given, newlines are allowed before, and not after,
boolean operators and comparison operators.

### After

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (except in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_SEMICOLON`
- Assignment operators

### Before

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_CONCAT`
- `T_DOUBLE_ARROW` (in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- `T_PIPE`
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Boolean operators - `!`, `&&`, `||`, `and`, `or`, `xor`
- Comparison operators
- Ternary operators

## Operators last

With **`--operators-last`**, newlines are allowed after, and not before,
`T_CONCAT`, arithmetic operators and bitwise operators.

### After

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_CONCAT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (except in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_SEMICOLON`
- Arithmetic operators
- Assignment operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Boolean operators (except `T_LOGICAL_NOT`) - `&&`, `||`, `and`, `or`, `xor`
- Comparison operators (except `T_COALESCE`)

### Before

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_COALESCE`
- `T_DOUBLE_ARROW` (in arrow functions if `NewlineBeforeFnDoubleArrow` is
  enabled)
- `T_LOGICAL_NOT`
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- `T_PIPE`
- Ternary operators

[unit test]: ../tests/unit/TokenIndexTest.php
