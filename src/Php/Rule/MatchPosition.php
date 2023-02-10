<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class MatchPosition implements TokenRule
{
    use TokenRuleTrait;

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

        while ($current && $current !== $arms->ClosedBy) {
            if (($current = $current->nextSiblingOf(T_DOUBLE_ARROW)) &&
                    ($current = $current->nextSiblingOf(','))) {
                $current->WhitespaceAfter |= WhitespaceType::LINE;
            }
        }
    }
}
