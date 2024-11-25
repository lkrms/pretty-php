<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;
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
 * - Suppresses blank lines between `use` statements, one-line `declare`
 *   statements, and property hooks not declared over multiple lines
 * - Normalises consecutive one-line declarations of the same type by
 *   propagating the gap between the first and second declarations
 * - Collapses DocBlocks as needed
 *
 * @api
 */
final class DeclarationSpacing implements DeclarationRule
{
    use DeclarationRuleTrait;

    private const EXPANDABLE_TAG = '@(?:phan-|psalm-|phpstan-)?(?:api|internal|method|property(?:-read|-write)?|param|return|throws|template(?:-covariant|-contravariant)?|(?:(?i)inheritDoc))(?=\s|$)';

    private bool $SortImportsEnabled;

    /**
     * [ Token index => [ token, type, modifiers, hasDocComment, hasDocCommentOrBlankLineBefore, isMultiLine ] ]
     *
     * @var array<int,array{Token,int,int[],bool|null,bool|null,bool|null}>
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
        // Ignore promoted constructor parameters
        return [
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
        $this->Declarations = [];
    }

    /**
     * @inheritDoc
     */
    public function processDeclarations(array $declarations): void
    {
        foreach ($declarations as $token) {
            $type = $token->Data[TokenData::NAMED_DECLARATION_TYPE];
            /** @var TokenCollection */
            $parts = $token->Data[TokenData::NAMED_DECLARATION_PARTS];

            // Don't separate `use`, `use function` and `use constant` if
            // imports are not being sorted
            if (!$this->SortImportsEnabled && (
                $type === Type::USE_FUNCTION
                || $type === Type::USE_CONST
            )) {
                $type = Type::_USE;
            }

            // Get a canonical representation of the declaration's modifiers
            $modifiers = [];
            if ($type !== Type::HOOK) {
                if ($modifier = $parts->getFirstFrom($this->Idx->GetVisibility)) {
                    $modifiers[] = $modifier->id;
                }
                foreach ([\T_ABSTRACT, \T_FINAL, \T_READONLY, \T_STATIC, \T_VAR] as $id) {
                    if ($parts->hasOneOf($id)) {
                        $modifiers[] = $id;
                    }
                }
            }

            $this->Declarations[$token->Index] = [
                $token,
                $type,
                $modifiers,
                null,
                null,
                null,
            ];
        }

        // Collapse nested comments (e.g. in property hooks) before formatting
        // their parents (e.g. properties)
        uasort(
            $this->Declarations,
            fn($a, $b) => $b[0]->Depth <=> $a[0]->Depth
                ?: $a[0]->Index <=> $b[0]->Index,
        );

        $declarations = $this->Declarations;
        while ($declarations) {
            [$token, $type, $modifiers] = reset($declarations);
            unset($declarations[$token->Index]);

            $collapse = [];
            if (!$this->hasDocComment($token) && !$this->isMultiLine($token)) {
                $collapse[] = $token;
            }

            // Add a blank line before the first declaration of each type
            $this->maybeApplyBlankLineBefore($token);

            $group = [$modifiers];
            $prevModifiers = $modifiers;
            $nextExpand = null;
            $nonDeclarationReached = false;

            while (
                ($prevEnd = ($prev = $token)->EndStatement)
                && ($token = $prevEnd->NextSibling)
            ) {
                if (!isset($declarations[$token->Index])) {
                    $nonDeclarationReached = true;
                    break;
                }
                [, $nextType, $modifiers] = $declarations[$token->Index];
                if ($nextType !== $type) {
                    break;
                }
                unset($declarations[$token->Index]);

                $prevIsMultiLine = false;
                $applied = false;

                // Suppress blank lines between `use` statements, one-line
                // `declare` statements, and property hooks not declared over
                // multiple lines
                if ($type & Type::_USE || (
                    ($type === Type::_DECLARE || $type === Type::HOOK)
                    && !$this->isMultiLine($prev)
                    && !$this->isMultiLine($token)
                )) {
                    $prevEnd->collect($token)->applyWhitespace(Space::NO_BLANK_BEFORE);
                    $expand = false;
                    $applied = true;
                } elseif (
                    // Apply "loose" spacing to multi-line declarations
                    $this->hasDocComment($token)
                    || $this->isMultiLine($token)
                    // And to the declaration subsequent to them
                    || ($prevIsMultiLine = $this->hasDocComment($prev)
                        || $this->isMultiLine($prev))
                ) {
                    $expand = true;
                    if ($prevIsMultiLine) {
                        $collapse[] = $token;
                    }
                } elseif ($nextExpand === null) {
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
                        $nextExpand = $expand;
                    } elseif ($expand) {
                        $collapse[] = $token;
                    }
                } else {
                    $expand = $nextExpand;
                }

                if (!$expand && !$applied && (
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
                        !$this->Formatter->TightDeclarationSpacing
                        && $modifiers !== $prevModifiers
                        && $this->isGroupedByModifier($token, $type, $group)
                        && $this->hasDocComment($token, true)
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
                    $group = [$modifiers];
                    $prevModifiers = $modifiers;
                    continue;
                }

                $group[] = $modifiers;
                $prevModifiers = $modifiers;

                $token->Whitespace |= Space::LINE_BEFORE;

                // Collapse DocBlocks and suppress blank lines before DocBlocks
                // above tightly-spaced declarations
                $this->maybeCollapseComment($token);
                if ($applied) {
                    continue;
                }
                $token->Whitespace |= Space::NO_BLANK_BEFORE;
                if ($token->Prev && $token->Prev->id === \T_DOC_COMMENT) {
                    $token->Prev->Whitespace |= Space::NO_BLANK_BEFORE;
                }
            }

            // Add a blank line between declarations and subsequent statements
            // or comments
            if (
                $prevEnd
                && $prevEnd->Next
                && $prevEnd->Next->id !== \T_CLOSE_TAG
                && (
                    $nonDeclarationReached
                    || !($prevEnd->Next->Flags & TokenFlag::CODE)
                )
            ) {
                $prevEnd->Whitespace |= Space::BLANK_AFTER;
            }

            if ($nextExpand) {
                continue;
            }

            foreach ($collapse as $token) {
                if (
                    $this->Formatter->TightDeclarationSpacing
                    || $nextExpand === false
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
        $prevGroup = Arr::unique($group);

        $group = [];
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
                if ($group) {
                    break;
                }
                $group[] = $modifiers;
            } elseif ($group) {
                $group[] = $modifiers;
            } else {
                return false;
            }
        } while (
            $token->EndStatement
            && ($token = $token->EndStatement->NextSibling)
        );

        if (!$group) {
            return true;
        }

        $group = Arr::unique($group);
        return array_udiff($group, $prevGroup, fn($a, $b) => $a <=> $b) === $group;
    }

    private function hasDocComment(Token $token, bool $orBlankLineBefore = false): bool
    {
        return $this->Declarations[$token->Index][$orBlankLineBefore ? 4 : 3] ??=
            $this->doHasDocComment($token, $orBlankLineBefore);
    }

    /**
     * Check if the token before a declaration is a multi-line DocBlock that
     * cannot be collapsed
     */
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
                // Check if the comment has already been collapsed
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
        return $this->Declarations[$token->Index][5] ??=
            $this->doIsMultiLine($token);
    }

    private function doIsMultiLine(Token $token): bool
    {
        $type = $this->Declarations[$token->Index][1];
        $end = $type === Type::HOOK
            ? $token->nextSiblingFrom($this->Idx->StartOfPropertyHookBody)->PrevCode
            : $token->EndStatement;

        return $end && $token->collect($end)->hasNewline();
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
        if ($prev->Flags & TokenFlag::COLLAPSIBLE_COMMENT) {
            $prev->setText('/** ' . $prev->Data[TokenData::COMMENT_CONTENT] . ' */');
        }
    }

    private function maybeApplyBlankLineBefore(Token $token, bool $force = false): void
    {
        $this->Declarations[$token->Index][3] = null;
        $this->Declarations[$token->Index][4] = null;

        if (
            !$this->Formatter->Psr12
            && !$this->Formatter->ExpandHeaders
            && $token->OpenTag
            && $token->OpenTag->NextCode === $token
        ) {
            $token->Whitespace |= Space::LINE_BEFORE;
            return;
        }

        $token->applyBlankLineBefore($force);
    }
}
