# PSR-12

This document describes what happens when strict [PSR-12] / [PER Coding Style] compliance is enforced with `--psr12`,
and explains how (and why) `PrettyPHP`'s default style is not quite 100% compliant.

## Rules

Enabled:

- `StrictLists`
- `SortImports`
- `DeclarationSpacing`

Suppressed:

- `AlignLists`
- `PreserveOneLineStatements`

## Behaviours

The following behaviours apply only when strict PSR-12 compliance is enabled.

### Declare statements

With or without a semicolon after the closing parenthesis, the following is collapsed to one line as per [PSR-12,
section 3]:

```php
<?php declare(strict_types=1) ?>
```

Otherwise, header blocks are formatted as prescribed:

```php
<?php

/**
 * Header
 */

declare(strict_types=1);

namespace Vendor\Package;
```

> *PrettyPHP*'s default style departs from the standard to collapse an opening `declare` statement as follows:
>
> ```php
> <?php declare(strict_types=1);
>
> /**
>  * Header
>  */
>
> namespace Vendor\Package;
> ```

### Heredocs and nowdocs

Newlines before heredocs and nowdocs are suppressed, and unconditional heredoc indentation is enforced as per [PER
Coding Style 2.0, section 10]:

```php
<?php
$foo = [
    'bar',
    <<<EOF
        Content
        EOF,
];
$baz = <<<EOF
    Content
    EOF;
```

> *PrettyPHP*'s default style does not apply indentation to heredocs in contexts where there is no ambiguity without it:
>
> ```php
> $foo = <<<EOF
>     Content
>     EOF;
>
> $baz =
>     <<<EOF
>     Content
>     EOF;
> ```

## Lists

`StrictLists` unconditionally adds a newline after the opening bracket of vertical lists.


[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-12, section 3]: https://www.php-fig.org/psr/psr-12/#3-declare-statements-namespace-and-import-statements
[PER Coding Style]: https://www.php-fig.org/per/coding-style/
[PER Coding Style 2.0, section 10]: https://www.php-fig.org/per/coding-style/#10-heredoc-and-nowdoc
