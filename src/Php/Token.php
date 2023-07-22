<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Pretty\Php\Catalog\CommentType;
use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Utility\Convert;
use RuntimeException;

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
     */
    private const EXPRESSION_TERMINATOR = [
        T_CLOSE_BRACKET,
        T_CLOSE_PARENTHESIS,
        T_SEMICOLON,
        ...TokenType::OPERATOR_ASSIGNMENT,
        ...TokenType::OPERATOR_COMPARISON_EXCEPT_COALESCE,
        ...TokenType::OPERATOR_DOUBLE_ARROW,
    ];

    public bool $BodyIsUnenclosed = false;

    public ?Token $OpenTag = null;

    public ?Token $CloseTag = null;

    public ?Token $Statement = null;

    public ?Token $EndStatement = null;

    /**
     * @var Token|false|null
     */
    public $Expression = null;

    public ?Token $EndExpression = null;

    public bool $IsTernaryOperator = false;

    public ?Token $TernaryOperator1 = null;

    public ?Token $TernaryOperator2 = null;

    /**
     * @var CommentType::*|null
     */
    public ?string $CommentType = null;

    public bool $NewlineAfterPreserved = false;

    public int $TagIndent = 0;

    public int $PreIndent = 0;

    public int $Indent = 0;

    public int $Deindent = 0;

    public int $HangingIndent = 0;

    public bool $IsHangingParent = false;

    public bool $IsOverhangingParent = false;

    /**
     * Tokens responsible for each level of hanging indentation applied to the
     * token
     *
     * @var Token[]
     */
    public array $IndentStack = [];

    /**
     * Parent tokens associated with hanging indentation levels applied to the
     * token
     *
     * @var Token[]
     */
    public array $IndentParentStack = [];

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * Only used by {@see AddHangingIndentation::processToken()}.
     *
     * @var array<array<Token[]|Token>>
     */
    public array $IndentBracketStack = [];

    /**
     * Entries represent parent tokens and the collapsible ("overhanging")
     * levels of indentation applied to the token on their behalf
     *
     * Parent token index => collapsible indentation levels applied
     *
     * @var array<int,int>
     */
    public array $OverhangingParents = [];

    public int $LinePadding = 0;

    public int $LineUnpadding = 0;

    public int $Padding = 0;

    public ?string $HeredocIndent = null;

    public ?Token $AlignedWith = null;

    public ?Token $ChainOpenedBy = null;

    public ?Token $HeredocOpenedBy = null;

    public ?Token $StringOpenedBy = null;

    /**
     * Bitmask representing whitespace between the token and its predecessor
     *
     */
    public int $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * Bitmask representing whitespace between the token and its successor
     *
     */
    public int $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * Bitmask applied to whitespace between the token and its predecessor
     *
     */
    public int $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Bitmask applied to whitespace between the token and its successor
     *
     */
    public int $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * predecessor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     *
     */
    public int $CriticalWhitespaceBefore = WhitespaceType::NONE;

    /**
     * Secondary bitmask representing whitespace between the token and its
     * successor
     *
     * Values added to this bitmask MUST NOT BE REMOVED.
     *
     */
    public int $CriticalWhitespaceAfter = WhitespaceType::NONE;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * predecessor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     *
     */
    public int $CriticalWhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * Secondary bitmask applied to whitespace between the token and its
     * successor
     *
     * Values removed from this bitmask MUST NOT BE RESTORED.
     *
     */
    public int $CriticalWhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * True if the token is a T_CLOSE_TAG that terminates a statement
     *
     */
    public bool $IsCloseTagStatementTerminator = false;

    public ?int $OutputLine = null;

    public ?int $OutputPos = null;

    public ?int $OutputColumn = null;

    public Formatter $Formatter;

    /**
     * @param static[] $tokens
     * @return static[]
     */
    public static function prepareTokens(array $tokens, Formatter $formatter): array
    {
        foreach ($tokens as $token) {
            $token->Formatter = $formatter;
            if (!$token->IsVirtual) {
                $text = $token->text;
                if ($token->TokenTypeIndex->DoNotModifyLeft[$token->id]) {
                    $text = rtrim($text);
                } elseif ($token->TokenTypeIndex->DoNotModifyRight[$token->id]) {
                    $text = ltrim($text);
                } elseif (!$token->TokenTypeIndex->DoNotModify[$token->id]) {
                    $text = trim($text);
                }
                if ($text !== $token->text) {
                    $token->setText($text);
                }

                if ($token->id === T_COMMENT) {
                    preg_match('/^(\/\/|\/\*|#)/', $token->text, $matches);
                    $token->CommentType = $matches[1];
                } elseif ($token->id === T_DOC_COMMENT) {
                    $token->CommentType = '/**';
                }

                if ($token->id === T_OPEN_TAG ||
                        $token->id === T_OPEN_TAG_WITH_ECHO) {
                    $token->OpenTag = $token;
                }
            }

            if (!($prev = $token->_prev)) {
                continue;
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
            if (!$token->OpenTag && $prev->OpenTag && !$prev->CloseTag) {
                $token->OpenTag = $prev->OpenTag;
                if ($token->id === T_CLOSE_TAG) {
                    $t = $token;
                    do {
                        $t->CloseTag = $token;
                        $t = $t->_prev;
                    } while ($t && $t->OpenTag === $token->OpenTag);

                    $t = $prev;
                    while ($t->id === T_COMMENT ||
                            $t->id === T_DOC_COMMENT) {
                        $t = $t->_prev;
                    }
                    if ($t->Index > $token->OpenTag->Index &&
                            !$t->is([T_COLON, T_SEMICOLON, T_OPEN_BRACE]) &&
                            ($t->id !== T_CLOSE_BRACE || !$t->isCloseBraceStatementTerminator())) {
                        $token->IsCloseTagStatementTerminator = true;
                        $token->IsCode = true;
                        $t = $token;
                        while ($t = $t->_next) {
                            $t->_prevCode = $token;
                            if ($t->IsCode) {
                                break;
                            }
                        }
                        $t = $token;
                        while ($t = $t->_prev) {
                            $t->_nextCode = $token;
                            if ($t->IsCode) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        reset($tokens)->load();

        return $tokens;
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
        if ((($this->id === T_SEMICOLON ||
            $this->IsCloseTagStatementTerminator ||
            ($this->id === T_CLOSE_BRACE &&
                $this->isCloseBraceStatementTerminator())) &&
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
                ($this->id === T_COLON && ($this->inSwitchCase() || $this->inLabel()))) {
            $this->applyStatement();
        } elseif ($this->is([T_CLOSE_PARENTHESIS, T_CLOSE_BRACKET]) ||
                ($this->id === T_CLOSE_BRACE && !$this->isStructuralBrace(false))) {
            $this->_prevCode->applyStatement();
        } elseif ($this->id === T_COMMA) {
            // For formatting purposes, commas are statement delimiters:
            // - between parentheses and square brackets, e.g. in argument
            //   lists, arrays, `for` expressions
            // - between braces in `match` expressions
            // - in `use` declarations, e.g. `use my_namespace\{a, b}`
            //
            // But they aren't delimiters:
            // - in `use` statements, e.g. `use my_trait { a as b; c as d; }`
            $parent = $this->parent();
            if ($parent->is([T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE]) ||
                ($parent->id === T_OPEN_BRACE &&
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
        if ($this->id !== T_CLOSE_BRACE || !$this->isStructuralBrace(false)) {
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
        if ($this->id === T_QUESTION) {
            $current = $this;
            $count = 0;
            while (($current = $current->_nextSibling) &&
                    $this->EndStatement !== ($current->ClosedBy ?: $current)) {
                if ($current->IsTernaryOperator) {
                    continue;
                }
                if ($current->id === T_QUESTION) {
                    $count++;
                    continue;
                }
                if ($current->id !== T_COLON || $count--) {
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

        if ($this->id === T_CLOSE_BRACE && $this->isStructuralBrace()) {
            $this->_prevCode->applyExpression();
            $this->applyExpression();
        } elseif ($this->is(self::EXPRESSION_TERMINATOR) ||
                $this->IsCloseTagStatementTerminator ||
                ($this->id === T_COLON && ($this->inSwitchCase() || $this->inLabel())) ||
                ($this->id === T_CLOSE_BRACE &&
                    (!$this->isStructuralBrace() || $this->isMatchBrace())) ||
                $this->IsTernaryOperator) {
            // Expression terminators don't form part of the expression
            $this->Expression = false;
            if ($this->_prevCode) {
                $this->_prevCode->applyExpression();
            }
        } elseif ($this->id === T_COMMA) {
            $parent = $this->parent();
            if ($parent->is([T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE]) ||
                ($parent->id === T_OPEN_BRACE &&
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

        return $current->id === T_OPEN_BRACE &&
            $current->prevSibling(2)->id === T_MATCH;
    }

    final public function isMatchDelimiter(bool $betweenArms = true): bool
    {
        return $this->id === T_COMMA &&
            $this->parent()->isMatchBrace() &&
            (!$betweenArms ||
                !($this->prevSiblingOf(
                    T_COMMA, ...TokenType::OPERATOR_DOUBLE_ARROW
                )->is([T_COMMA, T_NULL])));
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
            $a['Index'],
            $a['BracketStack'],
            $a['OpenTag'],
            $a['CloseTag'],
            $a['OpenedBy'],
            $a['ClosedBy'],
            $a['IsCode'],
            $a['IsNull'],
            $a['IsVirtual'],
            $a['TokenTypeIndex'],
            $a['Formatter'],
        );
        $a['id'] = $this->getTokenName();
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($a['WhitespaceBefore']);
        $a['WhitespaceAfter'] = WhitespaceType::toWhitespace($a['WhitespaceAfter']);
        if ($this->Expression === $this || $this->EndExpression === $this || $this->Expression === false) {
            $a['PragmaticStartExpression'] = $this->pragmaticStartOfExpression();
            $a['PragmaticEndExpression'] = $this->pragmaticEndOfExpression();
        }
        foreach ($a as $key => $value) {
            if ($value === null ||
                    $value === [] ||
                    ($value === false && $key !== 'Expression')) {
                unset($a[$key]);
            }
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

    /**
     * @api
     */
    final public function wasFirstOnLine(): bool
    {
        if ($this->IsNull) {
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

    /**
     * @api
     */
    final public function wasLastOnLine(): bool
    {
        if ($this->IsNull) {
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

    /**
     * True if the token is a reserved PHP word
     *
     * Aside from `enum`, "soft reserved words" are not considered PHP keywords,
     * so `false` is returned for `resource` and `numeric`.
     *
     */
    public function isKeyword(): bool
    {
        return $this->is(TokenType::KEYWORD) ||
            ($this->id === T_STRING && in_array($this->text, [
                'bool',
                'false',
                'float',
                'int',
                'iterable',
                'mixed',
                'never',
                'null',
                'object',
                'string',
                'true',
                'void',
            ]));
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
     */
    final public function prevSiblingOf(int ...$types): Token
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
     */
    final public function nextSiblingOf(int ...$types): Token
    {
        $next = $this;
        do {
            $next = $next->_nextSibling;
        } while ($next && !$next->is($types));

        return $next ?: $this->null();
    }

    /**
     * Collect the token's siblings up to but not including the last that isn't
     * one of the listed types
     *
     * Tokens are collected in order from closest to farthest.
     *
     */
    final public function prevSiblingsWhile(int ...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the last that
     * isn't one of the listed types
     *
     * Tokens are collected in order from closest to farthest.
     *
     */
    final public function withPrevSiblingsWhile(int ...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, ...$types);
    }

    /**
     */
    private function _prevSiblingsWhile(bool $includeToken = false, int ...$types): TokenCollection
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
     */
    final public function nextSiblingsWhile(int ...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the first that
     * isn't one of the listed types
     *
     */
    final public function withNextSiblingsWhile(int ...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, ...$types);
    }

    /**
     */
    private function _nextSiblingsWhile(bool $includeToken = false, int ...$types): TokenCollection
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
     */
    final public function parentsWhile(int ...$types): TokenCollection
    {
        return $this->_parentsWhile(false, ...$types);
    }

    /**
     * Collect the token and its parents up to but not including the first that
     * isn't one of the listed types
     *
     */
    final public function withParentsWhile(int ...$types): TokenCollection
    {
        return $this->_parentsWhile(true, ...$types);
    }

    /**
     */
    private function _parentsWhile(bool $includeToken = false, int ...$types): TokenCollection
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
        while (!$current->hasNewlineBefore() &&
                $current->id !== T_END_HEREDOC &&
                $current->_prev) {
            $current = $current->_prev;
        }

        return $current;
    }

    final public function endOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineAfter() &&
                $current->id !== T_START_HEREDOC &&
                $current->_next) {
            $current = $current->_next;
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
        $from =
            $startOfLine
                ->collect($this)
                ->reverse()
                ->find(
                    fn(Token $t, ?Token $next) =>
                        ($t->AlignedWith &&
                                ($allowSelfAlignment || $t !== $this)) ||
                            ($next &&
                                $next === $this->AlignedWith)
                ) ?: $startOfLine;

        $code = $from->collect($this)->render(true);
        if (!$includeToken &&
                ($remove = ltrim($this->render(true))) !== '') {
            $code = substr($code, 0, -strlen($remove));
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
                        ($body = $current->nextSibling())->id !== T_OPEN_BRACE &&
                        $current->EndExpression->withTerminator()->Index >= $this->Index) {
                    return $body->_pragmaticStartOfExpression($this);
                }
                if ($current->is(TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES) &&
                        ($body = $current->nextSibling(2))->id !== T_OPEN_BRACE &&
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
                ($end = $last->nextSiblingOf(T_OPEN_BRACE))->Index < $this->EndStatement->Index) {
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
                    $terminator->id === T_CLOSE_BRACE ||
                    $terminator->prevSiblingOf(T_IF, T_ELSEIF)->Index >= $this->Index
            )) {
                continue;
            }
            if ($next->id === T_WHILE &&
                    $next->Statement !== $next && (
                        !$containUnenclosed ||
                            $terminator->id === T_CLOSE_BRACE ||
                            $next->Statement->Index >= $this->Index
                    )) {
                continue;
            }

            return $last;
        }

        return $current;
    }

    /**
     */
    final public function adjacent(int ...$types): ?Token
    {
        $current = $this->ClosedBy ?: $this;
        if (!$types) {
            $types = [T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_COMMA];
        }
        $outer = $current->withNextCodeWhile(true, ...$types)->last();
        if (!$outer ||
                !$outer->_nextCode ||
                !$outer->EndStatement ||
                $outer->EndStatement->Index <= $outer->_nextCode->Index) {
            return null;
        }
        return $outer->_nextCode;
    }

    final public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?Token
    {
        $current = $this->ClosedBy ?: $this;
        if (!$current->OpenedBy &&
            !(($current = end($this->BracketStack)) &&
                ($current = $current->ClosedBy))) {
            return null;
        }
        $eol = $this->endOfLine();
        $outer = $current->withNextCodeWhile(false, T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_COMMA)
                         ->filter(fn(Token $t) => $t->Index <= $eol->Index)
                         ->last();
        $next = $outer;
        while ($next &&
                $next->Expression === false &&
                $next->_nextSibling &&
                $next->_nextSibling->Index <= $eol->Index) {
            $next = $next->_nextSibling;
        }
        if (!$outer ||
            !$outer->_nextCode ||
            $outer->_nextCode->Index > $eol->Index ||
            ((!$outer->EndStatement ||
                    $outer->EndStatement->Index <= $outer->_nextCode->Index) &&
                ($next === $outer ||
                    $next->EndStatement->Index <= $next->_nextCode->Index))) {
            return null;
        }

        if ($requireAlignedWith &&
            !$outer->_nextCode
                   ->collect($eol)
                   ->find(fn(Token $t) => (bool) $t->AlignedWith)) {
            return null;
        }

        return $next === $outer
            ? $outer->_nextCode
            : $next;
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
            ($this->is([T_SEMICOLON, T_COMMA, T_COLON]) ||
                $this->IsCloseTagStatementTerminator)) {
            return $this->_prevCode;
        }

        return $this;
    }

    final public function withTerminator(): Token
    {
        if ($this->_nextCode &&
            !($this->is([T_SEMICOLON, T_COMMA, T_COLON]) ||
                $this->IsCloseTagStatementTerminator) &&
            ($this->_nextCode->is([T_SEMICOLON, T_COMMA, T_COLON]) ||
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
                               !($t->id === T_OPEN_PARENTHESIS && $t->_prevCode->id === T_CLASS)
                   );
    }

    public function sinceStartOfStatement(): TokenCollection
    {
        return $this->startOfStatement()->collect($this);
    }

    /**
     * @api
     */
    final public function applyBlankLineBefore(bool $withMask = false): void
    {
        $current = $this;
        /** @var Token|null */
        $last = null;
        while (!$current->hasBlankLineBefore() &&
                $current->_prev &&
                $current->_prev->CommentType &&
                $current->_prev->hasNewlineBefore() &&
                (!$last || $current->_prev->CommentType === $last->_prev->CommentType)) {
            $last = $current;
            $current = $current->_prev;
        }
        $current->WhitespaceBefore |= WhitespaceType::BLANK;
        if ($withMask) {
            $current->WhitespaceMaskPrev |= WhitespaceType::BLANK;
        }
    }

    final public function effectiveWhitespaceBefore(): int
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
        while ($prev->id === T_SEMICOLON) {
            $prev = $prev->prevSibling();
        }

        return $prev->startOfStatement();
    }

    /**
     * True if the next code token starts a new expression
     *
     */
    final public function precedesExpression(): bool
    {
        return $this->_nextCode && $this->_nextCode->Expression === $this->_nextCode;
    }

    /**
     * True if the next code token starts a new statement
     *
     */
    final public function precedesStatement(): bool
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
                    $this->prevSiblingOf(T_COLON, T_SEMICOLON, T_CLOSE_TAG, T_CASE, T_DEFAULT)
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
        if ($this->id === T_COLON) {
            return $this->prevCode()->id === T_STRING &&
                (($prev = $this->prevCode(2))->is([T_SEMICOLON, T_CLOSE_TAG]) ||
                    $prev->isStructuralBrace() ||
                    $prev->startsAlternativeSyntax() ||
                    ($prev->id === T_COLON && ($prev->inSwitchCase() || $prev->inLabel())));
        }

        return $this->_nextCode &&
            $this->_nextCode->id === T_COLON &&
            $this->_nextCode->inLabel();
    }

    public function isArrayOpenBracket(): bool
    {
        return $this->id === T_OPEN_BRACKET ||
            ($this->id === T_OPEN_PARENTHESIS && $this->prevCode()->id === T_ARRAY);
    }

    public function isDestructuringConstruct(): bool
    {
        $current = $this->OpenedBy ?: $this;
        return $current->id === T_LIST ||
            $current->prevCode()->id === T_LIST ||
            ($current->id === T_OPEN_BRACKET &&
                (($adjacent = $current->adjacent(T_COMMA, T_CLOSE_BRACKET)) &&
                    $adjacent->id === T_EQUAL) ||
                (($root = $current->withParentsWhile(T_OPEN_BRACKET)->last()) &&
                    $root->prevCode()->id === T_AS &&
                    $root->parent()->prevCode()->id === T_FOREACH));
    }

    final public function isBrace(): bool
    {
        return $this->id === T_OPEN_BRACE || ($this->id === T_CLOSE_BRACE && $this->OpenedBy->id === T_OPEN_BRACE);
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
        if ($current->id !== T_OPEN_BRACE) {
            return false;
        }
        if (($current->_prevSibling->_prevSibling->id ?? null) === T_MATCH) {
            return $orMatch;
        }
        $lastInner = $current->ClosedBy->_prevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current ||                                           // `{}`
            $lastInner->is([T_COLON, T_SEMICOLON]) ||                               // `{ statement; }`
            $lastInner->IsCloseTagStatementTerminator ||                            /* `{ statement ?>...<?php }` */
            ($lastInner->id === T_CLOSE_BRACE && $lastInner->isStructuralBrace());  // `{ { statement; } }`
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
        return $this->is(TokenType::OPERATOR_ALL);
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isUnaryOperator(): bool
    {
        return $this->is([
            T_NOT,
            T_DOLLAR,
            T_LOGICAL_NOT,
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
        ]) || (
            $this->is([T_PLUS, T_MINUS]) &&
                $this->inUnaryContext()
        );
    }

    final public function inUnaryContext(): bool
    {
        if ($this->Expression === $this) {
            return true;
        }
        if (!($prev = $this->_prevCode)) {
            return false;
        }
        if ($prev->EndStatement === $prev) {
            return true;
        }
        return $prev->IsTernaryOperator ||
            $prev->is([
                T_OPEN_BRACE,
                T_OPEN_BRACKET,
                T_OPEN_PARENTHESIS,
                T_DOLLAR_OPEN_CURLY_BRACES,
                T_COMMA,
                T_DOUBLE_ARROW,
                T_SEMICOLON,
                T_BOOLEAN_AND,
                T_BOOLEAN_OR,
                T_AMPERSAND,
                T_CONCAT,
                T_ELLIPSIS,
                ...TokenType::OPERATOR_ARITHMETIC,
                ...TokenType::OPERATOR_ASSIGNMENT,
                ...TokenType::OPERATOR_BITWISE,
                ...TokenType::OPERATOR_COMPARISON,
                ...TokenType::OPERATOR_LOGICAL,
                ...TokenType::CAST,
                ...TokenType::KEYWORD,
            ]);
    }

    /**
     */
    public function isDeclaration(int ...$types): bool
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
            $parent->id === T_OPEN_PARENTHESIS &&
            (($parent->_prevCode->id ?? null) === T_FN ||
                $parent->prevOf(T_FUNCTION)->nextOf(T_OPEN_PARENTHESIS) === $parent);
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

    public function renderWhitespaceBefore(bool $softTabs = false, bool $withNewlines = false): string
    {
        $before = WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore());
        $padding = $this->Padding;
        if ($before && $before[0] === "\n") {
            if (!$withNewlines) {
                $before = ltrim($before, "\n");
            }
            $before .= $this->renderIndent($softTabs);
            $padding += $this->LinePadding - $this->LineUnpadding;
        }
        if ($padding) {
            $before .= str_repeat(' ', $padding);
        }
        return $before;
    }

    public function render(bool $softTabs = false, bool $setPosition = false): string
    {
        if (!($this->TokenTypeIndex->DoNotModify[$this->id] ||
                $this->TokenTypeIndex->DoNotModifyLeft[$this->id])) {
            if (($before = $this->effectiveWhitespaceBefore() ?: '') &&
                    ($before = WhitespaceType::toWhitespace($before)) &&
                    $before[0] === "\n") {
                // Don't indent close tags unless subsequent text is indented by
                // at least the same amount
                if ($this->id === T_CLOSE_TAG &&
                        $this->_next &&
                        $this->_next->getIndentSpacesFromText() < $this->getIndentSpaces()) {
                    $before .= str_repeat(
                        $softTabs
                            ? $this->Formatter->SoftTab
                            : $this->Formatter->Tab,
                        $this->OpenTag->TagIndent
                    );
                } else {
                    if ($indent = $this->indent()) {
                        $before .= str_repeat(
                            $softTabs
                                ? $this->Formatter->SoftTab
                                : $this->Formatter->Tab,
                            $indent
                        );
                    }
                    if ($padding = $this->LinePadding - $this->LineUnpadding) {
                        $before .= str_repeat(' ', $padding);
                    }
                }
            }
            if ($this->Padding) {
                $before .= str_repeat(' ', $this->Padding);
            }
        }

        if ((!$this->_next ||
                $this->TokenTypeIndex->DoNotModify[$this->_next->id] ||
                $this->TokenTypeIndex->DoNotModifyLeft[$this->_next->id]) &&
            !($this->TokenTypeIndex->DoNotModify[$this->id] ||
                $this->TokenTypeIndex->DoNotModifyRight[$this->id])) {
            $after = WhitespaceType::toWhitespace($this->effectiveWhitespaceAfter());
        }

        if ($setPosition) {
            if (!$this->_prev) {
                $this->OutputLine = 1;
                $this->OutputColumn = 1;
                $this->OutputPos = 0;
            }

            // Adjust the token's position to account for any leading whitespace
            if ($before ?? null) {
                $this->movePosition($before, $this->Formatter->Tab === "\t");
            }

            // And use it as the baseline for the next token's position
            if ($this->_next) {
                $this->_next->OutputLine = $this->OutputLine;
                $this->_next->OutputColumn = $this->OutputColumn;
                $this->_next->OutputPos = $this->OutputPos;
            }
        }

        // Multi-line comments are only formatted when output is being generated
        if ($setPosition &&
                $this->CommentType &&
                strpos($this->text, "\n") !== false) {
            $text = $this->renderComment($softTabs);
        }

        if ($this->id === T_START_HEREDOC ||
                ($this->HeredocOpenedBy && $this->id !== T_END_HEREDOC)) {
            $heredoc = $this->HeredocOpenedBy ?: $this;
            if ($heredoc->HeredocIndent) {
                $text = preg_replace(
                    ($this->_next->text[0] ?? null) === "\n"
                        ? "/\\n{$heredoc->HeredocIndent}\$/m"
                        : "/\\n{$heredoc->HeredocIndent}(?=\\n)/",
                    "\n",
                    $text ?? $this->text
                );
            }
        }

        $output = ($text ?? $this->text) . ($after ?? '');
        if ($output !== '' && $setPosition && $this->_next) {
            $this->_next->movePosition($output, $this->TokenTypeIndex->Expandable[$this->id]);
        }

        return ($before ?? '') . $output;
    }

    private function movePosition(string $code, bool $expandTabs): void
    {
        $expanded = !$expandTabs || strpos($code, "\t") === false
            ? $code
            : Convert::expandTabs($code, $this->Formatter->TabSize, $this->OutputColumn);
        $this->OutputLine += ($newlines = substr_count($code, "\n"));
        $this->OutputColumn = $newlines
            ? mb_strlen($expanded) - mb_strrpos($expanded, "\n")
            : $this->OutputColumn + mb_strlen($expanded);
        $this->OutputPos += strlen($code);
    }

    private function renderComment(bool $softTabs): string
    {
        if ($this->ExpandedText) {
            /** @todo Guess input tab size and use it instead */
            $code = Convert::expandLeadingTabs(
                $this->text, $this->Formatter->TabSize, !$this->wasFirstOnLine(), $this->column
            );
        }

        // Remove trailing whitespace from each line
        $code = preg_replace('/\h++$/m', '', $code ?? $this->text);

        if ($this->id === T_COMMENT && preg_match('/\n\h*+(?!\*)\S/', $code)) {
            $delta = $this->OutputColumn - $this->column;
            if (!$delta) {
                return $this->maybeUnexpandTabs($code, $softTabs);
            }
            $spaces = str_repeat(' ', abs($delta));
            if ($delta < 0) {
                // Don't deindent if any non-empty lines have insufficient
                // whitespace
                if (preg_match("/\\n(?!{$spaces}|\\n)/", $code)) {
                    return $this->maybeUnexpandTabs($code, $softTabs);
                }
                return $this->maybeUnexpandTabs(str_replace("\n" . $spaces, "\n", $code), $softTabs);
            }
            return $this->maybeUnexpandTabs(str_replace("\n", "\n" . $spaces, $code), $softTabs);
        }

        $start = $this->startOfLine();
        if ($start === $this) {
            $indent = "\n" . $this->renderIndent($softTabs)
                . str_repeat(' ', $this->LinePadding - $this->LineUnpadding + $this->Padding);
        } else {
            $indent = "\n" . $start->renderWhitespaceBefore($softTabs)
                . str_repeat(' ', mb_strlen($start->collect($this->prev())->render($softTabs))
                    + strlen(WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore()))
                    + $this->Padding);
        }
        // Normalise the start and end of multi-line docblocks as per PSR-5
        if ($this->id === T_DOC_COMMENT) {
            $code = preg_replace(
                ['/^\/\*\*++\s*+/', '/\s*+\*++\/$/'],
                ["/**\n", $indent . ' */'],
                $code
            );
        } else {
            $code = preg_replace(
                '/\n\h*+(\*++\/)$/',
                $indent . ' $1',
                $code
            );
        }
        return preg_replace([
            '/\n\h*+(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
            '/\n\h*+\*?$/m',
        ], [
            $indent . ' * ',
            $indent . ' *',
        ], $code);
    }

    private function maybeUnexpandTabs(string $text, bool $softTabs): string
    {
        if ($this->Formatter->Tab === "\t" && !$softTabs) {
            return preg_replace("/(?<=\\n|\G){$this->Formatter->SoftTab}/", "\t", $text);
        }
        return $text;
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
}
