<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add a blank line before declarations that span multiple lines, with some
 * exceptions
 *
 */
final class AddBlankLineBeforeDeclaration implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return $method === self::PROCESS_TOKEN
            ? 620
            : null;
    }

    public function getTokenTypes(): ?array
    {
        return TokenType::DECLARATION;
    }

    public function processToken(Token $token): void
    {
        if (($token->is(T_USE) && $token->prevCode()->is(T[')'])) ||
                ($token->is(TokenType::VISIBILITY) && $token->inFunctionDeclaration())) {
            return;
        }

        $start = $token->withPrevSiblingsWhile(...TokenType::DECLARATION_PART)->last();
        if ($start->IsStartOfDeclaration) {
            return;
        }
        $start->IsStartOfDeclaration = true;
        if ($start !== $start->startOfStatement()) {
            return;
        }
        $parts = $start->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        $last  = $parts->last();
        // Leave anonymous and arrow functions alone
        if ($last->is([T_FN, T_FUNCTION]) && $last->nextCode()->is(T['('])) {
            return;
        }

        // If the same DECLARATION_UNIQUE tokens appear in consecutive one-line
        // statements, don't force a blank line between them
        $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                       ->getTypes();
        $prev      = $start->prevCode()->startOfStatement();
        $prevParts = $prev->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        $prevTypes = $prevParts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                               ->getTypes();

        if ($types === $prevTypes) {
            // Suppress blank lines between DECLARATION_CONDENSE statements,
            // multi-line or otherwise
            if ($parts->hasOneOf(...TokenType::DECLARATION_CONDENSE)) {
                $start->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                return;
            }
            if (!$start->collect($start->endOfStatement())->hasOuterNewline() &&
                    !$prev->collect($start->prev())->hasOuterNewline()) {
                return;
            }
        }

        $start->WhitespaceBefore |= WhitespaceType::BLANK;
    }
}
