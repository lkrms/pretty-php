<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask as Mask;
use Lkrms\PrettyPHP\Catalog\TokenSubId as SubId;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Contract\HasTokenNames;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Salient\Utility\Str;
use Closure;
use JsonSerializable;

/**
 * @api
 */
final class Token extends GenericToken implements HasTokenNames, JsonSerializable
{
    /**
     * The starting column number (1-based) of the token
     */
    public int $column = -1;

    /**
     * The token's position (0-based) in an array of token objects
     */
    public int $index = -1;

    /**
     * @internal
     *
     * @var SubId::*|-1|null
     */
    public ?int $subId = null;

    public ?self $Prev = null;
    public ?self $Next = null;
    public ?self $PrevCode = null;
    public ?self $NextCode = null;
    public ?self $PrevSibling = null;
    public ?self $NextSibling = null;
    public ?self $OpenTag = null;
    public ?self $CloseTag = null;
    public ?self $Statement = null;
    public ?self $EndStatement = null;
    public ?self $OpenBracket = null;
    public ?self $CloseBracket = null;
    public ?self $Parent = null;
    public int $Depth = 0;
    public ?self $String = null;
    public ?self $Heredoc = null;
    public int $Flags = 0;

    /**
     * @var array<Data::*,mixed>
     * @phpstan-var array{self,self|null,self|null,self,bool,TokenCollection,int,TokenCollection,array{TokenCollection,TokenCollection,TokenCollection,TokenCollection,TokenCollection},self,self,self,int,TokenCollection,int,self,string,Closure[]}
     */
    public array $Data;

    /**
     * The original content of the token after expanding tabs, or null if there
     * were no tabs to expand
     */
    public ?string $ExpandedText = null;

    /**
     * The original content of the token, or null if it has not been modified
     */
    public ?string $OriginalText = null;

    public Formatter $Formatter;
    public AbstractTokenIndex $Idx;
    public int $Whitespace = 0;
    public ?int $TagIndent = null;
    public int $PreIndent = 0;
    public int $Indent = 0;
    public int $Deindent = 0;
    public int $HangingIndent = 0;
    public int $LinePadding = 0;
    public int $LineUnpadding = 0;
    public int $Padding = 0;
    public ?self $AlignedWith = null;

    /**
     * @internal
     */
    public ?string $HeredocIndent = null;

    public int $OutputLine = -1;
    public int $OutputPos = -1;
    public int $OutputColumn = -1;

    /**
     * @return list<static>
     */
    public static function tokenize(
        string $code,
        int $flags = 0,
        Filter ...$filters
    ): array {
        /** @var list<static> */
        $tokens = parent::tokenize($code, $flags);
        return $tokens && $filters
            ? self::filter($tokens, $filters)
            : $tokens;
    }

    /**
     * Same as tokenize(), but returns lower-cost GenericToken instances
     *
     * @return list<GenericToken>
     */
    public static function tokenizeForComparison(
        string $code,
        int $flags = 0,
        Filter ...$filters
    ): array {
        /** @var list<GenericToken> */
        $tokens = GenericToken::tokenize($code, $flags);
        return $tokens && $filters
            ? self::filter($tokens, $filters)
            : $tokens;
    }

    /**
     * @template T of GenericToken
     *
     * @param non-empty-list<T> $tokens
     * @param non-empty-array<Filter> $filters
     * @return list<T>
     */
    private static function filter(array $tokens, array $filters): array
    {
        foreach ($filters as $filter) {
            $tokens = $filter->filterTokens($tokens);
            if (!$tokens) {
                break;
            }
        }
        return $tokens;
    }

    /**
     * @inheritDoc
     */
    public function getTokenName(): ?string
    {
        return parent::getTokenName() ?? self::TOKEN_NAME[$this->id] ?? null;
    }

    /**
     * Get the line number (1-based) of the token's last character
     */
    public function getEndLine(): int
    {
        $text = $this->OriginalText ?? $this->text;
        return $this->line + substr_count($text, "\n");
    }

    /**
     * Get the position (0-based) of the token's last character
     */
    public function getEndPos(): int
    {
        $text = $this->OriginalText ?? $this->text;
        return $this->pos + strlen($text);
    }

    /**
     * Get the column number (1-based) of the token's last character, or -1 if
     * its starting column is unknown
     */
    public function getEndColumn(): int
    {
        if ($this->column < 1) {
            return -1;
        }

        $text = $this->OriginalText ?? $this->text;
        return ($pos = mb_strrpos($text, "\n")) === false
            ? $this->column + mb_strlen($text)
            : mb_strlen($text) - $pos;
    }

    /**
     * Expand leading tabs in the content of the token
     */
    public function expandText(bool $forOutput = false): string
    {
        if ($this->ExpandedText === null) {
            return $this->text;
        }

        return Str::expandLeadingTabs(
            $this->text,
            $forOutput || !$this->Formatter->Indentation
                ? $this->Formatter->TabSize
                : $this->Formatter->Indentation->TabSize,
            !$this->wasFirstOnLine(),
            $this->column,
        );
    }

    /**
     * Set the content of the token
     */
    public function setText(string $text): void
    {
        if ($this->text !== $text) {
            if ($this->OriginalText === null) {
                $this->OriginalText = $this->text;
            }
            $this->text = $text;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return TokenUtil::serialize($this);
    }

    // Navigation methods:

    /**
     * Get the previous non-virtual token
     */
    public function prevReal(): ?self
    {
        return $this->Prev && $this->Idx->Virtual[$this->Prev->id]
            ? $this->Prev->Data[Data::PREV_REAL]
            : $this->Prev;
    }

    /**
     * Get the next non-virtual token
     */
    public function nextReal(): ?self
    {
        return $this->Next && $this->Idx->Virtual[$this->Next->id]
            ? $this->Next->Data[Data::NEXT_REAL]
            : $this->Next;
    }

    /**
     * Get the previous sibling with the given token ID
     */
    public function prevSiblingOf(int $id, bool $sameStatement = false): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($sameStatement && $t->Statement !== $this->Statement) {
                break;
            }
            if ($t->id === $id) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling with the given token ID
     */
    public function nextSiblingOf(int $id, bool $sameStatement = false): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($sameStatement && $t->Statement !== $this->Statement) {
                break;
            }
            if ($t->id === $id) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is in an index
     *
     * @param array<int,bool> $index
     */
    public function prevSiblingFrom(array $index, bool $sameStatement = false): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($sameStatement && $t->Statement !== $this->Statement) {
                break;
            }
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is in an index
     *
     * @param array<int,bool> $index
     */
    public function nextSiblingFrom(array $index, bool $sameStatement = false): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($sameStatement && $t->Statement !== $this->Statement) {
                break;
            }
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Skip to the previous token that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipPrevFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t && $index[$t->id]) {
            $t = $t->Prev;
        }
        return $t ?? $this->null();
    }

    /**
     * Skip to the next token that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipNextFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t && $index[$t->id]) {
            $t = $t->Next;
        }
        return $t ?? $this->null();
    }

    /**
     * Skip to the previous code token that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipPrevCodeFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & Flag::CODE
            ? $this
            : $this->PrevCode;
        while ($t && $index[$t->id]) {
            $t = $t->PrevCode;
        }
        return $t ?? $this->null();
    }

    /**
     * Skip to the next code token that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipNextCodeFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & Flag::CODE
            ? $this
            : $this->NextCode;
        while ($t && $index[$t->id]) {
            $t = $t->NextCode;
        }
        return $t ?? $this->null();
    }

    /**
     * Skip to the previous sibling that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipPrevSiblingFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & Flag::CODE
            ? $this
            : $this->PrevCode;
        while ($t && $index[$t->id]) {
            $t = $t->PrevSibling;
        }
        return $t ?? $this->null();
    }

    /**
     * Skip to the next sibling that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipNextSiblingFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & Flag::CODE
            ? $this
            : $this->NextCode;
        while ($t && $index[$t->id]) {
            $t = $t->NextSibling;
        }
        return $t ?? $this->null();
    }

    /**
     * Get the last reachable token
     */
    public function last(): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this;
        while ($t->Parent) {
            $t = $t->Parent;
        }
        while ($t->Next) {
            $t = $t->NextSibling ?? $t->NextCode ?? $t->Next;
        }
        return $t;
    }

    /**
     * Get the token, or the given value if it's a T_NULL
     *
     * @template T
     *
     * @param T|(Closure(): T) $value
     * @return static|T
     */
    public function or($value)
    {
        if ($this->id !== \T_NULL) {
            return $this;
        }
        if ($value instanceof Closure) {
            return $value();
        }
        return $value;
    }

    private function null(): self
    {
        $token = new self(\T_NULL, '');
        $token->Formatter = $this->Formatter;
        $token->Idx = $this->Idx;
        return $token;
    }

    // Context detection methods:

    /**
     * Check if the token is the colon before an alternative syntax block
     */
    public function isColonAltSyntaxDelimiter(): bool
    {
        $subId = $this->getSubId();
        return $subId === SubId::COLON_ALT_SYNTAX;
    }

    /**
     * Check if the token is the colon after a switch case or label
     */
    public function isColonStatementDelimiter(): bool
    {
        $subId = $this->getSubId();
        return $subId === SubId::COLON_SWITCH_CASE
            || $subId === SubId::COLON_LABEL;
    }

    /**
     * Check if the token is the colon before a type declaration
     */
    public function isColonTypeDelimiter(): bool
    {
        $subId = $this->getSubId();
        return $subId === SubId::COLON_RETURN_TYPE
            || $subId === SubId::COLON_ENUM_TYPE;
    }

    /**
     * Get the sub-id of a T_COLON, T_QUESTION or T_USE token
     *
     * @return SubId::*|-1
     */
    public function getSubId(): int
    {
        if ($this->subId !== null) {
            return $this->subId;
        }

        switch ($this->id) {
            case \T_COLON:
                return $this->subId = $this->getColonSubId();

            case \T_QUESTION:
                return $this->subId = $this->getQuestionSubId();

            default:
                return $this->subId = -1;
        }
    }

    /**
     * @return SubId::COLON_*
     */
    private function getColonSubId(): int
    {
        /** @var self */
        $prevCode = $this->PrevCode;

        if (
            $this->CloseBracket
            || $prevCode->id === \T_ELSE
            || (
                $prevCode->id === \T_CLOSE_PARENTHESIS
                && ($prev = $prevCode->PrevSibling)
                && $this->Idx->AltStartOrContinue[$prev->id]
            )
        ) {
            return SubId::COLON_ALT_SYNTAX;
        }

        if (
            $this->Parent
            && $this->Parent->id === \T_OPEN_PARENTHESIS
            && $prevCode->id === \T_STRING
            && ($prev = $prevCode->PrevCode)
            && ($prev === $this->Parent || $prev->id === \T_COMMA)
        ) {
            return SubId::COLON_NAMED_ARGUMENT;
        }

        if (
            $prevCode->id === \T_STRING
            && ($prev = $prevCode->PrevCode)
            && $prev->id === \T_ENUM
        ) {
            return SubId::COLON_ENUM_TYPE;
        }

        if ($this->isColonReturnTypeDelimiter()) {
            return SubId::COLON_RETURN_TYPE;
        }

        if ($this->endOfSwitchCase() === $this) {
            return SubId::COLON_SWITCH_CASE;
        }

        if (
            $prevCode->id === \T_STRING
            && ($prev = $prevCode->PrevSibling)
            && $prev->id === \T_COLON
        ) {
            $subId = $prev->getSubId();
            if (
                $subId === SubId::COLON_ALT_SYNTAX
                || $subId === SubId::COLON_SWITCH_CASE
                || $subId === SubId::COLON_LABEL
            ) {
                return SubId::COLON_LABEL;
            }
        }

        if ($prevCode->id === \T_STRING && (
            !($prev = $prevCode->PrevSibling) || (
                $prev->id === \T_SEMICOLON
                || $prev->Flags & Flag::TERMINATOR
                || (
                    $prev->CloseBracket
                    && $prev->CloseBracket->Flags & Flag::TERMINATOR
                )
                || $this->Idx->HasOptionalBraces[$prev->id]
                || (
                    $prev->id === \T_OPEN_PARENTHESIS
                    && $prev->PrevSibling
                    && $this->Idx->HasOptionalBracesWithExpression[$prev->PrevSibling->id]
                )
            )
        )) {
            return SubId::COLON_LABEL;
        }

        return SubId::COLON_TERNARY;
    }

    private function isColonReturnTypeDelimiter(): bool
    {
        /** @var self */
        $prevCode = $this->PrevCode;
        if ($prevCode->id === \T_CLOSE_PARENTHESIS) {
            $prev = $prevCode->PrevSibling;
            if (
                $prev
                && $prev->id === \T_USE
                && $prev->PrevCode
                && $prev->PrevCode->id === \T_CLOSE_PARENTHESIS
            ) {
                $prev = $prev->PrevCode->PrevSibling;
            }

            if ($prev) {
                $prev = $prev->skipPrevSiblingFrom($this->Idx->FunctionIdentifier);
                if ($this->Idx->FunctionOrFn[$prev->id]) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return SubId::QUESTION_*
     */
    private function getQuestionSubId(): int
    {
        /** @var self */
        $prevCode = $this->PrevCode;
        /** @var self */
        $statement = $this->Statement;
        if (
            // Nullable variable types (and constant types, as of PHP 8.3)
            (
                $this->Idx->NonMethodMember[$prevCode->id]
                && $statement->Flags & Flag::DECLARATION
            )
            // Nullable return types
            || ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter())
            // Nullable parameter types
            || $this->inParameterList()
        ) {
            return SubId::QUESTION_NULLABLE;
        }

        return SubId::QUESTION_TERNARY;
    }

    public function continuesControlStructure(): bool
    {
        return $this->Idx->ContinuesControlStructure[$this->id]
            || ($this->id === \T_WHILE && $this->Statement !== $this);
    }

    public function isArrayOpenBracket(): bool
    {
        if ($this->id === \T_OPEN_PARENTHESIS) {
            return ($prev = $this->PrevCode)
                && $prev->id === \T_ARRAY;
        }
        return $this->id === \T_OPEN_BRACKET && (
            !($prev = $this->PrevCode)
            || !$this->Idx->EndOfDereferenceable[$prev->id]
            || $prev->Flags & Flag::STRUCTURAL_BRACE
        );
    }

    public function isUnaryOperator(): bool
    {
        return $this->Idx->Unary[$this->id] || (
            $this->Idx->PlusOrMinus[$this->id]
            && $this->inUnaryContext()
        );
    }

    public function inUnaryContext(): bool
    {
        return $this->Statement === $this
            || !($prev = $this->PrevCode)
            || $this->Idx->BeforeUnary[$prev->id]
            || $prev->Flags & Flag::TERNARY;
    }

    /**
     * Check if the token is in a parameter list
     */
    public function inParameterList(): bool
    {
        return $this->Parent && $this->Parent->isParameterList();
    }

    /**
     * Check if the token is the open parenthesis of a parameter list in a
     * function, arrow function or property hook
     */
    public function isParameterList(): bool
    {
        return $this->id === \T_OPEN_PARENTHESIS
            && ($prev = $this->PrevSibling)
            && ($this->Idx->FunctionOrFn[
                ($prev2 = $prev->skipPrevSiblingFrom($this->Idx->FunctionIdentifier))->id
            ] || (
                $prev->id === \T_STRING
                && (
                    $prev2->id === \T_NULL
                    || $prev2->skipPrevSiblingFrom($this->Idx->AttributeOrModifier)->id === \T_NULL
                )
                && ($parent = $this->Parent)
                && $parent->id === \T_OPEN_BRACE
                && $parent->Statement
                && $parent->Statement->isProperty()
            ));
    }

    /**
     * Get the last token in the statement list of a switch case, or null if the
     * token is not part of a case or default statement in a switch
     */
    public function endOfSwitchCaseStatementList(): ?self
    {
        if (!$this->EndStatement || !$this->inSwitchCase()) {
            return null;
        }

        $t = $this->EndStatement;
        while (
            ($next = $t->NextSibling)
            && !$this->Idx->CaseOrDefault[$next->id]
        ) {
            /** @var self */
            $t = $next->EndStatement;
        }
        return $t;
    }

    /**
     * Check if the token is in a case or default statement in a switch
     */
    public function inSwitchCase(): bool
    {
        return $this->inSwitch() && (
            $this->Idx->CaseOrDefault[$this->id] || (
                ($end = $this->getEndOfSwitchCase())
                && $this->index <= $end->index
            )
        );
    }

    /**
     * Get the last token in the switch case, or null if the token is not part
     * of a case or default statement in a switch
     */
    public function endOfSwitchCase(): ?self
    {
        return $this->inSwitch()
            && ($end = $this->getEndOfSwitchCase())
            && $this->index <= $end->index
                ? $end
                : null;
    }

    private function getEndOfSwitchCase(): ?self
    {
        $t = $this->prevSiblingFrom($this->Idx->CaseOrDefault);
        if ($t->id === \T_NULL) {
            return null;
        }

        $ternaryCount = 0;
        do {
            $t = $t->nextSiblingFrom($this->Idx->SwitchCaseDelimiterOrTernary);
            if ($t->id === \T_NULL) {
                return null;
            } elseif ($t->id === \T_QUESTION) {
                /** @var self */
                $prev = $t->PrevCode;
                if (
                    $prev->id !== \T_COLON
                    || !$prev->isColonReturnTypeDelimiter()
                ) {
                    $ternaryCount++;
                }
                continue;
            } elseif ($t->id === \T_COLON) {
                if (
                    $t->isColonReturnTypeDelimiter()
                    || $ternaryCount--
                ) {
                    continue;
                }
            }
            break;
        } while (true);

        return $t;
    }

    /**
     * Check if the token is in a switch case list
     */
    public function inSwitch(): bool
    {
        return ($prev = $this->Parent)
            && ($prev = $prev->PrevSibling)
            && ($prev = $prev->PrevSibling)
            && $prev->id === \T_SWITCH;
    }

    /**
     * Check if the token is a comma between the arms of a match expression
     */
    public function isDelimiterBetweenMatchArms(): bool
    {
        return $this->isMatchDelimiter()
            && $this->prevSiblingFrom($this->Idx->CommaOrDoubleArrow)->id === \T_DOUBLE_ARROW;
    }

    /**
     * Check if the token is a comma between conditional expressions in a match
     * expression
     */
    public function isDelimiterBetweenMatchExpressions(): bool
    {
        return $this->isMatchDelimiter()
            && $this->prevSiblingFrom($this->Idx->CommaOrDoubleArrow)->id !== \T_DOUBLE_ARROW;
    }

    /**
     * Check if the token is a comma in a match expression
     */
    public function isMatchDelimiter(): bool
    {
        return $this->id === \T_COMMA
            && $this->Parent
            && $this->Parent->isMatchOpenBrace();
    }

    /**
     * Check if the token is the open brace of a match expression
     */
    public function isMatchOpenBrace(): bool
    {
        return $this->id === \T_OPEN_BRACE
            && ($prev = $this->PrevSibling)
            && ($prev = $prev->PrevSibling)
            && $prev->id === \T_MATCH;
    }

    /**
     * Check if the token is part of a property declaration, promoted
     * constructor parameter or property hook
     */
    public function inPropertyOrPropertyHook(): bool
    {
        return (
            $this->Statement
            && $this->Statement->isProperty()
        ) || $this->inPropertyHook();
    }

    /**
     * Check if the token is part of a property hook
     */
    public function inPropertyHook(): bool
    {
        return ($parent = $this->Parent)
            && $parent->id === \T_OPEN_BRACE
            && $parent->Statement
            && $parent->Statement->isProperty();
    }

    /**
     * Check if the token is the first in a property declaration or promoted
     * constructor parameter
     */
    public function isProperty(): bool
    {
        return $this->Flags & Flag::DECLARATION
            && $this->Data[Data::DECLARATION_TYPE] & Type::PROPERTY;
    }

    /**
     * Check if the token is part of an anonymous function declaration or arrow
     * function
     */
    public function inAnonymousFunctionOrFn(): bool
    {
        return $this->skipToStartOfDeclaration()
                    ->isAnonymousFunctionOrFn();
    }

    private function isAnonymousFunctionOrFn(): bool
    {
        return !($this->Flags & Flag::DECLARATION)
            && $this->Idx->FunctionOrFn[
                $this->skipNextSiblingFrom($this->Idx->AttributeOrStatic)->id
            ];
    }

    /**
     * Check if the token is part of a declaration
     */
    public function inDeclaration(): bool
    {
        return $this->skipToStartOfDeclaration()
                    ->isDeclaration();
    }

    private function isDeclaration(): bool
    {
        return $this->Flags & Flag::DECLARATION
            || $this->Idx->ClassOrFunction[
                $this->skipNextSiblingFrom(
                    $this->Idx->BeforeAnonymousClassOrFunction
                )->id
            ];
    }

    /**
     * Get the previous sibling that is the first part of the declaration to
     * which the token belongs, or the token itself
     */
    public function skipToStartOfDeclaration(): self
    {
        if (
            $this->Idx->OperatorExceptTernaryOrDelimiter[$this->id]
            || $this->Flags & Flag::TERNARY
        ) {
            return $this;
        }

        $t = $this;
        while (
            $t->PrevSibling
            && $t->PrevSibling->Statement === $this->Statement
            && $this->Idx->DeclarationPartWithNewAndBody[$t->PrevSibling->id]
        ) {
            $t = $t->PrevSibling;
        }
        return $t;
    }

    /**
     * Skip to the previous code token that is not subsequent to the T_SEMICOLON
     * of an empty statement
     *
     * The token returns itself if it satisfies the criteria.
     */
    public function skipPrevEmptyStatements(): self
    {
        $t = $this;
        while (
            $t->PrevCode
            && $t->PrevCode->id === \T_SEMICOLON
            && $t->PrevCode->Statement === $t->PrevCode
        ) {
            $t = $t->PrevCode;
        }
        return $t;
    }

    // Position-related methods:

    /**
     * Check if the token was originally at the start of a line
     */
    public function wasFirstOnLine(): bool
    {
        if ($this->id === \T_NULL || $this->Idx->Virtual[$this->id]) {
            return false;
        }
        if (!$this->Prev) {
            return true;
        }
        $prev = $this->Prev->skipPrevFrom($this->Idx->Virtual);
        $text = rtrim($prev->OriginalText ?? $prev->text, "\n");
        $newlines = substr_count($text, "\n");
        return $this->line > ($prev->line + $newlines);
    }

    /**
     * Check if the token was originally at the end of a line
     */
    public function wasLastOnLine(): bool
    {
        if ($this->id === \T_NULL || $this->Idx->Virtual[$this->id]) {
            return false;
        }
        if (!$this->Next) {
            return true;
        }
        $next = $this->Next->skipNextFrom($this->Idx->Virtual);
        $text = rtrim($this->OriginalText ?? $this->text, "\n");
        $newlines = substr_count($text, "\n");
        return ($this->line + $newlines) < $next->line;
    }

    /**
     * Get the sibling of the token closest to the start of the line it will
     * currently render on
     *
     * The token returns itself if it satisfies the criteria.
     */
    public function firstSiblingAfterNewline(bool $ignoreComments = true): self
    {
        $sol = $this->startOfLine($ignoreComments);
        $t = $this->OpenBracket ?? $this;
        while (
            $t->PrevSibling
            && $t->PrevSibling->index >= $sol->index
        ) {
            $t = $t->PrevSibling;
        }
        return $t;
    }

    /**
     * Get the last sibling between the token and the end of the line it will
     * currently render on
     *
     * The token returns itself if it satisfies the criteria.
     */
    public function lastSiblingBeforeNewline(bool $ignoreComments = true): self
    {
        $eol = $this->endOfLine($ignoreComments);
        $t = $this->CloseBracket ?? $this;
        while (
            $t->NextSibling
            && $t->NextSibling->index <= $eol->index
        ) {
            $t = $t->NextSibling;
        }
        return $t;
    }

    /**
     * Get the token that will currently render at the start of the token's line
     */
    public function startOfLine(bool $ignoreComments = true): self
    {
        $t = $this;
        while (
            !$t->hasNewlineBefore()
            && ($ignoreComments || !(
                $t->Flags & Flag::MULTILINE_COMMENT
                && $t->hasNewline()
            ))
            && $t->id !== \T_END_HEREDOC
            && $t->Prev
        ) {
            $t = $t->Prev;
        }

        return $t;
    }

    /**
     * Get the token that will currently render at the end of the token's line
     */
    public function endOfLine(bool $ignoreComments = true): self
    {
        $t = $this;
        while (
            !$t->hasNewlineAfter()
            && ($ignoreComments || !(
                $t->Flags & Flag::MULTILINE_COMMENT
                && $t->hasNewline()
            ))
            && $t->id !== \T_START_HEREDOC
            && $t->Next
        ) {
            $t = $t->Next;
        }

        return $t;
    }

    /**
     * If the token has an adjacent token on the same line, return it
     */
    public function adjacentBeforeNewline(): ?self
    {
        return ($adjacent = $this->adjacent())
            && $adjacent->index <= $this->endOfLine()->index
                ? $adjacent
                : null;
    }

    /**
     * If, after skipping close brackets and commas in any combination, the
     * token has a subsequent token in the same statement other than its
     * terminator, return it
     */
    public function adjacent(): ?self
    {
        if (
            !$this->Idx->CloseBracketOrComma[$this->id]
            && $this->NextCode
            && $this->Idx->CloseBracketOrComma[$this->NextCode->id]
        ) {
            return $this->NextCode->adjacent();
        }

        $adjacent = $this->skipNextCodeFrom($this->Idx->CloseBracketOrComma);

        if ($adjacent->id === \T_NULL) {
            return null;
        }

        if ($adjacent === $this) {
            $adjacent = $this->NextCode;
            return $adjacent
                && $adjacent->Statement === $this->Statement
                && (
                    $adjacent !== $this->EndStatement
                    || !$this->Idx->StatementDelimiter[$adjacent->id]
                )
                    ? $adjacent
                    : null;
        }

        /** @var self */
        $prev = $adjacent->PrevCode;
        return (
            $adjacent->Statement === $prev->Statement && (
                $adjacent !== $prev->EndStatement
                || !$this->Idx->StatementDelimiter[$adjacent->id]
            )
        ) || (
            $prev->id === \T_COMMA
            && $adjacent->Statement === $adjacent
        )
            ? $adjacent
            : null;
    }

    // Whitespace-related methods:

    public function applyBlankBefore(bool $force = false): void
    {
        $t = $this;
        while (
            !$t->hasBlankBefore()
            && ($prev = $t->Prev)
            && $this->Idx->Comment[$prev->id]
            && $prev->hasNewlineBefore()
            // Newlines are always added after DocBlocks and comments that were
            // originally at the start of a line
            && ($prev->id === \T_DOC_COMMENT || (
                $prev->wasFirstOnLine()
                && $prev->column <= $this->column
            ))
            && ($t === $this || (
                !($t->Flags & Flag::MULTILINE_COMMENT)
                && ($t->Flags & Mask::COMMENT_TYPE)
                    === ($prev->Flags & Mask::COMMENT_TYPE)
            ))
        ) {
            $t = $prev;
        }
        $t->Whitespace |= Space::BLANK_BEFORE;
        if ($force) {
            $t->removeWhitespace(Space::NO_BLANK_BEFORE);
        }
    }

    /**
     * Apply whitespace to the token after removing conflicting whitespace
     */
    public function applyWhitespace(int $whitespace): void
    {
        // Ignore *_BEFORE if the token is bound to a previous token, *_AFTER if
        // it's bound to a subsequent token
        if ($this->Idx->Virtual[$this->id]) {
            $whitespace &= $this->Data[Data::BOUND_TO]->index < $this->index
                ? 0b111000111000111000111000
                : 0b111000111000111000111;
        }

        // Shift *_BEFORE and *_AFTER to their NO_* counterparts, then clear
        // other bits
        if ($remove = $whitespace << 6 & 0b111111000000) {
            $this->doRemoveWhitespace($remove);
        }

        $this->Whitespace |= $whitespace;
    }

    /**
     * Remove whitespace applied to the token or its neighbours
     *
     * @param int-mask-of<Space::NO_SPACE_BEFORE|Space::NO_LINE_BEFORE|Space::NO_BLANK_BEFORE|Space::NO_SPACE_AFTER|Space::NO_LINE_AFTER|Space::NO_BLANK_AFTER> $whitespace
     */
    public function removeWhitespace(int $whitespace): void
    {
        if ($this->Idx->Virtual[$this->id]) {
            $whitespace &= $this->Data[Data::BOUND_TO]->index < $this->index
                ? 0b111000111000
                : 0b111000111;
        }

        $this->doRemoveWhitespace($whitespace);
    }

    /**
     * @internal
     */
    public function doRemoveWhitespace(int $whitespace): void
    {
        $this->Whitespace &= ~$whitespace;

        if (
            ($before = $whitespace & 0b111000111)
            && ($prev = $this->Prev && $this->Idx->Virtual[$this->Prev->id]
                ? $this->Prev->Data[Data::PREV_REAL]
                : $this->Prev)
        ) {
            $prev->Whitespace &= ~($before << 3);
        }

        if (
            ($after = $whitespace & 0b111000111000)
            && ($next = $this->Next && $this->Idx->Virtual[$this->Next->id]
                ? $this->Next->Data[Data::NEXT_REAL]
                : $this->Next)
        ) {
            $next->Whitespace &= ~($after >> 3);
        }
    }

    /**
     * Check if, between the previous code token and the token, there's a
     * newline between tokens
     */
    public function hasNewlineAfterPrevCode(): bool
    {
        $real = $this->Idx->Virtual[$this->id]
            ? $this->Data[Data::BOUND_TO]
            : $this;
        $t = $this;
        do {
            if (
                $t->getWhitespaceBefore() & (Space::BLANK | Space::LINE)
                || ($this->Idx->Markup[$t->id] && $t->hasNewline())
            ) {
                return true;
            }
            $t = $t->Prev;
        } while ($t && (
            !($t->Flags & Flag::CODE)
            || $t === $real
            || (
                $this->Idx->Virtual[$t->id]
                && $t->Data[Data::BOUND_TO] === $real
            )
        ));

        return false;
    }

    /**
     * Check if, between the token and the next code token, there's a newline
     * between tokens
     */
    public function hasNewlineBeforeNextCode(): bool
    {
        $real = $this->Idx->Virtual[$this->id]
            ? $this->Data[Data::BOUND_TO]
            : $this;
        $t = $this;
        do {
            if (
                $t->getWhitespaceAfter() & (Space::BLANK | Space::LINE)
                || ($this->Idx->Markup[$t->id] && $t->hasNewline())
            ) {
                return true;
            }
            $t = $t->Next;
        } while ($t && (
            !($t->Flags & Flag::CODE)
            || $t === $real
            || (
                $this->Idx->Virtual[$t->id]
                && $t->Data[Data::BOUND_TO] === $real
            )
        ));

        return false;
    }

    public function hasNewlineBefore(): bool
    {
        return (bool) ($this->getWhitespaceBefore() & (Space::BLANK | Space::LINE));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool) ($this->getWhitespaceAfter() & (Space::BLANK | Space::LINE));
    }

    public function hasBlankBefore(): bool
    {
        return (bool) ($this->getWhitespaceBefore() & Space::BLANK);
    }

    public function hasBlankAfter(): bool
    {
        return (bool) ($this->getWhitespaceAfter() & Space::BLANK);
    }

    public function getWhitespaceBefore(): int
    {
        $real = $this->Idx->Virtual[$this->id]
            ? $this->Data[Data::BOUND_TO]
            : $this;
        $prev = $real->Prev && $this->Idx->Virtual[$real->Prev->id]
            ? $real->Prev->Data[Data::PREV_REAL]
            : $real->Prev;

        return $prev
            ? (
                $real->Whitespace >> 12        // CRITICAL_*_BEFORE
                | $prev->Whitespace >> 15      // CRITICAL_*_AFTER
                | ((
                    $real->Whitespace >> 0     // *_BEFORE
                    | $prev->Whitespace >> 3   // *_AFTER
                ) & ~(
                    $real->Whitespace >> 6     // NO_*_BEFORE
                    | $real->Whitespace >> 18  // CRITICAL_NO_*_BEFORE
                    | $prev->Whitespace >> 9   // NO_*_AFTER
                    | $prev->Whitespace >> 21  // CRITICAL_NO_*_AFTER
                ))
            ) & 7
            : ($real->Whitespace >> 12 | (
                $real->Whitespace >> 0 & ~(
                    $real->Whitespace >> 6
                    | $real->Whitespace >> 18
                )
            )) & 7;
    }

    public function getWhitespaceAfter(): int
    {
        $real = $this->Idx->Virtual[$this->id]
            ? $this->Data[Data::BOUND_TO]
            : $this;
        $next = $real->Next && $this->Idx->Virtual[$real->Next->id]
            ? $real->Next->Data[Data::NEXT_REAL]
            : $real->Next;

        return $next
            ? (
                $real->Whitespace >> 15        // CRITICAL_*_AFTER
                | $next->Whitespace >> 12      // CRITICAL_*_BEFORE
                | ((
                    $real->Whitespace >> 3     // *_AFTER
                    | $next->Whitespace >> 0   // *_BEFORE
                ) & ~(
                    $real->Whitespace >> 9     // NO_*_AFTER
                    | $real->Whitespace >> 21  // CRITICAL_NO_*_AFTER
                    | $next->Whitespace >> 6   // NO_*_BEFORE
                    | $next->Whitespace >> 18  // CRITICAL_NO_*_BEFORE
                ))
            ) & 7
            : ($real->Whitespace >> 15 | (
                $real->Whitespace >> 3 & ~(
                    $real->Whitespace >> 9
                    | $real->Whitespace >> 21
                )
            )) & 7;
    }

    /**
     * Check if the token has a newline character
     */
    public function hasNewline(): bool
    {
        return strpos($this->text, "\n") !== false;
    }

    /**
     * Get the indentation level of the token
     */
    public function getIndent(): int
    {
        return $this->TagIndent
            + $this->PreIndent
            + $this->Indent
            + $this->HangingIndent
            - $this->Deindent;
    }

    /**
     * Render the indentation level of the token as whitespace
     */
    public function renderIndent(bool $softTabs = false): string
    {
        $indent = $this->TagIndent
            + $this->PreIndent
            + $this->Indent
            + $this->HangingIndent
            - $this->Deindent;

        return $indent
            ? str_repeat(
                $softTabs
                    ? $this->Formatter->SoftTab
                    : $this->Formatter->Tab,
                $indent
            )
            : '';
    }

    /**
     * Get the difference in output column between the token and a given token
     */
    public function getColumnDelta(self $token, bool $beforeText): int
    {
        return $token->getOutputColumn($beforeText) - $this->getOutputColumn($beforeText);
    }

    /**
     * Get the output column of the token
     */
    public function getOutputColumn(bool $beforeText): int
    {
        return $beforeText
            ? ($this->Prev
                ? $this->Prev->getNextOutputColumn(true)
                : 1)
            : $this->getNextOutputColumn(false);
    }

    private function getNextOutputColumn(bool $afterWhitespace): int
    {
        $sol = $this->startOfLine(false);
        $line = $sol->collect($this)->render(true, false);
        if ($afterWhitespace) {
            /** @var self */
            $next = $this->Next;
            $line .= $this->Formatter->Renderer->renderWhitespaceBefore($next, true);
        }
        $delta = 0;
        if (($pos = strrpos($line, "\n")) !== false) {
            $line = substr($line, $pos + 1);
        } elseif ($sol->id === \T_END_HEREDOC) {
            $delta += $sol->getIndent() * $this->Formatter->TabSize
                + $sol->LinePadding
                - $sol->LineUnpadding;
        }
        return mb_strlen($line) + $delta + 1;
    }

    // Collection methods:

    /**
     * Get the token and any subsequent tokens that could be part of a
     * non-anonymous declaration
     */
    public function namedDeclarationParts(): TokenCollection
    {
        return $this->getDeclarationParts(false);
    }

    /**
     * Get the token and any subsequent tokens that could be part of a
     * declaration
     */
    public function declarationParts(): TokenCollection
    {
        return $this->getDeclarationParts(true);
    }

    private function getDeclarationParts(bool $allowAnonymous): TokenCollection
    {
        $index = $allowAnonymous
            ? $this->Idx->DeclarationPartWithNew
            : $this->Idx->DeclarationPart;

        if (!$index[$this->id]) {
            return new TokenCollection();
        }

        $t = $this;
        while (
            ($next = $t->NextSibling) && (
                $index[$next->id] || (
                    $allowAnonymous
                    && $t->id === \T_CLASS
                    && $next->id === \T_OPEN_PARENTHESIS
                )
            )
        ) {
            $t = $next;
        }

        if (
            !$allowAnonymous
            && $t->skipPrevSiblingFrom($this->Idx->Ampersand)->id === \T_FUNCTION
        ) {
            return new TokenCollection();
        }

        return $this->withNextSiblings($t);
    }

    /**
     * Get the token and any previous tokens in the same statement
     */
    public function sinceStatement(): TokenCollection
    {
        return ($this->Statement ?? $this)
                   ->collect($this);
    }

    /**
     * Get the token and any inner tokens
     */
    public function outer(): TokenCollection
    {
        return ($this->OpenBracket ?? $this)
                   ->collect($this->CloseBracket ?? $this);
    }

    /**
     * Get the token's inner tokens
     */
    public function inner(): TokenCollection
    {
        $from = ($this->OpenBracket ?? $this)->Next;
        $to = ($this->CloseBracket ?? $this)->Prev;
        return $from && $to && $from->index <= $to->index
            ? $from->collect($to)
            : new TokenCollection();
    }

    /**
     * Get the token's inner siblings
     */
    public function children(): TokenCollection
    {
        $from = ($this->OpenBracket ?? $this)->NextCode;
        $to = ($this->CloseBracket ?? $this)->PrevCode;
        return $from && $to && $from->index <= $to->index
            ? $from->withNextSiblings($to)
            : new TokenCollection();
    }

    /**
     * Get the token and any subsequent tokens up to and including a given token
     */
    public function collect(self $to): TokenCollection
    {
        return TokenCollection::collect($this, $to);
    }

    /**
     * Get the token and any subsequent siblings, optionally stopping at a given
     * sibling
     */
    public function withNextSiblings(?self $to = null): TokenCollection
    {
        if (
            $this->id === \T_NULL
            || ($to && $to->id === \T_NULL)
        ) {
            return new TokenCollection();
        }
        $t = $this->OpenBracket ?? $this;
        if ($to) {
            if ($this->Parent !== $to->Parent) {
                return new TokenCollection();
            }
            $to = $to->OpenBracket ?? $to;
            if ($this->index > $to->index) {
                return new TokenCollection();
            }
        }
        do {
            $tokens[] = $t;
            if ($to && $t === $to) {
                break;
            }
        } while ($t = $t->NextSibling);

        return new TokenCollection($tokens);
    }

    /**
     * Get the token and any subsequent siblings, up to but not including the
     * first that isn't in an index
     *
     * @param array<int,bool> $index
     * @param bool $testToken Only add the token to the collection if it is in
     * the index?
     */
    public function withNextSiblingsFrom(array $index, bool $testToken = false): TokenCollection
    {
        if ($this->id === \T_NULL) {
            return new TokenCollection();
        }
        $t = $this->OpenBracket ?? $this;
        if (!$testToken) {
            $tokens[] = $t;
            $t = $t->NextSibling;
        }
        while ($t && $index[$t->id]) {
            $tokens[] = $t;
            $t = $t->NextSibling;
        }

        return new TokenCollection($tokens ?? []);
    }
}
