<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concept;

use Lkrms\Pretty\Php\Contract\TokenRule;

abstract class AbstractTokenRule implements TokenRule
{
    public function getReverseTokens(): bool
    {
        return false;
    }
}
