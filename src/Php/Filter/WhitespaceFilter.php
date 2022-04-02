<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\PhpTokenType;

class WhitespaceFilter
{
    public function __invoke($token)
    {
        return !(is_array($token) && in_array($token[0], PhpTokenType::WHITESPACE));
    }
}

