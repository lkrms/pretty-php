<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Alias/import statement sort orders
 *
 * Comparison is always case-insensitive and locale-independent.
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class ImportSortOrder extends AbstractEnumeration
{
    /**
     * Do not sort imports
     */
    public const NONE = 0;

    /**
     * Order imports by name
     *
     * Grouped imports receive no special treatment.
     *
     * Example:
     *
     * ```php
     * <?php
     * use A;
     * use B\C\E;
     * use B\C\F\G;
     * use B\C\F\{H, I};
     * use B\C\F\J;
     * use B\D;
     * ```
     */
    public const NAME = 1;

    /**
     * Order imports by name, depth-first
     *
     * Grouped imports appear before ungrouped imports.
     *
     * Example:
     *
     * ```php
     * <?php
     * use B\C\F\{H, I};
     * use B\C\F\G;
     * use B\C\F\J;
     * use B\C\E;
     * use B\D;
     * use A;
     * ```
     */
    public const DEPTH = 2;
}
