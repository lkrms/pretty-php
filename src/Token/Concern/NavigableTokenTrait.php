<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token\Concern;

use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

trait NavigableTokenTrait
{
    /**
     * The token's position (0-based) in an array of token objects
     */
    public ?int $Index = null;

    /**
     * The starting column (1-based) of the token
     */
    public int $column = -1;

    public ?Token $_prev = null;

    public ?Token $_next = null;

    public ?Token $_prevCode = null;

    public ?Token $_nextCode = null;

    public ?Token $_prevSibling = null;

    public ?Token $_nextSibling = null;

    public ?Token $OpenedBy = null;

    public ?Token $ClosedBy = null;

    public ?Token $Parent = null;

    /**
     * @var Token[]
     */
    public array $BracketStack = [];

    public ?Token $OpenTag = null;

    public ?Token $CloseTag = null;

    public ?Token $String = null;

    public ?Token $StringClosedBy = null;

    public ?Token $Heredoc = null;

    /**
     * True unless the token is a tag, comment, whitespace or inline markup
     *
     * Also `true` if the token is a `T_CLOSE_TAG` that terminates a statement.
     */
    public bool $IsCode = true;

    /**
     * True if the token is a T_NULL
     */
    public bool $IsNull = false;

    /**
     * True if the token is a T_NULL, T_END_ALT_SYNTAX or some other zero-width
     * impostor
     */
    public bool $IsVirtual = false;

    /**
     * True if the token is a T_CLOSE_BRACE or T_CLOSE_TAG that terminates a
     * statement
     */
    public bool $IsStatementTerminator = false;

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
     * Indexed token types
     *
     * @readonly
     */
    public TokenTypeIndex $TokenTypeIndex;

    /**
     * @return Token[]
     */
    public static function onlyTokenize(string $code, int $flags = 0, Filter ...$filters): array
    {
        /** @var Token[] */
        $tokens = parent::tokenize($code, $flags);

        if (!$tokens) {
            return $tokens;
        }

        foreach ($filters as $filter) {
            $tokens = $filter->filterTokens($tokens);
        }

        return $tokens;
    }

    /**
     * @return Token[]
     */
    public static function tokenize(string $code, int $flags = 0, ?TokenTypeIndex $tokenTypeIndex = null, Filter ...$filters): array
    {
        $tokens = static::onlyTokenize($code, $flags, ...$filters);

        if (!$tokens) {
            return $tokens;
        }

        $idx = $tokenTypeIndex === null
            ? new TokenTypeIndex()
            : $tokenTypeIndex;

        // Pass 1:
        // - link adjacent tokens (set `_prev` and `_next`)
        // - assign token type index
        // - set `OpenTag`, `CloseTag`

        /** @var Token|null */
        $prev = null;
        foreach ($tokens as $token) {
            if ($prev) {
                $token->_prev = $prev;
                $prev->_next = $token;
            }

            $token->TokenTypeIndex = $idx;

            /**
             * ```php
             * <!-- markup -->  // OpenTag = null,   CloseTag = null
             * <?php            // OpenTag = itself, CloseTag = Token
             * $foo = 'bar';    // OpenTag = Token,  CloseTag = Token
             * ?>               // OpenTag = Token,  CloseTag = itself
             * <!-- markup -->  // OpenTag = null,   CloseTag = null
             * <?php            // OpenTag = itself, CloseTag = null
             * $foo = 'bar';    // OpenTag = Token,  CloseTag = null
             * ```
             */
            if (
                $token->id === \T_OPEN_TAG ||
                $token->id === \T_OPEN_TAG_WITH_ECHO
            ) {
                $token->OpenTag = $token;
                $prev = $token;
                continue;
            }

            if (!$prev || !$prev->OpenTag || $prev->CloseTag) {
                $prev = $token;
                continue;
            }

            $token->OpenTag = $prev->OpenTag;
            $token->CloseTag = &$token->OpenTag->CloseTag;

            if ($token->id === \T_CLOSE_TAG) {
                $token->OpenTag->CloseTag = $token;
            }

            $prev = $token;
        }

        // Pass 2:
        // - on PHP < 8.0, convert comments that appear to be PHP >= 8.0
        //   attributes to `T_ATTRIBUTE_COMMENT`
        // - add virtual close brackets after alternative syntax bodies
        // - pair open brackets and tags with their counterparts
        // - link siblings, parents and children (set `BracketStack`, `Parent`,
        //   `_prevCode`, `_nextCode`, `_prevSibling`, `_nextSibling`)
        // - set `Index`, `IsCode`, `IsStatementTerminator`, `OpenedBy`,
        //   `ClosedBy`, `String`, `Heredoc`, `StringClosedBy`

        /** @var Token[] */
        $linked = [];
        /** @var Token|null */
        $prev = null;
        $index = 0;

        $keys = array_keys($tokens);
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$keys[$i]];

            if (
                \PHP_VERSION_ID < 80000 &&
                $token->id === \T_COMMENT &&
                substr($token->text, 0, 2) === '#['
            ) {
                $token->id = \T_ATTRIBUTE_COMMENT;
            }

            if ($idx->NotCode[$token->id]) {
                $token->IsCode = false;
            }

            if (
                ($idx->AltSyntaxContinue[$token->id] ||
                    $idx->AltSyntaxEnd[$token->id]) &&
                $prev->id !== \T_END_ALT_SYNTAX
            ) {
                $stack = $prev->BracketStack;
                // If the previous token is a close bracket, remove its opener
                // from the top of the stack
                if ($idx->CloseBracket[$prev->id]) {
                    array_pop($stack);
                }
                $opener = array_pop($stack);
                if (($opener &&
                    $opener->id === \T_COLON &&
                    ($idx->AltSyntaxEnd[$token->id] ||
                        ($idx->AltSyntaxContinueWithExpression[$token->id] &&
                            $token->nextSimpleSibling(2)->id === \T_COLON) ||
                        ($idx->AltSyntaxContinueWithoutExpression[$token->id] &&
                            $token->nextSimpleSibling()->id === \T_COLON))) ||
                        $prev->startsAlternativeSyntax()) {
                    $i--;
                    $virtual = new static(\T_END_ALT_SYNTAX, '');
                    $virtual->IsVirtual = true;
                    $virtual->_prev = $prev;
                    $virtual->_next = $token;
                    $virtual->TokenTypeIndex = $idx;
                    $virtual->OpenTag = $token->OpenTag;
                    $virtual->CloseTag = &$virtual->OpenTag->CloseTag;
                    $prev->_next = $virtual;
                    $token->_prev = $virtual;
                    $token = $virtual;
                }
            }
            $linked[$index] = $token;
            $token->Index = $index++;

            if (!$prev) {
                $prev = $token;
                continue;
            }

            // Determine whether or not a close tag is also a statement
            // terminator and should therefore be regarded as a code token
            if ($token->id === \T_CLOSE_TAG) {
                $t = $prev;
                while (
                    $t->id === \T_COMMENT ||
                    $t->id === \T_DOC_COMMENT ||
                    $t->id === \T_ATTRIBUTE_COMMENT
                ) {
                    $t = $t->_prev;
                }

                if (
                    $t !== $token->OpenTag &&
                    $t->id !== \T_COLON &&
                    $t->id !== \T_SEMICOLON &&
                    $t->id !== \T_OPEN_BRACE &&
                    ($t->id !== \T_CLOSE_BRACE || !$t->IsStatementTerminator)
                ) {
                    $token->IsStatementTerminator = true;
                    $token->IsCode = true;
                }
            }

            $token->_prevCode = $prev->IsCode ? $prev : $prev->_prevCode;
            if ($token->IsCode) {
                $prev->_nextCode = $token;
            } else {
                $token->_nextCode = &$prev->_nextCode;
            }

            $token->BracketStack = $prev->BracketStack;
            $stackDelta = 0;
            if (
                $idx->OpenBracket[$prev->id] ||
                ($prev->id === \T_COLON && $prev->startsAlternativeSyntax())
            ) {
                $token->BracketStack[] = $prev;
                $token->Parent = $prev;
                $stackDelta++;
            } elseif ($idx->CloseBracketOrEndAltSyntax[$prev->id]) {
                array_pop($token->BracketStack);
                $token->Parent = $prev->Parent;
                $stackDelta--;
            } else {
                $token->Parent = $prev->Parent;
            }

            $token->String = $prev->String;
            $token->Heredoc = $prev->Heredoc;
            if ($idx->StringDelimiter[$prev->id]) {
                if ($prev->String && $prev->String->StringClosedBy === $prev) {
                    $token->String = $prev->String->String;
                    if ($prev->id === \T_END_HEREDOC) {
                        $token->Heredoc = $prev->Heredoc->Heredoc;
                    }
                } else {
                    $token->String = $prev;
                    if ($prev->id === \T_START_HEREDOC) {
                        $token->Heredoc = $prev;
                    }
                }
            }

            if (
                $idx->StringDelimiter[$token->id] &&
                $token->String &&
                $token->BracketStack === $token->String->BracketStack && (
                    ($token->String->id === \T_START_HEREDOC && $token->id === \T_END_HEREDOC) ||
                    ($token->String->id !== \T_START_HEREDOC && $token->String->id === $token->id)
                )
            ) {
                $token->String->StringClosedBy = $token;
            }

            if ($idx->CloseBracketOrEndAltSyntax[$token->id]) {
                $opener = end($token->BracketStack);
                $opener->ClosedBy = $token;
                $token->OpenedBy = $opener;
                $token->_prevSibling = &$opener->_prevSibling;
                $token->_nextSibling = &$opener->_nextSibling;
                $token->Parent = &$opener->Parent;

                // Treat `$token` as a statement terminator if it's a structural
                // `T_CLOSE_BRACE` that doesn't enclose an anonymous function or
                // class
                if (
                    $token->id !== \T_CLOSE_BRACE ||
                    !$token->isStructuralBrace(false)
                ) {
                    $prev = $token;
                    continue;
                }

                $_prev = $token->prevSiblingOf(\T_FUNCTION, \T_CLASS);
                if (
                    !$_prev->IsNull &&
                    $_prev->nextSiblingOf(\T_OPEN_BRACE)->ClosedBy === $token
                ) {
                    $_next = $_prev->_nextSibling;
                    if (
                        $_next->id === \T_OPEN_PARENTHESIS ||
                        $_next->id === \T_OPEN_BRACE ||
                        $_next->id === \T_EXTENDS ||
                        $_next->id === \T_IMPLEMENTS
                    ) {
                        $prev = $token;
                        continue;
                    }
                }

                $token->IsStatementTerminator = true;

                $prev = $token;
                continue;
            }

            // If $token continues the previous context ($stackDelta == 0) or is
            // the first token after a close bracket ($stackDelta < 0), set
            // $token->_prevSibling
            if ($stackDelta <= 0 && $token->_prevCode) {
                $prevCode = $token->_prevCode->OpenedBy ?: $token->_prevCode;
                if ($prevCode->BracketStack === $token->BracketStack) {
                    $token->_prevSibling = $prevCode;
                }
            }

            // Then, if there are gaps between siblings, fill them in
            if ($token->IsCode) {
                if (
                    $token->_prevSibling &&
                    !$token->_prevSibling->_nextSibling
                ) {
                    $t = $token;
                    do {
                        $t = $t->_prev->OpenedBy ?: $t->_prev;
                        $t->_nextSibling = $token;
                    } while ($t !== $token->_prevSibling && $t->_prev);
                } elseif (!$token->_prevSibling) {
                    $t = $token->_prev;
                    while ($t && $t->BracketStack === $token->BracketStack) {
                        $t->_nextSibling = $token;
                        $t = $t->_prev;
                    }
                }
            }

            $prev = $token;
        }

        return $linked;
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
     * expression braces if `$orMatch` is `true`.
     */
    final public function isStructuralBrace(bool $orMatch = true): bool
    {
        /** @var Token */
        $current = $this->OpenedBy ?: $this;

        // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
        if ($current->id !== \T_OPEN_BRACE) {
            return false;
        }

        /** @var Token|null */
        $prev = $current->_prevSibling->_prevSibling ?? null;
        if ($prev && $prev->id === \T_MATCH) {
            return $orMatch;
        }

        $lastInner = $current->ClosedBy->_prevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current ||                                            // `{}`
            $lastInner->is([\T_COLON, \T_SEMICOLON]) ||                              // `{ statement; }`
            $lastInner->IsStatementTerminator ||                                     /* `{ statement ?>...<?php }` */
            ($lastInner->id === \T_CLOSE_BRACE && $lastInner->isStructuralBrace());  // `{ { statement; } }`
    }

    /**
     * Get a new T_NULL token
     *
     * @return Token
     */
    public function null()
    {
        $token = new static(\T_NULL, '');
        $token->IsCode = false;
        $token->IsNull = true;
        $token->IsVirtual = true;
        if (isset($this->TokenTypeIndex)) {
            $token->TokenTypeIndex = $this->TokenTypeIndex;
        }
        return $token;
    }

    /**
     * Get a fallback token if the token is null or a callback succeeds,
     * otherwise get the token
     *
     * The token is returned if it is not null and either:
     *
     * - no `$condition` is given, or
     * - `$condition` returns `false` when it receives the token
     *
     * Otherwise, `$token` is resolved and returned.
     *
     * @param Token|(callable(): Token) $token
     * @param (callable(Token): bool)|null $condition
     * @return Token
     */
    public function or($token, ?callable $condition = null)
    {
        if (!$this->IsNull && (!$condition || !$condition($this))) {
            return $this;
        }
        if ($token instanceof static) {
            return $token;
        }
        return $token();
    }

    public function getTokenName(): ?string
    {
        return parent::getTokenName() ?: CustomToken::toName($this->id);
    }

    /**
     * True if the token is '(', ')', '[', ']', '{', '}', T_ATTRIBUTE,
     * T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES
     */
    final public function isBracket(): bool
    {
        return $this->TokenTypeIndex->Bracket[$this->id];
    }

    /**
     * True if the token is '(', ')', '[', ']', '{' or '}'
     */
    final public function isStandardBracket(): bool
    {
        return $this->TokenTypeIndex->StandardBracket[$this->id];
    }

    /**
     * True if the token is '(', '[', '{', T_ATTRIBUTE, T_CURLY_OPEN or
     * T_DOLLAR_OPEN_CURLY_BRACES
     */
    final public function isOpenBracket(): bool
    {
        return $this->TokenTypeIndex->OpenBracket[$this->id];
    }

    /**
     * True if the token is ')', ']' or '}'
     */
    final public function isCloseBracket(): bool
    {
        return $this->TokenTypeIndex->CloseBracket[$this->id];
    }

    /**
     * True if the token is '(', '[' or '{'
     */
    final public function isStandardOpenBracket(): bool
    {
        return $this->TokenTypeIndex->StandardOpenBracket[$this->id];
    }

    final public function startsAlternativeSyntax(): bool
    {
        if ($this->id !== \T_COLON) {
            return false;
        }
        if ($this->ClosedBy) {
            return true;
        }
        if ($this->TokenTypeIndex->AltSyntaxContinueWithoutExpression[$this->_prevCode->id]) {
            return true;
        }

        if ($this->_prevCode->id !== \T_CLOSE_PARENTHESIS) {
            return false;
        }

        $prev = $this->_prevCode->_prevSibling;
        if (
            $this->TokenTypeIndex->AltSyntaxStart[$prev->id] ||
            $this->TokenTypeIndex->AltSyntaxContinueWithExpression[$prev->id]
        ) {
            return true;
        }

        return false;
    }

    /**
     * Update the content of the token, setting OriginalText if needed
     *
     * @return $this
     */
    final public function setText(string $text)
    {
        if ($this->text !== $text) {
            if ($this->OriginalText === null) {
                $this->OriginalText = $this->text;
            }
            $this->text = $text;
        }
        return $this;
    }

    /**
     * Get the previous token that is one of the listed types
     *
     * @return Token
     */
    final public function prevOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->_prev) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next token that is one of the listed types
     *
     * @return Token
     */
    final public function nextOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->_next) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is one of the listed types
     *
     * @return Token
     */
    final public function prevSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->_prevSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is one of the listed types
     *
     * @return Token
     */
    final public function nextSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->_nextSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Skip to the next sibling that is not one of the listed types
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @return Token
     */
    final public function skipSiblingsOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this->IsCode ? $this : $this->_nextCode;
        while ($t && $t->is($types)) {
            $t = $t->_nextSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Skip to the previous sibling that is not one of the listed types
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @return Token
     */
    final public function skipPrevSiblingsOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this->IsCode ? $this : $this->_prevCode;
        while ($t && $t->is($types)) {
            $t = $t->_prevSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Get the first reachable token
     *
     * @return Token
     */
    final public function first()
    {
        $current = reset($this->BracketStack) ?: $this;
        while ($current->_prev) {
            $current = $current->_prevSibling ?: $current->_prev;
        }
        return $current;
    }

    /**
     * Get the last reachable token
     *
     * @return Token
     */
    final public function last()
    {
        $current = reset($this->BracketStack) ?: $this;
        while ($current->_next) {
            $current = $current->_nextSibling ?: $current->_next;
        }
        return $current;
    }

    /**
     * Get the token at the beginning of the token's original line
     *
     * @return Token
     */
    final public function startOfOriginalLine()
    {
        $current = $this;
        while (($current->_prev->line ?? null) === $this->line) {
            $current = $current->_prev;
        }
        return $current;
    }

    /**
     * Get the token at the end of the token's original line
     *
     * @return Token
     */
    final public function endOfOriginalLine()
    {
        $current = $this;
        while (($current->_next->line ?? null) === $this->line) {
            $current = $current->_next;
        }
        return $current;
    }

    /**
     * Get the next sibling via token traversal, without accounting for PHP's
     * alternative syntax
     *
     * @return Token
     */
    final public function nextSimpleSibling(int $offset = 1)
    {
        $depth = 0;
        $t = $this;
        while ($t->_next) {
            if ($t->isOpenBracket()) {
                $depth++;
            } elseif ($t->isCloseBracket()) {
                $depth--;
            }
            $t = $t->_next;
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    return $t;
                }
            }
        }
        return $this->null();
    }
}
