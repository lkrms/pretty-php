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
- Comparison operators (except `T_COALESCE`)
- Logical operators (except `T_LOGICAL_NOT`) - `&&`, `||`, `and`, `or`, `xor`

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
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Ternary operators

## Operators first

If **`--operators-first`** is given, newlines are allowed before, and not after,
comparison operators and logical operators.

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
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Comparison operators
- Logical operators - `!`, `&&`, `||`, `and`, `or`, `xor`
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
- Comparison operators (except `T_COALESCE`)
- Logical operators (except `T_LOGICAL_NOT`) - `&&`, `||`, `and`, `or`, `xor`

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
- Ternary operators

[unit test]: ../tests/unit/TokenIndexTest.php
