<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeDeclaration implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf(...TokenType::DECLARATION) ||
                ($token->is(T_USE) && $token->prevCode()->is(')'))) {
            return;
        }
        /** @var Token $start */
        $start = $token->withPrevSiblingsWhile(...TokenType::DECLARATION_PART)->last();
        if ($start->Tags['StartOfDeclaration'] ?? null) {
            return;
        }
        $start->Tags['StartOfDeclaration'] = true;
        if ($start !== $start->startOfStatement()) {
            return;
        }
        $parts = $start->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        /** @var Token $last */
        $last  = $parts->last();
        if ($last->isOneOf(T_FN, T_FUNCTION) && $last->nextCode()->is('(')) {
            return;
        }

        // If the same DECLARATION_CONDENSE token types appear in this
        // statement as in the last one, don't add a blank line between them
        $types = $parts->getAnyOf(...TokenType::DECLARATION_CONDENSE)->getTypes();
        if ($types) {
            $block      = $start->collect($start->endOfStatement());
            $hasNewline = $block->hasOuterNewline();
            if (!$hasNewline) {
                $prev = $start->prevCode()
                              ->startOfStatement();
                $start->Tags['HasNoInnerNewline'] = true;
                if ($prev->declarationParts()->hasOneOf(...$types) &&
                        ($prev->Tags['HasNoInnerNewline'] ?? null)) {
                    $start->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                    return;
                }
            }
        }

        $start->WhitespaceBefore |= WhitespaceType::BLANK;
    }
}
