<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class MatchPosition implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->is(T_MATCH)) {
            $arms    = $token->nextSibling(2);
            $current = $arms->nextCode();

            while ($current && $current !== $arms->ClosedBy) {
                if (($current = $current->nextSiblingOf(T_DOUBLE_ARROW)) &&
                    ($current = $current->nextSiblingOf(','))) {
                    $current->WhitespaceAfter |= WhitespaceType::LINE;
                }
            }

            return;
        }
    }
}
