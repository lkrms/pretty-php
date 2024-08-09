<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;

trait MultiTokenRuleTrait
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
