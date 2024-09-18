# Import sorting

Alias/import statements are grouped and sorted in one of the following ways.
[Depth-first sorting by name][depth] is enabled by default.

## No sorting

Imports are grouped by type without sorting.

Enabled when `--sort-imports-by none` is given.

```php
<?php
use B\C\F as FF;
use B\C\F\{H as HH, K};
use B\C\F\I;
use B\C\F\H as HHH;
use B\C\F\G;
use B\D;
use B\C\F\{H, J};
use A;

use function S\T\U\F\F;

use const B\C\F\H\A as AA;
use const B\C\E;
```

## Sort by name

Imports are grouped by type and sorted alphabetically.

Enabled when `--sort-imports-by name` is given.

```php
<?php
use A;
use B\C\E;
use B\C\F as FF;
use B\C\F\G;
use B\C\F\{H, J};
use B\C\F\{H as HH, K};
use B\C\F\H as HHH;
use B\C\F\H\A as AA;
use B\C\F\I;
use B\D;
use S\T\U\F\F;
```

## Sort by name, depth-first

Imports are grouped by type and sorted alphabetically, depth-first.

Enabled by default and when `--sort-imports-by depth` is given.

```php
<?php
use B\C\F\H\A as AA;
use B\C\F\{H, J};
use B\C\F\{H as HH, K};
use B\C\F\G;
use B\C\F\H as HHH;
use B\C\F\I;
use B\C\E;
use B\C\F as FF;
use B\D;
use S\T\U\F\F;
use A;
```

## No grouping or sorting

Enabled when `--no-sort-imports` or `--disable sort-imports` are given.

```php
<?php
use B\C\F as FF;
use function S\T\U\F\F;
use B\C\F\{H as HH, K};
use const B\C\F\H\A as AA;
use const B\C\E;
use B\C\F\I;
use B\C\F\H as HHH;
use B\C\F\G;
use B\D;
use B\C\F\{H, J};
use A;
```

[depth]: #sort-by-name-depth-first
