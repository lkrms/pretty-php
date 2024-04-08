<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
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
 * declarations, and a declaration includes any applicable attributes, modifiers
 * and statements.
 *
 * - `declare` (`T_DECLARE`)
 * - `namespace` (`T_NAMESPACE`)
 * - `class` (`T_CLASS`)
 * - `enum` (`T_ENUM`)
 * - `interface` (`T_INTERFACE`)
 * - `trait` (`T_TRAIT`)
 * - `function` (`T_FUNCTION`)): not including anonymous functions
 * - `case` (`T_CASE`): in enumerations
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

    private bool $SortImportsEnabled;

    /**
     * @var array<int,bool>
     */
    private array $VariableOrDeclarationIndex;

    /**
     * @var array<int,bool>
     */
    private array $AttributeIndex;

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
        return [
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
            ...TokenType::DECLARATION,
        ];
    }

    public function boot(): void
    {
        $this->SortImportsEnabled = isset(
            $this->Formatter->Enabled[SortImports::class]
        );

        $this->VariableOrDeclarationIndex = TokenType::getIndex(
            \T_VARIABLE,
            ...TokenType::DECLARATION,
        );

        $this->AttributeIndex = TokenType::getIndex(
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
        );
    }

    public function processTokens(array $tokens): void
    {
        /** @var Token[] */
        $current = [];
        /** @var int[] */
        $currentType = [];
        /** @var Token|null */
        $last = null;
        $currentExpand = false;
        $currentCondense = false;
        $currentCondenseOneLine = false;

        foreach ($tokens as $token) {
            // Ignore tokens other than the first in each declaration
            if ($token->Statement !== $token) {
                continue;
            }

            // Get the first non-attribute
            $first = $token->skipSiblingsFrom($this->AttributeIndex);

            // Ignore:
            // - `static` outside declarations
            // - `case` in switch statements
            // - `namespace` in relative names
            // - promoted constructor parameters
            if (
                ($first->id === \T_STATIC && !($first->NextCode && $this->VariableOrDeclarationIndex[$first->NextCode->id]))
                || ($first->id === \T_CASE && $first->inSwitchCaseList())
                || ($first->id === \T_NAMESPACE && $first->NextCode && $first->NextCode->id === \T_NS_SEPARATOR)
                || ($this->TypeIndex->VisibilityWithReadonly[$first->id] && $first->inParameterList())
            ) {
                continue;
            }

            $parts = $token->namedDeclarationParts();

            // Ignore anonymous functions and classes
            if (!$parts->count()) {
                continue;
            }

            $type = $parts->getAnyFrom($this->TypeIndex->DeclarationExceptModifiers)
                          ->getTypes();

            // Ignore declarations with no apparent type unless they are
            // property or variable declarations
            if (!$type && !$parts->hasOneFrom($this->TypeIndex->DeclarationPropertyOrVariable)) {
                continue;
            }

            // Don't separate `use`, `use function` and `use constant` if
            // imports are not being sorted
            if (!$this->SortImportsEnabled && (
                $type === [\T_USE, \T_FUNCTION] || $type === [\T_USE, \T_CONST]
            )) {
                $type = [\T_USE];
            }

            $token->Flags |= TokenFlag::NAMED_DECLARATION;

            // Add a blank line between declarations and subsequent
            // non-declarations
            assert($token->EndStatement !== null);
            $next = $token->EndStatement->nextCode()->skipSiblingsFrom($this->AttributeIndex)->orNull();
            if (
                $next
                && !$this->TypeIndex->Declaration[$next->id]
                && $token->EndStatement->Next
                && $token->EndStatement->Next->id !== \T_CLOSE_TAG
            ) {
                $token->EndStatement->WhitespaceAfter |= WhitespaceType::BLANK;
            }

            // If one-line declarations of the same type appear consecutively,
            // propagate the gap between the first and second statements to
            // subsequent statements

            // - `$currentType` contains the `$type` of the last declaration
            // - `$current` contains declarations of the current `$type` and is
            //   reset when one of the following is encountered:
            //   - a declaration of a different `$type`
            //   - code that is not a declaration
            // - `$last` contains the last declaration in `$current`
            // - `$this->PrevExpand` is `true` when blank lines are being
            //   applied before declarations of the current `$type`
            // - `$this->PrevCondense` is `true` when blank lines before
            //   declarations of the current `$type` are being suppressed
            // - `$this->PrevCondenseOneLine` is `true` when blank lines before
            //   declarations of the current `$type` are being suppressed unless
            //   they have inner newlines
            $prev = $last;
            $prevSibling = $token->PrevCode ? $token->PrevCode->Statement : null;

            if (!$prev || $prevSibling !== $prev || $type !== $currentType) {
                $current = [];
                // If `$prevSibling` is a declaration of the same type (possible
                // if it is a parent of `$prev`), add it to `$current`
                if (
                    $prevSibling
                    && $prevSibling !== $prev
                    && $this->getDeclarationType($prevSibling) === $type
                ) {
                    $current[] = $prevSibling;
                }

                $currentType = $type;
                if ($this->hasComment($token)) {
                    $currentExpand = true;
                } elseif ($current) {
                    assert($prevSibling !== null);
                    assert($prevSibling->EndStatement !== null);
                    $currentExpand = $this->hasComment($prevSibling)
                        || $prevSibling->hasBlankLineBefore()
                        || $prevSibling->collect($prevSibling->EndStatement)->hasNewline();
                } else {
                    $currentExpand = false;
                }
            }
            $current[] = $token;
            $last = $token;
            $count = count($current);

            // Always add a blank line above the first declaration of each type
            if ($count < 2) {
                $this->maybeApplyBlankLineBefore($token);
                continue;
            }

            assert($prev !== null);
            assert($prev->EndStatement !== null);

            // Suppress blank lines between DECLARATION_CONDENSE statements,
            // multi-line or otherwise, and between one-line
            // DECLARATION_CONDENSE_ONE_LINE statements, comments or not
            if ($count === 2) {
                $currentCondense = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE);
                $currentCondenseOneLine = $parts->hasOneOf(...TokenType::DECLARATION_CONDENSE_ONE_LINE);
            }
            if ($currentCondense
                || ($currentCondenseOneLine
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
            if (!$currentExpand) {
                $prevParts = $prev->namedDeclarationParts();
                if (($parts->getFirstOf(...TokenType::VISIBILITY)->id ?? null) !== ($prevParts->getFirstOf(...TokenType::VISIBILITY)->id ?? null)
                        || ($parts->getFirstOf(\T_ABSTRACT)->id ?? null) !== ($prevParts->getFirstOf(\T_ABSTRACT)->id ?? null)
                        || ($parts->getFirstOf(\T_FINAL)->id ?? null) !== ($prevParts->getFirstOf(\T_FINAL)->id ?? null)
                        || ($parts->getFirstOf(\T_GLOBAL)->id ?? null) !== ($prevParts->getFirstOf(\T_GLOBAL)->id ?? null)
                        || ($parts->getFirstOf(\T_READONLY)->id ?? null) !== ($prevParts->getFirstOf(\T_READONLY)->id ?? null)
                        || ($parts->getFirstOf(\T_STATIC)->id ?? null) !== ($prevParts->getFirstOf(\T_STATIC)->id ?? null)
                        || ($parts->getFirstOf(\T_VAR)->id ?? null) !== ($prevParts->getFirstOf(\T_VAR)->id ?? null)) {
                    $current = [$token];
                    $count = 1;
                }
            }

            assert($token->Prev !== null);
            $expand = $currentExpand
                || $token->collect($token->EndStatement)->hasNewline()
                || $prev->collect($this->getWithoutDocComment($token->Prev))->hasNewline()
                || (!$currentCondenseOneLine
                    && ($this->hasComment($token)
                        || ($count === 2 && $token->hasBlankLineBefore())));

            if ($expand) {
                if (!$currentExpand && !$currentCondenseOneLine) {
                    if (!$this->hasComment($token)) {
                        foreach ($current as $t) {
                            $this->maybeApplyBlankLineBefore($t, true);
                        }
                    } else {
                        $token->applyBlankLineBefore(true);
                    }
                    $currentExpand = true;
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
     * @return int[]|null
     */
    private function getDeclarationType(Token $token): ?array
    {
        if (!($token->Flags & TokenFlag::NAMED_DECLARATION)) {
            return null;
        }

        $type = $token->namedDeclarationParts()
                      ->getAnyFrom($this->TypeIndex->DeclarationExceptModifiers)
                      ->getTypes();

        if (!$this->SortImportsEnabled && (
            $type === [\T_USE, \T_FUNCTION] || $type === [\T_USE, \T_CONST]
        )) {
            return [\T_USE];
        }

        return $type;
    }

    private function getWithoutDocComment(Token $token): Token
    {
        if ($token->id === \T_DOC_COMMENT) {
            /** @var Token */
            return $token->Prev;
        }
        return $token;
    }

    private function hasComment(Token $token): bool
    {
        $prev = $token->Prev;
        return $prev
            && $this->TypeIndex->Comment[$prev->id]
            && ($prev->id !== \T_DOC_COMMENT || $prev->hasNewline() || $prev->text[4] !== '@' || $prev->hasBlankLineBefore())
            && $prev->hasNewlineBefore()
            && !$prev->hasBlankLineAfter();
    }

    private function maybeApplyBlankLineBefore(Token $token, bool $withMask = false): void
    {
        assert($token->OpenTag !== null);
        if ($token->OpenTag->NextCode === $token
                && !$this->Formatter->Psr12) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            return;
        }
        $token->applyBlankLineBefore($withMask);
    }
}
