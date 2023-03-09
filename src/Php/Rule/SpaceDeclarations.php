<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
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

    /**
     * @var Token[]
     */
    private $Prev = [];

    /**
     * @var int[]
     */
    private $PrevTypes = [];

    /**
     * @var bool
     */
    private $PrevCondense = false;

    /**
     * @var bool
     */
    private $PrevExpand = false;

    public function getPriority(string $method): ?int
    {
        return 620;
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
                ($token->is(TokenType::VISIBILITY) && $token->inFunctionDeclaration())) {
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

        // Don't add blank lines between `<?php` and declarations
        $lineType = $token->OpenTag->nextCode() === $token
            ? WhitespaceType::LINE
            : WhitespaceType::BLANK;

        // If the same DECLARATION_UNIQUE tokens appear in consecutive one-line
        // statements, propagate the gap between statements 1 and 2 to
        // subsequent statements
        $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                       ->getTypes();
        if (!$types) {
            $token->WhitespaceBefore |= $lineType;

            return;
        }
        $prev = end($this->Prev);
        if (!($types === $this->PrevTypes &&
                $token->prevCode()->startOfStatement() === $prev)) {
            $this->Prev       = [];
            $this->PrevTypes  = $types;
            $this->PrevExpand = $this->hasComment($token);
        }
        $this->Prev[] = $token;
        $count        = count($this->Prev);
        // Always add a blank line above the first declaration of each type
        if ($count < 2) {
            $token->WhitespaceBefore |= $lineType;

            return;
        }

        // Suppress blank lines between DECLARATION_CONDENSE statements,
        // multi-line or otherwise
        if ($count < 3) {
            $this->PrevCondense = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE);
        }
        if ($this->PrevCondense) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

            return;
        }

        // Always propagate blank lines to the third statement and beyond, but
        // don't suppress them if there's a change of visibility, e.g. preserve
        // the blank line before the `private const` here:
        //
        // ```php
        // public const A = 0;
        // public const B = 1;
        //
        // private const C = 2;
        // ```
        if ($count > 2 &&
            !$this->PrevExpand &&
            ($parts->getFirstOf(...TokenType::VISIBILITY)->id ?? null) !==
                ($prev->withNextSiblingsWhile(...TokenType::DECLARATION_PART)
                      ->getFirstOf(...TokenType::VISIBILITY)
                      ->id ?? null)) {
            $this->Prev = [$token];
            $count      = 1;
        }

        $expand = $this->PrevExpand ||
            $this->hasComment($token) ||
            $token->collect($token->endOfStatement())->hasNewline() ||
            ($count > 1 &&
                ($prev->collect($token->prev())->hasNewline() ||
                    ($count < 3 && $token->hasBlankLineBefore())));
        if ($expand) {
            if (!$this->PrevExpand) {
                if (!$this->hasComment($token)) {
                    array_walk(
                        $this->Prev,
                        function (Token $t) {
                            $t->WhitespaceBefore   |= WhitespaceType::BLANK;
                            $t->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                        }
                    );
                }
                $this->PrevExpand = true;
            } else {
                $token->WhitespaceBefore   |= WhitespaceType::BLANK;
                $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
            }

            return;
        }

        if ($count > 2) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
        }
    }

    private function hasComment(Token $token): bool
    {
        return ($comment = $token->prev())->is(TokenType::COMMENT) &&
            $comment->PinToCode;
    }
}
