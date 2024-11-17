<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;

/**
 * @api
 */
interface ListRule extends Rule
{
    public const PROCESS_LIST = 'processList';

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * If `$parent` is a `T_OPEN_PARENTHESIS`, `T_OPEN_BRACKET` or `T_ATTRIBUTE`
     * token, `$items` has at least one item.
     *
     * Otherwise, `$parent` is a `T_EXTENDS` or `T_IMPLEMENTS` token, and
     * `$items` has at least two items.
     *
     * Each token in `$items` is the first code token after `$parent` or a
     * delimiter.
     *
     * This method is not called for empty lists or for classes that extend or
     * implement fewer than two interfaces.
     */
    public function processList(Token $parent, TokenCollection $items): void;
}
