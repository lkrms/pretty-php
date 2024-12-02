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
use Lkrms\PrettyPHP\Internal\TokenIndentDelta;
use Salient\Utility\Str;
use Closure;
use JsonSerializable;

class Token extends GenericToken implements HasTokenNames, JsonSerializable
{
    /**
     * The starting column number (1-based) of the token
     */
    public int $column = -1;

    /**
     * The token's position (0-based) in an array of token objects
     */
    public int $Index = -1;

    /** @var TokenSubId::*|-1|null */
    public ?int $SubId = null;
    public ?self $Prev = null;
    public ?self $Next = null;
    public ?self $PrevCode = null;
    public ?self $NextCode = null;
    public ?self $PrevSibling = null;
    public ?self $NextSibling = null;
    public ?self $Statement = null;
    public ?self $EndStatement = null;

    /**
     * The token at the start of the token's expression, or null if the token is
     * an expression delimiter
     */
    public ?self $Expression = null;

    /**
     * The token at the end of the token's expression, or null if the token is a
     * statement delimiter
     */
    public ?self $EndExpression = null;

    public ?self $OpenedBy = null;
    public ?self $ClosedBy = null;
    public ?self $Parent = null;
    public int $Depth = 0;
    public ?self $OpenTag = null;
    public ?self $CloseTag = null;
    public ?self $String = null;
    public ?self $Heredoc = null;
    public int $Flags = 0;

    /**
     * @var array<TokenData::*,mixed>
     * @phpstan-var array{string,TokenCollection,int,self,self,self,self,TokenCollection,int,TokenCollection,int}
     */
    public array $Data;

    /**
     * The original content of the token after expanding tabs if CollectColumn
     * found tabs to expand
     */
    public ?string $ExpandedText = null;

    /**
     * The original content of the token if its content was changed by setText()
     */
    public ?string $OriginalText = null;

    /**
     * The formatter to which the token belongs
     */
    public Formatter $Formatter;

    /**
     * Token index
     */
    public TokenIndex $Idx;

    public ?int $TagIndent = null;

    /**
     * Indentation levels ignored until the token is rendered
     *
     * Applied to unenclosed control structure bodies and `switch` constructs.
     */
    public int $PreIndent = 0;

    /**
     * Indentation levels associated with the token's enclosing brackets
     */
    public int $Indent = 0;

    /**
     * Indentation levels removed when the token is rendered, ignored otherwise
     *
     * Applied to `case` and `default` statements in `switch` constructs.
     */
    public int $Deindent = 0;

    /**
     * Indentation levels applied by HangingIndentation
     */
    public int $HangingIndent = 0;

    /**
     * The token on behalf of which a level of hanging indentation was most
     * recently applied to the token
     */
    public ?self $HangingIndentToken = null;

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * @var array<array{self|null,1?:self}>
     */
    public array $HangingIndentContext = [];

    /**
     * Parent tokens associated with hanging indentation applied to the token
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
     * @var array<int,int>
     */
    public array $HangingIndentParentLevels = [];

    public int $LinePadding = 0;
    public int $LineUnpadding = 0;
    public int $Padding = 0;
    public ?string $HeredocIndent = null;
    public ?self $AlignedWith = null;

    /**
     * Bitmask representing whitespace applied between the token and its
     * neighbours
     */
    public int $Whitespace = 0;

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
     * Update the content of the token, setting OriginalText if needed
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

    // Navigation methods:

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
     * Skip to the next sibling that is not in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     */
    public function skipNextSiblingsFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & TokenFlag::CODE
            ? $this
            : $this->NextSibling;
        while ($t && $index[$t->id]) {
            $t = $t->NextSibling;
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
    public function skipPrevSiblingsFrom(array $index): self
    {
        if ($this->id === \T_NULL) {
            return $this;
        }
        $t = $this->Flags & TokenFlag::CODE
            ? $this
            : $this->PrevSibling;
        while ($t && $index[$t->id]) {
            $t = $t->PrevSibling;
        }
        return $t ?? $this->null();
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
     * Get the last reachable token
     */
    public function last(): self
    {
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
     * Get the token, or a fallback if it is T_NULL
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

    // Context-aware methods:

    /**
     * Check if the token is the colon before an alternative syntax block
     */
    public function isColonAltSyntaxDelimiter(): bool
    {
        return $this->getSubId() === TokenSubId::COLON_ALT_SYNTAX_DELIMITER;
    }

    /**
     * Check if the token is the colon after a switch case or a label
     */
    public function isColonStatementDelimiter(): bool
    {
        return $this->getSubId() === TokenSubId::COLON_SWITCH_CASE_DELIMITER
            || $this->SubId === TokenSubId::COLON_LABEL_DELIMITER;
    }

    /**
     * Check if the token is the colon before a type declaration
     */
    public function isColonTypeDelimiter(): bool
    {
        return $this->getSubId() === TokenSubId::COLON_RETURN_TYPE_DELIMITER
            || $this->SubId === TokenSubId::COLON_BACKED_ENUM_TYPE_DELIMITER;
    }

    /**
     * Get the sub-id of a T_COLON, T_QUESTION or T_USE token
     *
     * @return TokenSubId::*|-1
     */
    public function getSubId(): int
    {
        if ($this->SubId !== null) {
            return $this->SubId;
        }

        switch ($this->id) {
            case \T_COLON:
                // If it's too early to determine the token's sub-id, save
                // `null` to resolve it later and return `-1`
                return ($this->SubId = $this->getColonType()) ?? -1;
            case \T_QUESTION:
                return $this->SubId = $this->getQuestionType();
            case \T_USE:
                return $this->SubId = $this->getUseType();
            default:
                // @codeCoverageIgnoreStart
                return $this->SubId = -1;
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @return TokenSubId::COLON_*|null
     */
    private function getColonType(): ?int
    {
        /** @var self */
        $prevCode = $this->PrevCode;

        if (
            $this->ClosedBy
            || $this->Idx->AltContinueWithNoExpression[$prevCode->id]
            || (
                $prevCode->id === \T_CLOSE_PARENTHESIS
                && $prevCode->PrevSibling
                && (
                    $this->Idx->AltStart[$prevCode->PrevSibling->id]
                    || $this->Idx->AltContinueWithExpression[$prevCode->PrevSibling->id]
                )
            )
        ) {
            return TokenSubId::COLON_ALT_SYNTAX_DELIMITER;
        }

        if (
            $this->Parent
            && $this->Parent->id === \T_OPEN_PARENTHESIS
            && $prevCode->id === \T_STRING
            && $prevCode->PrevCode
            && (
                $prevCode->PrevCode === $this->Parent
                || $prevCode->PrevCode->id === \T_COMMA
            )
        ) {
            return TokenSubId::COLON_NAMED_ARGUMENT_DELIMITER;
        }

        if ($this->inSwitchCase()) {
            return TokenSubId::COLON_SWITCH_CASE_DELIMITER;
        }

        if (
            $prevCode->id === \T_STRING
            && $prevCode->PrevCode
            && $prevCode->PrevCode->id === \T_ENUM
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
                $prev = $prev->skipPrevSiblingsFrom($this->Idx->FunctionIdentifier);

                if ($prev->id === \T_FUNCTION || $prev->id === \T_FN) {
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
    private function getQuestionType(): int
    {
        /** @var self */
        $prevCode = $this->PrevCode;
        if (
            (
                $this->Idx->NonMethodMember[$prevCode->id]
                && $prevCode->inNamedDeclaration()
            )
            || ($prevCode->id === \T_COLON && $prevCode->isColonTypeDelimiter())
            || $this->inParameterList()
        ) {
            return TokenSubId::QUESTION_NULLABLE;
        }

        return TokenSubId::QUESTION_TERNARY_OPERATOR;
    }

    /**
     * @return TokenSubId::USE_*
     */
    private function getUseType(): int
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

    /**
     * Check if the token is in a parameter list
     */
    public function inParameterList(): bool
    {
        return $this->Parent && $this->Parent->isParameterList();
    }

    /**
     * Check if the token encloses a parameter list
     */
    public function isParameterList(): bool
    {
        return $this->id === \T_OPEN_PARENTHESIS
            && ($prev = $this->PrevSibling)
            && ($this->Idx->FunctionOrFn[
                ($prev2 = $prev->skipPrevSiblingsFrom($this->Idx->FunctionIdentifier))->id
            ] || (
                $prev->id === \T_STRING
                && ($prev2->id === \T_NULL || $prev2->skipPrevSiblingsFrom($this->Idx->AttributeOrModifier)->id === \T_NULL)
                && $this->Parent
                && $this->Parent->id === \T_OPEN_BRACE
                && $this->Parent->Statement
                && $this->Parent->Statement->isProperty()
            ));
    }

    /**
     * Check if the token is in a T_CASE or T_DEFAULT statement in a T_SWITCH
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
     * Check if the token is in a T_SWITCH
     */
    public function inSwitch(): bool
    {
        return $this->Parent
            && $this->Parent->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling
            && $this->Parent->PrevSibling->PrevSibling->id === \T_SWITCH;
    }

    /**
     * Check if the token is a T_COMMA between the arms of a match expression
     */
    public function isDelimiterBetweenMatchArms(): bool
    {
        return $this->isMatchDelimiter()
            && $this->prevSiblingFrom($this->Idx->CommaOrDoubleArrow)->id === \T_DOUBLE_ARROW;
    }

    /**
     * Check if the token is a T_COMMA between conditional expressions in a
     * match expression
     */
    public function isDelimiterBetweenMatchExpressions(): bool
    {
        return $this->isMatchDelimiter()
            && $this->prevSiblingFrom($this->Idx->CommaOrDoubleArrow)->id !== \T_DOUBLE_ARROW;
    }

    /**
     * Check if the token is a T_COMMA in a match expression
     */
    public function isMatchDelimiter(): bool
    {
        return $this->id === \T_COMMA
            && $this->Parent
            && $this->Parent->isMatchOpenBrace();
    }

    /**
     * Check if the token is the T_OPEN_BRACE of a match expression
     */
    public function isMatchOpenBrace(): bool
    {
        return $this->id === \T_OPEN_BRACE
            && $this->PrevSibling
            && $this->PrevSibling->PrevSibling
            && $this->PrevSibling->PrevSibling->id === \T_MATCH;
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
        return $this->Parent
            && $this->Parent->id === \T_OPEN_BRACE
            && $this->Parent->Statement
            && $this->Parent->Statement->isProperty();
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
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->doIsAnonymousFunctionOrFn();
    }

    private function doIsAnonymousFunctionOrFn(): bool
    {
        return !($this->Flags & TokenFlag::NAMED_DECLARATION)
            && $this->Idx->FunctionOrFn[$this->skipNextSiblingsFrom($this->Idx->AttributeOrStatic)->id];
    }

    /**
     * Check if the token is part of a non-anonymous declaration
     */
    public function inNamedDeclaration(?TokenCollection &$parts = null): bool
    {
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->doIsDeclaration(false, $parts);
    }

    /**
     * Check if the token is part of a declaration
     */
    public function inDeclaration(): bool
    {
        return $this->skipPrevSiblingsToDeclarationStart()
                    ->doIsDeclaration(true);
    }

    private function doIsDeclaration(
        bool $allowAnonymous,
        ?TokenCollection &$parts = null
    ): bool {
        if ($this->Flags & TokenFlag::NAMED_DECLARATION) {
            if (!$allowAnonymous) {
                $parts = $this->Data[TokenData::NAMED_DECLARATION_PARTS];
            }
            return true;
        }

        if (!$allowAnonymous) {
            return false;
        }

        // Exclude tokens other than the first in a declaration
        if (!$this->Expression || (
            $this->PrevSibling
            && $this->PrevSibling->Expression === $this->Expression
            && $this->Idx->DeclarationPartWithNewAndBody[$this->PrevSibling->id]
        )) {
            return false;
        }

        // Check if the first token after `new`, `static` and any attributes is
        // `class` or `function`
        return $this->Idx->ClassOrFunction[
            $this->skipNextSiblingsFrom($this->Idx->BeforeAnonymousClassOrFunction)->id
        ];
    }

    /**
     * Get the first token in the sequence of declaration parts to which the
     * token belongs, or the token itself
     *
     * The token returned by this method may not be part of a declaration. It
     * should only be used as a starting point for further checks.
     */
    public function skipPrevSiblingsToDeclarationStart(): self
    {
        if (!$this->Expression) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        $t = $this;
        while (
            $t->PrevSibling
            && $t->PrevSibling->Expression === $this->Expression
            && $this->Idx->DeclarationPartWithNewAndBody[$t->PrevSibling->id]
        ) {
            $t = $t->PrevSibling;
        }
        return $t;
    }

    // --

    public function wasFirstOnLine(): bool
    {
        if ($this->id === \T_NULL) {
            return false;
        }
        if (!$this->Prev) {
            return true;
        }
        $prev = $this->Prev->skipPrevFrom($this->Idx->Virtual);
        $prevText = rtrim($prev->OriginalText ?? $prev->text, "\n");
        $prevNewlines = substr_count($prevText, "\n");
        return $this->line > ($prev->line + $prevNewlines);
    }

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

    public function startOfLine(bool $ignoreComments = true): self
    {
        $current = $this;
        while (
            !$current->hasNewlineBefore()
            && ($ignoreComments
                || !($current->isMultiLineComment() && $current->hasNewline()))
            && $current->id !== \T_END_HEREDOC
            && $current->Prev
        ) {
            $current = $current->Prev;
        }

        return $current;
    }

    public function endOfLine(bool $ignoreComments = true): self
    {
        $current = $this;
        while (
            !$current->hasNewlineAfter()
            && ($ignoreComments
                || !($current->isMultiLineComment() && $current->hasNewline()))
            && $current->id !== \T_START_HEREDOC
            && $current->Next
        ) {
            $current = $current->Next;
        }

        return $current;
    }

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

    public function continuesControlStructure(): bool
    {
        return $this->Idx->ContinuesControlStructure[$this->id]
            || ($this->id === \T_WHILE && $this->Statement !== $this);
    }

    /**
     * Get the first sibling in the token's expression
     *
     * @param bool $containUnenclosed If `true`, braces are imagined around
     * control structures with unenclosed bodies. The default is `false`.
     */
    public function pragmaticStartOfExpression(bool $containUnenclosed = false): self
    {
        // If the token is between `?` and `:` in a ternary expression, return
        // the first token after `?`
        $ternary1 =
            $this->prevSiblings()
                 ->find(fn(self $t) =>
                            ($t->Flags & TokenFlag::TERNARY_OPERATOR)
                                && $t->id === \T_QUESTION);
        if ($ternary1 && $ternary1->Data[TokenData::OTHER_TERNARY_OPERATOR]->Index > $this->Index) {
            /** @var self */
            $next = $ternary1->NextCode;
            return $next->_pragmaticStartOfExpression($this);
        }

        // Otherwise, traverse expressions until an appropriate terminator is
        // reached
        $current = $this->OpenedBy ?: $this;
        $last = $current;
        $i = -1;
        while (true) {
            $i++;
            // If this is the first iteration, or `$current` is an ignored
            // expression boundary, move back to a sibling that isn't a
            // terminator
            while ($current && !$current->Expression) {
                if ($i && !(
                    $current->Flags & TokenFlag::TERNARY_OPERATOR
                    || $this->Idx->OperatorComparison[$current->id]
                )) {
                    break;
                }
                $i++;
                [$last, $current] =
                    [$current, $current->PrevSibling];
            }
            $current = $current->Expression ?? null;
            if (!$current) {
                return $last->_pragmaticStartOfExpression($this);
            }

            // Honour imaginary braces around control structures with unenclosed
            // bodies if needed
            if ($containUnenclosed && $current->EndExpression) {
                if (
                    $this->Idx->HasStatementWithOptionalBraces[$current->id]
                    && ($body = $current->NextSibling)
                    && $body->id !== \T_OPEN_BRACE
                    && $current->EndExpression->withTerminator()->Index >= $this->Index
                ) {
                    return $body->_pragmaticStartOfExpression($this);
                }
                if (
                    $this->Idx->HasExpressionAndStatementWithOptionalBraces[$current->id]
                    && $current->NextSibling
                    && ($body = $current->NextSibling->NextSibling)
                    && $body->id !== \T_OPEN_BRACE
                    && $current->EndExpression->withTerminator()->Index >= $this->Index
                ) {
                    return $body->_pragmaticStartOfExpression($this);
                }
            }

            // Preemptively traverse the boundary so subsequent code can simply
            // `continue`
            [$last, $current] =
                [$current, $current->PrevSibling->PrevSibling ?? null];

            // Don't terminate if the current token continues a control
            // structure
            if ($last->continuesControlStructure()) {
                continue;
            }

            // Undo the boundary traversal
            $current = $last->PrevSibling;
        }
    }

    private function _pragmaticStartOfExpression(self $requester): self
    {
        if ($requester !== $this && $this->Idx->Return[$this->id]) {
            /** @var self */
            return $this->NextCode;
        }

        return $this;
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

            return $last->ClosedBy ?: $last;
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
                    if ($current->Data[TokenData::OTHER_TERNARY_OPERATOR]->Index > $this->Index) {
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
        $current = $this->OpenedBy ?: $this;
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
                    || $terminator->prevSiblingFrom($this->Idx->IfOrElseIf)->Index >= $this->Index)
            ) {
                continue;
            }
            if (
                $next->id === \T_WHILE
                && $next->Statement !== $next
                && (!$containUnenclosed
                    || $terminator->id === \T_CLOSE_BRACE
                    || ($next->Statement && $next->Statement->Index >= $this->Index))
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
        $parts = $this->skipPrevSiblingsToDeclarationStart()
                      ->declarationParts();
        if (!$parts->hasOneFrom($this->Idx->DeclarationTopLevel)) {
            return null;
        }

        /** @var self */
        $last = $parts->last();
        if ($last->Index < $this->Index) {
            return null;
        }

        // Exclude anonymous functions, which can move as needed
        if ($last->skipPrevSiblingsFrom($this->Idx->Ampersand)->id === \T_FUNCTION) {
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
        if ($body->id === \T_NULL || $body->Index >= $end->Index) {
            return null;
        }

        return $body->PrevCode;
    }

    /**
     * If the token belongs to a sequence of one or more consecutive close
     * brackets or commas in any combination, and the last of these has a
     * subsequent token in the same statement, return it
     */
    public function adjacent(): ?self
    {
        $t = $this->ClosedBy ?? $this;
        $outer = $t->withNextCodeWhile($this->Idx->CloseBracketOrComma, true)
                   ->last();
        return !$outer
            || !$outer->NextCode
            || !$outer->EndStatement
            || $outer->EndStatement->Index <= $outer->NextCode->Index
                ? null
                : $outer->NextCode;
    }

    /**
     * Get the first token of an expression, statement or block in a parent
     * scope that appears between the token and the end of the line
     *
     * In this example, the token adjacent to `$b` is `{`:
     *
     * ```php
     * if ($c &&
     *         ($a || $b)) {
     *     // ...
     * }
     * ```
     *
     * Returns `null` if:
     *
     * - there are no tokens adjacent to the token
     * - neither the token nor its parent have a close bracket to establish a
     *   distinct scope for subsequent tokens
     * - `$requireAlignedWith` is `true` (the default) and there are no tokens
     *   between the adjacent token and the end of the line with an
     *   {@see Token::$AlignedWith} token
     */
    public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?self
    {
        // Return `null` if neither the token nor its parent have a close
        // bracket
        $current = $this->ClosedBy ?: $this;
        if (!$current->OpenedBy) {
            /** @var static|null */
            $current = $current->Parent->ClosedBy ?? null;
            if (!$current) {
                return null;
            }
        }

        // Find the last `)`, `]`, `}`, or `,` on the same line as the close
        // bracket and assign it to `$outer`
        $eol = $this->endOfLine();
        $outer = $current->withNextCodeWhile($this->Idx->CloseBracketOrComma)
                         ->filter(fn(self $t) => $t->Index <= $eol->Index)
                         ->last();

        // If it's a `,`, move to the first token of the next expression on the
        // same line and assign it to `$next`
        $next = $outer;
        while (
            $next
            && !$next->Expression
            && $next->NextSibling
            && $next->NextSibling->Index <= $eol->Index
        ) {
            $next = $next->NextSibling;
        }

        // Return `null` if the first code token after `$outer` is on a
        // subsequent line, or if neither `$outer` nor `$next` belong to a
        // statement that continues beyond their respective next code tokens
        if (
            !$outer
            || !$outer->NextCode
            || $outer->NextCode->Index > $eol->Index
            || ((!$outer->EndStatement
                    || $outer->EndStatement->Index <= $outer->NextCode->Index)
                && ($next === $outer
                    || !$next
                    || !$next->EndStatement
                    || !$next->NextCode
                    || $next->EndStatement->Index <= $next->NextCode->Index))
        ) {
            return null;
        }

        // Return `null` if `$requireAlignedWith` is `true` and there are no
        // tokens between `$outer` and the end of the line where `AlignedWith`
        // is set
        if (
            $requireAlignedWith
            && !$outer->NextCode
                      ->collect($eol)
                      ->find(fn(self $t) => (bool) $t->AlignedWith)
        ) {
            return null;
        }

        return $next === $outer
            ? $outer->NextCode
            : $next;
    }

    /**
     * Get the token's last sibling before the end of the line
     *
     * The token returns itself if it satisfies the criteria.
     */
    public function lastSiblingBeforeNewline(): self
    {
        $eol = $this->endOfLine();
        $current = $this->ClosedBy ?: $this;
        do {
            $last = $current;
            $current = $current->NextSibling;
        } while (
            $current
            && $current->Index <= $eol->Index
        );

        return $last;
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

    public function applyBlankLineBefore(bool $force = false): void
    {
        $t = $this;
        $i = 0;
        while (
            !$t->hasBlankLineBefore()
            && $t->Prev
            && $this->Idx->Comment[$t->Prev->id]
            && $t->Prev->hasNewlineBefore()
            && ($t->Prev->id === \T_DOC_COMMENT || (
                $t->Prev->wasFirstOnLine()
                && $t->Prev->column <= $this->column
            ))
            && (!$i || (
                !($t->Flags & TokenFlag::MULTILINE_COMMENT)
                && ($t->Flags & TokenFlagMask::COMMENT_TYPE)
                    === ($t->Prev->Flags & TokenFlagMask::COMMENT_TYPE)
            ))
        ) {
            $i++;
            $t = $t->Prev;
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

    public function hasNewlineBefore(): bool
    {
        return (bool) ($this->getWhitespaceBefore() & (Space::BLANK | Space::LINE));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool) ($this->getWhitespaceAfter() & (Space::BLANK | Space::LINE));
    }

    public function hasBlankLineBefore(): bool
    {
        return (bool) ($this->getWhitespaceBefore() & Space::BLANK);
    }

    public function hasBlankLineAfter(): bool
    {
        return (bool) ($this->getWhitespaceAfter() & Space::BLANK);
    }

    /**
     * Check if the token contains a newline
     */
    public function hasNewline(): bool
    {
        return strpos($this->text, "\n") !== false;
    }

    /**
     * Check if, between the token and the next code token, there's a newline
     * between tokens
     */
    public function hasNewlineBeforeNextCode(bool $orInHtml = true): bool
    {
        if ($this->hasNewlineAfter()) {
            return true;
        }
        if (!$this->NextCode || $this->NextCode === $this->Next) {
            return false;
        }
        $t = $this;
        while (true) {
            /** @var self */
            $t = $t->Next;
            if ($t === $this->NextCode) {
                break;
            }
            if ($t->hasNewlineAfter()) {
                return true;
            }
            if ($orInHtml && (
                $t->id === \T_INLINE_HTML
                || $t->id === \T_CLOSE_TAG
                || $t->id === \T_OPEN_TAG
            ) && $t->hasNewline()) {
                return true;
            }
        }

        return false;
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
            || !$this->PrevCode->isDereferenceableTerminator()
        );
    }

    public function isDereferenceableTerminator(): bool
    {
        return $this->Idx->EndOfDereferenceable[$this->id] || (
            $this->PrevCode
            && $this->PrevCode->id === \T_DOUBLE_COLON
            && $this->id === \T_STRING
        );
    }

    public function isOneLineComment(): bool
    {
        return (bool) ($this->Flags & TokenFlag::ONELINE_COMMENT);
    }

    public function isMultiLineComment(): bool
    {
        return (bool) ($this->Flags & TokenFlag::MULTILINE_COMMENT);
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
            || ($this->PrevCode && (
                $this->PrevCode->Flags & TokenFlag::TERNARY_OPERATOR
                || $this->Idx->BeforeUnary[$this->PrevCode->id]
            ));
    }

    /**
     * Get the difference in indentation between the token and a token being
     * used for alignment
     */
    public function indentDelta(self $token): TokenIndentDelta
    {
        return TokenIndentDelta::between($this, $token);
    }

    public function indent(): int
    {
        return $this->TagIndent
            + $this->PreIndent
            + $this->Indent
            + $this->HangingIndent
            - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($indent = $this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
            : '';
    }

    public function expandedText(): string
    {
        if ($this->ExpandedText === null) {
            return $this->text;
        }

        return Str::expandLeadingTabs(
            $this->text,
            $this->Formatter->Indentation->TabSize
                ?? $this->Formatter->TabSize,
            !$this->wasFirstOnLine(),
            $this->column,
        );
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
        while ($t->NextSibling && (
            $index[$t->NextSibling->id] || (
                $allowAnonymous
                && $t->NextSibling->id === \T_OPEN_PARENTHESIS
                && $t->id === \T_CLASS
            )
        )) {
            $t = $t->NextSibling;
        }

        if (
            !$allowAnonymous
            && $t->skipPrevSiblingsFrom($this->Idx->Ampersand)->id === \T_FUNCTION
        ) {
            return new TokenCollection();
        }

        return $this->collectSiblings($t);
    }

    /**
     * Get the token and its preceding tokens in the same statement, in document
     * order
     */
    public function sinceStartOfStatement(): TokenCollection
    {
        return $this->Statement
            ? $this->Statement->collect($this)
            : $this->collect($this);
    }

    /**
     * Get the token and its nested tokens
     */
    public function outer(): TokenCollection
    {
        return ($this->OpenedBy ?? $this)
                   ->collect($this->ClosedBy ?? $this);
    }

    /**
     * Get the token's nested tokens
     */
    public function inner(): TokenCollection
    {
        $t = $this->OpenedBy ?? $this;
        return $t->ClosedBy
            && $t->ClosedBy->Prev
            && $t->Next
            && $t->Next !== $t->ClosedBy
                ? $t->Next->collect($t->ClosedBy->Prev)
                : new TokenCollection();
    }

    /**
     * Get the token's nested siblings
     */
    public function children(): TokenCollection
    {
        $t = $this->OpenedBy ?? $this;
        return $t->ClosedBy
            && $t->ClosedBy->PrevCode
            && $t->NextCode
            && $t->NextCode !== $t->ClosedBy
                ? $t->NextCode->collectSiblings($t->ClosedBy->PrevCode)
                : new TokenCollection();
    }

    /**
     * Get the token and its following tokens up to and including a given token
     */
    public function collect(self $to): TokenCollection
    {
        return TokenCollection::collect($this, $to);
    }

    /**
     * Get the token and its following siblings, optionally stopping at a given
     * sibling
     */
    public function collectSiblings(?self $to = null): TokenCollection
    {
        if ($this->id === \T_NULL || ($to && $to->id === \T_NULL)) {
            return new TokenCollection();
        }
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            if ($this->Parent !== $to->Parent) {
                return new TokenCollection();
            }
            $to = $to->OpenedBy ?? $to;
            if ($this->Index > $to->Index) {
                return new TokenCollection();
            }
        }
        do {
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        } while ($current = $current->NextSibling);

        return new TokenCollection($tokens);
    }

    /**
     * Get preceding siblings in reverse document order, optionally stopping at
     * a given sibling
     */
    public function prevSiblings(?self $to = null): TokenCollection
    {
        if ($this->id === \T_NULL || ($to && $to->id === \T_NULL)) {
            return new TokenCollection();
        }
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            if ($this->Parent !== $to->Parent) {
                return new TokenCollection();
            }
            $to = $to->OpenedBy ?? $to;
            if ($this->Index < $to->Index) {
                return new TokenCollection();
            }
        }
        while ($current = $current->PrevSibling) {
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        }

        return new TokenCollection($tokens ?? []);
    }

    /**
     * Get preceding code tokens in reverse document order, up to but not
     * including the first that isn't in an index
     *
     * @param array<int,bool> $index
     */
    public function prevCodeWhile(array $index): TokenCollection
    {
        return $this->_prevCodeWhile(false, false, $index);
    }

    /**
     * Get the token and its preceding code tokens in reverse document order, up
     * to but not including the first that isn't in an index
     *
     * @param array<int,bool> $index
     * @param bool $testToken If `true` and the token isn't in `$index`, an
     * empty collection is returned. Otherwise, the token is added to the
     * collection regardless.
     */
    public function withPrevCodeWhile(array $index, bool $testToken = false): TokenCollection
    {
        return $this->_prevCodeWhile(true, $testToken, $index);
    }

    /**
     * @param array<int,bool> $index
     */
    private function _prevCodeWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevCode;
        while ($prev && $index[$prev->id]) {
            $tokens[] = $prev;
            $prev = $prev->PrevCode;
        }

        return new TokenCollection($tokens ?? []);
    }

    /**
     * Get following code tokens, up to but not including the first that isn't
     * in an index
     *
     * @param array<int,bool> $index
     */
    public function nextCodeWhile(array $index): TokenCollection
    {
        return $this->_nextCodeWhile(false, false, $index);
    }

    /**
     * Get the token and its following code tokens, up to but not including the
     * first that isn't in an index
     *
     * @param array<int,bool> $index
     * @param bool $testToken If `true` and the token isn't in `$index`, an
     * empty collection is returned. Otherwise, the token is added to the
     * collection regardless.
     */
    public function withNextCodeWhile(array $index, bool $testToken = false): TokenCollection
    {
        return $this->_nextCodeWhile(true, $testToken, $index);
    }

    /**
     * @param array<int,bool> $index
     */
    private function _nextCodeWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextCode;
        while ($next && $index[$next->id]) {
            $tokens[] = $next;
            $next = $next->NextCode;
        }

        return new TokenCollection($tokens ?? []);
    }

    /**
     * Get preceding siblings in reverse document order, up to but not including
     * the first that isn't in an index
     *
     * @param array<int,bool> $index
     */
    public function prevSiblingsWhile(array $index): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, false, $index);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that isn't in an index
     *
     * @param array<int,bool> $index
     * @param bool $testToken If `true` and the token isn't in `$index`, an
     * empty collection is returned. Otherwise, the token is added to the
     * collection regardless.
     */
    public function withPrevSiblingsWhile(array $index, bool $testToken = false): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, $testToken, $index);
    }

    /**
     * @param array<int,bool> $index
     */
    private function _prevSiblingsWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevSibling;
        while ($prev && $index[$prev->id]) {
            $tokens[] = $prev;
            $prev = $prev->PrevSibling;
        }

        return new TokenCollection($tokens ?? []);
    }

    /**
     * Get following siblings, up to but not including the first that isn't in
     * an index
     *
     * @param array<int,bool> $index
     */
    public function nextSiblingsWhile(array $index): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, false, $index);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that isn't in an index
     *
     * @param array<int,bool> $index
     * @param bool $testToken If `true` and the token isn't in `$index`, an
     * empty collection is returned. Otherwise, the token is added to the
     * collection regardless.
     */
    public function withNextSiblingsWhile(array $index, bool $testToken = false): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, $testToken, $index);
    }

    /**
     * @param array<int,bool> $index
     */
    private function _nextSiblingsWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextSibling;
        while ($next && $index[$next->id]) {
            $tokens[] = $next;
            $next = $next->NextSibling;
        }

        return new TokenCollection($tokens ?? []);
    }

    // --

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return TokenUtil::serialize($this);
    }
}
