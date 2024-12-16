# Indentation

## Heredoc indentation

Heredocs and nowdocs are indented in one of four ways. [Mixed
indentation][mixed] is enabled by default.

### No indentation

Enabled when `--heredoc-indent none` is given.

```php
<?php
$foo = [
    'bar' => <<<EOF
Content
EOF,
];
```

### Line indentation

Enabled when `--heredoc-indent line` is given.

```php
<?php
$foo = [
    'bar' => <<<EOF
    Content
    EOF,
];
```

### Mixed indentation

Line indentation is applied to heredocs that start on their own line, otherwise
hanging indentation is applied.

Enabled by default and when `--heredoc-indent mixed` is given.

```php
<?php
$foo = <<<EOF
    Content
    EOF;
$bar =
    <<<EOF
    Content
    EOF;
```

### Hanging indentation

Enabled when `--heredoc-indent hanging` is given.

```php
<?php
$foo = <<<EOF
    Content
    EOF;
$bar =
    <<<EOF
        Content
        EOF;
```

## Standard indentation

Code between brackets (`()`, `[]`, `{}`) is always indented when the open
bracket is followed by a line break.

### Comments

The `PlaceComments` rule aligns comments with subsequent code unless the next
code token is a close bracket or switch case:

```php
<?php
switch ($foo) {
    //
    case 0:
    case 1:
        //
        bar();
        // Indented
    case 2:
        // Indented
    case 3:
        baz();
        break;

        // Indented

    case 4:
        qux();
        break;

        // Indented

    //
    case 5:
        quux();
        break;

    //
    default:
        break;
}
```

## Hanging indentation

Hanging indentation is applied when the context of a line of code is unclear
after standard indentation has been applied.

Consider this contrived example:

```php
<?php
if (!$request->route()->isPrivate() ||
        ($request->hasUser() &&
            ($this->userHasRoute($request->user(),
                    $request->route()) ||
                $request->user()->role() === User::ADMIN_ROLE ||
                $request->user()->role() === User::EDITOR_ROLE)) ||
        Session::hasCarteBlanche()) {
    Console::log('Access granted');
}
```

Without `pretty-php`'s "overhanging" indentation, more effort is required to
understand how it works because the same level of indentation has been applied
to adjacent lines that belong to different contexts:

```php
<?php
if (!$request->route()->isPrivate() ||
    ($request->hasUser() &&
        ($this->userHasRoute($request->user(),
            $request->route()) ||
        $request->user()->role() === User::ADMIN_ROLE ||
        $request->user()->role() === User::EDITOR_ROLE)) ||
    Session::hasCarteBlanche()) {
    Console::log('Access granted');
}
```

With no hanging indentation at all, even more effort is required:

```php
<?php
if (!$request->route()->isPrivate() ||
($request->hasUser() &&
($this->userHasRoute($request->user(),
$request->route()) ||
$request->user()->role() === User::ADMIN_ROLE ||
$request->user()->role() === User::EDITOR_ROLE)) ||
Session::hasCarteBlanche()) {
    Console::log('Access granted');
}
```

One solution (adopted in [PSR-12][] / [PER][]) is to insert line breaks after
open brackets. In many cases this is sensible, but it doesn't cover every PHP
construct and isn't always desirable.

```php
<?php
if (
    !$request->route()->isPrivate() ||
    (
        $request->hasUser() &&
        (
            $this->userHasRoute(
                $request->user(),
                $request->route()
            ) ||
            $request->user()->role() === User::ADMIN_ROLE ||
            $request->user()->role() === User::EDITOR_ROLE
        )
    ) ||
    Session::hasCarteBlanche()
) {
    Console::log('Access granted');
}
```

### How `HangingIndentation` works

Using arrays as a metaphor for constructs with one or more entries that may be
arbitrarily nested, the `HangingIndentation` rule recognises the following
indentation scenarios.

> `.hh.` represents a level of hanging indentation, and `.HH.` represents a
> level of "overhanging" indentation.

1. Standard indentation is sufficient

   ```
   [
       ___, ___,
       ___, ___
   ];
   ```

2. One level of hanging indentation is required

   ```
   [
       ___, ___
       .hh.___, ___,
       ___, ___
   ];
   ```

3. One level of hanging indentation is sufficient

   ```
   [___, ___,
   .hh.___, ___];
   ```

4. Two levels of hanging indentation are required

   ```
   [___, ___
   .hh..HH.___, ___,
   .hh.___, ___];
   ```

5. Two levels of hanging indentation are required per level of nesting

   ```
   [___, [___,
   .hh..HH..hh.___],
   .hh.___,[___, ___
   .hh..HH..hh..HH.___,
   .hh..HH..hh.___]];
   ```

   ```
   [[[___
   .hh..HH..hh..HH..hh..HH.___,
   .hh..HH..hh..HH..hh.___],
   .hh..HH..hh.___],
   .hh.___]
   ```

Overhanging indentation is also applied to blocks that form part of a continuing
structure, e.g. the [`if` block above](#hanging-indentation).

#### Context

After finding a token to indent, `HangingIndentation` creates a context for it,
and if indentation for that context has already been applied, the token is not
indented further.

A token's context is comprised of its parent token (or `null` if it's a
top-level token), and an optional anchor token shared by any siblings that
should receive the same level of indentation.

The aim is to differentiate between lines where a new expression starts, and
lines where an expression continues:

```php
$iterator = new RecursiveDirectoryIterator($dir,
    FilesystemIterator::KEY_AS_PATHNAME |
        FilesystemIterator::CURRENT_AS_FILEINFO |
        FilesystemIterator::SKIP_DOTS);
```

```php
return is_string($contents)
    ? $contents
    : json_encode($contents, JSON_PRETTY_PRINT);
```

```php
fn($a, $b) =>
    $a === $b
        ? 0
        : $a <=>
            $b;
```

[mixed]: #mixed-indentation
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PER]: https://www.php-fig.org/per/coding-style/
