<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * @phpstan-require-implements TokenRule
 */
trait TokenRuleTrait
{
    use RuleTrait;

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return ['*'];
    }

    public static function getRequiresSortedTokens(): bool
    {
        return true;
    }

    public function beforeRender(array $tokens): void {}
}
