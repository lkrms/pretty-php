<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Normalise whitespace between declarations
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

    public function getTokenTypes(): array
    {
        return TokenType::DECLARATION;
    }

    public function processToken(Token $token): void
    {
        // Excluding `use` after `function ()` is unnecessary because it never
        // appears mid-statement
        if ($token->Statement->skipAnySiblingsOf(T_ATTRIBUTE, T_ATTRIBUTE_COMMENT) !== $token ||
                ($token->id === T_STATIC &&
                    !$token->_nextCode->is([T_VARIABLE, ...TokenType::DECLARATION])) ||
                ($token->id === T_NAMESPACE && $token->_nextCode->id === T_NS_SEPARATOR) ||
                // For formatting purposes, promoted constructor parameters
                // aren't declarations
                ($token->is(TokenType::VISIBILITY) && $token->inFunctionDeclaration())) {
            return;
        }

        $token = $token->Statement;
        $parts = $token->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
        $last = $parts->last();
        // Leave anonymous functions alone
        if ($last->id === T_FUNCTION) {
            return;
        }

        // Add blank lines between declarations and subsequent non-declarations
        if (!$token->EndStatement->nextCode()->skipAnySiblingsOf(
            T_ATTRIBUTE, T_ATTRIBUTE_COMMENT
        )->is([T_NULL, ...TokenType::DECLARATION]) &&
                $token->EndStatement->next()->id !== T_CLOSE_TAG) {
            $token->EndStatement->WhitespaceAfter |= WhitespaceType::BLANK;
        }

        // Don't add blank lines between `<?php` and subsequent declarations
        // unless strict PSR-12 compliance is enabled
        $blank =
            $this->Formatter->Psr12Compliance ||
                $token->OpenTag->nextCode() !== $token;

        // If the same DECLARATION_UNIQUE tokens appear in consecutive one-line
        // statements, propagate the gap between statements 1 and 2 to
        // subsequent statements
        $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                       ->getTypes();
        // Allow `$types` to be empty if this is a property declaration
        if (!$types && !$parts->hasOneOf(...TokenType::VISIBILITY)) {
            if ($blank) {
                $token->applyBlankLineBefore();
            } else {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
            }

            return;
        }
        $prev = end($this->Prev);
        if (!($types === $this->PrevTypes &&
                $token->prevCode()->startOfStatement() === $prev)) {
            $this->Prev = [];
            $this->PrevTypes = $types;
            $this->PrevExpand = $this->hasComment($token);
        }
        $this->Prev[] = $token;
        $count = count($this->Prev);
        // Always add a blank line above the first declaration of each type
        if ($count < 2) {
            if ($blank) {
                $token->applyBlankLineBefore();
            } else {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
            }

            return;
        }

        // Suppress blank lines between DECLARATION_CONDENSE statements,
        // multi-line or otherwise
        if ($count < 3) {
            $this->PrevCondense = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE);
        }
        if ($this->PrevCondense) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
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
        if (!$this->PrevExpand &&
            ($parts->getFirstOf(...TokenType::VISIBILITY)->id ?? null) !==
                ($prev->withNextSiblingsWhile(...TokenType::DECLARATION_PART)
                      ->getFirstOf(...TokenType::VISIBILITY)
                      ->id ?? null)) {
            $this->Prev = [$token];
            $count = 1;
        }

        $expand = $this->PrevExpand ||
            $this->hasComment($token) ||
            $token->collect($token->EndStatement)->hasNewline() ||
            ($count > 1 &&
                ($prev->collect($token->prev())->hasNewline() ||
                    ($count < 3 && $token->hasBlankLineBefore())));

        if ($expand) {
            if (!$this->PrevExpand) {
                if (!$this->hasComment($token)) {
                    array_walk(
                        $this->Prev,
                        fn(Token $t) =>
                            $t->applyBlankLineBefore(true)
                    );
                }
                $this->PrevExpand = true;
            } else {
                $token->applyBlankLineBefore(true);
            }

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::LINE;

        if ($count > 2) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
        }
    }

    private function hasComment(Token $token): bool
    {
        return ($prev = $token->_prev) &&
            $prev->CommentType &&
            $prev->hasNewlineBefore() &&
            !$prev->hasBlankLineAfter();
    }

    public function reset(): void
    {
        $this->Prev = [];
        $this->PrevTypes = [];
        $this->PrevCondense = false;
        $this->PrevExpand = false;
    }
}
