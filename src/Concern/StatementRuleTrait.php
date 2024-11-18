<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\StatementRule;

/**
 * @api
 *
 * @phpstan-require-implements StatementRule
 */
trait StatementRuleTrait
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}
}
