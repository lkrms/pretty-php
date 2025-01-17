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
                if (!$loose) {
                    foreach ($uncollapsed as $decl) {
                        $token = $decl->Token;
                        if (
                            $this->Formatter->TightDeclarationSpacing
                            || $loose === false
                            || (
                                ($prev = $token->Prev)
                                && $prev->id === \T_DOC_COMMENT
                                && strpos($prev->OriginalText ?? $prev->text, "\n") === false
                            )
                        ) {
                            $this->maybeCollapseComment($token);
                        }
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
                    if ($decl->isCollapsible()) {
                        $uncollapsed[] = $decl;
                    }
                    $this->maybeApplyBlankBefore($decl);
                    $from = $i;
                    continue;
                }

                $addBlank = false;
                $noBlankApplied = false;

                // Suppress blank lines between `use` statements, one-line
                // `declare` statements, and property hooks not declared over
                // multiple lines
                if ($type & Type::_USE || (
                    ($type === Type::_DECLARE || $type === Type::HOOK)
                    && !$prev->isMultiLine()
                    && !$decl->isMultiLine()
                )) {
                    $prev->End->collect($token)->setInnerWhitespace(Space::NO_BLANK);
                    $noBlankApplied = true;
                } elseif (!$decl->isCollapsible()) {
                    // Apply "loose" spacing to multi-line declarations
                    $addBlank = true;
                } elseif (!$prev->isCollapsible()) {
                    // And to one-line declarations subsequent to them
                    $addBlank = true;
                    $uncollapsed[] = $decl;
                } elseif ($loose === null) {
                    $addBlank = !$this->Formatter->TightDeclarationSpacing
                        && $decl->hasDocComment(true);
                    // Propagate the gap between the first and second one-line
                    // declarations to subsequent one-line declarations unless
                    // they have different modifiers
                    if (
                        !$addBlank
                        || $decl->Modifiers === $prev->Modifiers
                        || !$this->isGroupedByModifier($group, $from, $i - 1, $type)
                    ) {
                        $loose = $addBlank;
                    } elseif ($addBlank) {
                        $uncollapsed[] = $decl;
                    }
                } else {
                    $addBlank = $loose;
                }

                // Don't suppress blank lines between declarations with
                // different modifiers, and add a blank line if there are
                // non-code tokens other than one DocBlock between declarations
                if (
                    !$addBlank
                    && !$noBlankApplied
                    && (
                        (
                            !$this->Formatter->TightDeclarationSpacing
                            && $decl->hasDocComment(true)
                            && $decl->Modifiers !== $prev->Modifiers
                            && $this->isGroupedByModifier($group, $from, $i - 1, $type)
                        )
                        || $this->hasNewlineSince($token, $prev->End)
                    )
                ) {
                    $addBlank = true;
                    $uncollapsed[] = $decl;
                }

                if ($addBlank) {
                    $this->maybeApplyBlankBefore($decl, true);
                    $from = $i;
                } else {
                    // Suppress blank lines and collapse DocBlocks before
                    // tightly-spaced declarations
                    $token->Whitespace |= Space::LINE_BEFORE;
                    if (!$noBlankApplied) {
                        $token->Whitespace |= Space::NO_BLANK_BEFORE;
                        if ($token->Prev && $token->Prev->id === \T_DOC_COMMENT) {
                            $token->Prev->Whitespace |= Space::NO_BLANK_BEFORE;
                        }
                    }
                    $this->maybeCollapseComment($token);
                }
            }

            $finalise();

            // Add a blank line after declarations
            if (
                ($next = $decl->End->Next)
                && $next->id !== \T_CLOSE_TAG
            ) {
                $decl->End->Whitespace |= Space::BLANK_AFTER;
            }
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
            $token = $decl->Token;
            if ($decl->Type !== $type || !$decl->isCollapsible()) {
                break;
            }
            if (
                $decl->hasDocComment(true) || (
                    $token->PrevCode
                    && $this->hasNewlineSince($token, $token->PrevCode)
                )
            ) {
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

    private function hasNewlineSince(Token $token, Token $since): bool
    {
        /** @var Token */
        $prev = $token->Prev;
        if ($prev->id === \T_DOC_COMMENT) {
            /** @var Token */
            $prev = $prev->Prev;
        }
        return $since->collect($prev)->hasNewline();
    }

    private function maybeCollapseComment(Token $token): void
    {
        /** @var Token */
        $prev = $token->Prev;
        if ($prev->Flags & Flag::COLLAPSIBLE_COMMENT) {
            $prev->setText('/** ' . $prev->Data[Data::COMMENT_CONTENT] . ' */');
        }
    }

    private function maybeApplyBlankBefore(Declaration $decl, bool $force = false): void
    {
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
    }
}
