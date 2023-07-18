<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Alias/import statement sort orders
 *
 * Comparison is always case-insensitive and locale-independent.
 *
 * @extends Enumeration<int>
 */
final class ImportSortOrder extends Enumeration
{
    /**
     * Do not sort imports
     *
     */
    public const NONE = 0;

    /**
     * Order by name
     *
     * Grouped imports appear after ungrouped imports. Example:
     *
     * ```php
     * use A;
     * use A\B;
     * use A\B\C;
     * use A\B\{D, E};
     * ```
     */
    public const NAME = 1;

    /**
     * Order by name, depth-first
     *
     * Grouped imports appear before ungrouped imports. Example:
     *
     * ```php
     * use A\B\{D, E};
     * use A\B\C;
     * use A\B;
     * use A;
     * ```
     */
    public const DEPTH = 2;
}
