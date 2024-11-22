<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;
use Salient\Utility\Arr;
use Salient\Utility\Regex;

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
 * @api
 */
final class DeclarationSpacing implements DeclarationRule
{
    use DeclarationRuleTrait;

    private const EXPANDABLE_TAG = '@(?:phan-|psalm-|phpstan-)?(?:api|internal|method|property(?:-read|-write)?|param|return|throws|(?:(?i)inheritDoc))(?=\s|$)';

    private bool $SortImportsEnabled;

    /**
     * [ Token index => [ token, type, modifiers, tight, tightOneLine, hasDocComment, hasDocCommentOrBlankLineBefore, isMultiLine ] ]
     *
     * @var array<int,array{Token,int,int[],bool,bool,bool|null,bool|null,bool|null}>
     */
    private array $Declarations;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_DECLARATIONS => 620,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getDeclarationTypes(array $all): array
    {
        // Ignore promoted constructor parameters and property hooks
        return [
            Type::HOOK => false,
            Type::PARAM => false,
        ] + $all;
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
    public function processDeclarations(array $declarations): void
    {
        $this->Declarations = [];

        foreach ($declarations as $token) {
            $type = $token->Data[TokenData::NAMED_DECLARATION_TYPE];

            // Apply the same formatting to imports and trait insertion, and
            // don't separate `use`, `use function` and `use constant` if
            // imports are not being sorted
            if (
                $type === Type::USE_TRAIT
                || (
                    !$this->SortImportsEnabled && (
                        $type === Type::USE_FUNCTION
                        || $type === Type::USE_CONST
                    )
                )
            ) {
                $type = Type::_USE;
            }

            $parts = $token->Data[TokenData::NAMED_DECLARATION_PARTS];

            $modifiers = [];
            $modifier = $parts->getFirstFrom($this->Idx->Visibility);
            if ($modifier) {
                $modifiers[] = $modifier->id;
            }
            foreach ([\T_ABSTRACT, \T_FINAL, \T_READONLY, \T_STATIC, \T_VAR] as $id) {
                $modifier = $parts->getFirstOf($id);
                if ($modifier) {
                    $modifiers[] = $modifier->id;
                }
            }

            $this->Declarations[$token->Index] = [
                $token,
                $type,
                $modifiers,
                $parts->hasOneFrom($this->Idx->SuppressBlankBetween),
                $parts->hasOneFrom($this->Idx->SuppressBlankBetweenOneLine),
                null,
                null,
                null,
            ];
        }

        $declarations = $this->Declarations;
        while ($declarations) {
            [$token, $type, $modifiers, $tight, $tightOneLine] = reset($declarations);
            // array_shift() can't be used here because it doesn't preserve keys
            unset($declarations[$token->Index]);

            $group = [$prevModifiers = $modifiers];
            $alwaysExpand = null;
            $collapse = [];

            if (!($this->hasDocComment($token) || $this->isMultiLine($token))) {
                $collapse[] = $token;
            }

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

                unset($declarations[$token->Index]);

                $prevIsMultiLine = false;
                $masked = false;

                // Suppress blank lines between `SuppressBlankBetween`
                // declarations, even if they break over multiple lines, and
                // between one-line `SuppressBlankBetweenOneLine` declarations
                if ($tight || (
                    $tightOneLine
                    && !$this->isMultiLine($prev)
                    && !$this->isMultiLine($token)
                )) {
                    $prevEnd->collect($token)->maskWhitespaceBefore(~WhitespaceType::BLANK);
                    $expand = false;
                    $masked = true;
                } elseif (
                    // Apply unconditional "loose" spacing to multi-line
                    // declarations and their successors
                    $this->hasDocComment($token)
                    || $this->isMultiLine($token)
                    || ($prevIsMultiLine = $this->hasDocComment($prev)
                        || $this->isMultiLine($prev))
                ) {
                    $expand = true;
                    if ($prevIsMultiLine) {
                        $collapse[] = $token;
                    }
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
                        || !$this->isGroupedByModifier($token, $type, $group)
                    ) {
                        $alwaysExpand = $expand;
                    } elseif ($expand) {
                        $collapse[] = $token;
                    }
                } else {
                    $expand = $alwaysExpand;
                }

                if (!$expand && (
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
                    (
                        $modifiers !== $prevModifiers
                        && !$this->Formatter->TightDeclarationSpacing
                        && $this->hasDocComment($token, true)
                        && $this->isGroupedByModifier($token, $type, $group)
                    )
                    // If there are non-code tokens other than one DocBlock
                    // between declarations, add a blank line for consistency
                    || $this->hasNewlineSince($token, $prevEnd)
                )) {
                    $expand = true;
                    $collapse[] = $token;
                }

                if ($expand) {
                    $this->maybeApplyBlankLineBefore($token, true);
                    $group = [$prevModifiers = $modifiers];
                    continue;
                }

                $group[] = $prevModifiers = $modifiers;

                $token->WhitespaceBefore |= WhitespaceType::LINE;

                // Collapse DocBlocks and suppress blank lines before DocBlocks
                // above tightly-spaced declarations
                $this->maybeCollapseComment($token);
                if ($masked) {
                    continue;
                }
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                if ($token->Prev && $token->Prev->id === \T_DOC_COMMENT) {
                    $token->Prev->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                }
            }

            // Add a blank line between declarations and subsequent statements
            // or comments
            if ($prevEnd && $prevEnd->Next && $prevEnd->Next->id !== \T_CLOSE_TAG && (
                $nextNotDeclaration || !($prevEnd->Next->Flags & TokenFlag::CODE)
            )) {
                $prevEnd->WhitespaceAfter |= WhitespaceType::BLANK;
            }

            if ($alwaysExpand) {
                continue;
            }

            foreach ($collapse as $token) {
                if (
                    $this->Formatter->TightDeclarationSpacing
                    || $alwaysExpand === false
                    || (
                        // Collapse standalone DocBlocks if they were originally
                        // collapsed
                        $token->Prev
                        && $token->Prev->id === \T_DOC_COMMENT
                        && strpos($token->Prev->OriginalText ?? $token->Prev->text, "\n") === false
                    )
                ) {
                    $this->maybeCollapseComment($token);
                }
            }
        }
    }

    /**
     * Check if $token and any subsequent tightly-spaced declarations of $type
     * have modifiers mutually exclusive with $group
     *
     * @param non-empty-array<int[]> $group
     */
    private function isGroupedByModifier(Token $token, int $type, array $group): bool
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
                || $this->isMultiLine($token)
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
        return $this->Declarations[$token->Index][$orBlankLineBefore ? 6 : 5] ??=
            $this->doHasDocComment($token, $orBlankLineBefore);
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
                && !Regex::match(
                    '/^' . self::EXPANDABLE_TAG . '/',
                    $prev->Data[TokenData::COMMENT_CONTENT],
                )
            ) || (
                // Check for comments that are not collapsible because they have
                // already been collapsed
                !$prev->hasNewline()
                && $prev->text[4] === '@'
                && !Regex::match(
                    '/^\/\*\* ' . self::EXPANDABLE_TAG . '/',
                    $prev->text,
                )
            )
        ) || (
            $orBlankLineBefore && $prev->hasBlankLineBefore()
        );
    }

    private function isMultiLine(Token $token): bool
    {
        return $this->Declarations[$token->Index][7] ??=
            $token->EndStatement
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
            && !$this->Formatter->ExpandHeaders
            && $token->OpenTag
            && $token->OpenTag->NextCode === $token
        ) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            return;
        }

        $token->applyBlankLineBefore($withMask);
    }
}
