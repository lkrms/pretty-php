<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

interface ListRule extends Rule
{
    public const PROCESS_LIST = 'processList';

    /**
     * Apply the rule to a list containing one or more items
     *
     */
    public function processList(Token $owner, TokenCollection $items): void;
}
