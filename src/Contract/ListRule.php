<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenCollection;

interface ListRule extends Rule
{
    public const PROCESS_LIST = 'processList';

    /**
     * Apply the rule to a list containing one or more items
     *
     */
    public function processList(Token $owner, TokenCollection $items): void;
}
