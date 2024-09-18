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

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return ['*'];
    }

    /**
     * @inheritDoc
     */
    public static function getRequiresSortedTokens(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}
}
