# Token flags

`Token::$Flag` is a bitmask of `TokenFlag` constants arranged as follows:

| Bit(s) | Value | Constant                | Applied by                |
| ------ | ----- | ----------------------- | ------------------------- |
| 2      | 4     | `ONELINE_COMMENT`       | `Parser`                  |
| 3      | 8     | `MULTILINE_COMMENT`     | `Parser`                  |
| 0, 2   | 5     | `CPP_COMMENT` ("//")    | `Parser`                  |
| 1, 2   | 6     | `SHELL_COMMENT` ("#")   | `Parser`                  |
| 0, 3   | 9     | `C_COMMENT` ("/\*")     | `Parser`                  |
| 1, 3   | 10    | `DOC_COMMENT` ("/\*\*") | `Parser`                  |
| 4      | 16    | `CODE`                  | `Parser`                  |
| 5      | 32    | `STATEMENT_TERMINATOR`  | `Parser`                  |
| 6      | 64    | `TERNARY_OPERATOR`      | `Parser`                  |
| 7      | 128   | `INFORMAL_DOC_COMMENT`  | `Parser`                  |
| 8      | 256   | `STRUCTURAL_BRACE`      | `Parser`                  |
| 9      | 512   | `NAMED_DECLARATION`     | `Token`                   |
| 10     | 1024  | `LIST_PARENT`           | `Formatter`               |
| 11     | 2048  | `COLLAPSIBLE_COMMENT`   | `NormaliseComments`       |
| 12     | 4096  | `HAS_UNENCLOSED_BODY`   | `ControlStructureSpacing` |
