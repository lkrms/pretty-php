# Formatter

To generate a summary of rule operations, run the following Bash command in the
relevant directory:

```bash
find . -type f -name '*.php' -print0 |
  xargs -0 \
    grep -EoHe '->Whitespace(Before|After|Mask(Prev|Next)) +[^ =]*= +.*' |
  tr -d ' ' |
  sed -E '
s/\.php:->/ /
s/WhitespaceType:://g
s/Whitespace//g
s/\|=?/+/g
s/(&=?|~)+/-/g
s/[;)]+$//' |
  sort -u |
  awk -F'[ =+-]' '
              { o = $0 }
$1 $2 != l p  { op(); n = 0; p = $2 }
$1 != l       { if (l) print ""; l = $1; sub(/.*\//, "", $1); print "`" $1 "`:" }
              { sub(/^[^ =+-]+[ =+-][^ =+-]+/, "", o); a[n++] = o }
END           { op() }

function op(_c) {
  if (!p) return
  _c = length(a)
  printf "- `%s`%s", p, (_c > 1 ? "(" : "")
  for (i = 0; i < _c; i++) {
    printf "%s%s", (i > 0 ? "|" : ""), a[i]
    delete a[i]
  }
  print (_c > 1 ? ")" : "")
}'
```

Sample output:

`AddBlankLineBeforeDeclaration`:
- `Before`+BLANK
- `MaskPrev`-BLANK

`AddBlankLineBeforeReturn`:
- `Before`+BLANK+SPACE

`AddEssential`:
- `After`(+LINE|+SPACE)
- `MaskNext`(+LINE|+SPACE)
- `MaskPrev`(+LINE|+SPACE)

`AddIndentation`:
- `Before`+LINE
- `MaskNext`+LINE
- `MaskPrev`(+LINE|-BLANK-LINE)

`AddStandard`:
- `After`(+LINE|+LINE+SPACE|+SPACE)
- `Before`(+LINE+SPACE|+SPACE)
- `MaskNext`(+LINE|-BLANK-SPACE|=NONE|=SPACE)
- `MaskPrev`(-BLANK-SPACE|=NONE)

`AlignChainedCalls`:
- `Before`+LINE

`AlignComments`:
- `Before`+BLANK

`BracePosition`:
- `After`(+LINE+SPACE|+SPACE)
- `Before`(+$before|+LINE+SPACE|+SPACE)
- `MaskNext`(-BLANK|-BLANK-LINE|-SPACE)
- `MaskPrev`(-BLANK|=SPACE)

`BreakAfterSeparators`:
- `After`(+LINE+SPACE|+SPACE)
- `Before`=NONE
- `MaskNext`+SPACE
- `MaskPrev`(+SPACE|=NONE)

`BreakBeforeControlStructureBody`:
- `After`+LINE+SPACE
- `Before`+LINE+SPACE
- `MaskNext`(+LINE|-BLANK)
- `MaskPrev`(+LINE|-BLANK)

`BreakBeforeMultiLineList`:
- `After`+LINE
- `MaskNext`+LINE
- `MaskPrev`+LINE

`BreakBetweenMultiLineItems`:
- `Before`+LINE
- `MaskNext`+LINE
- `MaskPrev`+LINE

`CommaCommaComma`:
- `After`+SPACE
- `MaskPrev`=NONE

`DeclareArgumentsOnOneLine`:
- `MaskNext`-$mask
- `MaskPrev`-$mask

`AddSpaceAfterFn`:
- `After`+SPACE
- `MaskNext`+SPACE

`AddSpaceAfterNot`:
- `After`+SPACE
- `MaskNext`+SPACE

`SuppressSpaceAroundStringOperator`:
- `MaskNext`-SPACE
- `MaskPrev`-SPACE

`MatchPosition`:
- `After`+LINE

`PlaceAttributes`:
- `After`+LINE
- `Before`+LINE
- `MaskNext`-BLANK

`PlaceComments`:
- `After`(+BLANK|+LINE|+SPACE)
- `Before`(+LINE+SPACE|+SPACE|+SPACE+$type|+TAB)
- `MaskNext`-BLANK
- `MaskPrev`-BLANK-LINE

`PreserveNewlines`:
- `After`+$type
- `Before`+$type

`PreserveOneLineStatements`:
- `MaskNext`-$mask
- `MaskPrev`-$mask

`ProtectStrings`:
- `MaskNext`=NONE
- `MaskPrev`=NONE

`SpaceOperators`:
- `After`(+SPACE|=NONE)
- `Before`(+SPACE|=NONE)
- `MaskNext`=NONE
- `MaskPrev`=NONE

`SwitchPosition`:
- `After`+LINE+SPACE
- `Before`+LINE
- `MaskNext`-BLANK
