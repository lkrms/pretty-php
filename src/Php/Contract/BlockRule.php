<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\TokenCollection;

interface BlockRule
{
    /**
     * @param TokenCollection[] $block
     */
    public function __invoke(array $block): void;

}
