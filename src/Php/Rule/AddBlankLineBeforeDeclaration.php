<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeDeclaration extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->isOneOf(...TokenType::DECLARATION) ||
                ($token->is(T_USE) && $token->prevCode()->is(')'))) {
            return;
        }
        /** @var Token $start */
        $start = $token->prevSiblingsWhile(true, ...TokenType::DECLARATION_PART)->last();
        if ($start->Tags['StartOfDeclaration'] ?? null) {
            return;
        }
        $start->Tags['StartOfDeclaration'] = true;
        if ($start !== $start->startOfStatement()) {
            return;
        }
        $parts = $start->nextSiblingsWhile(true, ...TokenType::DECLARATION_PART);
        /** @var Token $last */
        $last  = $parts->last();
        if ($last->isOneOf(T_FN, T_FUNCTION)) {
            return;
        }

        // If the same DECLARATION_CONDENSE token types appear in this
        // statement as in the last one, don't add a blank line between them
        $types = $parts->getAnyOf(...TokenType::DECLARATION_CONDENSE)->getTypes();
        if ($types) {
            $block      = $start->collect($start->endOfStatement());
            $hasNewline = $block->hasInnerNewline();
            if (!$hasNewline) {
                [$prev, $start->Tags['HasNoInnerNewline']] = [
                    $start->prevCode()->startOfStatement(),
                    true,
                ];
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
