# Newlines

By default, newlines are applied after:

- `T_COLON`
- `T_COMMA`
- `T_DOUBLE_ARROW` (if applicable)
- `T_SEMICOLON`
- Assignment operators (except `??=`)
- Comparison operators (except `??`)
- Logical operators (except `!`) - `&&`, `||`, `and`, `or`, `xor`

And newlines are applied before:

- `T_CLOSE_BRACKET`
- `T_CLOSE_PARENTHESIS`
- `T_COALESCE`
- `T_COALESCE_EQUAL`
- `T_CONCAT`
- `T_DOUBLE_ARROW` (if applicable)
- `T_LOGICAL_NOT`
- Arithmetic operators
- Bitwise operators - `&`, `^`, `|`, `~`, `<<`, `>>`
- Ternary operators

If **`--operators-first`** is given, the only change is that newlines are
allowed before logical operators (except `!`).

With **`--operators-last`**, newlines are allowed after `T_COALESCE`,
`T_CONCAT`, arithmetic and bitwise operators.
