<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;

/**
 * @api
 *
 * @phpstan-require-implements TokenRule
 */
trait TokenRuleTrait
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return ['*'];
    }
}
