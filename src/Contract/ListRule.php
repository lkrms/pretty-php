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
     * Apply the rule to a list containing one or more items
     *
     * If `$parent` is a `T_ATTRIBUTE`, `T_OPEN_BRACKET` or `T_OPEN_PARENTHESIS`
     * token, at least one item is passed to this method.
     *
     * Otherwise, `$parent` is a `T_EXTENDS` or `T_IMPLEMENTS` token with at
     * least two subsequent interface names.
     *
     * Each token in `$items` is the first code token after `$parent` or a
     * delimiter.
     *
     * This method is not called for empty lists or for classes that extend or
     * implement fewer than two interfaces.
     */
    public function processList(Token $parent, TokenCollection $items): void;
}
