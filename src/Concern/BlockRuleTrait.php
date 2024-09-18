<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\BlockRule;

/**
 * @phpstan-require-implements BlockRule
 */
trait BlockRuleTrait
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}
}
