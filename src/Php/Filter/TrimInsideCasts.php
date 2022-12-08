<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\TokenType;

class TrimInsideCasts implements TokenFilter
{
    public function __invoke(&$token): bool
    {
        if (!is_array($token) || !in_array($token[0], TokenType::CAST)) {
            return true;
        }

        $token[1] = '(' . trim(substr($token[1], 1, -1)) . ')';

        return true;
    }
}
