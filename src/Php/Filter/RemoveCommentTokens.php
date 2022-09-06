<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\TokenType;

class RemoveCommentTokens implements TokenFilter
{
    public function __invoke($token): bool
    {
        return !(is_array($token) && in_array($token[0], TokenType::COMMENT));
    }

}
