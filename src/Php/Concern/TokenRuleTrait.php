<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

trait TokenRuleTrait
{
    use RuleTrait;

    public function getTokenTypes(): ?array
    {
        return null;
    }
}
