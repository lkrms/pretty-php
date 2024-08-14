<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Contract\ListRule;

/**
 * @phpstan-require-implements ListRule
 */
trait ListRuleTrait
{
    use RuleTrait;

    public function beforeRender(array $tokens): void {}
}
