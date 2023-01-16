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

- [ ] Improve spacing in attributes (e.g. add space after `':'`)
- [ ] Add support for named arguments (e.g. `myFunction(paramName: $value)`, `array_foobar(array: $value)`)
- [ ] Allow anonymous `function` arguments to break over multiple lines
- [ ] Review anonymous `class` formatting
- [ ] Match indentation of `?>` tags with their respective `<?php` tags
- [ ] Sort `use <FQCN>` blocks

## Bugs

