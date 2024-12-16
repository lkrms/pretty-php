# Token flags

`Token::$Flag` is a bitmask of `TokenFlag` constants arranged as follows:

| Bit(s) | Value | Constant                | Applied by          |
| ------ | ----- | ----------------------- | ------------------- |
| 2      | 4     | `ONELINE_COMMENT`       | `Parser`            |
| 3      | 8     | `MULTILINE_COMMENT`     | `Parser`            |
| 0, 2   | 5     | `CPP_COMMENT` ("//")    | `Parser`            |
| 1, 2   | 6     | `SHELL_COMMENT` ("#")   | `Parser`            |
| 0, 3   | 9     | `C_COMMENT` ("/\*")     | `Parser`            |
| 1, 3   | 10    | `DOC_COMMENT` ("/\*\*") | `Parser`            |
| 4      | 16    | `INFORMAL_DOC_COMMENT`  | `Parser`            |
| 5      | 32    | `COLLAPSIBLE_COMMENT`   | `NormaliseComments` |
| 6      | 64    | `CODE`                  | `Parser`            |
| 7      | 128   | `STATEMENT_TERMINATOR`  | `Parser`            |
| 8      | 256   | `STRUCTURAL_BRACE`      | `Parser`            |
| 9      | 512   | `TERNARY_OPERATOR`      | `Parser`            |
| 10     | 1024  | `FN_DOUBLE_ARROW`       | `Parser`            |
| 11     | 2048  | `NAMED_DECLARATION`     | `Parser`            |
| 12     | 4096  | `LIST_PARENT`           | `Formatter`         |
| 13     | 8192  | `LIST_ITEM`             | `Formatter`         |
| 14     | 16384 | `UNENCLOSED_PARENT`     | `Parser`            |
