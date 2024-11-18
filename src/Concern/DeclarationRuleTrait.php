<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\DeclarationRule;

/**
 * @api
 *
 * @phpstan-require-implements DeclarationRule
 */
trait DeclarationRuleTrait
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}
}
