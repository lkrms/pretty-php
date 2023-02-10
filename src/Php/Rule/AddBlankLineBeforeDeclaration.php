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

    public function getTokenTypes(): ?array
    {
        return TokenType::DECLARATION;
    }

    public function processToken(Token $token): void
    {
        if (($token->is(T_USE) && $token->prevCode()->is(')')) ||
                ($token->isOneOf(...TokenType::VISIBILITY) && $token->inFunctionDeclaration())) {
            return;
        }

        /** @var Token $start */
        $start = $token->withPrevSiblingsWhile(...TokenType::DECLARATION_PART)->last();
        if ($start->IsStartOfDeclaration) {
            return;
        }
        $start->IsStartOfDeclaration = true;
        if ($start !== $start->startOfStatement()) {
            return;
        }
        $parts = $start->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        /** @var Token $last */
        $last  = $parts->last();
        if ($last->isOneOf(T_FN, T_FUNCTION) && $last->nextCode()->is('(')) {
            return;
        }

        // If the same DECLARATION_CONDENSE token types appear in this statement
        // as in the last one, don't add a blank line between them
        if (($types = $parts->getAnyOf(...TokenType::DECLARATION_CONDENSE)->getTypes()) &&
            ($prev = $start->prevCode()->startOfStatement())->declarationParts()->hasOneOf(...$types) &&
            // `use` statements are always condensed, otherwise this and the
            // previous statement can't have newlines
            ($parts->hasOneOf(T_USE) ||
                (!$start->collect($start->endOfStatement())->hasOuterNewline() &&
                    !$prev->collect($start->prev())->hasOuterNewline()))) {
            $start->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

            return;
        }

        $start->WhitespaceBefore |= WhitespaceType::BLANK;
    }
}
