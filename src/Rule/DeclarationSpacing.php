<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Rule\Internal\Declaration;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Arr;

/**
 * Normalise vertical spacing between declarations
 *
 * @api
 */
final class DeclarationSpacing implements DeclarationRule
{
    use DeclarationRuleTrait;

    private const MODIFIER_MASK = Type::_CLASS | self::VISIBILITY_MASK;
    private const VISIBILITY_MASK = Type::_CONST | Type::_FUNCTION | Type::PROPERTY;

    private bool $SortImportsEnabled;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_DECLARATIONS => 299,
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
    public static function needsSortedDeclarations(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->SortImportsEnabled = $this->Formatter->Enabled[SortImports::class]
            ?? false;
    }

    /**
     * Apply the rule to the given declarations
     *
     * One-line declarations with a collapsed or collapsible DocBlock, or no
     * DocBlock at all, are considered "collapsible". Declarations that break
     * over multiple lines or have a DocBlock that cannot be collapsed to one
     * line are considered "non-collapsible".
     *
     * "Tight" spacing is applied by suppressing blank lines between collapsible
     * declarations of the same type when they appear consecutively and:
     *
     * - the formatter's `TightDeclarationSpacing` property is `true`, or
     * - there is no blank line in the input between the first and second
     *   declarations in the group
     *
     * DocBlocks in tightly-spaced groups are collapsed to a single line.
     *
     * Otherwise, "loose" spacing is applied by adding blank lines between
     * declarations.
     *
     * Blank lines are also added before and after each group of declarations.
     * They are suppressed between `use` statements, one-line `declare`
     * statements, and property hooks not declared over multiple lines.
     */
    public function processDeclarations(array $declarations): void
    {
        /** @var array<int,non-empty-list<non-empty-list<Declaration>>> */
        $decls = [];
        $declDepths = [];

        foreach ($declarations as $token) {
            $type = $token->Data[Data::DECLARATION_TYPE];
            /** @var TokenCollection */
            $parts = $token->Data[Data::DECLARATION_PARTS];

            // Don't separate `use`, `use function` and `use constant` if
            // imports are not being sorted
            if (
                !$this->SortImportsEnabled
                && ($type === Type::USE_FUNCTION || $type === Type::USE_CONST)
            ) {
                $type = Type::_USE;
            }

            // Get a canonical representation of the declaration's modifiers
            $modifiers = [];
            if ($type & self::MODIFIER_MASK) {
                if (
                    $type & self::VISIBILITY_MASK
                    && ($modifier = $parts->getFirstFrom($this->Idx->SymmetricVisibility))
                ) {
                    $modifiers[] = $modifier->id;
                }
                foreach ([\T_ABSTRACT, \T_FINAL, \T_READONLY, \T_STATIC, \T_VAR] as $id) {
                    if ($parts->hasOneOf($id)) {
                        $modifiers[] = $id;
                    }
                }
            }

            $decl = new Declaration($token, $type, $modifiers);

            // Group consecutive declarations by parent
            $parentIndex = $token->Parent
                ? $token->Parent->index
                : -1;
            if (isset($decls[$parentIndex])) {
                $i = array_key_last($decls[$parentIndex]);
                $lastInParent = Arr::last($decls[$parentIndex][$i])->Token;
                if (
                    ($prev = $token->skipPrevEmptyStatements()->PrevCode)
                    && $prev->Statement === $lastInParent
                ) {
                    $decls[$parentIndex][$i][] = $decl;
                    continue;
                }
            }
            $decls[$parentIndex][][] = $decl;
            $declDepths[$parentIndex] = $token->Depth;
        }

        // Collapse nested comments (e.g. in property hooks) before formatting
        // their parents (e.g. properties)
        uksort(
            $decls,
            fn($a, $b) => $declDepths[$b] <=> $declDepths[$a],
        );

        /** @var non-empty-list<Declaration> $group */
        foreach (Arr::flatten($decls, 1) as $group) {
            // One-line declarations with comments to collapse if:
            // - `TightDeclarationSpacing` is enabled
            // - subsequent declarations are tightly spaced, or
            // - they were collapsed in the input
            /** @var Declaration[] */
            $uncollapsed = [];
            // - `null`: spacing not yet determined
            // - `true`: "loose" spacing applies
            // - `false`: "tight" spacing applies
            /** @var bool|null */
            $loose = null;

            $finalise = function () use (&$uncollapsed, &$loose) {
                foreach ($uncollapsed as $decl) {
                    if ($loose) {
                        $decl->applyBlankAfter();
                    } elseif (
                        ($comment = $decl->DocComment) && (
                            $this->Formatter->TightDeclarationSpacing
                            || $loose === false
                            || strpos($comment->OriginalText ?? $comment->text, "\n") === false
                        )
                    ) {
                        $this->maybeCollapseComment($decl);
                    }
                }
                $uncollapsed = [];
                $loose = null;
            };

            /** @var Declaration|null */
            $nextPrev = null;
            /** @var Declaration|null */
            $prev = null;
            $from = -1;
            foreach ($group as $i => $decl) {
                $prev = $nextPrev;
                $nextPrev = $decl;
                $token = $decl->Token;
                $type = $decl->Type;

                // Handle the first declaration of each type
                if (!$prev || $type !== $prev->Type) {
                    if ($prev) {
                        $finalise();
                    }
                    if ($collapsible = $decl->isCollapsible()) {
                        $uncollapsed[] = $decl;
                    }
                    $this->maybeApplyBlankBefore($decl, !$collapsible);
                    $from = $i;
                    continue;
                }

                $addBlankBefore = false;
                $addBlankAfter = false;
                $noBlankApplied = false;

                $prevEnd = $prev->End;

                // Suppress blank lines between `use` statements, one-line
                // `declare` statements, and property hooks not declared over
                // multiple lines
                if ($type & Type::_USE || (
                    ($type === Type::_DECLARE || $type === Type::HOOK)
                    && !$prev->isMultiLine()
                    && !$decl->isMultiLine()
                )) {
                    $prevEnd->collect($token)
                            ->setInnerWhitespace(Space::NO_BLANK);
                    $noBlankApplied = true;
                } elseif (!$decl->isCollapsible()) {
                    // Apply "loose" spacing to multi-line declarations
                    $addBlankBefore = true;
                    $addBlankAfter = true;
                } elseif (!$prev->isCollapsible()) {
                    // And to one-line declarations subsequent to them
                    $addBlankBefore = true;
                    $uncollapsed[] = $decl;
                } elseif ($loose === null) {
                    $addBlankBefore = !$this->Formatter->TightDeclarationSpacing
                        && $decl->hasBlankBefore();
                    // Propagate the gap between the first and second one-line
                    // declarations to subsequent one-line declarations unless
                    // they have different modifiers
                    if (
                        !$addBlankBefore
                        || $decl->Modifiers === $prev->Modifiers
                        || !$this->isGroupedByModifier($group, $from, $i - 1, $type)
                    ) {
                        $loose = $addBlankBefore;
                        $addBlankAfter = $addBlankBefore;
                    } elseif ($addBlankBefore) {
                        $uncollapsed[] = $decl;
                    }
                } else {
                    $addBlankBefore = $loose;
                    $addBlankAfter = $loose;
                }

                // Don't suppress blank lines between declarations with
                // different modifiers
                if (
                    !$addBlankBefore
                    && !$noBlankApplied
                    && !$this->Formatter->TightDeclarationSpacing
                    && $decl->hasBlankBefore()
                    && $decl->Modifiers !== $prev->Modifiers
                    && $this->isGroupedByModifier($group, $from, $i - 1, $type)
                ) {
                    $addBlankBefore = true;
                    $uncollapsed[] = $decl;
                }

                if ($addBlankBefore) {
                    $this->maybeApplyBlankBefore($decl, $addBlankAfter, true);
                    $from = $i;
                } else {
                    $token->Whitespace |= Space::LINE_BEFORE;
                    if (!$noBlankApplied) {
                        // Allow comments to disrupt spacing if there is a blank
                        // line before the first comment and another between
                        // comments or before the declaration, otherwise
                        // suppress blank lines between declarations
                        $first = $decl->DocComment ?? $token;
                        if (!(
                            $prevEnd->hasBlankAfter()
                            && $prevEnd->collect($first)
                                       ->shift()
                                       ->hasBlankLineBetweenTokens()
                        )) {
                            /** @var Token */
                            $first = $prevEnd->Next;
                        } elseif ($first->hasBlankBefore()) {
                            // Preserve blank lines before declarations
                            /** @var Token */
                            $first = $first->Next;
                        }
                        $first->collect($token)
                              ->setTokenWhitespace(Space::NO_BLANK_BEFORE);
                    }
                    $this->maybeCollapseComment($decl);
                }
            }

            $finalise();

            // Add a blank line after declarations
            $decl->applyBlankAfter();
        }
    }

    /**
     * Check if declarations in $group between $from and $to have modifiers
     * mutually exclusive with subsequent tightly-spaced declarations of $type
     *
     * @param list<Declaration> $group
     */
    private function isGroupedByModifier(array $group, int $from, int $to, int $type): bool
    {
        $modifiers = [];
        $i = $to;
        $count = count($group);
        while (++$i < $count) {
            $decl = $group[$i];
            if ($decl->Type !== $type || !$decl->isCollapsible()) {
                break;
            }
            if ($decl->hasBlankBefore()) {
                if ($modifiers) {
                    break;
                }
            } elseif (!$modifiers) {
                // The first declaration after `$to` must follow a blank line
                return false;
            }
            $modifiers[] = $decl->Modifiers;
        }

        if (!$modifiers) {
            return true;
        }

        $modifiers = Arr::unique($modifiers);

        for ($i = $from; $i <= $to; $i++) {
            $decl = $group[$i];
            if (in_array($decl->Modifiers, $modifiers, true)) {
                return false;
            }
        }

        return true;
    }

    private function maybeCollapseComment(Declaration $decl): void
    {
        if (
            ($comment = $decl->DocComment)
            && $comment->Flags & Flag::COLLAPSIBLE_COMMENT
        ) {
            $comment->setText('/** ' . $comment->Data[Data::COMMENT_CONTENT] . ' */');
        }
    }

    private function maybeApplyBlankBefore(
        Declaration $decl,
        bool $andAfter,
        bool $force = false
    ): void {
        $token = $decl->Token;
        if (
            !$this->Formatter->ExpandHeaders
            && $token->OpenTag
            && $token->OpenTag->NextCode === $token
        ) {
            $token->Whitespace |= Space::LINE_BEFORE;
        } else {
            $decl->applyBlankBefore($force);
        }
        if ($andAfter) {
            $decl->applyBlankAfter();
        }
    }
}
