<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\PrettyException;
use Lkrms\Pretty\WhitespaceType;
use PhpToken;
use RuntimeException;
use Throwable;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

class Token extends PhpToken implements JsonSerializable
{
    /**
     * @var string[]
     */
    protected const ALLOW_READ = [];

    /**
     * @var string[]
     */
    protected const ALLOW_WRITE = [];

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

    private const ALT_SYNTAX_TERMINATOR = [
        ...TokenType::ENDS_ALTERNATIVE_SYNTAX,
        ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITH_EXPRESSION,
        ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITHOUT_EXPRESSION,
    ];

    // Declare these first for ease of debugging

    /**
     * @var Token|null
     */
    private $_prev;

    /**
     * @var Token|null
     */
    private $_next;

    /**
     * @var Token|null
     */
    private $_prevCode;

    /**
     * @var Token|null
     */
    private $_nextCode;

    /**
     * @var Token|null
     */
    private $_prevSibling;

    /**
     * @var Token|null
     */
    private $_nextSibling;

    /**
     * The token's position (0-based) in the array returned by tokenize()
     *
     */
    public ?int $Index = null;

    public ?string $OriginalText = null;

    /**
     * @var Token[]
     */
    public $BracketStack = [];

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
    public $OpenedBy;

    /**
     * @var Token|null
     */
    public $ClosedBy;

    /**
     * @var bool
     */
    public $IsCode = true;

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

    /**
     * @var bool
     */
    public $IsTernaryOperator = false;

    /**
     * @var Token|null
     */
    public $TernaryOperator1;

    /**
     * @var Token|null
     */
    public $TernaryOperator2;

    /**
     * @var array<array<string,mixed>>
     */
    public $Log = [];

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
     * Only used by
     * {@see \Lkrms\Pretty\Php\Rule\AddHangingIndentation::processToken()}.
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
     * @var bool
     */
    public bool $IsNull = false;

    /**
     * @var bool
     */
    public bool $IsVirtual = false;

    /**
     * @var int
     */
    public $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * @var int
     */
    public $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * @var int
     */
    public $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * @var int
     */
    public $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * @var Formatter|null
     */
    protected $Formatter;

    /**
     * @var bool
     */
    public $IsCloseTagStatementTerminator = false;

    /**
     * @return static[]
     */
    public static function tokenize(string $code, int $flags = 0, TokenFilter ...$filters): array
    {
        $tokens = parent::tokenize($code, $flags);
        if ($filters) {
            $tokens = self::filterTokens($tokens, ...$filters);
        }

        return $tokens;
    }

    /**
     * @template T0 of Token
     * @param T0[] $tokens
     * @return T0[]
     */
    public static function filterTokens(array $tokens, TokenFilter ...$filters): array
    {
        try {
            foreach ($filters as $filter) {
                $tokens = $filter($tokens);
            }

            return $tokens;
        } catch (Throwable $ex) {
            throw new PrettyException(
                'filterTokens failed',
                null,
                $tokens,
                null,
                $ex
            );
        }
    }

    /**
     * @param static[] $tokens
     * @return static[]
     */
    public static function prepareTokens(array $tokens, Formatter $formatter): array
    {
        if (!$tokens) {
            return $tokens;
        }
        /** @var static[] */
        $prepared = [];
        /** @var static */
        $prev     = null;
        $nextKey  = (int) array_key_last($tokens) + 1;
        try {
            foreach ($tokens as $index => $token) {
                // PHP's alternative syntax has no `}` equivalent, so insert a
                // virtual token where it should be
                if ($token->is(self::ALT_SYNTAX_TERMINATOR)) {
                    $stack  = $prev->BracketStack;
                    $opener = array_pop($stack);
                    if (($opener && $opener->is(T[':']) && $opener->BracketStack === $stack) ||
                            $prev->startsAlternativeSyntax()) {
                        $virtual            = VirtualToken::create(T_END_ALT_SYNTAX);
                        $prepared[$nextKey] = $virtual;
                        $virtual->Index     = $nextKey++;
                        $virtual->Formatter = $formatter;
                        $virtual->prepare($prev);
                        $prev = $virtual;
                    }
                }
                $prepared[$index] = $token;
                $token->Index     = $index;
                $token->Formatter = $formatter;
                $token->prepare($prev);
                $prev = $token;
            }
            reset($prepared)->load();

            return $prepared;
        } catch (Throwable $ex) {
            throw new PrettyException(
                'prepareTokens failed',
                null,
                $prepared,
                null,
                $ex
            );
        }
    }

    public function getTokenName(): ?string
    {
        return TokenType::NAME_MAP[$this->id] ?? parent::getTokenName();
    }

    protected function prepare(?Token $prev): void
    {
        if (!$this->IsVirtual) {
            $text = $this->text;
            if ($this->is(TokenType::DO_NOT_MODIFY_LHS)) {
                $this->text = rtrim($this->text);
            } elseif ($this->is(TokenType::DO_NOT_MODIFY_RHS)) {
                $this->text = ltrim($this->text);
            } elseif (!$this->is(TokenType::DO_NOT_MODIFY)) {
                $this->text = trim($this->text);
            }
            if ($text !== $this->text) {
                $this->OriginalText = $text;
            }

            if ($this->is(TokenType::NOT_CODE)) {
                $this->IsCode = false;
            }

            if ($this->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                $this->OpenTag = $this;
            }
        }

        if (!$prev) {
            return;
        }

        $this->_prev = $prev;
        $prev->_next = $this;

        $this->_prevCode = $prev->IsCode
            ? $prev
            : $prev->_prevCode;
        if ($this->IsCode) {
            $t = $prev;
            do {
                $t->_nextCode = $this;
                $t            = $t->_prev;
            } while ($t && !$t->_nextCode);
        }

        $this->BracketStack = $prev->BracketStack;
        $stackDelta         = 0;
        if ($prev->isOpenBracket() || $prev->startsAlternativeSyntax()) {
            $this->BracketStack[] = $prev;
            $stackDelta++;
        } elseif ($prev->isCloseBracket() || $prev->endsAlternativeSyntax()) {
            array_pop($this->BracketStack);
            $stackDelta--;
        }

        if ($this->isCloseBracket() || $this->endsAlternativeSyntax()) {
            $opener             = end($this->BracketStack);
            $opener->ClosedBy   = $this;
            $this->OpenedBy     = $opener;
            $this->_prevSibling = &$opener->_prevSibling;
            $this->_nextSibling = &$opener->_nextSibling;
        } else {
            switch (true) {
                // First token inside a pair of brackets
                case $stackDelta > 0:
                    // Nothing to do
                    break;

                // First token after a close bracket
                case $stackDelta < 0:
                    $this->_prevSibling = $prev->OpenedBy;
                    break;

                // Continuation of previous context
                default:
                    if ($this->_prevCode &&
                            $this->_prevCode->canonical()->BracketStack === $this->BracketStack) {
                        $this->_prevSibling = $this->_prevCode->canonical();
                    }
                    break;
            }

            if ($this->IsCode) {
                if ($this->_prevSibling &&
                        !$this->_prevSibling->_nextSibling) {
                    $t = $this;
                    do {
                        $t               = $t->_prev->OpenedBy ?: $t->_prev;
                        $t->_nextSibling = $this;
                    } while ($t->_prev && $t !== $this->_prevSibling);
                } elseif (!$this->_prevSibling) {
                    $t = $this->_prev;
                    while ($t && $t->BracketStack === $this->BracketStack) {
                        $t->_nextSibling = $this;
                        $t               = $t->_prev;
                    }
                }
            }
        }

        /**
         * Intended outcome:
         *
         * ```php
         * <?php            // OpenTag = itself, CloseTag = Token
         * $foo = 'bar';    // OpenTag = Token,  CloseTag = Token
         * ?>               // OpenTag = Token,  CloseTag = itself
         * ```
         *
         * `CloseTag` is `null` if there's no closing `?>`
         */
        if (!$this->OpenTag && $prev->OpenTag && !$prev->CloseTag) {
            $this->OpenTag = $prev->OpenTag;
            if ($this->is(T_CLOSE_TAG)) {
                $t = $this;
                do {
                    $t->CloseTag = $this;
                    $t           = $t->_prev;
                } while ($t && $t->OpenTag === $this->OpenTag);

                // TODO: use BracketStack for a more robust assessment?
                $t = $prev;
                while ($t->is(TokenType::COMMENT)) {
                    $t = $t->_prev;
                }
                if ($t->Index > $this->OpenTag->Index &&
                        !$t->is([T['('], T[','], T[':'], T[';'], T['['], T['{']])) {
                    $this->IsCode                        = true;
                    $this->IsCloseTagStatementTerminator = true;
                }
            }
        }
    }

    protected function load(): void
    {
        $passes = [
            fn(Token $t) => !$t->IsCode || $t->maybeApplyStatement(),
            fn(Token $t) => !$t->IsCode || $t->maybeApplyExpression(),
        ];
        foreach ($passes as $pass) {
            $current = $this;
            do {
                $pass($current);
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
        if ((($this->is(T[';']) ||
                $this->IsCloseTagStatementTerminator ||
                ($this->is(T['}']) &&
                    $this->isCloseBraceStatementTerminator())) &&
                !$this->nextCode()->is([T_CATCH, T_FINALLY]) &&
                !$this->nextCode()->is([T_ELSEIF, T_ELSE]) &&
                !($this->nextCode()->is(T_WHILE) &&
                    !($do = $this->prevSiblingOf(T_DO))->IsNull &&
                    $do->nextSibling()->nextSiblingOf(T_WHILE) === $this->nextCode())) ||
                $this->startsAlternativeSyntax() ||
                ($this->is(T[':']) && ($this->inSwitchCase() || $this->inLabel()))) {
            $this->applyStatement();
        } elseif ($this->OpenedBy && $this->OpenedBy->is(T_ATTRIBUTE)) {
            $this->_prevCode->applyStatement();
            $this->applyStatement();
        } elseif ($this->is([T[')'], T[']']])) {
            $this->_prevCode->applyStatement();
        } elseif ($this->is(T[','])) {
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
                ($parent->is(T['{']) &&
                    ($parent->prevSibling(2)->is(T_MATCH) || !$parent->isStructuralBrace()))) {
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
            $latest  = $current;
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
        if (!$this->is(T['}']) || !$this->isStructuralBrace()) {
            return false;
        }

        if (!($start = $this->Statement)) {
            // Find the end of the last statement for the start of this one
            $current = $this->OpenedBy->_prevSibling;
            while ($current && !$current->EndStatement) {
                $start   = $current;
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

        if ($start->is(T_USE)) {
            $parent = $start->parent();
            // - Alias/import statements (e.g. `use const <FQCN>`) end with `;`
            // - `use <trait> { ... }` ends with `}`
            if ($parent->isNull() ||
                $parent->prevSiblingsWhile(...TokenType::DECLARATION_PART)
                       ->hasOneOf(T_NAMESPACE)) {
                return false;
            }

            return true;
        }

        // - Anonymous functions and classes are unterminated
        // - Other declarations end with `}`
        $parts = $start->withNextSiblingsWhile(...[
            T_NEW,
            ...TokenType::DECLARATION_PART
        ]);
        if ($parts->hasOneOf(...TokenType::DECLARATION) &&
                !$parts->last()->is(T_FUNCTION) &&
                !($parts->first()->is(T_NEW) && $parts->nth(2)->is(T_CLASS))) {
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
        if ($this->is(T['?'])) {
            $current = $this;
            $count   = 0;
            while (($current = $current->_nextSibling) &&
                    $this->EndStatement !== ($current->ClosedBy ?: $current)) {
                if ($current->IsTernaryOperator) {
                    continue;
                }
                if ($current->is(T['?'])) {
                    $count++;
                    continue;
                }
                if (!$current->is(T[':']) || $count--) {
                    continue;
                }
                $current->IsTernaryOperator = $this->IsTernaryOperator = true;
                $current->TernaryOperator1  = $this->TernaryOperator1 = $this;
                $current->TernaryOperator2  = $this->TernaryOperator2 = $current;
                break;
            }
        }

        if (($this->is(T['}']) && $this->isStructuralBrace()) ||
                ($this->OpenedBy && $this->OpenedBy->is(T_ATTRIBUTE))) {
            $this->_prevCode->applyExpression();
            $this->applyExpression();
        } elseif ($this->is(self::EXPRESSION_TERMINATOR) ||
                $this->IsCloseTagStatementTerminator ||
                $this->startsAlternativeSyntax() ||
                ($this->is(T[':']) && ($this->inSwitchCase() || $this->inLabel())) ||
                $this->isTernaryOperator()) {
            // Expression terminators don't form part of the expression
            $this->Expression = false;
            $this->_prevCode->applyExpression();
        } elseif ($this->is(T[','])) {
            $parent = $this->parent();
            if ($parent->is([T['('], T['[']]) ||
                ($parent->is(T['{']) &&
                    ($parent->prevSibling(2)->is(T_MATCH) || !$parent->isStructuralBrace()))) {
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
            $latest  = $current;
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

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $a                 = get_object_vars($this);
        $_prevSibling      = (string) $a['_prevSibling'];
        $_nextSibling      = (string) $a['_nextSibling'];
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
        $a['id']               = $this->getTokenName();
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($a['WhitespaceBefore']);
        $a['WhitespaceAfter']  = WhitespaceType::toWhitespace($a['WhitespaceAfter']);
        if ($this->Expression === $this || $this->Expression === false) {
            $a['PragmaticEndExpression'] = $this->pragmaticEndOfExpression();
        }
        if (empty($a['Log'])) {
            unset($a['Log']);
        } else {
            $a['Log'] = array_map(fn(array $entry) => json_encode($entry),
                                  $a['Log']);
        }
        array_walk_recursive($a, function (&$value) {
            if ($value instanceof Token) {
                $value = (string) $value;
            }
        });

        return $a;
    }

    final public function canonical(): Token
    {
        return $this->OpenedBy ?: $this;
    }

    final public function wasFirstOnLine(): bool
    {
        if ($this->IsVirtual) {
            return false;
        }
        do {
            $prev = $this->prev();
            if ($prev->IsNull) {
                return true;
            }
        } while ($prev->IsVirtual);
        $prevCode     = $prev->OriginalText ?: $prev->text;
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
            $next = $this->next();
            if ($next->IsNull) {
                return true;
            }
        } while ($next->IsVirtual);
        $code     = $this->OriginalText ?: $this->text;
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

        return $t ?: NullToken::create();
    }

    public function prev(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prev ?: NullToken::create();

            case 2:
                return ($this->_prev->_prev ?? null) ?: NullToken::create();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function next(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_next ?: NullToken::create();

            case 2:
                return ($this->_next->_next ?? null) ?: NullToken::create();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    /**
     * @param int|string ...$types
     */
    public function prevWhile(...$types): TokenCollection
    {
        return $this->_prevWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withPrevWhile(...$types): TokenCollection
    {
        return $this->_prevWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $p = $with ? $this : $this->prev();
        while ($p->is($types)) {
            $c[] = $p;
            $p   = $p->prev();
        }

        return $c;
    }

    /**
     * @param int|string ...$types
     */
    public function nextWhile(...$types): TokenCollection
    {
        return $this->_nextWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withNextWhile(...$types): TokenCollection
    {
        return $this->_nextWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $n = $with ? $this : $this->next();
        while ($n->is($types)) {
            $c[] = $n;
            $n   = $n->next();
        }

        return $c;
    }

    public function prevCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prevCode ?: NullToken::create();

            case 2:
                return ($this->_prevCode->_prevCode ?? null) ?: NullToken::create();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextCode(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_nextCode ?: NullToken::create();

            case 2:
                return ($this->_nextCode->_nextCode ?? null) ?: NullToken::create();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    /**
     * @param int|string ...$types
     */
    public function prevCodeWhile(...$types): TokenCollection
    {
        return $this->_prevCodeWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withPrevCodeWhile(...$types): TokenCollection
    {
        return $this->_prevCodeWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevCodeWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $p = $with ? $this : $this->prevCode();
        while ($p->is($types)) {
            $c[] = $p;
            $p   = $p->prevCode();
        }

        return $c;
    }

    /**
     * @param int|string ...$types
     */
    public function nextCodeWhile(...$types): TokenCollection
    {
        return $this->_nextCodeWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withNextCodeWhile(...$types): TokenCollection
    {
        return $this->_nextCodeWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextCodeWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $n = $with ? $this : $this->nextCode();
        while ($n->is($types)) {
            $c[] = $n;
            $n   = $n->nextCode();
        }

        return $c;
    }

    public function prevSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_prevSibling ?: NullToken::create();

            case 2:
                return ($this->_prevSibling->_prevSibling ?? null) ?: NullToken::create();
        }

        return $this->byOffset(__FUNCTION__, $offset);
    }

    public function nextSibling(int $offset = 1): Token
    {
        switch ($offset) {
            case 1:
                return $this->_nextSibling ?: NullToken::create();

            case 2:
                return ($this->_nextSibling->_nextSibling ?? null) ?: NullToken::create();
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

        return $prev ?: NullToken::create();
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

        return $next ?: NullToken::create();
    }

    /**
     * Collect the token's previous siblings in order from closest to farthest
     *
     * The token itself is not collected.
     *
     * If set, `$until` must be a previous sibling of the token. It will be
     * collected.
     */
    final public function prevSiblings(Token $until = null): TokenCollection
    {
        $tokens  = new TokenCollection();
        $current = $this->OpenedBy ?: $this;
        if ($until) {
            $until = $until->OpenedBy ?: $until;
            if ($current->Index < $until->Index || $until->IsNull) {
                return $tokens;
            }
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
     * Tokens are collected in order from the closest sibling to the farthest.
     *
     * @param int|string ...$types
     */
    public function prevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the last that
     * isn't one of the listed types
     *
     * Tokens are collected in order from the closest sibling to the farthest.
     *
     * @param int|string ...$types
     */
    public function withPrevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $prev   = $includeToken ? $this : $this->prevSibling();
        while ($prev->is($types)) {
            $tokens[] = $prev;
            $prev     = $prev->prevSibling();
        }

        return $tokens;
    }

    /**
     * Collect the token's siblings up to but not including the first that isn't
     * one of the listed types
     *
     * @param int|string ...$types
     */
    public function nextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the first that
     * isn't one of the listed types
     *
     * @param int|string ...$types
     */
    public function withNextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $next   = $includeToken ? $this : $this->nextSibling();
        while ($next->is($types)) {
            $tokens[] = $next;
            $next     = $next->nextSibling();
        }

        return $tokens;
    }

    public function prevSiblingsUntil(callable $callback): TokenCollection
    {
        $tokens  = new TokenCollection();
        $current = $this;
        while ($current->_prevSibling && !$callback($current->_prevSibling, $tokens)) {
            $current  = $current->_prevSibling;
            $tokens[] = $current;
        }

        return $tokens;
    }

    public function parent(): Token
    {
        $current = $this->OpenedBy ?: $this;

        return end($current->BracketStack) ?: NullToken::create();
    }

    /**
     * Collect the token's parents up to but not including the first that isn't
     * one of the listed types
     *
     * @param bool $includeToken If `true`, collect the token itself. If it
     * isn't one of the listed types, an empty collection is returned.
     * @param int|string ...$types
     */
    public function parentsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $next   = $this->OpenedBy ?: $this;
        $next   = $includeToken ? $next : $next->parent();
        while ($next->is($types)) {
            $tokens[] = $next;
            $next     = $next->parent();
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

    public function isCode(): bool
    {
        return $this->IsCode;
    }

    public function isNull(): bool
    {
        return $this->IsNull;
    }

    public function isVirtual(): bool
    {
        return $this->IsVirtual;
    }

    public function startOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineBefore() && !($prev = $current->prev())->isNull()) {
            $current = $prev;
        }

        return $current;
    }

    public function endOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineAfter() && !($next = $current->next())->isNull()) {
            $current = $next;
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
    public function alignmentOffset(): int
    {
        $start = $this->startOfLine();
        $start = $start->collect($this)
                       ->reverse()
                       ->find(fn(Token $t, ?Token $prev, ?Token $next) =>
                           ($t->AlignedWith && $t->AlignedWith !== $this) ||
                               ($next && $next === $this->AlignedWith))
                           ?: $start;

        $code   = $start->collect($this)->render(true);
        $offset = mb_strlen($code);
        // Handle strings with embedded newlines
        if (($newline = mb_strrpos($code, "\n")) !== false) {
            $newLinePadding = $offset - $newline - 1;
            $offset         = $newLinePadding - ($this->LinePadding - $this->LineUnpadding);
        } else {
            $offset        -= $start->hasNewlineBefore() ? $start->LineUnpadding : 0;
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

    public function startOfExpression(): Token
    {
        return $this->Expression ?: $this;
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
    public function pragmaticEndOfExpression(bool $containUnenclosed = false): Token
    {
        // If the token is an expression boundary, return the last token in the
        // statement
        if (!$containUnenclosed && $this->Expression === false) {
            $end = $this->EndStatement ?: $this;

            return $end === $this
                ? $end
                : $end->withoutTerminator();
        }

        // If the token is between `?` and `:` in a ternary expression, return
        // the last token before `:`
        $current = $this->OpenedBy ?: $this;
        if (($prev = $current->Expression->_prevCode ?? null) &&
                $prev->is(T['?']) &&
                $prev->IsTernaryOperator) {
            return $prev->_nextCode->EndExpression;
        }

        // Otherwise, traverse expressions until an appropriate terminator is
        // reached
        $inCase = $current->inSwitchCase();
        while ($current->EndExpression) {
            $current    = $current->EndExpression;
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
                        T_DOUBLE_ARROW,
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
                    $terminator->is(T['}']) ||
                    $terminator->prevSiblingOf(T_IF, T_ELSEIF)->Index >= $this->Index
            )) {
                continue;
            }
            if ($next->is(T_WHILE) &&
                    !($do = $terminator->prevSiblingOf(T_DO))->IsNull &&
                    $do->nextSibling()->nextSiblingOf(T_WHILE) === $next && (
                        !$containUnenclosed ||
                            $terminator->is(T['}']) ||
                            $do->Index >= $this->Index
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
        $_this = $this->ClosedBy ?: $this;
        if (!$_this->is($types ?: ($types = [T[')'], T[','], T[']'], T['}']]))) {
            return null;
        }
        $outer = $_this->withNextCodeWhile(...$types)->last();
        if (!$outer->EndStatement || !$outer->_nextCode ||
                ($outer->EndStatement->Index <= $outer->_nextCode->Index)) {
            return null;
        }

        return $outer->_nextCode;
    }

    public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?Token
    {
        $current =
            $this->is([T['('], T[')'], T['['], T[']'], T['{'], T['}']])
                ? ($this->ClosedBy ?: $this)
                : $this->parent()->ClosedBy;
        if (!$current) {
            return null;
        }
        $eol   = $this->endOfLine();
        $outer = $current->withNextCodeWhile(T[')'], T[']'], T['}'])
                         ->filter(fn(Token $t) => $t->Index <= $eol->Index)
                         ->last();
        if (!$outer || !$outer->_nextCode ||
                ($outer->_nextCode->Index > $eol->Index) ||
                !$outer->EndStatement ||
                ($outer->EndStatement->Index <= $outer->_nextCode->Index)) {
            return null;
        }

        if ($requireAlignedWith &&
            !$outer->_nextCode->collect($eol)
                              ->find(fn(Token $item) => (bool) $item->AlignedWith)) {
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

    public function withoutTerminator(): Token
    {
        if ($this->_prevCode &&
            ($this->is([T[';'], T[','], T[':']]) ||
                $this->IsCloseTagStatementTerminator)) {
            return $this->_prevCode;
        }

        return $this;
    }

    public function declarationParts(): TokenCollection
    {
        return $this->startOfExpression()->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
    }

    public function sinceStartOfStatement(): TokenCollection
    {
        return $this->startOfStatement()->collect($this);
    }

    public function effectiveWhitespaceBefore(): int
    {
        // If this is a comment pinned to the code below it ...
        if ($this->PinToCode &&
            // and the previous token isn't a pinned comment (or if it is, it
            // has a different type and is therefore distinct) ...
            (!$this->prev()->PinToCode || !$this->isSameTypeAs($this->prev())) &&
            // and there are no comments between this and the next code token
            // that aren't pinned or have a different type, then ...
            !count($this->next()
                        ->collect(($next = $this->nextCode())->prev())
                        ->filter(fn(Token $t) => !$t->PinToCode || !$this->isSameTypeAs($t)))) {
            // Combine this token's effective whitespace with the next code
            // token's effective whitespace
            return ($this->_effectiveWhitespaceBefore()
                    | $next->_effectiveWhitespaceBefore())
                & $this->prev()->WhitespaceMaskNext & $this->WhitespaceMaskPrev;
        }
        if (!$this->PinToCode && $this->prev()->PinToCode && $this->IsCode) {
            return ($this->_effectiveWhitespaceBefore() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceBefore();
    }

    private function _effectiveWhitespaceBefore(): int
    {
        return ($this->WhitespaceBefore | $this->prev()->WhitespaceAfter)
            & $this->prev()->WhitespaceMaskNext & $this->WhitespaceMaskPrev;
    }

    public function effectiveWhitespaceAfter(): int
    {
        if ($this->PinToCode && ($next = $this->next())->IsCode && !$next->PinToCode) {
            return ($this->_effectiveWhitespaceAfter() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceAfter();
    }

    private function _effectiveWhitespaceAfter(): int
    {
        return ($this->WhitespaceAfter | $this->next()->WhitespaceBefore)
            & $this->next()->WhitespaceMaskPrev & $this->WhitespaceMaskNext;
    }

    public function hasNewlineBefore(): bool
    {
        return (bool) ($this->effectiveWhitespaceBefore()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool) ($this->effectiveWhitespaceAfter()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasBlankLineBefore(): bool
    {
        return (bool) ($this->effectiveWhitespaceBefore()
            & WhitespaceType::BLANK);
    }

    public function hasBlankLineAfter(): bool
    {
        return (bool) ($this->effectiveWhitespaceAfter()
            & WhitespaceType::BLANK);
    }

    public function hasWhitespaceBefore(): bool
    {
        return (bool) $this->effectiveWhitespaceBefore();
    }

    public function hasWhitespaceAfter(): bool
    {
        return (bool) $this->effectiveWhitespaceAfter();
    }

    public function hasNewline(): bool
    {
        return strpos($this->text, "\n") !== false;
    }

    /**
     * There's a newline between this token and the next code token
     *
     */
    public function hasNewlineAfterCode(): bool
    {
        return $this->hasNewlineAfter() ||
            (!$this->next()->IsCode &&
                $this->next()
                     ->collect($this->nextCode())
                     ->find(fn(Token $t) =>
                         $t->hasNewlineBefore()));
    }

    public function prevStatementStart(): Token
    {
        $prev = $this->startOfStatement()->prevSibling();
        while ($prev->is(T[';'])) {
            $prev = $prev->prevSibling();
        }

        return $prev->startOfStatement();
    }

    final public function isStatementTerminator(): bool
    {
        return $this->is(T[';']) ||
            $this->IsCloseTagStatementTerminator ||
            ($this->is(T['}']) && $this->isStructuralBrace()) ||
            ($this->OpenedBy && $this->OpenedBy->is(T_ATTRIBUTE));
    }

    public function isStatementPrecursor(): bool
    {
        return $this->_nextCode && $this->_nextCode->Statement === $this->_nextCode;
    }

    /**
     * Token is a T_CLOSE_TAG that may also be a statement terminator
     */
    public function isCloseTagStatementTerminator(): bool
    {
        return $this->IsCloseTagStatementTerminator;
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
    public function inSwitchCase(): bool
    {
        return $this->is([T_CASE, T_DEFAULT]) ||
            ($this->parent()->prevSibling(2)->is(T_SWITCH) &&
                $this->prevSiblingOf(T[':'], T[';'], T_CLOSE_TAG, T_CASE, T_DEFAULT)
                     ->is([T_CASE, T_DEFAULT]));
    }

    /**
     * True if the token is part of a label
     *
     * The token may be the label itself (`T_STRING`) or its terminator (`:`).
     *
     */
    public function inLabel(): bool
    {
        if ($this->is(T[':'])) {
            return $this->prevCode()->is(T_STRING) &&
                (($prev = $this->prevCode(2))->is([T[';'], T_CLOSE_TAG]) ||
                    $prev->isStructuralBrace() ||
                    $prev->startsAlternativeSyntax() ||
                    ($prev->is(T[':']) && ($prev->inSwitchCase() || $prev->inLabel())));
        }

        return $this->_nextCode &&
            $this->_nextCode->is(T[':']) &&
            $this->_nextCode->inLabel();
    }

    public function isArrayOpenBracket(): bool
    {
        return $this->is(T['[']) ||
            ($this->is(T['(']) && $this->prevCode()->is(T_ARRAY));
    }

    public function isBrace(): bool
    {
        return $this->is(T['{']) || ($this->is(T['}']) && $this->OpenedBy->is(T['{']));
    }

    public function isStructuralBrace(): bool
    {
        if (!$this->isBrace()) {
            return false;
        }
        $_this     = $this->OpenedBy ?: $this;
        $lastInner = $_this->ClosedBy->prevCode();
        $parent    = $_this->parent();

        return ($lastInner === $_this ||                                         // `{}`
                $lastInner->is([T[':'], T[';']]) ||                              // `{ statement; }`
                $lastInner->IsCloseTagStatementTerminator ||                     /* `{ statement ?>...<?php }` */
                ($lastInner->is(T['}']) && $lastInner->isStructuralBrace())) &&  // `{ { statement; } }`
            !(($parent->isNull() ||
                    $parent->prevSiblingsWhile(...TokenType::DECLARATION_PART)->hasOneOf(T_NAMESPACE)) &&
                $parent->prevSiblingsWhile(...TokenType::DECLARATION_PART)->hasOneOf(T_USE));
    }

    public function isOpenBracket(): bool
    {
        return $this->is([T['('], T['['], T['{'], T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]);
    }

    public function isCloseBracket(): bool
    {
        return $this->is([T[')'], T[']'], T['}']]);
    }

    public function startsAlternativeSyntax(): bool
    {
        return $this->is(T[':']) && ($this->ClosedBy ||
            (($this->_prevCode->is(T[')']) &&
                    $this->_prevCode->_prevSibling->is([
                        ...TokenType::CAN_START_ALTERNATIVE_SYNTAX,
                        ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITH_EXPRESSION
                    ])) ||
                $this->_prevCode->is(
                    TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITHOUT_EXPRESSION
                )));
    }

    public function endsAlternativeSyntax(): bool
    {
        return $this->is(T_END_ALT_SYNTAX);
    }

    public function isOneLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->is(TokenType::COMMENT) && !$this->hasNewline()
            : $this->is(T_COMMENT) && preg_match('@^(//|#)@', $this->text);
    }

    public function isMultiLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->is(TokenType::COMMENT) && $this->hasNewline()
            : ($this->is(T_DOC_COMMENT) ||
                ($this->is(T_COMMENT) && preg_match('@^/\*@', $this->text)));
    }

    public function isOperator(): bool
    {
        return $this->is(TokenType::ALL_OPERATOR);
    }

    public function isTernaryOperator(): bool
    {
        return $this->IsTernaryOperator;
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isUnaryOperator(): bool
    {
        return $this->is([
            T['~'],
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
        $prev = $this->prevCode();

        return $prev->is([
            T['('],
            T[','],
            T[';'],
            T['['],
            T['{'],
            T['}'],
            ...TokenType::OPERATOR_ARITHMETIC,
            ...TokenType::OPERATOR_ASSIGNMENT,
            ...TokenType::OPERATOR_BITWISE,
            ...TokenType::OPERATOR_COMPARISON,
            ...TokenType::OPERATOR_LOGICAL,
            ...TokenType::OPERATOR_STRING,
            ...TokenType::OPERATOR_DOUBLE_ARROW,
            ...TokenType::CAST,
            ...TokenType::KEYWORD,
        ]) ||
            $prev->IsCloseTagStatementTerminator ||
            $prev->isTernaryOperator();
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
        $parent = $this->parent();

        return $parent->is(T['(']) &&
            ($parent->isDeclaration(T_FUNCTION) || $parent->prevCode()->is(T_FN));
    }

    public function indent(): int
    {
        return $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($indent = $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
            : '';
    }

    public function renderWhitespaceBefore(bool $softTabs = false): string
    {
        $whitespaceBefore = $this->effectiveWhitespaceBefore();

        return WhitespaceType::toWhitespace($whitespaceBefore)
            . ($whitespaceBefore & (WhitespaceType::LINE | WhitespaceType::BLANK)
                ? (($indent = $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
                        ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
                        : '')
                    . (($padding = $this->LinePadding - $this->LineUnpadding + $this->Padding)
                        ? str_repeat(' ', $padding)
                        : '')
                : ($this->Padding ? str_repeat(' ', $this->Padding) : ''));
    }

    public function render(bool $softTabs = false): string
    {
        if ($this->HeredocOpenedBy) {
            // Render heredocs in one go so we can safely trim empty lines
            if ($this->HeredocOpenedBy !== $this) {
                return '';
            }
            $heredoc = '';
            $current = $this;
            do {
                $heredoc .= $current->text;
                $current  = $current->next();
            } while ($current->HeredocOpenedBy === $this);
            if ($this->HeredocIndent) {
                $regex   = preg_quote($this->HeredocIndent, '/');
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
                if ($this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent) {
                    $code .= $this->renderIndent($softTabs);
                }
                if ($this->LinePadding - $this->LineUnpadding) {
                    $code .= str_repeat(' ', $this->LinePadding - $this->LineUnpadding);
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
        // Remove trailing whitespace from each line
        $code = preg_replace('/\h+$/m', '', $this->text);
        switch ($this->id) {
            case T_COMMENT:
                if (!$this->isMultiLineComment() ||
                        preg_match('/\n\h*(?!\*)(\S|$)/', $code)) {
                    return $code;
                }
            case T_DOC_COMMENT:
                $start = $this->startOfLine();
                $indent =
                    "\n" . ($start === $this
                        ? $this->renderIndent($softTabs)
                            . str_repeat(' ', $this->LinePadding - $this->LineUnpadding + $this->Padding)
                        : ltrim($start->renderWhitespaceBefore(), "\n")
                            . str_repeat(' ', mb_strlen($start->collect($this->prev())->render($softTabs))
                                + strlen(WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore()))
                                + $this->Padding));

                return preg_replace([
                    '/\n\h*(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
                    '/\n\h*\*?$/m',
                    '/\n\h*\*\//',
                ], [
                    $indent . ' * ',
                    $indent . ' *',
                    $indent . ' */',
                ], $code);
        }

        throw new RuntimeException('Not a T_COMMENT or T_DOC_COMMENT');
    }

    public function collect(Token $to): TokenCollection
    {
        return TokenCollection::collect($this, $to);
    }

    public function collectSiblings(Token $to = null): TokenCollection
    {
        $tokens  = new TokenCollection();
        $current = $this->OpenedBy ?: $this;
        if ($to) {
            if ($this->Index > $to->Index || $to->IsNull) {
                return $tokens;
            }
            $to = $to->OpenedBy ?: $to;
            if ($current->BracketStack !== $to->BracketStack) {
                throw new RuntimeException('Argument #1 ($to) is not a sibling');
            }
        }

        do {
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        } while ($current = $current->_nextSibling);

        return $tokens;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if (!in_array($name, [...static::ALLOW_READ, ...static::ALLOW_WRITE])) {
            throw new RuntimeException('Cannot access property ' . static::class . '::$' . $name);
        }

        return $this->$name;
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if (!in_array($name, static::ALLOW_WRITE)) {
            throw new RuntimeException('Cannot access property ' . static::class . '::$' . $name);
        }
        if ($this->$name === $value) {
            return;
        }
        if ($this->Formatter->Debug && ($service = $this->Formatter->RunningService)) {
            $this->Log[] = [
                'service' => $service,
                'value'   => $name,
                'from'    => $this->$name,
                'to'      => $value,
            ];
        }
        $this->$name = $value;
    }

    public function __toString(): string
    {
        return sprintf('T%d:L%d:%s',
                       $this->Index,
                       $this->line,
                       Convert::ellipsize(var_export($this->text, true), 20));
    }

    private function isSameTypeAs(Token $token): bool
    {
        return $this->id === $token->id &&
            (!$this->is(TokenType::COMMENT) ||
                $this->isMultiLineComment() === $token->isMultiLineComment());
    }
}
