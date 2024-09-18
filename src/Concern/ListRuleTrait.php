<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\ListRule;

/**
 * @phpstan-require-implements ListRule
 */
trait ListRuleTrait
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}
}
