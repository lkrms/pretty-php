<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

trait BlockRuleTrait
{
    use RuleTrait;

    public function afterBlockLoop(): void
    {
    }
}
