<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Facade\Convert;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * @extends CollectibleToken<Token>
 */
class Token extends CollectibleToken implements JsonSerializable
{
    /**
     * Tokens regarded as expression terminators
     *
     * Somewhat arbitrary, but effective for formatting purposes.
     *
     * @var array<int|string>
     */
    private const EXPRESSION_TERMINATOR = [
        T[')'],
        T[';'],
        T[']'],
        ...TokenType::OPERATOR_ASSIGNMENT,
        ...TokenType::OPERATOR_COMPARISON_EXCEPT_COALESCE,
        ...TokenType::OPERATOR_DOUBLE_ARROW,
    ];

    public bool $BodyIsUnenclosed = false;

    /**
     * @var Token|null
     */
    public $OpenTag;

    /**
     * @var Token|null
     */
    public $CloseTag;

    /**
     * @var Token|null
     */
    public $Statement;

    /**
     * @var Token|null
     */
    public $EndStatement;

    /**
     * @var Token|false|null
     */
    public $Expression;

    /**
     * @var Token|null
     */
    public $EndExpression;

    public bool $IsTernaryOperator = false;

    public ?Token $TernaryOperator1 = null;

    public ?Token $TernaryOperator2 = null;

    public ?string $CommentType = null;

    public bool $CommentPlaced = false;

    public bool $NewlineAfterPreserved = false;

    /**
     * @var int
     */
    public $TagIndent = 0;

    /**
     * @var int
     */
    public $PreIndent = 0;

    /**
     * @var int
     */
    public $Indent = 0;

    /**
     * @var int
     */
    public $Deindent = 0;

    /**
     * @var int
     */
    public $HangingIndent = 0;

    /**
     * @var bool
     */
    public $IsHangingParent;

    /**
     * @var bool
     */
    public $IsOverhangingParent;

    /**
     * Tokens responsible for each level of hanging indentation applied to the
     * token
     *
     * @var Token[]
     */
    public $IndentStack = [];

    /**
     * Parent tokens associated with hanging indentation levels applied to the
     * token
     *
     * @var Token[]
     */
    public $IndentParentStack = [];

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * Only used by {@see AddHangingIndentation::processToken()}.
     *
     * @var array<array<Token[]|Token>>
     */
    public $IndentBracketStack = [];

    /**
     * Entries represent parent tokens and the collapsible ("overhanging")
     * levels of indentation applied to the token on their behalf
     *
     * Parent token index => collapsible indentation levels applied
     *
     * @var array<int,int>
     */
    public $OverhangingParents = [];

    /**
     * @var bool
     */
    public $PinToCode = false;

    /**
     * @var int
     */
    public $LinePadding = 0;

    /**
     * @var int
     */
    public $LineUnpadding = 0;

    /**
     * @var int
     */
    public $Padding = 0;

    /**
     * @var string|null
     */
    public $HeredocIndent;

    /**
     * @var Token|null
     */
    public $AlignedWith;

    /**
     * @var Token|null
     */
    public $ChainOpenedBy;

    /**
     * @var Token|null
     */
    public $HeredocOpenedBy;

    /**
     * @var Token|null
     */
    public $StringOpenedBy;

    /**
     * Bitmask representing whitespace between the token and its predecessor
     *
     * @var int
     */
    public $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * Bitmask representing whitespace between the token and its successor
     *
     * @var int
     */
    public $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * Bitmask applied to whitespace between the token and its predecessor
     *
     * @var int
     */
    public $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Bitmask applied to whitespace between the token and its successor
     *
     * @var int
     */
    public $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * predecessor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     *
     * @var int
     */
    public $CriticalWhitespaceBefore = WhitespaceType::NONE;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * successor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     *
     * @var int
     */
    public $CriticalWhitespaceAfter = WhitespaceType::NONE;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * predecessor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     *
     * @var int
     */
    public $CriticalWhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * successor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     *
     * @var int
     */
    public $CriticalWhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * True if the token is a T_CLOSE_TAG that terminates a statement
     *
     * @var bool
     */
    public $IsCloseTagStatementTerminator = false;

    /**
     * @var Formatter|null
     */
    public $Formatter;

    /**
     * @param static[] $tokens
     * @return static[]
     */
    public static function prepareTokens(array $tokens, Formatter $formatter): array
    {
        foreach ($tokens as $token) {
            $token->Formatter = $formatter;
            $token->prepare();
        }
        reset($tokens)->load();

        return $tokens;
    }

    /**
     * @param static[] $tokens
     */
    public static function destroyTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $token->destroy();
        }
    }

    public function getTokenName(): ?string
    {
        return TokenType::NAME_MAP[$this->id] ?? parent::getTokenName();
    }

    protected function prepare(): void
    {
        if (!$this->IsVirtual) {
            if ($this->is(TokenType::DO_NOT_MODIFY_LHS)) {
                $this->setText(rtrim($this->text));
            } elseif ($this->is(TokenType::DO_NOT_MODIFY_RHS)) {
                $this->setText(ltrim($this->text));
            } elseif (!$this->is(TokenType::DO_NOT_MODIFY)) {
                $this->setText(trim($this->text));
            }

            if ($this->id === T_COMMENT) {
                preg_match('/^(\/\/|\/\*|#)/', $this->text, $matches);
                $this->CommentType = $matches[1];
            } elseif ($this->id === T_DOC_COMMENT) {
                $this->CommentType = '/**';
            }

            if ($this->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                $this->OpenTag = $this;
            }
        }

        if (!($prev = $this->_prev)) {
            return;
        }

        /**
         * Result:
         *
         * ```php
         * <?php            // OpenTag = itself, CloseTag = Token
         * $foo = 'bar';    // OpenTag = Token,  CloseTag = Token
         * ?>               // OpenTag = Token,  CloseTag = itself
         * <!-- markup -->  // OpenTag = null,   CloseTag = null
         * <?php            // OpenTag = itself, CloseTag = null
         * $foo = 'bar';    // OpenTag = Token,  CloseTag = null
         * ```
         */
        if (!$this->OpenTag && $prev->OpenTag && !$prev->CloseTag) {
            $this->OpenTag = $prev->OpenTag;
            if ($this->id === T_CLOSE_TAG) {
                $t = $this;
                do {
                    $t->CloseTag = $this;
                    $t = $t->_prev;
                } while ($t && $t->OpenTag === $this->OpenTag);

                $t = $prev;
                while ($t->is(TokenType::COMMENT)) {
                    $t = $t->_prev;
                }
                if ($t->Index > $this->OpenTag->Index &&
                        !$t->is([T[':'], T[';'], T['{']]) &&
                        ($t->id !== T['}'] || !$t->isCloseBraceStatementTerminator())) {
                    $this->IsCloseTagStatementTerminator = true;
                    $this->IsCode = true;
                    $t = $this;
                    while ($t = $t->_next) {
                        $t->_prevCode = $this;
                        if ($t->IsCode) {
                            break;
                        }
                    }
                    $t = $this;
                    while ($t = $t->_prev) {
                        $t->_nextCode = $this;
                        if ($t->IsCode) {
                            break;
                        }
                    }
                }
            }
        }
    }

    protected function load(): void
    {
        foreach ([
            'maybeApplyStatement',
            'maybeApplyExpression',
        ] as $pass) {
            $current = $this;
            do {
                if ($current->IsCode) {
                    $current->$pass();
                }
                $current = $current->_next;
            } while ($current);
        }
    }

    /**
     * If the token is a statement terminator, set Statement and EndStatement on
     * siblings loaded since the previous terminator
     *
     */
    private function maybeApplyStatement(): void
    {
        if ((($this->id === T[';'] ||
                $this->IsCloseTagStatementTerminator ||
                $this->isCloseBraceStatementTerminator()) &&
            !$this->nextCode()->is([T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY]) &&
            !($this->nextCode()->id === T_WHILE &&
                // Body enclosed: `do { ... } while ();`
                ($this->nextCode()->prevSibling(2)->id === T_DO ||
                    // Body unenclosed +/- nesting:
                    // - `do statement; while ();`
                    // - `do while () while (); while ();`
                    (!($do = $this->prevSiblingOf(T_DO))->IsNull &&
                        $do->nextSibling(2)
                           ->collectSiblings($this->nextCode())
                           ->filter(fn(Token $t) => $t->id === T_WHILE && $t->prevSibling(2)->id !== T_WHILE)
                           ->first() === $this->nextCode())))) ||
                ($this->id === T[':'] && ($this->inSwitchCase() || $this->inLabel()))) {
            $this->applyStatement();
        } elseif ($this->is([T[')'], T[']']]) ||
                ($this->id === T['}'] && !$this->isStructuralBrace(false))) {
            $this->_prevCode->applyStatement();
        } elseif ($this->id === T[',']) {
            // For formatting purposes, commas are statement delimiters:
            // - between parentheses and square brackets, e.g. in argument
            //   lists, arrays, `for` expressions
            // - between braces in `match` expressions
            // - in `use` declarations, e.g. `use my_namespace\{a, b}`
            //
            // But they aren't delimiters:
            // - in `use` statements, e.g. `use my_trait { a as b; c as d; }`
            $parent = $this->parent();
            if ($parent->is([T['('], T['[']]) ||
                ($parent->id === T['{'] &&
                    (!$parent->isStructuralBrace() || $this->isMatchDelimiter()))) {
                $this->applyStatement();
            }
        }
    }

    /**
     * Apply the token to the EndStatement property of itself and its previous
     * siblings
     *
     * Stops when the most recently applied {@see Token::$EndStatement} is
     * found, or when there are no more predecessors. The identified 'start'
     * token is then applied to the {@see Token::$Statement} property of the
     * same tokens.
     *
     */
    private function applyStatement(): void
    {
        // Skip empty brackets
        if ($this->ClosedBy && $this->_nextCode === $this->ClosedBy) {
            return;
        }

        // Navigate back to the most recent statement terminator
        $current = $this->OpenedBy ?: $this;
        do {
            $current->EndStatement = $this;
            if ($current->ClosedBy) {
                $current->ClosedBy->EndStatement = $this;
            }
            $latest = $current;
            $current = $current->_prevSibling;
        } while ($current && !$current->EndStatement);

        // And return
        $current = $latest;
        do {
            $current->Statement = $latest;
            if ($current->ClosedBy) {
                $current->ClosedBy->Statement = $latest;
            }
            $current = $current->_nextSibling;
        } while ($current && $current->EndStatement === $this);
    }

    private function isCloseBraceStatementTerminator(): bool
    {
        if ($this->id !== T['}'] || !$this->isStructuralBrace(false)) {
            return false;
        }

        if (!($start = $this->Statement)) {
            // Find the end of the last statement to find the start of this one
            $current = $this->OpenedBy->_prevSibling;
            while ($current && !$current->EndStatement) {
                $start = $current;
                $current = $current->_prevSibling;
            }
        }
        // If the open brace is the start, the close brace is the end
        if (!$start || $start === $this->OpenedBy) {
            return true;
        }

        // Control structure bodies are terminated with `}`
        if ($start->is(TokenType::HAS_STATEMENT)) {
            return true;
        }

        // Alias/import statements end with `;` but are already excluded by
        // `isStructuralBrace()`
        if ($start->id === T_USE) {
            return true;
        }

        // - Anonymous functions and classes are unterminated
        // - Other declarations end with `}`
        $parts = $start->withNextSiblingsWhile(...TokenType::DECLARATION_PART_WITH_NEW)
                       ->filter(fn(Token $t) => !$t->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT]));
        if ($parts->hasOneOf(...TokenType::DECLARATION) &&
                $parts->last()->id !== T_FUNCTION &&
                !($parts->first()->id === T_NEW && $parts->nth(2)->id === T_CLASS)) {
            return true;
        }

        return false;
    }

    /**
     * Similar to maybeApplyStatement() (but not the same)
     *
     */
    private function maybeApplyExpression(): void
    {
        if ($this->id === T['?']) {
            $current = $this;
            $count = 0;
            while (($current = $current->_nextSibling) &&
                    $this->EndStatement !== ($current->ClosedBy ?: $current)) {
                if ($current->IsTernaryOperator) {
                    continue;
                }
                if ($current->id === T['?']) {
                    $count++;
                    continue;
                }
                if ($current->id !== T[':'] || $count--) {
                    continue;
                }
                $current->IsTernaryOperator = $this->IsTernaryOperator = true;
                $current->TernaryOperator1 = $this->TernaryOperator1 = $this;
                $current->TernaryOperator2 = $this->TernaryOperator2 = $current;
                break;
            }
        }

        if ($this->is(TokenType::CHAIN) && !$this->ChainOpenedBy) {
            $this->ChainOpenedBy = $current = $this;
            while (($current = $current->_nextSibling) && $current->is(TokenType::CHAIN_PART)) {
                if ($current->is(TokenType::CHAIN)) {
                    $current->ChainOpenedBy = $this;
                }
            }
        }

        if ($this->id === T['}'] && $this->isStructuralBrace()) {
            $this->_prevCode->applyExpression();
            $this->applyExpression();
        } elseif ($this->is(self::EXPRESSION_TERMINATOR) ||
                $this->IsCloseTagStatementTerminator ||
                ($this->id === T[':'] && ($this->inSwitchCase() || $this->inLabel())) ||
                ($this->id === T['}'] &&
                    (!$this->isStructuralBrace() || $this->isMatchBrace())) ||
                $this->IsTernaryOperator) {
            // Expression terminators don't form part of the expression
            $this->Expression = false;
            if ($this->_prevCode) {
                $this->_prevCode->applyExpression();
            }
        } elseif ($this->id === T[',']) {
            $parent = $this->parent();
            if ($parent->is([T['('], T['[']]) ||
                ($parent->id === T['{'] &&
                    (!$parent->isStructuralBrace() || $this->isMatchDelimiter(false)))) {
                $this->Expression = false;
                $this->_prevCode->applyExpression();
            }
        }

        // Catch the last global expression
        if (!$this->_next) {
            $this->applyExpression();
        }
    }

    /**
     * Similar to applyStatement()
     *
     */
    private function applyExpression(): void
    {
        if ($this->ClosedBy && $this->_nextCode === $this->ClosedBy) {
            return;
        }

        $current = $this->OpenedBy ?: $this;
        while ($current && !$current->EndExpression) {
            $current->EndExpression = $this;
            if ($current->ClosedBy) {
                $current->ClosedBy->EndExpression = $this;
            }
            if ($current->Expression === false) {
                break;
            }
            $latest = $current;
            $current = $current->_prevSibling;
        }
        if (!($latest ?? null)) {
            return;
        }

        $current = $latest;
        do {
            $current->Expression = $latest;
            if ($current->ClosedBy) {
                $current->ClosedBy->Expression = $latest;
            }
            $current = $current->_nextSibling;
        } while ($current && $current->EndExpression === $this);
    }

    final public function isMatchBrace(): bool
    {
        $current = $this->OpenedBy ?: $this;

        return $current->id === T['{'] &&
            $current->prevSibling(2)->id === T_MATCH;
    }

    final public function isMatchDelimiter(bool $betweenArms = true): bool
    {
        return $this->id === T[','] &&
            $this->parent()->isMatchBrace() &&
            (!$betweenArms ||
                !($this->prevSiblingOf(
                    T[','], ...TokenType::OPERATOR_DOUBLE_ARROW
                )->is([T[','], T_NULL])));
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $a = get_object_vars($this);
        $_prevSibling = (string) $a['_prevSibling'];
        $_nextSibling = (string) $a['_nextSibling'];
        $a['_prevSibling'] = &$_prevSibling;
        $a['_nextSibling'] = &$_nextSibling;
        unset(
            $a['_prev'],
            $a['_next'],
            $a['_prevCode'],
            $a['_nextCode'],
            //$a['_prevSibling'],
            //$a['_nextSibling'],
            //$a['Index'],
            $a['BracketStack'],
            $a['OpenTag'],
            $a['CloseTag'],
            $a['OpenedBy'],
            $a['ClosedBy'],
            $a['IsCode'],
            $a['IsHangingParent'],
            $a['IsOverhangingParent'],
            $a['IndentStack'],
            $a['IndentParentStack'],
            $a['IndentBracketStack'],
            $a['OverhangingParents'],
            $a['AlignedWith'],
            $a['ChainOpenedBy'],
            $a['HeredocOpenedBy'],
            $a['StringOpenedBy'],
            $a['IsNull'],
            $a['IsVirtual'],
            $a['Formatter'],
            $a['IsCloseTagStatementTerminator'],
        );
        $a['id'] = $this->getTokenName();
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($a['WhitespaceBefore']);
        $a['WhitespaceAfter'] = WhitespaceType::toWhitespace($a['WhitespaceAfter']);
        if ($this->Expression === $this || $this->EndExpression === $this || $this->Expression === false) {
            $a['PragmaticStartExpression'] = $this->pragmaticStartOfExpression();
            $a['PragmaticEndExpression'] = $this->pragmaticEndOfExpression();
        }
        array_walk_recursive(
            $a,
            function (&$value) {
                if ($value instanceof Token) {
                    $value = (string) $value;
                }
            }
        );

        return $a;
    }

    final public function canonical(): Token
    {
        return $this->OpenedBy ?: $this;
    }

    final public function canonicalClose(): Token
    {
        return $this->ClosedBy ?: $this;
    }

    final public function wasFirstOnLine(): bool
    {
        if ($this->IsVirtual) {
            return false;
        }
        do {
            $prev = ($prev ?? $this)->_prev;
            if (!$prev) {
                return true;
            }
        } while ($prev->IsVirtual);
        $prevCode = $prev->OriginalText ?: $prev->text;
        $prevNewlines = substr_count($prevCode, "\n");

        return $this->line > ($prev->line + $prevNewlines) ||
            $prevCode[-1] === "\n";
    }

    final public function wasLastOnLine(): bool
    {
        if ($this->IsVirtual) {
            return false;
        }
        do {
            $next = ($next ?? $this)->_next;
            if (!$next) {
                return true;
            }
        } while ($next->IsVirtual);
        $code = $this->OriginalText ?: $this->text;
        $newlines = substr_count($code, "\n");

        return ($this->line + $newlines) < $next->line ||
            $code[-1] === "\n";
    }

    public function wasBetweenTokensOnLine(bool $canHaveInnerNewline = false): bool
    {
        return !$this->wasFirstOnLine() &&
            !$this->wasLastOnLine() &&
            ($canHaveInnerNewline || !$this->hasNewline());
    }

    private function byOffset(string $name, int $offset): Token
    {
        $t = $this;
        for ($i = 0; $i < $offset; $i++) {
            $t = $t->{"_$name"} ?? null;
        }

        return $t ?: $this->null();
    }

    public function prev(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prev ?: $this->null();

            case 2:
                return ($this->_prev->_prev ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function next(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_next ?: $this->null();

            case 2:
                return ($this->_next->_next ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function prevCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prevCode ?: $this->null();

            case 2:
                return ($this->_prevCode->_prevCode ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_nextCode ?: $this->null();

            case 2:
                return ($this->_nextCode->_nextCode ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function prevSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prevSibling ?: $this->null();

            case 2:
                return ($this->_prevSibling->_prevSibling ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_nextSibling ?: $this->null();

            case 2:
                return ($this->_nextSibling->_nextSibling ?? null) ?: $this->null();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    /**
     * Get the token's most recent sibling that is one of the listed types
     *
     * @param int|string ...$types
     */
    final public function prevSiblingOf(...$types): Token
    {
        $prev = $this;
        do {
            $prev = $prev->_prevSibling;
        } while ($prev && !$prev->is($types));

        return $prev ?: $this->null();
    }

    /**
     * Get the token's next sibling that is one of the listed types
     *
     * @param int|string ...$types
     */
    final public function nextSiblingOf(...$types): Token
    {
        $next = $this;
        do {
            $next = $next->_nextSibling;
        } while ($next && !$next->is($types));

        return $next ?: $this->null();
    }

    /**
     * Collect the token's previous siblings in order from closest to farthest
     *
     * The token itself is not collected.
     *
     * If set, `$until` must be a previous sibling of the token. It will be the
     * last token collected.
     *
     * @see Token::collectSiblings()
     */
    final public function prevSiblings(Token $until = null): TokenCollection
    {
        $tokens = new TokenCollection();
        $current = $this->OpenedBy ?: $this;
        if ($until) {
            if ($this->Index < $until->Index || $until->IsNull) {
                return $tokens;
            }
            $until = $until->OpenedBy ?: $until;
            if ($current->BracketStack !== $until->BracketStack) {
                throw new RuntimeException('Argument #1 ($until) is not a sibling');
            }
        }

        while ($current = $current->_prevSibling) {
            $tokens[] = $current;
            if ($until && $current === $until) {
                break;
            }
        }

        return $tokens;
    }

    /**
     * Collect the token's siblings up to but not including the last that isn't
     * one of the listed types
     *
     * Tokens are collected in order from closest to farthest.
     *
     * @param int|string ...$types
     */
    final public function prevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the last that
     * isn't one of the listed types
     *
     * Tokens are collected in order from closest to farthest.
     *
     * @param int|string ...$types
     */
    final public function withPrevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $prev = $includeToken ? $this : $this->_prevSibling;
        while ($prev && $prev->is($types)) {
            $tokens[] = $prev;
            $prev = $prev->_prevSibling;
        }

        return $tokens;
    }

    /**
     * Collect the token's siblings up to but not including the first that isn't
     * one of the listed types
     *
     * @param int|string ...$types
     */
    final public function nextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the first that
     * isn't one of the listed types
     *
     * @param int|string ...$types
     */
    final public function withNextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $next = $includeToken ? $this : $this->_nextSibling;
        while ($next && $next->is($types)) {
            $tokens[] = $next;
            $next = $next->_nextSibling;
        }

        return $tokens;
    }

    final public function parent(): Token
    {
        $current = $this->OpenedBy ?: $this;

        return end($current->BracketStack) ?: $this->null();
    }

    /**
     * Collect the token's parents up to but not including the first that isn't
     * one of the listed types
     *
     * @param int|string ...$types
     */
    final public function parentsWhile(...$types): TokenCollection
    {
        return $this->_parentsWhile(false, ...$types);
    }

    /**
     * Collect the token and its parents up to but not including the first that
     * isn't one of the listed types
     *
     * @param int|string ...$types
     */
    final public function withParentsWhile(...$types): TokenCollection
    {
        return $this->_parentsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _parentsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $current = $this->OpenedBy ?: $this;
        $current = $includeToken ? $current : end($current->BracketStack);
        while ($current && $current->is($types)) {
            $tokens[] = $current;
            $current = end($current->BracketStack);
        }

        return $tokens;
    }

    public function outer(): TokenCollection
    {
        return $this->canonical()->collect($this->ClosedBy ?: $this);
    }

    public function inner(): TokenCollection
    {
        return $this->canonical()->next()->collect(($this->ClosedBy ?: $this)->prev());
    }

    public function innerSiblings(): TokenCollection
    {
        return $this->canonical()->nextCode()->collectSiblings(($this->ClosedBy ?: $this)->prevCode());
    }

    final public function startOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineBefore() && $current->_prev) {
            $current = $current->_prev;
        }

        return $current;
    }

    final public function endOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineAfter() && $current->_next) {
            $current = $current->_next;
        }

        return $current;
    }

    /**
     * Get the number of characters since the closest alignment token or the
     * start of the line, whichever is encountered first
     *
     * An alignment token is a token where {@see Token::$AlignedWith} is set.
     * Whitespace at the start of the line is ignored. Code in the token itself
     * is included.
     *
     */
    public function alignmentOffset(bool $includeText = true): int
    {
        $start = $this->startOfLine();
        $start = $start->collect($this)
                       ->reverse()
                       ->find(fn(Token $t, ?Token $next) =>
                                  ($t->AlignedWith && $t->AlignedWith !== $this) ||
                                      ($next && $next === $this->AlignedWith))
            ?: $start;

        $code = $start->collect($this)->render(true);
        $offset = mb_strlen($code);
        // Handle strings with embedded newlines
        if (($newline = mb_strrpos($code, "\n")) !== false) {
            $newLinePadding = $offset - $newline - 1;
            $offset = $newLinePadding - ($this->LinePadding - $this->LineUnpadding);
            if (!$includeText) {
                $offset -= mb_strlen($this->text) - mb_strrpos("\n" . $this->text, "\n");
            }
        } else {
            $offset -= $start->hasNewlineBefore() ? $start->LineUnpadding : 0;
            if (!$includeText) {
                $offset -= mb_strlen($this->text);
            }
        }

        return $offset;
    }

    public function startOfStatement(): Token
    {
        return $this->Statement ?: $this;
    }

    public function endOfStatement(): Token
    {
        return $this->EndStatement ?: $this;
    }

    public function isStartOfExpression(): bool
    {
        return $this->Expression === $this;
    }

    final public function continuesControlStructure(): bool
    {
        return $this->is([T_CATCH, T_FINALLY, T_ELSEIF, T_ELSE]) ||
            ($this->id === T_WHILE && $this->Statement !== $this);
    }

    /**
     * Get the first sibling in the token's expression
     *
     * @param bool $containUnenclosed If `true`, braces are imagined around
     * control structures with unenclosed bodies. The default is `false`.
     */
    final public function pragmaticStartOfExpression(bool $containUnenclosed = false): Token
    {
        // If the token is an object operator, return the first token in the
        // chain
        if ($this->is(TokenType::CHAIN)) {
            $current = $this;
            $first = null;
            while (($current = $current->_prevSibling) &&
                    $this->Expression === $current->Expression &&
                    $current->is([
                        T_DOUBLE_COLON,
                        T_NAME_FULLY_QUALIFIED,
                        T_NAME_QUALIFIED,
                        T_NAME_RELATIVE,
                        T_VARIABLE,
                        ...TokenType::CHAIN_PART
                    ])) {
                $first = $current;
            }

            return $first->_pragmaticStartOfExpression($this);
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the first token after `?`
        $ternary1 =
            $this->prevSiblings()
                 ->find(fn(Token $t) =>
                            $t->IsTernaryOperator &&
                                $t === $t->TernaryOperator1);
        if ($ternary1 && $ternary1->TernaryOperator2->Index > $this->Index) {
            return $ternary1->_nextCode->_pragmaticStartOfExpression($this);
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
            while ($current && $current->Expression === false) {
                if ($i && !($current->IsTernaryOperator ||
                        $current->is(TokenType::OPERATOR_COMPARISON_EXCEPT_COALESCE))) {
                    break;
                }
                $i++;
                [$last, $current] =
                    [$current, $current->_prevSibling];
            }
            $current = $current->Expression ?? null;
            if (!$current) {
                return $last->_pragmaticStartOfExpression($this);
            }

            // Honour imaginary braces around control structures with unenclosed
            // bodies if needed
            if ($containUnenclosed) {
                if ($current->is(TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES) &&
                        ($body = $current->nextSibling())->id !== T['{'] &&
                        $current->EndExpression->withTerminator()->Index >= $this->Index) {
                    return $body->_pragmaticStartOfExpression($this);
                }
                if ($current->is(TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES) &&
                        ($body = $current->nextSibling(2))->id !== T['{'] &&
                        $current->EndExpression->withTerminator()->Index >= $this->Index) {
                    return $body->_pragmaticStartOfExpression($this);
                }
            }

            // Preemptively traverse the boundary so subsequent code can simply
            // `continue`
            [$last, $current] =
                [$current, $current->_prevSibling->_prevSibling ?? null];

            // Don't terminate if the current token continues a control
            // structure
            if ($last->continuesControlStructure()) {
                continue;
            }

            // Undo the boundary traversal
            $current = $last->_prevSibling;
        }
    }

    private function _pragmaticStartOfExpression(Token $requester): Token
    {
        if ($requester !== $this &&
                $this->is([T_RETURN, T_YIELD, T_YIELD_FROM])) {
            return $this->_nextCode;
        }

        return $this;
    }

    /**
     * Get the last sibling in the token's expression
     *
     * Statement separators (e.g. `,` and `;`) are not part of expressions and
     * are not returned by this method.
     *
     * @param bool $containUnenclosed If `true`, braces are imagined around
     * control structures with unenclosed bodies. The default is `false`.
     */
    final public function pragmaticEndOfExpression(bool $containUnenclosed = false, bool $containTopLevelDeclaration = true): Token
    {
        if ($this->EndStatement === $this && $this->Expression === false) {
            return $this;
        }

        // If the token is part of a top-level declaration (namespace, class,
        // function, trait, etc.), return the token before its opening brace
        if ($containTopLevelDeclaration && $this->Statement &&
                !$this->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT]) &&
                ($parts = $this->declarationParts())->has($this, true) &&
                $parts->hasOneOf(...TokenType::DECLARATION_TOP_LEVEL) &&
                // Anonymous functions aren't top-level declarations
                ($last = $parts->last())->id !== T_FUNCTION &&
                ($end = $last->nextSiblingOf(T['{']))->Index < $this->EndStatement->Index) {
            return $end->prevCode();
        }

        // If the token is an expression boundary, return the last token in the
        // statement
        if (!$containUnenclosed && $this->Expression === false) {
            $end = $this->EndStatement ?: $this;

            return $end === $this
                ? $end
                : $end->withoutTerminator();
        }

        // If the token is an object operator, return the last token in the
        // chain
        if ($this->is(TokenType::CHAIN)) {
            $current = $this;
            $last = null;
            while (($current = $current->_nextSibling) &&
                    $this->Expression === $current->Expression &&
                    $current->is(TokenType::CHAIN_PART)) {
                $last = $current;
            }

            return $last->ClosedBy ?: $last;
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the last token before `:`
        $ternary1 =
            $this->prevSiblings()
                 ->find(fn(Token $t) =>
                            $t->IsTernaryOperator &&
                                $t === $t->TernaryOperator1);
        if ($ternary1 && $ternary1->TernaryOperator2->Index > $this->Index) {
            return $ternary1->TernaryOperator2->_prevCode;
        }

        // Otherwise, traverse expressions until an appropriate terminator is
        // reached
        $current = $this->OpenedBy ?: $this;
        $inCase = $current->inSwitchCase();
        while ($current->EndExpression) {
            $current = $current->EndExpression;
            $terminator = ($current->_nextSibling->Expression ?? null) === false
                ? $current->_nextSibling
                : $current;
            $next = $terminator->_nextSibling ?? null;
            if (!$next) {
                return $current;
            }
            [$last, $current] = [$current, $next];

            // Ignore most expression boundaries
            if ($terminator->IsTernaryOperator ||
                    $terminator->is([
                        ...TokenType::OPERATOR_DOUBLE_ARROW,
                        ...TokenType::OPERATOR_ASSIGNMENT,
                        ...TokenType::OPERATOR_COMPARISON_EXCEPT_COALESCE,
                    ])) {
                continue;
            }

            // Don't terminate `case` and `default` statements until the next
            // `case` or `default` is reached
            if ($inCase && !$next->is([T_CASE, T_DEFAULT])) {
                continue;
            }

            // Don't terminate if the next token continues a control structure
            if ($next->is([T_CATCH, T_FINALLY])) {
                continue;
            }
            if ($next->is([T_ELSEIF, T_ELSE]) && (
                !$containUnenclosed ||
                    $terminator->id === T['}'] ||
                    $terminator->prevSiblingOf(T_IF, T_ELSEIF)->Index >= $this->Index
            )) {
                continue;
            }
            if ($next->id === T_WHILE &&
                    $next->Statement !== $next && (
                        !$containUnenclosed ||
                            $terminator->id === T['}'] ||
                            $next->Statement->Index >= $this->Index
                    )) {
                continue;
            }

            return $last;
        }

        return $current;
    }

    /**
     * @param int|string ...$types
     */
    final public function adjacent(...$types): ?Token
    {
        $current = $this->ClosedBy ?: $this;
        if (!$current->is($types ?: ($types = [T[')'], T[','], T[']'], T['}']]))) {
            return null;
        }
        $outer = $current->withNextCodeWhile(true, ...$types)->last();
        if (!$outer->_nextCode ||
                !$outer->EndStatement ||
                $outer->EndStatement->Index <= $outer->_nextCode->Index) {
            return null;
        }

        return $outer->_nextCode;
    }

    final public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?Token
    {
        $current = $this->ClosedBy ?: $this;
        if (!$current->OpenedBy) {
            $current = $this->parent()->ClosedBy;
        }
        if (!$current) {
            return null;
        }
        $eol = $this->endOfLine();
        $outer = $current->withNextCodeWhile(false, T[')'], T[','], T[']'], T['}'])
                         ->filter(fn(Token $t) => $t->Index <= $eol->Index)
                         ->last();
        if (!$outer || !$outer->_nextCode ||
                $outer->_nextCode->Index > $eol->Index ||
                !$outer->EndStatement ||
                $outer->EndStatement->Index <= $outer->_nextCode->Index) {
            return null;
        }

        if ($requireAlignedWith &&
            !$outer->_nextCode->collect($eol)
                              ->find(fn(Token $t) => (bool) $t->AlignedWith)) {
            return null;
        }

        return $outer->_nextCode;
    }

    public function withAdjacentBeforeNewline(?Token $from = null, bool $requireAlignedWith = true): TokenCollection
    {
        if ($adjacent = $this->adjacentBeforeNewline($requireAlignedWith)) {
            $until = $adjacent->EndStatement;
        }

        return ($from ?: $this)->collect($until ?? $this);
    }

    final public function withoutTerminator(): Token
    {
        if ($this->_prevCode &&
            ($this->is([T[';'], T[','], T[':']]) ||
                $this->IsCloseTagStatementTerminator)) {
            return $this->_prevCode;
        }

        return $this;
    }

    final public function withTerminator(): Token
    {
        if ($this->_nextCode &&
            !($this->is([T[';'], T[','], T[':']]) ||
                $this->IsCloseTagStatementTerminator) &&
            ($this->_nextCode->is([T[';'], T[','], T[':']]) ||
                $this->_nextCode->IsCloseTagStatementTerminator)) {
            return $this->_nextCode;
        }

        return $this;
    }

    public function declarationParts(): TokenCollection
    {
        return ($this->Expression ?: $this)
            ->skipAnySiblingsOf(T_RETURN, T_YIELD, T_YIELD_FROM)
            ->withNextSiblingsUntil(
                fn(Token $t) =>
                    !$t->is(TokenType::DECLARATION_PART) &&
                        !($t->id === T['('] && $t->_prevCode->id === T_CLASS)
            );
    }

    public function sinceStartOfStatement(): TokenCollection
    {
        return $this->startOfStatement()->collect($this);
    }

    final public function effectiveWhitespaceBefore(): int
    {
        // If:
        // - this token is a comment pinned to the code below it, and
        // - the previous token isn't a pinned comment (or if it is, it has a
        //   different type and is therefore distinct), and
        // - there are no unpinned comments, or comments with a different type,
        //   between this and the next code token
        //
        // Then:
        // - combine this token's effective whitespace with the next code
        //   token's effective whitespace
        if ($this->PinToCode &&
                $this->_nextCode &&
                (!$this->_prev->PinToCode || $this->_prev->CommentType !== $this->CommentType)) {
            $current = $this;
            while (true) {
                $current = $current->_next;
                if ($current->Index >= $this->_nextCode->Index) {
                    return ($this->_effectiveWhitespaceBefore()
                            | $this->_nextCode->_effectiveWhitespaceBefore())
                        & $this->_prev->WhitespaceMaskNext & $this->_prev->CriticalWhitespaceMaskNext
                        & $this->WhitespaceMaskPrev & $this->CriticalWhitespaceMaskPrev;
                }
                if (!$current->PinToCode || $current->CommentType !== $this->CommentType) {
                    break;
                }
            }
        }
        if (!$this->PinToCode && ($this->_prev->PinToCode ?? false) && $this->IsCode) {
            return ($this->_effectiveWhitespaceBefore() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceBefore();
    }

    private function _effectiveWhitespaceBefore(): int
    {
        return $this->CriticalWhitespaceBefore
            | ($this->_prev->CriticalWhitespaceAfter ?? 0)
            | (($this->WhitespaceBefore
                    | ($this->_prev->WhitespaceAfter ?? 0))
                & ($this->_prev->WhitespaceMaskNext ?? WhitespaceType::ALL)
                & ($this->_prev->CriticalWhitespaceMaskNext ?? WhitespaceType::ALL)
                & $this->WhitespaceMaskPrev
                & $this->CriticalWhitespaceMaskPrev);
    }

    final public function effectiveWhitespaceAfter(): int
    {
        if ($this->PinToCode && ($this->_next->IsCode ?? false)) {
            return ($this->_effectiveWhitespaceAfter() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceAfter();
    }

    private function _effectiveWhitespaceAfter(): int
    {
        return $this->CriticalWhitespaceAfter
            | ($this->_next->CriticalWhitespaceBefore ?? 0)
            | (($this->WhitespaceAfter
                    | ($this->_next->WhitespaceBefore ?? 0))
                & ($this->_next->WhitespaceMaskPrev ?? WhitespaceType::ALL)
                & ($this->_next->CriticalWhitespaceMaskPrev ?? WhitespaceType::ALL)
                & $this->WhitespaceMaskNext
                & $this->CriticalWhitespaceMaskNext);
    }

    final public function hasNewlineBefore(): bool
    {
        return !!($this->effectiveWhitespaceBefore()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    final public function hasNewlineAfter(): bool
    {
        return !!($this->effectiveWhitespaceAfter()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    final public function hasBlankLineBefore(): bool
    {
        return !!($this->effectiveWhitespaceBefore() & WhitespaceType::BLANK);
    }

    final public function hasBlankLineAfter(): bool
    {
        return !!($this->effectiveWhitespaceAfter() & WhitespaceType::BLANK);
    }

    /**
     * True if the token contains a newline
     *
     */
    final public function hasNewline(): bool
    {
        return strpos($this->text, "\n") !== false;
    }

    /**
     * True if, between the token and the next code token, there's a newline
     * between tokens
     *
     */
    final public function hasNewlineAfterCode(): bool
    {
        if ($this->hasNewlineAfter()) {
            return true;
        }
        if (!$this->_nextCode || $this->_nextCode === $this->_next) {
            return false;
        }
        $current = $this;
        while (true) {
            $current = $current->_next;
            if ($current === $this->_nextCode) {
                break;
            }
            if ($current->hasNewlineAfter()) {
                return true;
            }
        }

        return false;
    }

    /**
     * True if, between the previous code token and the token, there's a newline
     * between tokens
     *
     */
    final public function hasNewlineBeforeCode(): bool
    {
        if ($this->hasNewlineBefore()) {
            return true;
        }
        if (!$this->_prevCode || $this->_prevCode === $this->_prev) {
            return false;
        }
        $current = $this;
        while (true) {
            $current = $current->_prev;
            if ($current === $this->_prevCode) {
                break;
            }
            if ($current->hasNewlineBefore()) {
                return true;
            }
        }

        return false;
    }

    public function prevStatementStart(): Token
    {
        $prev = $this->startOfStatement()->prevSibling();
        while ($prev->id === T[';']) {
            $prev = $prev->prevSibling();
        }

        return $prev->startOfStatement();
    }

    public function isStatementPrecursor(): bool
    {
        return $this->_nextCode && $this->_nextCode->Statement === $this->_nextCode;
    }

    /**
     * True if the token is part of a case or default statement
     *
     * Specifically, the token may be:
     * - `T_CASE` or `T_DEFAULT`
     * - the `:` or `;` after `T_CASE` or `T_DEFAULT`, or
     * - part of the expression between `T_CASE` and its terminator
     *
     */
    final public function inSwitchCase(): bool
    {
        return $this->id === T_CASE ||
            ($this->parent()->prevSibling(2)->id === T_SWITCH &&
                ($this->id === T_DEFAULT ||
                    $this->prevSiblingOf(T[':'], T[';'], T_CLOSE_TAG, T_CASE, T_DEFAULT)
                         ->is([T_CASE, T_DEFAULT])));
    }

    /**
     * True if the token is part of a label
     *
     * The token may be the label itself (`T_STRING`) or its terminator (`:`).
     *
     */
    final public function inLabel(): bool
    {
        if ($this->id === T[':']) {
            return $this->prevCode()->id === T_STRING &&
                (($prev = $this->prevCode(2))->is([T[';'], T_CLOSE_TAG]) ||
                    $prev->isStructuralBrace() ||
                    $prev->startsAlternativeSyntax() ||
                    ($prev->id === T[':'] && ($prev->inSwitchCase() || $prev->inLabel())));
        }

        return $this->_nextCode &&
            $this->_nextCode->id === T[':'] &&
            $this->_nextCode->inLabel();
    }

    public function isArrayOpenBracket(): bool
    {
        return $this->id === T['['] ||
            ($this->id === T['('] && $this->prevCode()->id === T_ARRAY);
    }

    final public function isBrace(): bool
    {
        return $this->id === T['{'] || ($this->id === T['}'] && $this->OpenedBy->id === T['{']);
    }

    /**
     * True if the token is a brace that delimits a code block
     *
     * Returns `false` for braces in:
     * - expressions (e.g. `$object->{$property}`)
     * - strings (e.g. `"{$object->property}"`)
     * - alias/import statements (e.g. `use A\{B, C}`)
     *
     * Returns `true` for braces around trait adaptations, and for `match`
     * statement braces if `$orMatch` is `true`.
     *
     */
    final public function isStructuralBrace(bool $orMatch = true): bool
    {
        $current = $this->OpenedBy ?: $this;
        // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
        if ($current->id !== T['{']) {
            return false;
        }
        if (($current->_prevSibling->_prevSibling->id ?? null) === T_MATCH) {
            return $orMatch;
        }
        $lastInner = $current->ClosedBy->_prevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current ||                                    // `{}`
            $lastInner->is([T[':'], T[';']]) ||                              // `{ statement; }`
            $lastInner->IsCloseTagStatementTerminator ||                     /* `{ statement ?>...<?php }` */
            ($lastInner->id === T['}'] && $lastInner->isStructuralBrace());  // `{ { statement; } }`
    }

    public function isOneLineComment(): bool
    {
        return $this->CommentType &&
            !(($this->CommentType[1] ?? null) === '*');
    }

    public function isMultiLineComment(): bool
    {
        return ($this->CommentType[1] ?? null) === '*';
    }

    public function isOperator(): bool
    {
        return $this->is(TokenType::ALL_OPERATOR);
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isUnaryOperator(): bool
    {
        return $this->is([
            T['~'],
            T['$'],
            T['!'],
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
        ]) || (
            $this->is([T['+'], T['-']]) &&
                $this->inUnaryContext()
        );
    }

    final public function inUnaryContext(): bool
    {
        return $this->_prevCode &&
            ($this->_prevCode->IsTernaryOperator ||
                $this->_prevCode->IsCloseTagStatementTerminator ||
                $this->_prevCode->isCloseBraceStatementTerminator() ||
                $this->_prevCode->is([
                    T['('],
                    T[','],
                    T[';'],
                    T['['],
                    T['{'],
                    ...TokenType::OPERATOR_ARITHMETIC,
                    ...TokenType::OPERATOR_ASSIGNMENT,
                    ...TokenType::OPERATOR_BITWISE,
                    ...TokenType::OPERATOR_COMPARISON,
                    ...TokenType::OPERATOR_LOGICAL,
                    ...TokenType::OPERATOR_STRING,
                    ...TokenType::OPERATOR_DOUBLE_ARROW,
                    ...TokenType::CAST,
                    ...TokenType::KEYWORD,
                ]));
    }

    /**
     * @param int|string ...$types
     */
    public function isDeclaration(...$types): bool
    {
        if (!$this->IsCode) {
            return false;
        }
        $parts = $this->declarationParts();

        return $parts->hasOneOf(...TokenType::DECLARATION) &&
            (!$types || $parts->hasOneOf(...$types));
    }

    public function inFunctionDeclaration(): bool
    {
        return ($parent = end($this->BracketStack)) &&
            $parent->id === T['('] &&
            (($parent->_prevCode->id ?? null) === T_FN ||
                $parent->prevOf(T_FUNCTION)->nextOf(T['(']) === $parent);
    }

    /**
     * @return array{PreIndent:int,Indent:int,Deindent:int,HangingIndent:int,LinePadding:int,LineUnpadding:int}
     */
    final public function getIndentDiff(Token $target): array
    {
        return [
            'PreIndent' => $target->PreIndent - $this->PreIndent,
            'Indent' => $target->Indent - $this->Indent,
            'Deindent' => $target->Deindent - $this->Deindent,
            'HangingIndent' => $target->HangingIndent - $this->HangingIndent,
            'LinePadding' => $target->LinePadding - $this->LinePadding,
            'LineUnpadding' => $target->LineUnpadding - $this->LineUnpadding,
        ];
    }

    /**
     * @param array{PreIndent:int,Indent:int,Deindent:int,HangingIndent:int,LinePadding:int,LineUnpadding:int} $diff
     */
    final public function applyIndentDiff(array $diff): void
    {
        $this->PreIndent += $diff['PreIndent'];
        $this->Indent += $diff['Indent'];
        $this->Deindent += $diff['Deindent'];
        $this->HangingIndent += $diff['HangingIndent'];
        $this->LinePadding += $diff['LinePadding'];
        $this->LineUnpadding += $diff['LineUnpadding'];
    }

    private function getIndentSpaces(): int
    {
        return $this->Formatter->TabSize
            * ($this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            + $this->LinePadding - $this->LineUnpadding;
    }

    private function getIndentSpacesFromText(): int
    {
        if (!preg_match('/^(?:\s*\n)?(?P<indent>\h*)\S/', $this->text, $matches)) {
            return 0;
        }

        return strlen(str_replace("\t", $this->Formatter->SoftTab, $matches['indent']));
    }

    public function indent(): int
    {
        return $this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($indent = $this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
            : '';
    }

    public function renderWhitespaceBefore(bool $softTabs = false): string
    {
        $whitespaceBefore = $this->effectiveWhitespaceBefore();

        return WhitespaceType::toWhitespace($whitespaceBefore)
            . ($whitespaceBefore & (WhitespaceType::LINE | WhitespaceType::BLANK)
                ? (($indent = $this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
                        ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
                        : '')
                    . (($padding = $this->LinePadding - $this->LineUnpadding + $this->Padding)
                        ? str_repeat(' ', $padding)
                        : '')
                : ($this->Padding ? str_repeat(' ', $this->Padding) : ''));
    }

    public function render(bool $softTabs = false, ?Token &$last = null): string
    {
        if ($this->id === T_START_HEREDOC) {
            // Render heredocs (including any nested heredocs) in one go so we
            // can safely trim empty lines
            $current = &$last;
            $heredoc = $this->text;
            $current = $this;
            do {
                $current = $current->_next;
                $heredoc .= $current->render($softTabs, $current);
            } while ($current->id !== T_END_HEREDOC ||
                $current->HeredocOpenedBy !== $this);
            if ($this->HeredocIndent) {
                $regex = preg_quote($this->HeredocIndent, '/');
                $heredoc = preg_replace("/\\n$regex\$/m", "\n", $heredoc);
            }
        } elseif ($this->is(TokenType::DO_NOT_MODIFY)) {
            return $this->text;
        } elseif ($this->isMultiLineComment(true)) {
            $comment = $this->renderComment($softTabs);
        }

        if (!$this->is(TokenType::DO_NOT_MODIFY_LHS)) {
            $code = WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore());
            if (($code[0] ?? null) === "\n") {
                // Don't indent close tags unless subsequent text is indented by
                // at least the same amount
                if ($this->id === T_CLOSE_TAG &&
                        $this->_next &&
                        $this->_next->getIndentSpacesFromText() < $this->getIndentSpaces()) {
                    $code .= str_repeat($this->Formatter->Tab, $this->OpenTag->TagIndent);
                } else {
                    if ($this->TagIndent + $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent) {
                        $code .= $this->renderIndent($softTabs);
                    }
                    if ($this->LinePadding - $this->LineUnpadding) {
                        $code .= str_repeat(' ', $this->LinePadding - $this->LineUnpadding);
                    }
                }
            }
            if ($this->Padding) {
                $code .= str_repeat(' ', $this->Padding);
            }
        }

        $code = ($code ?? '') . ($heredoc ?? $comment ?? $this->text);

        if ((is_null($this->_next) || $this->next()->is(TokenType::DO_NOT_MODIFY)) &&
                !$this->is(TokenType::DO_NOT_MODIFY_RHS)) {
            $code .= WhitespaceType::toWhitespace($this->effectiveWhitespaceAfter());
        }

        return $code;
    }

    private function renderComment(bool $softTabs = false): string
    {
        // Remove trailing whitespace from each line, preserving Markdown-style
        // line breaks if requested
        $code = preg_replace("/\\h++{$this->Formatter->PreserveTrailingSpacesRegex}\$/m", '', $this->text);
        switch ($this->id) {
            case T_COMMENT:
                if (!$this->isMultiLineComment() ||
                        preg_match('/\n\h*+(?!\*)(\S|$)/', $code)) {
                    return $code;
                }
            case T_DOC_COMMENT:
                $start = $this->startOfLine();
                $indent =
                    "\n" . ($start === $this || !$this->CommentPlaced
                        ? $this->renderIndent($softTabs)
                            . str_repeat(
                                ' ',
                                $this->LinePadding - $this->LineUnpadding + $this->Padding
                            )
                        : ltrim($start->renderWhitespaceBefore(), "\n")
                            . str_repeat(
                                ' ',
                                mb_strlen($start->collect($this->prev())->render($softTabs))
                                    + strlen(WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore()))
                                    + $this->Padding
                            ));

                return preg_replace([
                    '/\n\h*+(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
                    '/\n\h*+\*?\h*+$/m',
                    '/\n\h*+\*\//',
                ], [
                    $indent . ' * ',
                    $indent . ' *',
                    $indent . ' */',
                ], $code);
        }

        throw new RuntimeException('Not a T_COMMENT or T_DOC_COMMENT');
    }

    public function __toString(): string
    {
        return sprintf(
            'T%d:L%d:%s',
            $this->Index,
            $this->line,
            Convert::ellipsize(var_export($this->text, true), 20)
        );
    }

    public function destroy(): void
    {
        unset(
            $this->_prev,
            $this->_next,
            $this->_prevCode,
            $this->_nextCode,
            $this->_prevSibling,
            $this->_nextSibling,
            $this->BracketStack,
            $this->OpenTag,
            $this->CloseTag,
            $this->OpenedBy,
            $this->ClosedBy,
            $this->Statement,
            $this->EndStatement,
            $this->Expression,
            $this->EndExpression,
            $this->TernaryOperator1,
            $this->TernaryOperator2,
            $this->IndentStack,
            $this->IndentParentStack,
            $this->IndentBracketStack,
            $this->AlignedWith,
            $this->ChainOpenedBy,
            $this->HeredocOpenedBy,
            $this->StringOpenedBy,
            $this->Formatter,
        );
    }
}
