<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

class MatchPosition implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 600;
    }

    public function getTokenTypes(): ?array
    {
        return [
            T_MATCH,
        ];
    }

    public function processToken(Token $token): void
    {
        $arms    = $token->nextSibling(2);
        $current = $arms->nextCode();

        if ($current === $arms->ClosedBy) {
            return;
        }

        while (!$current->isNull()) {
            if (!($current = $current->nextSiblingOf(T_DOUBLE_ARROW))->isNull() &&
                    !($current = $current->nextSiblingOf(T[',']))->isNull()) {
                $current->WhitespaceAfter |= WhitespaceType::LINE;
            }
        }
    }
}
