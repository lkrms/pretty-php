# Newlines

## Default

### After

By default, newlines are allowed after:

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_RETURN`
- `T_SEMICOLON`
- `T_THROW`
- `T_YIELD`
- `T_YIELD_FROM`
- Assignment operators (except `??=`)
- Comparison operators (except `??`)
- Logical operators (except `!`) - `&&`, `||`, `and`, `or`, `xor`

### Before

They are also allowed before:

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_COALESCE`
- `T_COALESCE_EQUAL`
- `T_CONCAT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_LOGICAL_NOT`
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Ternary operators

## Operators first

If **`--operators-first`** is given, newlines are allowed before, and not after,
assignment operators (except `=`), comparison operators, and logical operators.

### After

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_EQUAL`
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_RETURN`
- `T_SEMICOLON`
- `T_THROW`
- `T_YIELD`
- `T_YIELD_FROM`

### Before

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_CONCAT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- Arithmetic operators
- Assignment operators (except `=`)
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Comparison operators
- Logical operators - `!`, `&&`, `||`, `and`, `or`, `xor`
- Ternary operators

## Operators last

With **`--operators-last`**, newlines are allowed after, and not before,
`T_COALESCE`, `T_COALESCE_EQUAL`, `T_CONCAT`, arithmetic and bitwise operators.

### After

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACE`
- `T_COLON` (except ternary operators)
- `T_COMMA`
- `T_COMMENT`
- `T_CONCAT`
- `T_DOC_COMMENT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_EXTENDS`
- `T_IMPLEMENTS`
- `T_OPEN_BRACE`
- `T_OPEN_BRACKET`
- `T_OPEN_PARENTHESIS`
- `T_OPEN_TAG`
- `T_OPEN_TAG_WITH_ECHO`
- `T_RETURN`
- `T_SEMICOLON`
- `T_THROW`
- `T_YIELD`
- `T_YIELD_FROM`
- Arithmetic operators
- Assignment operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Comparison operators
- Logical operators (except `!`) - `&&`, `||`, `and`, `or`, `xor`

### Before

- `T_ATTRIBUTE`
- `T_ATTRIBUTE_COMMENT`
- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_CLOSE_TAG`
- `T_DOUBLE_ARROW` (if applicable)
- `T_LOGICAL_NOT`
- `T_NULLSAFE_OBJECT_OPERATOR`
- `T_OBJECT_OPERATOR`
- Ternary operators
