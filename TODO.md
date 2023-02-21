# TODO

- [ ] Add `-d, --diff` option to fail with a diff when formatting differs
- [ ] Formalise support for PSR-1, PSR-12 and PSR-4
- [ ] Audit calls to `Token::prev()` vs. `Token::prevCode()` and `Token::next()` vs. `Token::nextCode()`
- [ ] Audit calls to `Token` methods that throw an exception if not called on a code token
- [ ] Improve performance
  - Resolve expensive values once per `Token`, before applying rules:
    - [x] Statements
    - [x] Expressions
    - [ ] `isTernaryOperator()`
    - [ ] Others?
  - [ ] Use `public` properties instead of method calls where possible
- [ ] Reimplement `Token::$Log` using object comparison after deprecating the (slow) `__get()` / `__set()` method
- [ ] Document "hanging" vs "overhanging" indentation

  > `.OH.` is applied if:
  >
  > - the opening bracket of the block is not followed by a newline, AND
  > - either:
  >   - the block contains `,`- or `;`- delimited items, OR
  >   - the block forms part of a structure that continues, e.g.
  >
  >     ```php
  >     if ($block) {
  >         // continuation
  >     }
  >     ```
  >
  >     ```
  >     1. Standard indentation is sufficient
  >
  >     [
  >         ___, ___,
  >         ___, ___
  >     ]
  >
  >     2. One level of hanging indentation is required
  >
  >     [
  >         ___, ___
  >         .hh.___, ___,
  >         ___, ___
  >     ]
  >
  >     3. One level of hanging indentation is sufficient
  >
  >     [___, ___,
  >     .hh.___, ___]
  >
  >     4. Two levels of hanging indentation are required
  >
  >     [___, ___
  >     .hh..OH.___, ___,
  >     .hh.___, ___]
  >
  >     5a. Two levels of hanging indentation are required per level of nesting
  >
  >     [___, [___,
  >     .hh..OH..hh.___],
  >     .hh.___,[___, ___
  >     .hh..OH..hh..OH.___,
  >     .hh..OH..hh.___]]
  >
  >     5b.
  >
  >     [[[___
  >     .hh..OH..hh..OH..hh..OH.___,
  >     .hh..OH..hh..OH..hh.___],
  >     .hh..OH..hh.___],
  >     .hh.___]
  >     ```

## Formatting

- [ ] Allow anonymous `function` arguments to break over multiple lines
- [ ] Match indentation of `?>` tags with their respective `<?php` tags
- [ ] Sort `use <FQCN>` blocks
- [ ] Extend one-line statement checks to the end of multi-block control structures, e.g.

      ```php
      // No newline after `c();`
      if ($a) c(); else b();
      ```
- [ ] Align one-line switch cases, e.g.

      ```php
      switch ($operator) {
        default:
        case '=':
        case '==':  return $retrieved == $value;
        case '!=':
        case '<>':  return $retrieved != $value;
        case '<':   return $retrieved < $value;
        case '>':   return $retrieved > $value;
        case '<=':  return $retrieved <= $value;
        case '>=':  return $retrieved >= $value;
        case '===': return $retrieved === $value;
        case '!==': return $retrieved !== $value;
        case '<=>': return $retrieved <=> $value;
      }
      ```

## Review rules

- [ ] AddBlankLineBeforeDeclaration
- [ ] AddBlankLineBeforeReturn
- [ ] AddEssentialWhitespace
- [ ] AddHangingIndentation
- [ ] AddIndentation
- [x] AddStandardWhitespace
- [ ] AlignAssignments
- [ ] AlignChainedCalls
- [ ] AlignComments
- [ ] AlignLists
- [ ] BracePosition
- [x] BreakAfterSeparators
- [x] BreakBeforeControlStructureBody
- [x] BreakBetweenMultiLineItems
- [ ] CommaCommaComma
- [ ] DeclareArgumentsOnOneLine
- [ ] MatchPosition
- [ ] PlaceAttributes
- [ ] PlaceComments
- [ ] PreserveNewlines
- [ ] PreserveOneLineStatements
- [x] ProtectStrings
- [x] ReindentHeredocs
- [ ] ReportUnnecessaryParentheses
- [x] SimplifyStrings
- [x] SpaceOperators
- [ ] SwitchPosition
- Extra
  - [ ] AddSpaceAfterFn
  - [ ] AddSpaceAfterNot
  - [x] BreakBeforeMultiLineList
  - [ ] SuppressSpaceAroundStringOperator

