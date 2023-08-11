<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

trait ListRuleTrait
{
    use RuleTrait;

    public function beforeRender(array $tokens): void {}
}
