<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
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
    private array $Prev = [];

    /**
     * @var int[]
     */
    private array $PrevTypes = [];

    private bool $PrevExpand = false;

    private bool $PrevCondense = false;

    private bool $PrevCondenseOneLine = false;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 620;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return TokenType::DECLARATION;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // After rewinding to the first attribute (if any), ignore tokens
            // other than the first in each declaration
            while ($token->Statement !== $token) {
                if (!$token->PrevSibling || !(
                    $token->PrevSibling->id === \T_ATTRIBUTE
                    || $token->PrevSibling->id === \T_ATTRIBUTE_COMMENT
                )) {
                    continue 2;
                }
                $token = $token->PrevSibling;
            }

            // Ignore `static` outside of declarations, `namespace` in the
            // context of relative names, and promoted constructor parameters
            if ((
                $token->id === \T_STATIC
                && !$token->NextCode->is([\T_VARIABLE, ...TokenType::DECLARATION])
            ) || (
                $token->id === \T_NAMESPACE
                && $token->NextCode->id === \T_NS_SEPARATOR
            ) || (
                $token->is(TokenType::VISIBILITY)
                && $token->inParameterList()
            )) {
                continue;
            }

            $parts = $token->namedDeclarationParts(false);

            // Ignore anonymous functions
            if (!$parts->count()) {
                continue;
            }

            // Add a blank line between declarations and subsequent
            // non-declarations
            if (!$token->EndStatement->nextCode()->skipSiblingsOf(
                \T_ATTRIBUTE, \T_ATTRIBUTE_COMMENT
            )->is([\T_NULL, ...TokenType::DECLARATION])
                    && $token->EndStatement->next()->id !== \T_CLOSE_TAG) {
                $token->EndStatement->WhitespaceAfter |= WhitespaceType::BLANK;
            }

            // If the same DECLARATION_EXCEPT_MODIFIERS tokens appear in
            // consecutive one-line statements, propagate the gap between
            // statements 1 and 2 to subsequent statements
            $types = $parts->getAnyOf(...TokenType::DECLARATION_EXCEPT_MODIFIERS)
                           ->getTypes();

            // Allow `$types` to be empty if this is a variable or property
            // declaration
            if (!$types && !$parts->hasOneOf(
                \T_GLOBAL, \T_READONLY, \T_STATIC, \T_VAR, ...TokenType::VISIBILITY
            )) {
                $this->maybeApplyBlankLineBefore($token);
                continue;
            }

            // Don't separate `use`, `use function` and `use constant` if
            // imports are not being sorted
            if (!($this->Formatter->Enabled[SortImports::class] ?? false) && (
                $types === [\T_USE, \T_FUNCTION]
                || $types === [\T_USE, \T_CONST]
            )) {
                $types = [\T_USE];
            }

            // - `$this->PrevTypes` contains the `$types` of the most recent
            //   declaration
            // - `$this->Prev` contains the most recent declarations of the
            //   current `$types` and is reset whenever a declaration with
            //   different `$types` is encountered
            // - `$this->PrevExpand` is `true` when blank lines are being
            //   applied before declarations of the current `$types`
            // - `$this->PrevCondense` is `true` when blank lines before
            //   declarations of the current `$types` are being suppressed
            // - `$this->PrevCondenseOneLine` is `true` when blank lines before
            //   declarations of the current `$types` are being suppressed
            //   unless they have inner newlines
            $prev = $this->Prev
                ? end($this->Prev)
                : null;
            $prevSibling = $token->PrevCode
                ? $token->PrevCode->Statement
                : null;

            if ($types !== $this->PrevTypes || !$prev || $prevSibling !== $prev) {
                $this->Prev = [];
                if (
                    $prevSibling
                    && $prevSibling !== $prev
                    && $this->uniqueDeclarationTypes($prevSibling) === $types
                ) {
                    $this->Prev[] = $prevSibling;
                }

                $this->PrevTypes = $types;
                $this->PrevExpand =
                    $this->hasComment($token)
                    || ($this->Prev
                        && ($this->hasComment($prevSibling)
                            || $prevSibling->hasBlankLineBefore()
                            || $prevSibling->collect($prevSibling->EndStatement)->hasNewline()));
            }
            $this->Prev[] = $token;
            $count = count($this->Prev);

            // Always add a blank line above the first declaration of each type
            if ($count < 2) {
                $this->maybeApplyBlankLineBefore($token);
                continue;
            }

            // Suppress blank lines between DECLARATION_CONDENSE statements,
            // multi-line or otherwise, and between one-line
            // DECLARATION_CONDENSE_ONE_LINE statements, comments or not
            if ($count === 2) {
                $this->PrevCondense = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE);
                $this->PrevCondenseOneLine = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE_ONE_LINE);
            }
            if ($this->PrevCondense
                || ($this->PrevCondenseOneLine
                    && !$prev->collect($prev->EndStatement)->hasNewline()
                    && !$token->collect($token->EndStatement)->hasNewline())) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
                $prev->EndStatement->collect($token)->maskWhitespaceBefore(~WhitespaceType::BLANK);
                continue;
            }

            // Always propagate blank lines to the third statement and beyond,
            // but don't suppress them if there's a modifier change, e.g.
            // preserve the blank line before the `private const` here:
            //
            // ```php
            // public const A = 0;
            // public const B = 1;
            //
            // private const C = 2;
            // ```
            if (!$this->PrevExpand) {
                $prevParts = $prev->declarationParts(false, false);
                if (($parts->getFirstOf(...TokenType::VISIBILITY)->id ?? null) !== ($prevParts->getFirstOf(...TokenType::VISIBILITY)->id ?? null)
                        || ($parts->getFirstOf(\T_ABSTRACT)->id ?? null) !== ($prevParts->getFirstOf(\T_ABSTRACT)->id ?? null)
                        || ($parts->getFirstOf(\T_FINAL)->id ?? null) !== ($prevParts->getFirstOf(\T_FINAL)->id ?? null)
                        || ($parts->getFirstOf(\T_GLOBAL)->id ?? null) !== ($prevParts->getFirstOf(\T_GLOBAL)->id ?? null)
                        || ($parts->getFirstOf(\T_READONLY)->id ?? null) !== ($prevParts->getFirstOf(\T_READONLY)->id ?? null)
                        || ($parts->getFirstOf(\T_STATIC)->id ?? null) !== ($prevParts->getFirstOf(\T_STATIC)->id ?? null)
                        || ($parts->getFirstOf(\T_VAR)->id ?? null) !== ($prevParts->getFirstOf(\T_VAR)->id ?? null)) {
                    $this->Prev = [$token];
                    $count = 1;
                }
            }

            $expand = $this->PrevExpand
                || $token->collect($token->EndStatement)->hasNewline()
                || $prev->collect($token->Prev)->hasNewline()
                || (!$this->PrevCondenseOneLine
                    && ($this->hasComment($token)
                        || ($count === 2 && $token->hasBlankLineBefore())));

            if ($expand) {
                if (!$this->PrevExpand && !$this->PrevCondenseOneLine) {
                    if (!$this->hasComment($token)) {
                        foreach ($this->Prev as $t) {
                            $this->maybeApplyBlankLineBefore($t, true);
                        }
                    } else {
                        $token->applyBlankLineBefore(true);
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

    /**
     * @return int[]|null `null` if there is no declaration at `$token`, or if
     * the declaration at `$token` does not contain any
     * {@see TokenType::DECLARATION_EXCEPT_MODIFIERS} tokens and is not a
     * variable or property declaration.
     */
    private function uniqueDeclarationTypes(Token $token): ?array
    {
        $parts = $token->declarationParts(false, false);

        if (!$parts->count()
                || !$parts->hasOneOf(...TokenType::DECLARATION)) {
            return null;
        }

        $types = $parts->getAnyOf(...TokenType::DECLARATION_EXCEPT_MODIFIERS)
                       ->getTypes();

        if (!$types && !$parts->hasOneOf(
            \T_GLOBAL, \T_READONLY, \T_STATIC, \T_VAR, ...TokenType::VISIBILITY
        )) {
            return null;
        }

        return $types;
    }

    private function maybeApplyBlankLineBefore(Token $token, bool $withMask = false): void
    {
        if ($token->OpenTag->NextCode === $token
                && !$this->Formatter->Psr12) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            return;
        }
        $token->applyBlankLineBefore($withMask);
    }

    private function hasComment(Token $token): bool
    {
        return ($prev = $token->Prev)
            && $prev->CommentType
            && $prev->hasNewlineBefore()
            && !$prev->hasBlankLineAfter();
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Prev = [];
        $this->PrevTypes = [];
        $this->PrevCondense = false;
        $this->PrevExpand = false;
    }
}
