<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Normalise whitespace between declarations
 *
 * With sensible exceptions, this rule:
 *
 * - Adds a blank line before declarations that span multiple lines
 * - Suppresses blank lines between declarations in
 *   {@see TokenType::DECLARATION_CONDENSE}
 * - Between subsequent one-line declarations of the same kind, propagates the
 *   gap between the first and second statements to subsequent statements
 *
 * For formatting purposes, the following constructs are treated as
 * declarations, and a declaration is comprised of every token in a declaration
 * statement, including any attributes, modifiers and statements (e.g.
 * `function` bodies).
 *
 * - `declare` (`T_DECLARE`)
 * - `namespace` (`T_NAMESPACE`)
 * - `class` (`T_CLASS`)
 * - `enum` (`T_ENUM`)
 * - `interface` (`T_INTERFACE`)
 * - `trait` (`T_TRAIT`)
 * - `function` (`T_FUNCTION`)): not including anonymous functions
 * - `const` (`T_CONST`)
 * - `public|protected|private`: when declaring a property
 * - `use` (`T_USE`): not including `use` in anonymous functions
 * - `global` (`T_GLOBAL`)
 * - `static` (`T_STATIC`): when declaring a `static` variable
 * - `var` (`T_VAR`)
 *
 * @api
 */
final class DeclarationSpacing implements MultiTokenRule
{
    use MultiTokenRuleTrait;

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
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 620;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return TokenType::DECLARATION;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore declaration tokens other than the first in each statement
            // after adjusting for any preceding attributes
            while ($token->Statement !== $token) {
                if (!$token->_prevSibling ||
                    !($token->_prevSibling->id === T_ATTRIBUTE ||
                        $token->_prevSibling->id === T_ATTRIBUTE_COMMENT)) {
                    continue 2;
                }
                $token = $token->_prevSibling;
            }

            // Ignore `static` outside of declarations, `namespace` in the
            // context of relative names, and promoted constructor parameters
            if (($token->id === T_STATIC &&
                        !$token->_nextCode->is([T_VARIABLE, ...TokenType::DECLARATION])) ||
                    ($token->id === T_NAMESPACE &&
                        $token->_nextCode->id === T_NS_SEPARATOR) ||
                    ($token->is(TokenType::VISIBILITY) && $token->inParameterList())) {
                continue;
            }

            $parts = $token->withNextSiblingsWhile(...TokenType::DECLARATION_PART);

            // Ignore anonymous functions
            if ($parts->last()->id === T_FUNCTION) {
                continue;
            }

            // Add a blank line between declarations and subsequent
            // non-declarations
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
                    $token->OpenTag->_nextCode !== $token;

            // If the same DECLARATION_UNIQUE tokens appear in consecutive
            // one-line statements, propagate the gap between statements 1 and 2
            // to subsequent statements
            $types = $parts->getAnyOf(...TokenType::DECLARATION_UNIQUE)
                           ->getTypes();
            // Allow `$types` to be empty if this is a property declaration
            if (!$types && !$parts->hasOneOf(...TokenType::VISIBILITY)) {
                if ($blank) {
                    $token->applyBlankLineBefore();
                } else {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                }

                continue;
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

                continue;
            }

            // Suppress blank lines between DECLARATION_CONDENSE statements,
            // multi-line or otherwise
            if ($count < 3) {
                $this->PrevCondense = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE);
            }
            if ($this->PrevCondense) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                continue;
            }

            // Always propagate blank lines to the third statement and beyond,
            // but don't suppress them if there's a change of visibility, e.g.
            // preserve the blank line before the `private const` here:
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

                continue;
            }

            $token->WhitespaceBefore |= WhitespaceType::LINE;

            if ($count > 2) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            }
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
