<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Apply hanging indentation to scopes and expressions that would otherwise be
 * difficult to differentiate from adjacent code
 *
 * @api
 */
final class HangingIndentation implements TokenRule
{
    use TokenRuleTrait;

    private const NO_INDENT = 1;
    private const OVERHANGING_INDENT = 2;
    private const NO_INNER_NEWLINE = 4;

    private int $HeredocIndent;
    /** @var array<int,int> */
    private array $ParentFlags;
    /** @var array<int,array<int,array{Token|null,Token|null,Token|null,3?:Token|int,4?:int}>> */
    private array $Contexts;
    /** @var array<int,Token> */
    private array $ContextTokens;
    /** @var array<int,array<int,int>> */
    private array $CollapsibleLevels;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 800,
            self::CALLBACK => 800,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->HeredocIndent = $this->Formatter->HeredocIndent;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->ParentFlags = [];
        $this->Contexts = [];
        $this->ContextTokens = [];
        $this->CollapsibleLevels = [];
    }

    /**
     * Apply the rule to the given tokens
     *
     * Scopes and expressions that would otherwise be difficult to differentiate
     * from adjacent code are indented for visual separation, and a callback is
     * registered to collapse any unnecessary "overhanging" indentation levels.
     *
     * @prettyphp-callback "Overhanging" indentation applied earlier is
     * collapsed to the minimum level required to ensure distinct scopes and
     * expressions do not appear to run together.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Populate `$this->ParentFlags` for every open bracket
            if ($close = $token->CloseBracket) {
                /** @var Token */
                $statement = $token->Statement;
                /** @var Token */
                $start = $token->NextCode;
                /** @var Token */
                $end = $close->PrevCode;
                $hasList = (
                    $token->Flags & TokenFlag::LIST_PARENT && (
                        $token->Data[TokenData::LIST_ITEM_COUNT] > 1
                        || $start->id === $token->Data[TokenData::LIST_DELIMITER]
                        || $end->id === $token->Data[TokenData::LIST_DELIMITER]
                    )
                ) || (
                    $token->id === \T_OPEN_BRACE && (
                        $token->Flags & TokenFlag::STRUCTURAL_BRACE
                        || $token->isMatchOpenBrace()
                    )
                ) || (
                    $token->id === \T_COLON
                    && $token->isColonAltSyntaxDelimiter()
                ) || $token->id === \T_OPEN_UNENCLOSED;
                $flags = 0;
                if ($token->hasNewlineBeforeNextCode()) {
                    if (!$hasList) {
                        $flags |= self::NO_INDENT;
                    }
                } else {
                    if ($hasList || (
                        $this->Idx->OpenBracketExceptBrace[$token->id]
                        && $close->adjacent()
                    )) {
                        $flags |= self::OVERHANGING_INDENT;
                    }
                    $flags |= self::NO_INNER_NEWLINE;
                }
                $this->ParentFlags[$token->index] = $flags;
            }

            // Ignore:
            // - non-code tokens, including T_CLOSE_TAG statement terminators
            // - tokens that continue a control structure
            // - tokens aligned by other rules
            // - the first code token in the document
            if (
                $this->Idx->NotCode[$token->id]
                || $this->Idx->Virtual[$token->id]
                || $this->Idx->HasStatement[$token->id]
                || $this->Idx->AltEnd[$token->id]
                || $this->Idx->CloseBracket[$token->id]
                || $token->id === \T_OPEN_BRACE
                || !$token->PrevCode
            ) {
                continue;
            }

            $prevCode = $token->PrevCode;
            $prevSibling = $token->PrevSibling;
            /** @var Token */
            $statement = $token->Statement;
            $declType = $statement->Flags & TokenFlag::NAMED_DECLARATION
                ? $statement->Data[TokenData::NAMED_DECLARATION_TYPE]
                : 0;
            $mayHaveListWithEqual = $declType === Type::_CONST
                || $declType === Type::PROPERTY
                || ($statement->id === \T_STATIC && !$declType)
                || $statement->id === \T_GLOBAL;
            $parent = $token->Parent;
            $parentFlags = $parent
                ? $this->ParentFlags[$parent->index]
                : 0;

            // Ignore:
            // - the first token in a statement, unless it has an
            //   OVERHANGING_INDENT parent
            // - conditional expressions in `match`
            // - aligned expressions in `for`
            // - tokens after an attribute
            if ((
                $statement === $token
                && !($parentFlags & self::OVERHANGING_INDENT)
            ) || (
                $prevCode->id === \T_COMMA && $parent && (
                    (
                        $parent->id === \T_OPEN_BRACE
                        && $prevCode->isMatchDelimiter()
                    ) || (
                        $parent->PrevCode
                        && $parent->PrevCode->id === \T_FOR
                        && ($forExpr = $token->prevSiblingOf(\T_SEMICOLON)->or($parent)->NextCode)
                        && $forExpr->AlignedWith
                    )
                )
            ) || (
                $prevSibling
                && $this->Idx->Attribute[$prevSibling->id]
            )) {
                continue;
            }

            // Do nothing if the token is not at the start of a line and is not
            // the first token in a heredoc with hanging indentation, or if its
            // indentation already differs from the previous token
            if (
                (!$prevCode->hasNewlineBeforeNextCode() && !(
                    $prevCode->id === \T_START_HEREDOC && (
                        $this->HeredocIndent === HeredocIndent::HANGING || (
                            $this->HeredocIndent === HeredocIndent::MIXED
                            && !$prevCode->AlignedWith
                            && !$prevCode->hasNewlineAfterPrevCode()
                        )
                    )
                ))
                || $this->indent($prevCode) !== $this->indent($token)
            ) {
                continue;
            }

            // Otherwise, having established the token should be indented, build
            // a context for it
            $contexts = $this->Contexts[$token->index] ?? [];
            if ($contexts) {
                $idx = [];
                foreach (array_reverse(array_keys($contexts)) as $index) {
                    if ($this->ContextTokens[$index]->Parent !== $token->Parent) {
                        break;
                    }
                    $idx[$index] = true;
                }
                $scopeContexts = array_intersect_key($contexts, $idx);
            } else {
                $scopeContexts = [];
            }
            $lastContext = $scopeContexts
                ? end($scopeContexts)
                : null;
            $lastToken = $scopeContexts
                ? $this->ContextTokens[key($scopeContexts)]
                : null;

            $trigger = $token->Parent === $prevCode->Parent
                && TokenUtil::isNewlineAllowedAfter($prevCode)
                && (
                    !TokenUtil::isNewlineAllowedBefore($token)
                    || $token->isUnaryOperator()
                )
                    ? $prevCode
                    : $token;

            if ($this->Idx->OperatorAssignmentOrDoubleArrow[$trigger->id]) {
                $assignment = $trigger;
            } else {
                $assignment = null;
                $t = $token;
                do {
                    $t = $t->prevSiblingFrom($this->Idx->OperatorAssignmentOrDoubleArrow, true);
                    if ($t->id === \T_NULL) {
                        break;
                    }
                    // Ignore assignment operators in ternary expressions the
                    // token does not belong to
                    if (
                        ($ternary2 = TokenUtil::getTernary2AfterTernary1($t))
                        && $token->index >= $ternary2->index
                    ) {
                        continue;
                    }
                    // In lists of declarations/variables, ignore assignment
                    // operators that belong to a different entry or don't
                    // trigger indentation
                    if (
                        $mayHaveListWithEqual && (
                            $t->withNextSiblings($token)->hasOneOf(\T_COMMA)
                            || !(
                                $t->hasNewlineBeforeNextCode()
                                || $t->hasNewlineAfterPrevCode()
                            )
                        )
                    ) {
                        break;
                    }
                    $assignment = $t;
                    break;
                } while (true);
            }

            $ternary = $lastContext
                ? $lastContext[2]
                : null;

            $context = [$token->Parent, $assignment, $ternary];
            $until = null;
            $onlyApply = false;
            $reapply = false;

            if ($prevCode->id === \T_START_HEREDOC) {
                $context[] = $prevCode;
            } elseif ($token->Flags & TokenFlag::LIST_ITEM) {
                /** @var Token */
                $listParent = $token->Data[TokenData::LIST_PARENT];
                /** @var TokenCollection */
                $listItems = $listParent->Data[TokenData::LIST_ITEMS];
                /** @var Token */
                $firstItem = $listItems->first();
                $context[] = $firstItem;
            } elseif ($this->Idx->Chain[$token->id]) {
                $context[] = $token->Data[TokenData::CHAIN_OPENED_BY];
                $until = TokenUtil::getOperatorEndExpression($token);
            } elseif (
                $token->Flags & TokenFlag::TERNARY_OPERATOR
                || $token->id === \T_COALESCE
            ) {
                $ternary = TokenUtil::getTernaryContext($token)
                    ?? TokenUtil::getTernary1($token)
                    ?? $token;
                $context[2] = $ternary;
                $context[] = TokenUtil::getOperatorPrecedence($ternary);
                $until = TokenUtil::getTernaryEndExpression($token);
            } elseif ($statement !== $token) {
                // Don't indent subsequent assignments in the same statement
                if (
                    $this->Idx->OperatorAssignment[$trigger->id]
                    && $lastToken
                    && $lastToken->PrevCode
                    && (
                        $this->Idx->OperatorAssignment[$lastToken->PrevCode->id]
                        || $this->Idx->OperatorAssignment[$lastToken->id]
                    )
                ) {
                    continue;
                }

                // If the newline before the token is associated with an
                // operator, add its precedence to the context, otherwise add:
                // - `$lastToken` if a precedence value was added to the last
                //   context, followed by the precedence value added previously
                // - the token added to the last context, followed by the
                //   precedence value added previously (if present)
                // - `$lastToken` if no token was added to the last context
                if (
                    TokenUtil::OPERATOR_PRECEDENCE_INDEX[$trigger->id]
                    && ($precedence = TokenUtil::getOperatorPrecedence($trigger)) < 99
                ) {
                    $context[] = $precedence;
                    $until = TokenUtil::getOperatorEndExpression($trigger);
                    // Reapply the context if necessary so any subsequent lines
                    // with no operator are indented
                    $reapply = true;
                } elseif ($lastContext) {
                    /** @var Token $lastToken */
                    $lastValue = $lastContext[3] ?? null;
                    if (is_int($lastValue)) {
                        $context[] = $lastToken;
                        $context[] = $lastValue;
                        $until = TokenUtil::getOperatorEndExpression($token, $lastValue - 1);
                    } elseif ($lastValue) {
                        $context[] = $lastValue;
                        $lastValue = $lastContext[4] ?? null;
                        if ($lastValue !== null) {
                            $context[] = $lastValue;
                            $until = TokenUtil::getOperatorEndExpression($token, $lastValue - 1);
                        }
                    }
                }

                // Suppress the first level of indentation in scenarios where
                // disambiguation between multiple expressions is unnecessary
                $onlyApply = (
                    !$lastToken
                    && !$context[1]
                    && $parent
                    && $parentFlags & self::NO_INDENT
                    && !$this->Idx->OperatorAssignmentOrDoubleArrow[$trigger->id]
                ) || (
                    $lastToken
                    && $lastToken->PrevCode
                    && (
                        $this->Idx->OperatorAssignmentOrDoubleArrow[$lastToken->PrevCode->id]
                        || $this->Idx->OperatorAssignmentOrDoubleArrow[$lastToken->id]
                    )
                );
            }

            $until ??= $this->getLastIndentable($token);

            if (!$until && $lastContext) {
                $lastValue = end($lastContext);
                if (is_int($lastValue)) {
                    $until = TokenUtil::getOperatorEndExpression($token, $lastValue - 1);
                }
            }

            $until ??= $token->EndStatement;
            /** @var Token $until */

            // Indent ternary operators relative to their first expression
            if (
                ($next = $until->NextCode)
                && ((
                    $next->id === \T_QUESTION
                    && $next->Flags & TokenFlag::TERNARY_OPERATOR
                ) || $next->id === \T_COALESCE)
                && !TokenUtil::getTernaryContext($next)
            ) {
                $until = TokenUtil::getTernaryEndExpression($next);
            }

            // Indent expressions relative to assignment operators
            if (
                ($next = $until->NextCode)
                && $this->Idx->OperatorAssignment[$next->id]
            ) {
                $until = TokenUtil::getOperatorEndExpression($next);
            }

            while ($adjacent = $until->adjacentBeforeNewline()) {
                // Cover items aligned by `AlignLists` on the same line
                foreach ($adjacent->collect($adjacent->endOfLine()) as $t) {
                    if ($t->AlignedWith) {
                        /** @var Token */
                        $until = $t->EndStatement;
                        continue 2;
                    }
                }
                break;
            }

            // Do nothing if hanging indentation is unnecessary or the context
            // has already been applied to the token
            $onlyApply = $onlyApply || $token->AlignedWith;
            if ($onlyApply || in_array($context, $contexts, true)) {
                if ($onlyApply || $reapply) {
                    foreach ($token->collect($until) as $t) {
                        $i = $t !== $token
                            ? $t->index
                            : -$t->index;
                        $this->Contexts[$i][$token->index] = $context;
                    }
                    $this->ContextTokens[$token->index] = $token;
                }
                continue;
            }

            // Always add at least one level of indentation
            $indent = 1;
            $collapsible = [];

            // And another for mid-declaration constants and properties
            if ($mayHaveListWithEqual && $trigger->id !== \T_COMMA) {
                $indent++;
            }

            if ($parent) {
                $collapsible[$parent->index] = 0;

                // And another for mid-statement OVERHANGING_INDENT children
                if (
                    $parentFlags & self::OVERHANGING_INDENT
                    && $statement !== $token
                ) {
                    $indent++;
                    $collapsible[$parent->index]++;
                }

                // And one or two more per unseen parent scope
                $p = $parent;
                while ($p = $p->Parent) {
                    if (isset($this->CollapsibleLevels[$token->index][$p->index])) {
                        break;
                    }
                    $collapsible[$p->index] = 0;
                    if ($this->ParentFlags[$p->index] & self::NO_INNER_NEWLINE) {
                        $indent++;
                        $collapsible[$p->index]++;
                        if ($this->ParentFlags[$p->index] & self::OVERHANGING_INDENT) {
                            $indent++;
                            $collapsible[$p->index]++;
                        }
                    }
                }
            }

            // Apply indentation and update collapsible levels
            foreach ($token->collect($until) as $t) {
                $t->HangingIndent += $indent;
                foreach ($collapsible as $index => $levels) {
                    $this->CollapsibleLevels[$t->index][$index] ??= 0;
                    $this->CollapsibleLevels[$t->index][$index] += $levels;
                }
                $i = $t !== $token
                    ? $t->index
                    : -$t->index;
                $this->Contexts[$i][$token->index] = $context;
            }
            $this->ContextTokens[$token->index] = $token;

            if ($indent < 2) {
                continue;
            }

            $this->Formatter->registerCallback(
                static::class,
                $token,
                function () use ($token, $until, $indent, $mayHaveListWithEqual) {
                    $levels = $this->getLevelsToCollapse($token, $until, $indent - 1, $mayHaveListWithEqual);
                    if ($levels) {
                        foreach ($token->collect($until) as $t) {
                            $t->HangingIndent -= $levels;
                        }
                        foreach ($token->collect($until->endOfLine()) as $t) {
                            $callbacks = $t->Data[TokenData::ALIGNMENT_CALLBACKS] ?? null;
                            if ($callbacks) {
                                foreach ($callbacks as $callback) {
                                    $callback();
                                }
                            }
                        }
                    }
                },
                true,
            );
        }
    }

    private function getLastIndentable(Token $token): ?Token
    {
        // Check if the token is part of a declaration with a body that hasn't
        // been reached yet, not including anonymous functions or classes like
        // the following, which can be moved around in their entirety:
        //
        // ```
        // $foo = new
        //     #[Attribute]
        //     class implements
        //         Bar,
        //         Baz
        //     {
        //         // ...
        //     };
        // ```
        //
        // Whereas anonymous classes like this cannot:
        //
        // ```
        // $foo = new class implements
        //     Bar,
        //     Baz
        // {
        //     // ...
        // };
        // ```
        /** @var Token */
        $statement = $token->Statement;
        $parts = $token->skipToStartOfDeclaration()
                       ->declarationParts();
        if (
            !$parts->isEmpty()
            && (
                $parts->hasOneFrom($this->Idx->DeclarationTopLevel)
                || $statement->isProperty()
            )
            && ($last = $parts->last())->index >= $token->index
            && $last->skipPrevSiblingFrom($this->Idx->Ampersand)->id !== \T_FUNCTION
            && !(
                ($first = $parts->first())->id === \T_NEW
                && $first->NextCode === $token
                && $first->nextSiblingOf(\T_CLASS)->hasNewlineAfterPrevCode()
            )
            && ($body = $last->nextSiblingOf(\T_OPEN_BRACE, true))->id !== \T_NULL
        ) {
            return $body->PrevCode;
        }

        return null;
    }

    private function getLevelsToCollapse(
        Token $token,
        Token $until,
        int $levels,
        bool $mayHaveListWithEqual
    ): int {
        // Collapse every possible level if there's a multi-line comment between
        // `$until` and the next line, there are no more lines, or the next line
        // starts with a close bracket and there is no comment to fall back on
        $eol = $until->endOfLine(false);
        if (
            $eol->Flags & TokenFlag::MULTILINE_COMMENT
            && $eol->hasNewline()
            && !$eol->hasNewlineBefore()
        ) {
            return $levels;
        }
        $next = $eol->NextCode;
        if (
            !$next
            || $next->OpenBracket
        ) {
            $next = $eol->Next;
            if (
                !$next
                || !$this->Idx->Comment[$next->id]
            ) {
                return $levels;
            }
        }

        // Check for a multi-line comment between `$until` and the end of the
        // previous line too
        $last = $until->startOfLine(false);
        if (
            $last->Flags & TokenFlag::MULTILINE_COMMENT
            && $last->hasNewline()
        ) {
            return $levels;
        }

        // If the next line is in the same scope and has the same context as
        // `$token`, allow it to align with `$token` after levels are collapsed,
        // otherwise require `$until` to be indented by at least one level
        // relative to the next line
        if (
            $next->Parent === $token->Parent
            && ($this->Contexts[$next->index] ?? null)
                === ($this->Contexts[$token->index] ?? null)
            && !($next->Statement === $next xor $token->Statement === $token)
            && !($mayHaveListWithEqual && (
                ($next->PrevCode && $next->PrevCode->id === \T_COMMA)
                xor ($token->PrevCode && $token->PrevCode->id === \T_COMMA)
            ))
        ) {
            $maxLevels = $this->effectiveIndent($token)
                - $this->effectiveIndent($next);
        } else {
            $maxLevels = $this->effectiveIndent($last)
                - $this->effectiveIndent($next)
                - 1;
        }

        // If the next line is already indented relative to `$token` or
        // `$until`, collapsing levels will have no impact on visual separation
        if ($maxLevels < 0) {
            return $levels;
        }

        return min($levels, $maxLevels);
    }

    private function effectiveIndent(Token $token): int
    {
        // `$token->LineUnpadding` is ignored on purpose
        return $token->getIndent() + (int) (
            ($token->LinePadding + $token->Padding)
            / $this->Formatter->TabSize
        );
    }

    private function indent(Token $token): int
    {
        return $token->PreIndent + $token->Indent - $token->Deindent;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getData(): array
    {
        $t = $this->Formatter->Document->Tokens;

        foreach ($this->Contexts as $index => $contexts) {
            $index = abs($index);
            foreach ($contexts as $tokenIndex => $context) {
                $context = array_map(
                    fn($value) => $value instanceof Token
                        ? TokenUtil::describe($value)
                        : $value,
                    $context,
                );
                $key = implode("\0", $context);
                $byKey[$key][0] = $context;
                $byKey[$key][1][$tokenIndex][] = $index;
            }
        }

        foreach ($byKey ?? [] as $key => [$context, $indexMaps]) {
            $tokens = [];
            foreach ($indexMaps as $tokenIndex => $range) {
                $tokens[] = [
                    'appliedFor' => TokenUtil::describe($t[$tokenIndex]),
                    'range' => sprintf(
                        '%s - %s',
                        TokenUtil::describe($t[min($range)]),
                        TokenUtil::describe($t[max($range)]),
                    ),
                ];
            }
            $data['contexts'][] = [
                'values' => $context,
                'tokens' => $tokens,
            ];
        }

        return $data ?? [];
    }
}
