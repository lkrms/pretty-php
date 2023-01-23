# TODO

- [ ] Add `-d, --diff` option to fail with a diff when formatting differs
- [ ] Formalise support for PSR-1, PSR-12 and PSR-4
- [ ] Audit calls to `Token::prev()` vs. `Token::prevCode()` and `Token::next()` vs. `Token::nextCode()`
- [ ] Audit calls to `Token` methods that throw an exception if not called on a code token

## Formatting

- [ ] Allow anonymous `function` arguments to break over multiple lines
- [ ] Review anonymous `class` formatting
- [ ] Match indentation of `?>` tags with their respective `<?php` tags
- [ ] Sort `use <FQCN>` blocks
- [ ] Extend one-line statement checks to the end of multi-block control structures, e.g.

      ```php
      // No newline after `c();`
      if ($a) c(); else b();
      ```

