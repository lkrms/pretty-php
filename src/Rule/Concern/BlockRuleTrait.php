<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Contract\BlockRule;

/**
 * @phpstan-require-implements BlockRule
 */
trait BlockRuleTrait
{
    use RuleTrait;

    public function beforeRender(array $tokens): void {}
}
