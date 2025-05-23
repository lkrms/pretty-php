<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;
use Closure;

/**
 * Apply standard vertical spacing
 *
 * @api
 */
final class VerticalSpacing implements TokenRule, ListRule, DeclarationRule
{
    use TokenRuleTrait;
    use ListRuleTrait;
    use DeclarationRuleTrait;

    private bool $AlignChainsEnabled;
    private bool $ListRuleEnabled;
    /** @var array<int,Closure(Token): bool> */
    private array $HasNewline;
    /** @var array<int,Closure(Token): void> */
    private array $ApplyNewline;
    /** @var array<string,true> */
    private array $Seen;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 242,
            self::PROCESS_LIST => 220,
            self::PROCESS_DECLARATIONS => 220,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->merge(
            [
                \T_FOR => true,
                \T_OPEN_BRACE => true,
                \T_QUESTION => true,
                \T_USE => true,
            ],
            $idx->Chain,
            $idx->BooleanExceptNot,
        );
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getDeclarationTypes(array $all): array
    {
        return [
            Type::_CONST => true,
            Type::_USE => true,
            Type::PROPERTY => true,
            Type::PARAM => true,
            Type::USE_CONST => true,
            Type::USE_FUNCTION => true,
            Type::USE_TRAIT => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedDeclarations(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->AlignChainsEnabled = $this->Formatter->Enabled[AlignChains::class]
            ?? false;
        $this->ListRuleEnabled = $this->Formatter->Enabled[StrictLists::class]
            ?? $this->Formatter->Enabled[AlignLists::class]
            ?? false;

        $hasNewlineBefore = fn(Token $t): bool => $t->hasNewlineAfterPrevCode();
        $hasNewlineAfter = fn(Token $t): bool => $t->hasNewlineBeforeNextCode();
        $applyNewlineBefore = function (Token $t): void {
            $sol = $t->startOfLine();
            /** @var Token */
            $prev = $t->Prev;
            /** @var Token */
            $next = $t->Next;
            // Suppress newlines after standalone close brackets, e.g. `) &&`
            // where `)` is at the start of a line
            if (
                ($prev->index >= $sol->index ? $sol : $prev->startOfLine())
                    ->collect($prev)
                    ->hasOneNotFrom($this->Idx->CloseBracket)
                || $next
                       ->collect($next->endOfLine())
                       ->hasOneNotFrom($this->Idx->OpenBracketOrNot)
            ) {
                $t->Whitespace |= Space::LINE_BEFORE;
            } else {
                $t->Whitespace |= Space::NO_LINE_BEFORE;
            }
        };
        $applyNewlineAfter = function (Token $t): void {
            $eol = $t->endOfLine();
            /** @var Token */
            $next = $t->Next;
            /** @var Token */
            $prev = $t->Prev;
            // Suppress newlines before standalone open brackets, e.g. `&& (`
            // where `(` is at the end of a line
            if (
                $next->collect($next->index <= $eol->index ? $eol : $next->endOfLine())
                     ->hasOneNotFrom($this->Idx->OpenBracketOrNot)
                || $prev->startOfLine()
                        ->collect($prev)
                        ->hasOneNotFrom($this->Idx->CloseBracket)
            ) {
                $t->Whitespace |= Space::LINE_AFTER;
            } else {
                $t->Whitespace |= Space::NO_LINE_AFTER;
            }
        };

        foreach (array_keys(array_filter($this->Idx->BooleanExceptNot)) as $id) {
            if (
                $this->Idx->AllowNewlineBefore[$id]
                || !$this->Idx->AllowNewlineAfter[$id]
            ) {
                $this->HasNewline[$id] = $hasNewlineBefore;
                $this->ApplyNewline[$id] = $applyNewlineBefore;
            } else {
                $this->HasNewline[$id] = $hasNewlineAfter;
                $this->ApplyNewline[$id] = $applyNewlineAfter;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Seen = [];
    }

    /**
     * Apply the rule to the given tokens
     *
     * In expressions where one or more boolean operators have an adjacent
     * newline, newlines are added to other boolean operators of equal or lower
     * precedence.
     *
     * In `for` loops:
     *
     * - If an expression with multiple expressions breaks over multiple lines,
     *   newlines are added after comma-delimited expressions, and blank lines
     *   are added after semicolon-delimited expressions.
     * - Otherwise, if an expression breaks over multiple lines, newlines are
     *   added after semicolon-delimited expressions.
     * - Otherwise, if the second or third expression has a leading newline, a
     *   newline is added before the other.
     *
     * Newlines are added before open braces that belong to top-level
     * declarations and anonymous classes declared over multiple lines.
     *
     * Newlines are added before both operators in ternary expressions where one
     * operator has a leading newline.
     *
     * In anonymous functions with lexical variables after parameters that break
     * over multiple lines, newlines are added before each parameter. If the
     * list of variables after `use` breaks over multiple lines, newlines are
     * also added before each variable.
     *
     * In method chains where an object operator (`->` or `?->`) has a leading
     * newline, newlines are added before every object operator. If the
     * `AlignChains` rule is enabled and strict PSR-12 compliance is not, the
     * first object operator in the chain is excluded from this operation.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Propagate newlines adjacent to boolean operators to others of
            // equal or lower precedence in the same statement
            if ($this->Idx->BooleanExceptNot[$token->id]) {
                $logical = $this->Idx->LogicalExceptNot[$token->id];
                $minPrecedence = $logical
                    ? TokenUtil::getPrecedenceOf(\T_LOGICAL_OR)
                    : TokenUtil::getPrecedenceOf(\T_BOOLEAN_OR);
                $start = TokenUtil::getOperatorExpression($token, $minPrecedence);

                // Ignore statements already processed and tokens with no
                // adjacent newline
                $key = $start->index . ':' . $minPrecedence;
                if (
                    isset($this->Seen[$key])
                    || !($this->HasNewline[$token->id])($token)
                ) {
                    continue;
                }

                $this->Seen[$key] = true;

                // Get the statement's boolean operators and find the
                // highest-precedence operator with an adjacent newline
                $precedence = TokenUtil::getOperatorPrecedence($token);
                /** @var array<int,Token[]> */
                $byPrecedence = [$precedence => [$token]];
                $maxPrecedence = $precedence;

                $end = TokenUtil::getOperatorEndExpression($token, $minPrecedence);
                foreach ($start->withNextSiblings($end) as $t) {
                    if (
                        $t === $token
                        || !$this->Idx->BooleanExceptNot[$t->id]
                        || $logical !== $this->Idx->LogicalExceptNot[$t->id]
                    ) {
                        continue;
                    }
                    $precedence = TokenUtil::getOperatorPrecedence($t);
                    $byPrecedence[$precedence][] = $t;
                    if (!($this->HasNewline[$t->id])($t)) {
                        continue;
                    }
                    // Lower numbers = higher precedence
                    $maxPrecedence = min($maxPrecedence, $precedence);
                }

                foreach ($byPrecedence as $precedence => $tokens) {
                    if ($precedence >= $maxPrecedence) {
                        foreach ($tokens as $t) {
                            ($this->ApplyNewline[$t->id])($t);
                        }
                    }
                }
            } elseif ($token->id === \T_FOR) {
                /**
                 * @var TokenCollection $semicolons
                 * @var TokenCollection $commas
                 */
                [$expr1, $expr2, $expr3, $semicolons, $commas] = $token->Data[Data::FOR_PARTS];
                $hasNewline = false;
                $hasNewlineAndComma = false;
                /** @var TokenCollection $expr */
                foreach ([$expr1, $expr2, $expr3] as $expr) {
                    if ($expr->hasNewlineBetweenTokens()) {
                        $hasNewline = true;
                        if ($expr->hasOneOf(\T_COMMA)) {
                            $hasNewlineAndComma = true;
                            break;
                        }
                    }
                }
                if ($hasNewlineAndComma) {
                    $commas->setTokenWhitespace(Space::LINE_AFTER);
                    $semicolons->setTokenWhitespace(Space::BLANK_AFTER);
                } elseif ($hasNewline || $semicolons->tokenHasNewlineAfter()) {
                    $semicolons->setTokenWhitespace(Space::LINE_AFTER);
                }
            } elseif ($token->id === \T_OPEN_BRACE) {
                if (
                    $this->Formatter->OneTrueBraceStyle
                    || !($token->Flags & Flag::STRUCTURAL_BRACE)
                    || (
                        $token->Next
                        && $token->Next->id === \T_CLOSE_BRACE
                        && !$token->hasNewlineAfter()
                    )
                ) {
                    continue;
                }

                // Ignore:
                // - non-declarations
                // - `declare` blocks
                // - grouped imports and trait adaptations
                // - property hooks
                // - anonymous functions
                // - anonymous classes declared on one line
                $parts = $token->skipToStartOfDeclaration()->declarationParts();
                if (
                    !$parts->hasOneFrom($this->Idx->Declaration)
                    || ($first = $parts->first())->id === \T_DECLARE
                    || $first->id === \T_USE
                    || $token->inPropertyOrPropertyHook()
                    || $parts->last()
                             ->skipPrevSiblingFrom($this->Idx->Ampersand)
                             ->id === \T_FUNCTION
                    || ($first->id === \T_NEW && !$parts->hasNewlineBetweenTokens())
                ) {
                    continue;
                }

                $token->Whitespace |= Space::LINE_BEFORE;
            } elseif ($token->id === \T_QUESTION) {
                if (!($token->Flags & Flag::TERNARY)) {
                    continue;
                }

                $other = $token->Data[Data::OTHER_TERNARY];
                if ($other === $token->Next) {
                    continue;
                }

                $op1Newline = $token->hasNewlineBefore();
                $op2Newline = $other->hasNewlineBefore();
                if ($op1Newline && !$op2Newline) {
                    $other->Whitespace |= Space::LINE_BEFORE;
                } elseif (!$op1Newline && $op2Newline) {
                    $token->Whitespace |= Space::LINE_BEFORE;
                }
            } elseif ($token->id === \T_USE) {
                if (
                    ($close = $token->PrevCode)
                    && $close->id === \T_CLOSE_PARENTHESIS
                    && !$close->hasNewlineAfterPrevCode()
                    && $close->outer()->hasNewline()
                ) {
                    /** @var Token */
                    $open = $close->OpenBracket;
                    /** @var TokenCollection */
                    $items = $open->Data[Data::LIST_ITEMS];
                    $items->add($close)
                          ->applyTokenWhitespace(Space::LINE_BEFORE);

                    /** @var Token */
                    $open = $token->NextCode;
                    if (
                        !$open->hasNewlineBeforeNextCode()
                        && $open->outer()->hasNewline()
                    ) {
                        /** @var Token */
                        $close = $open->CloseBracket;
                        /** @var TokenCollection */
                        $items = $open->Data[Data::LIST_ITEMS];
                        $items->add($close)
                              ->applyTokenWhitespace(Space::LINE_BEFORE);
                    }
                }
            } else {
                if ($token !== $token->Data[Data::CHAIN]) {
                    continue;
                }

                $chain = $token->withNextSiblingsFrom($this->Idx->ChainPart)
                               ->getAnyFrom($this->Idx->Chain);

                if (
                    $chain->count() < 2
                    || !$chain->tokenHasNewlineBefore()
                ) {
                    continue;
                }

                if (
                    $this->AlignChainsEnabled
                    && !$this->Formatter->Psr12
                ) {
                    $chain = $chain->shift();
                }

                $chain->setTokenWhitespace(Space::LINE_BEFORE);
            }
        }
    }

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * If interface lists break over multiple lines and neither `StrictLists`
     * nor `AlignLists` are enabled, a newline is added before the first
     * interface.
     *
     * Arrays and argument lists with trailing ("magic") commas are split into
     * one item per line.
     *
     * If parameter lists have one or more attributes with a trailing newline,
     * every attribute is placed on its own line, and blank lines are added
     * before and after annotated parameters to improve readability.
     */
    public function processList(Token $parent, TokenCollection $items, Token $lastChild): void
    {
        if (!$parent->CloseBracket) {
            if (
                !$this->ListRuleEnabled
                && ($parent->id === \T_EXTENDS || $parent->id === \T_IMPLEMENTS)
                && $items->tokenHasNewlineBefore()
            ) {
                /** @var Token */
                $token = $items->first();
                $token->applyWhitespace(Space::LINE_BEFORE);
            }
            return;
        }

        if ($lastChild->id === \T_COMMA) {
            $parent->Whitespace |= Space::LINE_AFTER;
            $items->add($parent->CloseBracket)
                  ->applyTokenWhitespace(Space::CRITICAL_LINE_BEFORE);
        }

        if ($parent->id === \T_OPEN_PARENTHESIS && $parent->isParameterList()) {
            $this->normaliseDeclarationList($items);
        }
    }

    /**
     * Apply the rule to the given declarations
     *
     * Newlines are added between comma-delimited constant declarations and
     * property declarations. When neither `StrictLists` nor `AlignLists` are
     * enabled, they are also added to `use` statements between comma-delimited
     * imports and trait insertions that break over multiple lines.
     *
     * If a list of property hooks has one or more attributes with a trailing
     * newline, every attribute is placed on its own line, and blank lines are
     * added before and after annotated hooks to improve readability.
     */
    public function processDeclarations(array $declarations): void
    {
        foreach ($declarations as $token) {
            $type = $token->Data[Data::DECLARATION_TYPE];

            if (
                $type === Type::_CONST
                || $type === Type::PROPERTY
                || $type & Type::_USE
            ) {
                $commas = $token->withNextSiblings($token->EndStatement)
                                ->getAnyOf(\T_COMMA);
                if (!($type & Type::_USE) || (
                    !$this->ListRuleEnabled
                    && $commas->tokenHasNewlineBeforeNextCode()
                )) {
                    $commas->setTokenWhitespace(Space::LINE_AFTER);
                }
            }

            if ($type & Type::PROPERTY) {
                /** @var TokenCollection */
                $hooks = $token->Data[Data::PROPERTY_HOOKS];
                if ($hooks->count()) {
                    $this->normaliseDeclarationList($hooks);
                }
            }
        }
    }

    /**
     * @param iterable<Token> $items
     */
    private function normaliseDeclarationList(iterable $items): void
    {
        $hasAttributeWithNewline = false;
        foreach ($items as $item) {
            $attributes = $item->withNextSiblingsFrom($this->Idx->Attribute, true);
            $itemTokens[$item->index] = $attributes;
            if (
                $attributes->tokenHasNewlineAfter(true)
                || $attributes->shift()->tokenHasNewlineBefore()
            ) {
                $hasAttributeWithNewline = true;
                break;
            }
        }
        if (!$hasAttributeWithNewline) {
            return;
        }

        $addBlankBefore = false;
        $i = 0;
        foreach ($items as $item) {
            if ($addBlankBefore) {
                $item->applyBlankBefore(true);
                $addBlankBefore = false;
                $hasBlankBefore = true;
            } else {
                $hasBlankBefore = false;
            }
            $tokens = $itemTokens[$item->index]
                ?? $item->withNextSiblingsFrom($this->Idx->Attribute, true);
            $tokens[] = $item->skipNextSiblingFrom($this->Idx->Attribute);
            foreach ($tokens as $token) {
                $token->applyWhitespace(Space::LINE_BEFORE);
                if ($this->Idx->Attribute[$token->id]) {
                    $token = $token->CloseBracket ?? $token;
                    $token->Whitespace |= Space::LINE_AFTER;
                    // Add a blank line before each item with an attribute, and
                    // another before the next item
                    $addBlankBefore = true;
                }
            }
            if ($i++ && $addBlankBefore && !$hasBlankBefore) {
                $item->applyBlankBefore(true);
            }
        }
    }
}
