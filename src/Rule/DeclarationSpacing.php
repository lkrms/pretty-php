<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Str;

/**
 * Normalise vertical spacing between declarations
 *
 * With sensible exceptions, this rule:
 *
 * - Adds a blank line before the first declaration of each type
 * - Adds a blank line before declarations that break over multiple lines or
 *   have a multi-line DocBlock that cannot be collapsed
 * - Adds a blank line between declarations and subsequent statements or
 *   comments
 * - Suppresses blank lines between declarations in
 *   {@see TokenTypeIndex::$SuppressBlankBetween}
 * - Suppresses blank lines between one-line declarations in
 *   {@see TokenTypeIndex::$SuppressBlankBetweenOneLine}
 * - Normalises consecutive one-line declarations of the same type by
 *   propagating the gap between the first and second declarations
 * - Collapses DocBlocks as needed
 *
 * Properties, variables and constants are formatted as one-line declarations
 * unless they have one or more attributes, even if a multi-line value is
 * applied to them.
 *
 * @api
 */
final class DeclarationSpacing implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    private bool $SortImportsEnabled;

    /**
     * [ Token index => [ token, type, modifiers, tight, tightOneLine, hasDocComment, hasDocCommentOrBlankLineBefore, isMultiLine ] ]
     *
     * @var array<int,array{Token,int[],int[],bool,bool,bool|null,bool|null,bool|null}>
     */
    private array $Declarations;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 620;

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
            ...TokenType::DECLARATION,
        ];
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->SortImportsEnabled = $this->Formatter->Enabled[SortImports::class] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        unset($this->Declarations);
    }

    /**
     * @inheritDoc
     */
    public function processTokens(array $tokens): void
    {
        $this->Declarations = [];

        foreach ($tokens as $token) {
            // Ignore tokens other than the first in each declaration
            if ($token->Statement !== $token) {
                continue;
            }

            if (!$token->isNamedDeclaration($parts)) {
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

            $modifiers = [];
            $modifier = $parts->getFirstFrom($this->TypeIndex->Visibility);
            if ($modifier) {
                $modifiers[] = $modifier->id;
            }
            foreach ([\T_ABSTRACT, \T_FINAL, \T_GLOBAL, \T_READONLY, \T_STATIC, \T_VAR] as $id) {
                $modifier = $parts->getFirstOf($id);
                if ($modifier) {
                    $modifiers[] = $modifier->id;
                }
            }

            $this->Declarations[$token->Index] = [
                $token,
                $type,
                $modifiers,
                $parts->hasOneFrom($this->TypeIndex->SuppressBlankBetween),
                $parts->hasOneFrom($this->TypeIndex->SuppressBlankBetweenOneLine),
                null,
                null,
                null,
            ];
        }

        $declarations = $this->Declarations;
        while ($declarations) {
            [$token, $type, $modifiers, $tight, $tightOneLine] = reset($declarations);
            unset($declarations[$token->Index]);

            $assignable = $type === [] || $type === [\T_CONST];
            $group = [$prevModifiers = $modifiers];
            $count = 1;
            $expand = false;
            $alwaysExpand = null;

            // Add a blank line before the first declaration of each type
            $this->maybeApplyBlankLineBefore($token);

            $nextNotDeclaration = false;
            while (($prevEnd = ($prev = $token)->EndStatement) && ($token = $prevEnd->NextSibling)) {
                if (!isset($this->Declarations[$token->Index])) {
                    $nextNotDeclaration = true;
                    break;
                }
                [, $nextType, $modifiers] = $declarations[$token->Index];
                if ($nextType !== $type) {
                    break;
                }

                $prevExpand = $expand;
                $expandOnce = false;
                $masked = false;

                // Suppress blank lines between `SuppressBlankBetween`
                // declarations, even if they break over multiple lines, and
                // between one-line `SuppressBlankBetweenOneLine` declarations
                if ($tight || (
                    $tightOneLine
                    && !$this->isMultiLine($prev, $assignable)
                    && !$this->isMultiLine($token, $assignable)
                )) {
                    $prevEnd->collect($token)->maskWhitespaceBefore(~WhitespaceType::BLANK);
                    $expand = false;
                    $masked = true;
                } elseif (
                    // Apply unconditional "loose" spacing to multi-line
                    // declarations and their successors
                    $this->hasDocComment($token)
                    || $this->isMultiLine($token, $assignable)
                    || $this->hasDocComment($prev)
                    || $this->isMultiLine($prev, $assignable)
                ) {
                    $expand = true;
                } elseif ($alwaysExpand === null) {
                    $expand = !$this->Formatter->TightDeclarationSpacing
                        && $this->hasDocComment($token, true);
                    // Propagate the gap between the first and second one-line
                    // declarations to subsequent one-line declarations unless
                    // they have different modifiers, e.g.:
                    //
                    // ```php
                    // public const A = 0;
                    //
                    // private const B = 1;
                    // private const C = 2;
                    // ```
                    if (
                        !$expand
                        || $modifiers === $prevModifiers
                        || !$this->isGroupedByModifier($token, $type, $assignable, $group)
                    ) {
                        $alwaysExpand = $expand;
                    }
                } else {
                    $expand = $alwaysExpand;
                }

                // Don't suppress blank lines between declarations with
                // different modifiers, e.g. preserve the blank line before
                // `private const` here:
                //
                // ```php
                // public const A = 0;
                // public const B = 1;
                //
                // private const C = 2;
                // ```
                if (
                    !$expand
                    && $modifiers !== $prevModifiers
                    && !$this->Formatter->TightDeclarationSpacing
                    && $this->hasDocComment($token, true)
                    && $this->isGroupedByModifier($token, $type, $assignable, $group)
                ) {
                    $expandOnce = true;
                }

                unset($declarations[$token->Index]);
                $count++;

                if (
                    $expand
                    || $expandOnce
                    // If there are non-code tokens other than one DocBlock
                    // between declarations, add a blank line for consistency
                    || $this->hasNewlineSince($token, $prevEnd)
                ) {
                    $this->maybeApplyBlankLineBefore($token, true);
                    $group = [$prevModifiers = $modifiers];
                    continue;
                }

                $group[] = $prevModifiers = $modifiers;

                $token->WhitespaceBefore |= WhitespaceType::LINE;

                // Collapse DocBlocks and suppress blank lines before DocBlocks
                // above tightly-spaced declarations
                if ($count === 2 || $prevExpand) {
                    $this->maybeCollapseComment($prev);
                }
                $this->maybeCollapseComment($token);
                if ($masked) {
                    continue;
                }
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                if ($token->Prev && $token->Prev->id === \T_DOC_COMMENT) {
                    $token->Prev->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                }
            }

            if (
                $count === 1
                && ($this->Formatter->TightDeclarationSpacing || (
                    // Collapse standalone DocBlocks if they were originally
                    // collapsed
                    $prev->Prev
                    && $prev->Prev->id === \T_DOC_COMMENT
                    && strpos($prev->Prev->OriginalText ?? $prev->Prev->text, "\n") === false
                ))
                && !$this->hasDocComment($prev)
                && !$this->isMultiLine($prev, $assignable)
            ) {
                $this->maybeCollapseComment($prev);
            }

            // Add a blank line between declarations and subsequent statements
            // or comments
            if ($prevEnd && $prevEnd->Next && $prevEnd->Next->id !== \T_CLOSE_TAG && (
                $nextNotDeclaration || !$prevEnd->Next->IsCode
            )) {
                $prevEnd->WhitespaceAfter |= WhitespaceType::BLANK;
            }
        }
    }

    /**
     * Check if $token and any subsequent tightly-spaced declarations of $type
     * have modifiers mutually exclusive with $group
     *
     * @param int[] $type
     * @param non-empty-array<int[]> $group
     */
    private function isGroupedByModifier(Token $token, array $type, bool $assignable, array $group): bool
    {
        $groups = [Arr::unique($group)];
        $group = null;
        do {
            if (!isset($this->Declarations[$token->Index])) {
                break;
            }
            [, $nextType, $modifiers] = $this->Declarations[$token->Index];
            if (
                $nextType !== $type
                || $this->hasDocComment($token)
                || $this->isMultiLine($token, $assignable)
            ) {
                break;
            }
            if ($this->hasDocComment($token, true) || (
                $token->PrevSibling
                && $this->hasNewlineSince($token, $token->PrevSibling)
            )) {
                if ($group !== null) {
                    break;
                }
                $group = [$modifiers];
            } elseif ($group !== null) {
                $group[] = $modifiers;
            } else {
                return false;
            }
        } while ($token->EndStatement && ($token = $token->EndStatement->NextSibling));

        if ($group !== null) {
            $groups[] = Arr::unique($group);
        }

        do {
            $group = array_shift($groups);
            if (!$groups) {
                return true;
            }
            $others = array_merge(...$groups);
            if (array_udiff($group, $others, fn($a, $b) => $a <=> $b) !== $group) {
                return false;
            }
        } while (true);
    }

    private function hasDocComment(Token $token, bool $orBlankLineBefore = false): bool
    {
        return $this->Declarations[$token->Index][$orBlankLineBefore ? 6 : 5]
            ??= $this->doHasDocComment($token, $orBlankLineBefore);
    }

    private function doHasDocComment(Token $token, bool $orBlankLineBefore): bool
    {
        /** @var Token */
        $prev = $token->Prev;
        if ($prev->id !== \T_DOC_COMMENT) {
            return $orBlankLineBefore && $token->hasBlankLineBefore();
        }
        if (!$prev->hasNewlineBefore() || $prev->hasBlankLineAfter()) {
            return false;
        }
        return !(
            (
                $prev->Flags & TokenFlag::COLLAPSIBLE_COMMENT
                && ($prev->Data[TokenData::COMMENT_CONTENT][0] ?? null) === '@'
                && Str::lower($prev->Data[TokenData::COMMENT_CONTENT]) !== '@inheritdoc'
            ) || (
                // Check for comments that are not collapsible because they have
                // already been collapsed
                !$prev->hasNewline()
                && $prev->text[4] === '@'
                && Str::lower($prev->text) !== '/** @inheritdoc */'
            )
        ) || (
            $orBlankLineBefore && $prev->hasBlankLineBefore()
        );
    }

    private function isMultiLine(Token $token, bool $assignable): bool
    {
        return $this->Declarations[$token->Index][7]
            ??= $this->doIsMultiLine($token, $assignable);
    }

    private function doIsMultiLine(Token $token, bool $assignable): bool
    {
        return (!$assignable || $this->TypeIndex->Attribute[$token->id])
            && $token->EndStatement
            && $token->collect($token->EndStatement)->hasNewline();
    }

    private function hasNewlineSince(Token $token, Token $since): bool
    {
        /** @var Token */
        $tokenPrev = $token->Prev;
        if ($tokenPrev->id === \T_DOC_COMMENT) {
            /** @var Token */
            $tokenPrev = $tokenPrev->Prev;
        }
        return $since->collect($tokenPrev)->hasNewline();
    }

    private function maybeCollapseComment(Token $token): void
    {
        /** @var Token */
        $prev = $token->Prev;
        if (
            $prev->id === \T_DOC_COMMENT
            && $prev->Flags & TokenFlag::COLLAPSIBLE_COMMENT
        ) {
            $prev->setText('/** ' . $prev->Data[TokenData::COMMENT_CONTENT] . ' */');
        }
    }

    private function maybeApplyBlankLineBefore(Token $token, bool $withMask = false): void
    {
        $this->Declarations[$token->Index][5] = null;
        $this->Declarations[$token->Index][6] = null;

        if (
            !$this->Formatter->Psr12
            && $token->OpenTag
            && $token->OpenTag->NextCode === $token
        ) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            return;
        }
        $token->applyBlankLineBefore($withMask);
    }
}
