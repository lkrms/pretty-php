<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply symmetrical vertical whitespace to brackets
 *
 */
final class MirrorBrackets implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 96;
    }

    public function getTokenTypes(): array
    {
        return [
            T[':'],
            T['('],
            T['['],
            T['{'],
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        ];
    }

    public function processToken(Token $token): void
    {
        if (!$this->Formatter->MirrorBrackets ||
                !$token->ClosedBy) {
            return;
        }

        $this->mirrorBracket($token);
    }
}
