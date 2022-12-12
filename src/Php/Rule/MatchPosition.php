<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class MatchPosition extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
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
