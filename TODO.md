# TODO

## General

- [ ] Audit calls to `Token::prev()`, `Token::prevCode()`, `Token::next()`, `Token::nextCode()`

- [ ] Audit calls to methods that throw an exception if not called on a code token:
  - [ ] `nextSibling()`
  - [ ] `prevSibling()`
  - [ ] `parentsWhile()`
  - [ ] `outer()`
  - [ ] `inner()`
  - [ ] `innerSiblings()`
  - [ ] `startOfStatement()`
  - [ ] `endOfStatement()`
  - [ ] `startOfExpression()`
  - [ ] `endOfExpression()`
  - [ ] `collectSiblings()`

## Formatting

### Required

- [x] **Preserve one-line case statements**, e.g.

    ```php
    case $value1: return something();
    case $value2: return somethingElse();
    ```

- [x] **Preserve blank lines between DocBlocks**
- [ ] Place labels on their own line (labels are comprised of `T_STRING` folled by `':'`)
- [ ] Suppress blank lines between consecutive `yield` statements
- [ ] Suppress blank line before `private` in `function __construct(private $var) {`
- [ ] Suppress blank line before `use function <FUNCTION>` in `use <FQCN>` block
- [ ] Improve handling of `use <trait>` directives
  - [ ] Move opening brace onto the same line as `use`
  - [ ] Condense consecutive directives (even multiline ones)
- [ ] Improve spacing in attributes (e.g. add space after `':'`)
- [ ] Review anonymous `class` formatting

### Optional

- [ ] Align on opening parentheses (maybe limited to `AlignedChainedCalls` contexts?), i.e.

    ```php
    someFunction($argument1,
                 $argument2);
    ```

- [ ] Sort `use <FQCN>` blocks
- [ ] Output UTF-8 characters when simplifying strings, i.e. `'—'` instead of `"\xe2\x80\x94"`

