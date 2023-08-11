<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

trait TokenRuleTrait
{
    use RuleTrait;

    public function getTokenTypes(): array
    {
        return ['*'];
    }

    public function getRequiresSortedTokens(): bool
    {
        return true;
    }

    public function beforeRender(array $tokens): void {}
}
