<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

trait BlockRuleTrait
{
    use RuleTrait;

    public function beforeRender(array $tokens): void {}
}
