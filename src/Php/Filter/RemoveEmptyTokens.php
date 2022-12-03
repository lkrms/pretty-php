<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;

class RemoveEmptyTokens implements TokenFilter
{
    public function __invoke(&$token): bool
    {
        return !($token === "" || (is_array($token) && $token[1] === ""));
    }
}
