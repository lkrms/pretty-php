<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;
use const Lkrms\Pretty\Php\T_NULL;

/**
 * Add, suppress or normalise blank lines before declarations
 *
 * With sensible exceptions:
 * - Add blank lines before declarations that span multiple lines
 * - Suppress blank lines between declarations in
 *   {@see TokenType::DECLARATION_CONDENSE}
 * - Between subsequent one-line declarations of the same type, propagate the
 *   gap between the first and second statements to subsequent statements
 *
 */
final class SpaceDeclarations implements TokenRule
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
        // Checking for `use` after `function ()` is unnecessary because it
        // never appears mid-statement
        if ($token->Statement !== $token ||
                // For formatting purposes, promoted constructor parameters
                // aren't declarations
                ($token->is(TokenType::VISIBILITY) && $token->inFunctionDeclaration()) ||
                $token->OpenTag->nextCode() === $token) {
            return;
        }

        $parts = $token->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        $last  = $parts->last();
        // Leave anonymous and arrow functions alone
        if ($last->is([T_FN, T_FUNCTION]) && $last->nextCode()->is(T['('])) {
            return;
        }

        // Add a blank line between declarations and other code
        if (!$token->EndStatement->nextCode()->is([T_NULL, ...TokenType::DECLARATION])) {
            $token->EndStatement->WhitespaceAfter |= WhitespaceType::BLANK;
        }

        // If the same DECLARATION_UNIQUE tokens appear in consecutive one-line
        // statements, propagate the gap between statements 1 and 2 to
        // subsequent statements
        $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                       ->getTypes();
        if ($types) {
            $prev = $this->maybeGetPrevDeclaration($token, $prevTypes, $prevParts);
            if ($types === $prevTypes) {
                // Suppress blank lines between DECLARATION_CONDENSE statements,
                // multi-line or otherwise
                if ($parts->hasOneOf(...TokenType::DECLARATION_CONDENSE)) {
                    $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                    return;
                }
                if (!$token->collect($token->endOfStatement())->hasOuterNewline() &&
                        !$prev->collect($token->prev())->hasOuterNewline()) {
                    $prev2 = $this->maybeGetPrevDeclaration($prev, $prev2Types);
                    if ($types === $prev2Types &&
                            !$prev2->collect($prev->prev())->hasOuterNewline()) {
                        // Always propagate blank lines to the third statement
                        // and beyond, but don't suppress them if there's a
                        // change of visibility, e.g. preserve the blank line
                        // before the `private const` here:
                        //
                        // ```php
                        // public const A = 0;
                        // public const B = 1;
                        //
                        // private const C = 2;
                        // ```
                        if ($prev->hasBlankLineBefore()) {
                            $token->WhitespaceBefore |= WhitespaceType::BLANK;
                        } elseif (($parts->getFirstOf(...TokenType::VISIBILITY)->id ?? null) ===
                                ($prevParts->getFirstOf(...TokenType::VISIBILITY)->id ?? null)) {
                            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                        }
                    }

                    return;
                }
            }
        }

        $token->WhitespaceBefore |= WhitespaceType::BLANK;
    }

    /**
     * @param array<int|string>|null $types
     */
    private function maybeGetPrevDeclaration(Token $token, ?array &$types = null, ?TokenCollection &$parts = null): ?Token
    {
        $prev  = $token->prevCode()->startOfStatement();
        $parts = $prev->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        if ($prev->IsNull || !count($parts)) {
            return $types = null;
        }
        $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                       ->getTypes();

        return $prev;
    }
}
