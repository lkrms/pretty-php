<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

class PhpSkipFilter
{
    public function __invoke($token)
    {
        return $token[0] != T_WHITESPACE;
    }
}

