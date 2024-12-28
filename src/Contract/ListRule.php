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
     * If `$parent` is a `T_OPEN_PARENTHESIS`, `T_OPEN_BRACKET`, `T_OPEN_BRACE`
     * or `T_ATTRIBUTE` token, `$items` has at least one item.
     *
     * Otherwise, `$parent` is a `T_EXTENDS`, `T_IMPLEMENTS`, `T_CONST`,
     * `T_USE`, `T_INSTEADOF`, `T_STATIC`, `T_GLOBAL` or modifier token, and
     * `$items` has at least two items.
     *
     * Each token in `$items` is the first code token after `$parent` or a
     * delimiter.
     *
     * This method is not called for empty lists.
     */
    public function processList(Token $parent, TokenCollection $items, Token $lastChild): void;
}
