<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Alias/import statement sort orders
 *
 * @api
 */
interface ImportSortOrder
{
    /**
     * Do not sort imports
     */
    public const NONE = 0;

    /**
     * Sort imports by name
     */
    public const NAME = 1;

    /**
     * Sort imports by name, depth-first
     */
    public const DEPTH = 2;
}
