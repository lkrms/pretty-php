# Formatter

To generate a summary of rule operations, run the following Bash command in the
relevant directory:

```bash
find . -type f -name '*.php' -print0 |
  xargs -0 grep -EoH \
    -e '->(Critical)?Whitespace(Before|After|Mask(Prev|Next)) +[^ =]*= +.*' \
    -e '->(addWhitespaceBefore|maskWhitespaceBefore|maskInnerWhitespace)\([^)]*\)' |
  tr -d ' ' |
  sed -E '
s/\.php:->/ /
s/WhitespaceType:://g
s/(Critical)?Whitespace//g
s/\|=?/+/g
s/(&=?|~)+/-/g
s/[;)]+$//
/addBefore/ {
  s/addBefore\(/Before+/; p
  s/Before/MaskPrev/; p
  s/MaskPrev/MaskNext/
}
/maskBefore/ {
  s/maskBefore\(-?/MaskPrev-/; p
  s/MaskPrev/MaskNext/
}
/maskInner/ {
  s/maskInner\(-?/MaskPrev-/; p
  s/MaskPrev/MaskNext/
}' |
  sed -E 's/-NONE/=NONE/' |
  LC_ALL=C sort -u |
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
- `Before`(+LINE|+LINE+SPACE|+SPACE)
- `MaskNext`(+LINE|-BLANK|-BLANK-SPACE|=NONE|=SPACE)
- `MaskPrev`(-BLANK-SPACE|=NONE)

`AlignChainedCalls`:
- `Before`+LINE

`AlignComments`:
- `Before`+BLANK

`ApplyMagicComma`:
- `Before`+LINE,true
- `MaskNext`+LINE,true
- `MaskPrev`+LINE,true

`BracePosition`:
- `After`(+LINE+SPACE|+SPACE)
- `Before`(+LINE+SPACE|+SPACE|+SPACE+$line)
- `MaskNext`(-BLANK|-BLANK-LINE|-SPACE)
- `MaskPrev`(-BLANK|=SPACE)

`BreakAfterSeparators`:
- `After`(+LINE+SPACE|+SPACE)
- `Before`=NONE
- `MaskNext`+SPACE
- `MaskPrev`(+SPACE|=NONE)

`BreakBeforeControlStructureBody`:
- `After`+LINE+SPACE
- `Before`(+LINE|+LINE+SPACE)
- `MaskNext`(+LINE|-BLANK)
- `MaskPrev`(+LINE|-BLANK)

`BreakBeforeMultiLineList`:
- `After`+LINE
- `MaskNext`+LINE
- `MaskPrev`+LINE

`AddSpaceAfterFn`:
- `After`+SPACE
- `MaskNext`+SPACE

`AddSpaceAfterNot`:
- `After`+SPACE
- `MaskNext`+SPACE

`DeclareArgumentsOnOneLine`:
- `MaskNext`-$allLines
- `MaskPrev`-$allLines

`SuppressSpaceAroundStringOperator`:
- `MaskNext`-SPACE
- `MaskPrev`-SPACE

`MatchPosition`:
- `After`+LINE

`NoMixedLists`:
- `Before`+LINE
- `MaskNext`(+LINE|-BLANK-LINE)
- `MaskPrev`(+LINE|-BLANK-LINE)

`PlaceComments`:
- `After`(+BLANK|+LINE|+SPACE)
- `Before`(+LINE+SPACE|+SPACE|+SPACE+$line|+TAB)
- `MaskNext`-BLANK
- `MaskPrev`-BLANK-LINE

`PreserveNewlines`:
- `After`+$line
- `Before`+$line

`PreserveOneLineStatements`:
- `MaskNext`-BLANK-LINE
- `MaskPrev`-BLANK-LINE

`ProtectStrings`:
- `MaskNext`=NONE
- `MaskPrev`=NONE

`SpaceDeclarations`:
- `After`+BLANK
- `Before`(+$line|+BLANK)
- `MaskPrev`(+BLANK|-BLANK)

`SpaceOperators`:
- `After`(+SPACE|=NONE)
- `Before`(+SPACE|=NONE)
- `MaskNext`=NONE
- `MaskPrev`=NONE

`SwitchPosition`:
- `After`+LINE+SPACE
- `Before`+LINE
- `MaskNext`-BLANK
