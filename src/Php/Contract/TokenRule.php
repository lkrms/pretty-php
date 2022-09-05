<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;

interface TokenRule
{
    public function __invoke(Token $token): void;

}
