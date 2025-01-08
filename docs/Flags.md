# Token flags

## `Token::$Flag`

`Token::$Flag` is a bitmask of `TokenFlag` constants arranged as follows:

| Bit(s) | Value | Constant                | Applied by          |
| ------ | ----- | ----------------------- | ------------------- |
| 2      | 4     | `ONELINE_COMMENT`       | `Parser`            |
| 3      | 8     | `MULTILINE_COMMENT`     | `Parser`            |
| 0, 2   | 5     | `CPP_COMMENT` ("//")    | `Parser`            |
| 1, 2   | 6     | `SHELL_COMMENT` ("#")   | `Parser`            |
| 0, 3   | 9     | `C_COMMENT` ("/\*")     | `Parser`            |
| 1, 3   | 10    | `DOC_COMMENT` ("/\*\*") | `Parser`            |
| 4      | 16    | `C_DOC_COMMENT`         | `Parser`            |
| 5      | 32    | `COLLAPSIBLE_COMMENT`   | `NormaliseComments` |
| 6      | 64    | `CODE`                  | `Parser`            |
| 7      | 128   | `TERMINATOR`            | `Parser`            |
| 8      | 256   | `STRUCTURAL_BRACE`      | `Parser`            |
| 9      | 512   | `TERNARY`               | `Parser`            |
| 10     | 1024  | `FN_DOUBLE_ARROW`       | `Parser`            |
| 11     | 2048  | `DECLARATION`           | `Parser`            |
| 12     | 4096  | `UNENCLOSED_PARENT`     | `Parser`            |
| 13     | 8192  | `LIST_PARENT`           | `Formatter`         |
| 14     | 16384 | `LIST_ITEM`             | `Formatter`         |

## `Token::$Whitespace`

`Token::$Whitespace` is a bitmask of `WhitespaceFlag` constants arranged so
`SPACE`, `LINE` and `BLANK` flags can be shifted left or right in 3-bit groups
as needed.

> Critical flags must not be removed after they are applied.

| Bit    | Value   | Constant(s)                                        | Group                      |
| ------ | ------- | -------------------------------------------------- | -------------------------- |
| **0**  | 1       | `SPACE_BEFORE`, _`SPACE`_                          | Before (add)               |
| 1      | 2       | `LINE_BEFORE`, _`LINE`_                            |                            |
| 2      | 4       | `BLANK_BEFORE`, _`BLANK`_                          |                            |
| **3**  | 8       | `SPACE_AFTER`                                      | After (add)                |
| 4      | 16      | `LINE_AFTER`                                       |                            |
| 5      | 32      | `BLANK_AFTER`                                      |                            |
| **6**  | 64      | `NO_SPACE_BEFORE` , _`NO_SPACE`_                   | Before (remove)            |
| 7      | 128     | `NO_LINE_BEFORE` , _`NO_LINE`_                     |                            |
| 8      | 256     | `NO_BLANK_BEFORE` , _`NO_BLANK`_                   |                            |
| **9**  | 512     | `NO_SPACE_AFTER`                                   | After (remove)             |
| 10     | 1024    | `NO_LINE_AFTER`                                    |                            |
| 11     | 2048    | `NO_BLANK_AFTER`                                   |                            |
| **12** | 4096    | `CRITICAL_SPACE_BEFORE` , _`CRITICAL_SPACE`_       | Before - critical (add)    |
| 13     | 8192    | `CRITICAL_LINE_BEFORE` , _`CRITICAL_LINE`_         |                            |
| 14     | 16384   | `CRITICAL_BLANK_BEFORE` , _`CRITICAL_BLANK`_       |                            |
| **15** | 32768   | `CRITICAL_SPACE_AFTER`                             | After - critical (add)     |
| 16     | 65536   | `CRITICAL_LINE_AFTER`                              |                            |
| 17     | 131072  | `CRITICAL_BLANK_AFTER`                             |                            |
| **18** | 262144  | `CRITICAL_NO_SPACE_BEFORE` , _`CRITICAL_NO_SPACE`_ | Before - critical (remove) |
| 19     | 524288  | `CRITICAL_NO_LINE_BEFORE` , _`CRITICAL_NO_LINE`_   |                            |
| 20     | 1048576 | `CRITICAL_NO_BLANK_BEFORE` , _`CRITICAL_NO_BLANK`_ |                            |
| **21** | 2097152 | `CRITICAL_NO_SPACE_AFTER`                          | After - critical (remove)  |
| 22     | 4194304 | `CRITICAL_NO_LINE_AFTER`                           |                            |
| 23     | 8388608 | `CRITICAL_NO_BLANK_AFTER`                          |                            |
