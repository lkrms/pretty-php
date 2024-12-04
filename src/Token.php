<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\DeclarationType;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask;
use Lkrms\PrettyPHP\Catalog\TokenSubId;
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
     * @var TokenSubId::*|-1|null
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

    /**
     * NULL if the token is an expression delimiter
     */
    public ?self $Expression = null;

    /**
     * NULL if the token is a statement delimiter
     */
    public ?self $EndExpression = null;

    public ?self $OpenBracket = null;
    public ?self $CloseBracket = null;
    public ?self $Parent = null;
    public int $Depth = 0;
    public ?self $String = null;
    public ?self $Heredoc = null;
    public int $Flags = 0;

    /**
     * @var array<TokenData::*,mixed>
     * @phpstan-var array{string,TokenCollection,int,self,self,self,self,TokenCollection,int,TokenCollection,int}
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
    public TokenIndex $Idx;
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
     * The token on behalf of which a level of hanging indentation was most
     * recently applied to the token
     *
     * @internal
     */
    public ?self $HangingIndentToken = null;

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * @internal
     *
     * @var array<array{self|null,1?:self}>
     */
    public array $HangingIndentContext = [];

    /**
     * Parent tokens associated with hanging indentation applied to the token
     *
     * @internal
     *
     * @var self[]
     */
    public array $HangingIndentParent = [];

    /**
     * Each entry represents a parent token associated with at least one level
     * of collapsible indentation applied to the token
     *
     * Parent token index => levels of collapsible indentation applied
     *
     * @internal
     *
     * @var array<int,int>
     */
    public array $HangingIndentParentLevels = [];

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
        $t = $this->Flags & TokenFlag::CODE
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
        $t = $this->Flags & TokenFlag::CODE
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
        $t = $this->Flags & TokenFlag::CODE
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
        $t = $this->Flags & TokenFlag::CODE
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
     * Get the token, or the given token if it's a T_NULL
     *
     * @param self|(Closure(): self) $token
     */
    public function or($token): self
    {
        if ($this->id !== \T_NULL) {
            return $this;
        }
        if ($token instanceof Closure) {
            return $token();
        }
        return $token;
    }

    /**
     * Get a new T_NULL token
     */
    public function null(): self
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
        return $subId === TokenSubId::COLON_ALT_SYNTAX_DELIMITER;
    }

    /**
     * Check if the token is the colon after a switch case or a label
     */
    public function isColonStatementDelimiter(): bool
    {
        $subId = $this->getSubId();
        return $subId === TokenSubId::COLON_SWITCH_CASE_DELIMITER
            || $subId === TokenSubId::COLON_LABEL_DELIMITER;
    }

    /**
     * Check if the token is the colon before a type declaration
     */
    public function isColonTypeDelimiter(): bool
    {
        $subId = $this->getSubId();
        return $subId === TokenSubId::COLON_RETURN_TYPE_DELIMITER
            || $subId === TokenSubId::COLON_BACKED_ENUM_TYPE_DELIMITER;
    }

    /**
     * Get the sub-id of a T_COLON, T_QUESTION or T_USE token
     *
     * @return TokenSubId::*|-1
     */
    public function getSubId(): int
    {
        if ($this->subId !== null) {
            return $this->subId;
        }

        switch ($this->id) {
            case \T_COLON:
                // If it's too early to determine the token's sub-id, assign
                // `null` to resolve it later and return `-1`
                return ($this->subId = $this->getColonSubId()) ?? -1;

            case \T_QUESTION:
                return $this->subId = $this->getQuestionSubId();

            case \T_USE:
                return $this->subId = $this->getUseSubId();

            default:
                // @codeCoverageIgnoreStart
                return $this->subId = -1;
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @return TokenSubId::COLON_*|null
     */
    private function getColonSubId(): ?int
    {
        /** @var self */
        $prevCode = $this->PrevCode;

        if (
            $this->CloseBracket
            || $this->Idx->AltContinueWithNoExpression[$prevCode->id]
            || (
                $prevCode->id === \T_CLOSE_PARENTHESIS
                && ($prev = $prevCode->PrevSibling)
                && $this->Idx->AltStartOrContinueWithExpression[$prev->id]
            )
        ) {
            return TokenSubId::COLON_ALT_SYNTAX_DELIMITER;
        }

        if (
            $this->Parent
            && $this->Parent->id === \T_OPEN_PARENTHESIS
            && $prevCode->id === \T_STRING
            && ($prev = $prevCode->PrevCode)
            && ($prev === $this->Parent || $prev->id === \T_COMMA)
        ) {
            return TokenSubId::COLON_NAMED_ARGUMENT_DELIMITER;
        }

        if ($this->inSwitchCase()) {
            return TokenSubId::COLON_SWITCH_CASE_DELIMITER;
        }

        if (
            $prevCode->id === \T_STRING
            && ($prev = $prevCode->PrevCode)
            && $prev->id === \T_ENUM
        ) {
            return TokenSubId::COLON_BACKED_ENUM_TYPE_DELIMITER;
        }

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
                    return TokenSubId::COLON_RETURN_TYPE_DELIMITER;
                }
            }
        }

        // The remaining possibilities require statements to have been parsed
        if ($prevCode->PrevSibling && !$prevCode->PrevSibling->EndStatement) {
            return null;
        }

        if ($prevCode->id === \T_STRING && (
            !$prevCode->PrevSibling || (
                $prevCode->PrevSibling->EndStatement
                && $prevCode->PrevSibling->EndStatement->NextSibling === $prevCode
            )
        )) {
            return TokenSubId::COLON_LABEL_DELIMITER;
        }

        return TokenSubId::COLON_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubId::QUESTION_*
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
                && $statement->Flags & TokenFlag::NAMED_DECLARATION
            )
            // Nullable return types
            || ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter())
            // Nullable parameter types
            || $this->inParameterList()
        ) {
            return TokenSubId::QUESTION_NULLABLE;
        }

        return TokenSubId::QUESTION_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubId::USE_*
     */
    private function getUseSubId(): int
    {
        if ($this->PrevCode && $this->PrevCode->id === \T_CLOSE_PARENTHESIS) {
            return TokenSubId::USE_VARIABLES;
        }

        if ($this->Parent && $this->Parent->id === \T_OPEN_BRACE) {
            $t = $this->Parent->PrevSibling;
            while ($t && $this->Idx->DeclarationPart[$t->id]) {
                if ($this->Idx->DeclarationClass[$t->id]) {
                    return TokenSubId::USE_TRAIT;
                }
                $t = $t->PrevSibling;
            }
        }

        return TokenSubId::USE_IMPORT;
    }

    public function continuesControlStructure(): bool
    {
        return $this->Idx->ContinuesControlStructure[$this->id]
            || ($this->id === \T_WHILE && $this->Statement !== $this);
    }

    public function isArrayOpenBracket(): bool
    {
        if ($this->id === \T_OPEN_PARENTHESIS) {
            return $this->PrevCode
                && $this->PrevCode->id === \T_ARRAY;
        }
        return $this->id === \T_OPEN_BRACKET && (
            $this->Expression === $this
            || !$this->PrevCode
            || !$this->Idx->EndOfDereferenceable[$this->PrevCode->id]
        );
    }

    public function isUnaryOperator(): bool
    {
        return $this->Idx->OperatorUnary[$this->id] || (
            $this->Idx->PlusOrMinus[$this->id]
            && $this->inUnaryContext()
        );
    }

    public function inUnaryContext(): bool
    {
        return $this->Expression === $this
            || !($prev = $this->PrevCode)
            || $prev->Flags & TokenFlag::TERNARY_OPERATOR
            || $this->Idx->BeforeUnary[$prev->id];
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
     * Check if the token is in a case or default statement in a switch
     */
    public function inSwitchCase(): bool
    {
        return $this->inSwitch() && (
            $this->id === \T_CASE
            || $this->id === \T_DEFAULT
            || ($prev = $this->prevSiblingFrom($this->Idx->SwitchCaseOrDelimiter))->id === \T_CASE
            || $prev->id === \T_DEFAULT
        );
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
        return $this->Flags & TokenFlag::NAMED_DECLARATION
            && $this->Data[TokenData::NAMED_DECLARATION_TYPE] & DeclarationType::PROPERTY;
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
        return !($this->Flags & TokenFlag::NAMED_DECLARATION)
            && $this->Idx->FunctionOrFn[$this->skipNextSiblingFrom($this->Idx->AttributeOrStatic)->id];
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
        return $this->Flags & TokenFlag::NAMED_DECLARATION
            || $this->Idx->ClassOrFunction[
                $this->skipNextSiblingFrom($this->Idx->BeforeAnonymousClassOrFunction)->id
            ];
    }

    /**
     * Get the previous sibling that is the first part of the declaration to
     * which the token belongs, or the token itself
     */
    public function skipToStartOfDeclaration(): self
    {
        if (
            $this->Idx->ExpressionDelimiter[$this->id]
            || $this->Flags & TokenFlag::TERNARY_OPERATOR
        ) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
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

    // Position-related methods:

    /**
     * Check if the token was originally at the start of a line
     */
    public function wasFirstOnLine(): bool
    {
        if ($this->id === \T_NULL) {
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
        if ($this->id === \T_NULL) {
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
     * Get the last sibling between the token and the end of the line it will
     * currently render on
     *
     * The token returns itself if it satisfies the criteria.
     */
    public function lastSiblingBeforeNewline(): self
    {
        $eol = $this->endOfLine();
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
                $t->Flags & TokenFlag::MULTILINE_COMMENT
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
                $t->Flags & TokenFlag::MULTILINE_COMMENT
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
                && $adjacent !== $this->EndStatement
                    ? $adjacent
                    : null;
        }

        /** @var self */
        $prev = $adjacent->PrevCode;
        return (
            $adjacent->Statement === $prev->Statement
            && $adjacent !== $prev->EndStatement
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
                !($t->Flags & TokenFlag::MULTILINE_COMMENT)
                && ($t->Flags & TokenFlagMask::COMMENT_TYPE)
                    === ($prev->Flags & TokenFlagMask::COMMENT_TYPE)
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
        // Shift *_BEFORE and *_AFTER to their NO_* counterparts, then clear
        // other bits
        if ($remove = $whitespace << 6 & 0b111111000000) {
            // @phpstan-ignore argument.type
            $this->removeWhitespace($remove);
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
        $this->Whitespace &= ~$whitespace;
        if ($this->Prev && ($before = $whitespace & 0b0111000111)) {
            $this->Prev->Whitespace &= ~($before << 3);
        }
        if ($this->Next && ($after = $whitespace & 0b111000111000)) {
            $this->Next->Whitespace &= ~($after >> 3);
        }
    }

    /**
     * Check if, between the token and the next code token, there's a newline
     * between tokens
     */
    public function hasNewlineBeforeNextCode(): bool
    {
        if ($this->hasNewlineAfter()) {
            return true;
        }
        if (!$this->NextCode || $this->NextCode === $this->Next) {
            return false;
        }
        $t = $this;
        do {
            /** @var self */
            $t = $t->Next;
            if (
                $t->hasNewlineAfter()
                || ($this->Idx->Markup[$t->id] && $t->hasNewline())
            ) {
                return true;
            }
        } while ($t->Next !== $this->NextCode);

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
        return $this->Prev
            ? (
                $this->Whitespace >> 12              // CRITICAL_*_BEFORE
                | $this->Prev->Whitespace >> 15      // CRITICAL_*_AFTER
                | ((
                    $this->Whitespace >> 0           // *_BEFORE
                    | $this->Prev->Whitespace >> 3   // *_AFTER
                ) & ~(
                    $this->Whitespace >> 6           // NO_*_BEFORE
                    | $this->Whitespace >> 18        // CRITICAL_NO_*_BEFORE
                    | $this->Prev->Whitespace >> 9   // NO_*_AFTER
                    | $this->Prev->Whitespace >> 21  // CRITICAL_NO_*_AFTER
                ))
            ) & 7
            : ($this->Whitespace >> 12 | (
                $this->Whitespace >> 0 & ~(
                    $this->Whitespace >> 6
                    | $this->Whitespace >> 18
                )
            )) & 7;
    }

    public function getWhitespaceAfter(): int
    {
        return $this->Next
            ? (
                $this->Whitespace >> 15              // CRITICAL_*_AFTER
                | $this->Next->Whitespace >> 12      // CRITICAL_*_BEFORE
                | ((
                    $this->Whitespace >> 3           // *_AFTER
                    | $this->Next->Whitespace >> 0   // *_BEFORE
                ) & ~(
                    $this->Whitespace >> 9           // NO_*_AFTER
                    | $this->Whitespace >> 21        // CRITICAL_NO_*_AFTER
                    | $this->Next->Whitespace >> 6   // NO_*_BEFORE
                    | $this->Next->Whitespace >> 18  // CRITICAL_NO_*_BEFORE
                ))
            ) & 7
            : ($this->Whitespace >> 15 | (
                $this->Whitespace >> 3 & ~(
                    $this->Whitespace >> 9
                    | $this->Whitespace >> 21
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

    private function getOutputColumn(bool $beforeText): int
    {
        return $beforeText
            ? ($this->Prev
                ? $this->Prev->getNextOutputColumn(true)
                : 0)
            : $this->getNextOutputColumn(false);
    }

    private function getNextOutputColumn(bool $afterWhitespace): int
    {
        $line = $this->startOfLine(false)->collect($this)->render(true, false);
        if ($afterWhitespace) {
            /** @var self */
            $next = $this->Next;
            $line .= $this->Formatter->Renderer->renderWhitespaceBefore($next, true);
        }
        if (($pos = strrpos($line, "\n")) !== false) {
            $line = substr($line, $pos + 1);
        }
        return mb_strlen($line);
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

    /**
     * Get previous siblings in reverse document order, optionally stopping at a
     * given sibling
     */
    public function prevSiblings(?self $to = null): TokenCollection
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
            if ($this->index < $to->index) {
                return new TokenCollection();
            }
        }
        while ($t = $t->PrevSibling) {
            $tokens[] = $t;
            if ($to && $t === $to) {
                break;
            }
        }

        return new TokenCollection($tokens ?? []);
    }

    // --

    /**
     * Get the token's offset relative to the most recent alignment token or the
     * start of the line, whichever is closest
     *
     * An alignment token is a token where {@see Token::$AlignedWith} is set.
     *
     * Whitespace at the start of the line is ignored.
     *
     * @param bool $includeToken If `true` (the default), the offset includes
     * the token itself.
     * @param bool $allowSelfAlignment If `true`, the token itself is considered
     * an alignment token candidate.
     */
    public function alignmentOffset(bool $includeToken = true, bool $allowSelfAlignment = false): int
    {
        $startOfLine = $this->startOfLine();
        $from = $startOfLine
                    ->collect($this)
                    ->reverse()
                    ->find(
                        fn(self $t, ?self $next) =>
                            ($t->AlignedWith
                                && ($allowSelfAlignment || $t !== $this))
                            || ($next
                                && $next === $this->AlignedWith)
                    ) ?? $startOfLine;

        if ($includeToken) {
            $code = $from->collect($this)->render(true);
        } else {
            /** @var self */
            $prev = $this->Prev;
            $code = $from->collect($prev)->render(true, true, false);
        }
        $offset = mb_strlen($code);
        // Handle strings with embedded newlines
        if (($newline = mb_strrpos($code, "\n")) !== false) {
            $newLinePadding = $offset - $newline - 1;
            $offset = $newLinePadding - ($this->LinePadding - $this->LineUnpadding);
        } else {
            $offset -= $from->hasNewlineBefore() ? $from->LineUnpadding : 0;
        }

        return $offset;
    }

    /**
     * If the token were moved to the right, get the last token that would move
     * with it
     *
     * Statement separators (e.g. `,` and `;`) are not part of expressions and
     * are not returned unless {@see Token::pragmaticEndOfExpression()} is
     * called on them directly.
     *
     * @param bool $containUnenclosed If `true` (the default), braces are
     * imagined around control structures with unenclosed bodies.
     */
    public function pragmaticEndOfExpression(
        bool $containUnenclosed = true,
        bool $containDeclaration = true,
        bool $containTernary = false
    ): self {
        // If the token is a statement terminator, there is nothing else to move
        if (!$this->EndExpression) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        // If the token is part of a declaration with an adjacent body (class,
        // function, interface, etc.), return the token that precedes the
        // opening brace of the body so the body is not moved
        if (
            $containDeclaration
            && $this->Expression
            && ($end = $this->getEndOfDeclaration())
        ) {
            return $end;
        }

        // If the token is an expression delimiter, return the last token in the
        // statement
        if (!$containUnenclosed && !$this->Expression) {
            /** @var self */
            $end = $this->EndStatement;
            return $end === $this
                ? $end
                : $end->withoutTerminator();
        }

        // If the token is an object operator, return the last token in the
        // chain
        if ($this->Idx->Chain[$this->id]) {
            $current = $this;
            do {
                $last = $current;
            } while (
                ($current = $current->NextSibling)
                && $this->Expression === $current->Expression
                && $this->Idx->ChainPart[$current->id]
            );

            return $last->CloseBracket ?: $last;
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the last token before `:`
        $prevTernaryIsColon = null;
        $current = $this;
        while (
            ($current = $current->PrevSibling)
            && $current->Statement === $this->Statement
        ) {
            if ($current->Flags & TokenFlag::TERNARY_OPERATOR) {
                if ($current->id === \T_QUESTION) {
                    $prevTernaryIsColon ??= false;
                    if ($current->Data[TokenData::OTHER_TERNARY_OPERATOR]->index > $this->index) {
                        /** @var self */
                        return $current->Data[TokenData::OTHER_TERNARY_OPERATOR]->PrevCode;
                    }
                    break;
                } else {
                    $prevTernaryIsColon ??= true;
                }
            }
        }

        // If the token is between `:` and `?` in chained ternary expressions,
        // return the last token before `?`
        if ($containTernary && $prevTernaryIsColon) {
            $current = $this;
            while (
                ($current = $current->NextSibling)
                && $current->Statement === $this->Statement
            ) {
                if ($current->Flags & TokenFlag::TERNARY_OPERATOR) {
                    if ($current->id === \T_QUESTION) {
                        /** @var self */
                        return $current->PrevCode;
                    }
                    break;
                }
            }
        }

        // Otherwise, traverse siblings by expression until none remain or an
        // appropriate terminator is found
        $current = $this->OpenBracket ?: $this;
        $inSwitchCase = $current->inSwitchCase();

        while ($current->EndExpression) {
            $current = $current->EndExpression;
            $terminator =
                $current->NextSibling
                && !$current->NextSibling->Expression
                    ? $current->NextSibling
                    : $current;
            $next = $terminator->NextSibling;

            if (!$next) {
                return $current;
            }

            [$last, $current] = [$current, $next];

            // Don't terminate if the token between expressions is a ternary
            // operator or an expression terminator other than `)`, `]` and `;`
            if (
                ($terminator->Flags & TokenFlag::TERNARY_OPERATOR)
                || $this->Idx->ExpressionDelimiter[$terminator->id]
            ) {
                continue;
            }

            // Don't terminate `case` and `default` statements until the next
            // `case` or `default` is reached
            if ($inSwitchCase && $next->id !== \T_CASE && $next->id !== \T_DEFAULT) {
                continue;
            }

            // Don't terminate if the next token continues a control structure
            if ($next->id === \T_CATCH || $next->id === \T_FINALLY) {
                continue;
            }
            if (
                ($next->id === \T_ELSEIF || $next->id === \T_ELSE)
                && (!$containUnenclosed
                    || $terminator->id === \T_CLOSE_BRACE
                    || $terminator->prevSiblingFrom($this->Idx->IfOrElseIf)->index >= $this->index)
            ) {
                continue;
            }
            if (
                $next->id === \T_WHILE
                && $next->Statement !== $next
                && (!$containUnenclosed
                    || $terminator->id === \T_CLOSE_BRACE
                    || ($next->Statement && $next->Statement->index >= $this->index))
            ) {
                continue;
            }

            // Otherwise, terminate
            return $last;
        }

        return $current;
    }

    private function getEndOfDeclaration(): ?self
    {
        $parts = $this->skipToStartOfDeclaration()
                      ->declarationParts();
        if (!$parts->hasOneFrom($this->Idx->DeclarationTopLevel)) {
            return null;
        }

        /** @var self */
        $last = $parts->last();
        if ($last->index < $this->index) {
            return null;
        }

        // Exclude anonymous functions, which can move as needed
        if ($last->skipPrevSiblingFrom($this->Idx->Ampersand)->id === \T_FUNCTION) {
            return null;
        }

        // Exclude anonymous classes with newlines before `class`, i.e.
        //
        // ```php
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
        // vs.:
        //
        // ```php
        // $foo = new class implements
        //     Bar,
        //     Baz
        // {
        //     // ...
        // };
        // ```
        /** @var self */
        $first = $parts->first();
        if ($first->id === \T_NEW && $first->NextCode === $this) {
            /** @var self */
            $class = $parts->getFirstOf(\T_CLASS);
            /** @var self */
            $prev = $class->PrevCode;
            if ($prev->hasNewlineBeforeNextCode()) {
                return null;
            }
        }

        $body = $last->nextSiblingOf(\T_OPEN_BRACE);
        /** @var self */
        $end = $this->EndStatement;
        if ($body->id === \T_NULL || $body->index >= $end->index) {
            return null;
        }

        return $body->PrevCode;
    }

    public function withoutTerminator(): self
    {
        if ($this->PrevCode && (
            $this->Idx->StatementDelimiter[$this->id]
            || $this->Flags & TokenFlag::STATEMENT_TERMINATOR
        )) {
            return $this->PrevCode;
        }
        return $this;
    }

    public function withTerminator(): self
    {
        if ($this->NextCode && !(
            $this->Idx->StatementDelimiter[$this->id]
            || $this->Flags & TokenFlag::STATEMENT_TERMINATOR
        ) && (
            $this->Idx->StatementDelimiter[$this->NextCode->id]
            || $this->NextCode->Flags & TokenFlag::STATEMENT_TERMINATOR
        )) {
            return $this->NextCode;
        }
        return $this;
    }
}
