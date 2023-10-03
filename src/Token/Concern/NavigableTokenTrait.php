<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token\Concern;

use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

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

    /**
     * @var static|null
     */
    public $_prev;

    /**
     * @var static|null
     */
    public $_next;

    /**
     * @var static|null
     */
    public $_prevCode;

    /**
     * @var static|null
     */
    public $_nextCode;

    /**
     * @var static|null
     */
    public $_prevSibling;

    /**
     * @var static|null
     */
    public $_nextSibling;

    /**
     * @var static|null
     */
    public $OpenedBy;

    /**
     * @var static|null
     */
    public $ClosedBy;

    /**
     * @var static|null
     */
    public $Parent;

    /**
     * @var static[]
     */
    public $BracketStack = [];

    /**
     * @var static|null
     */
    public $OpenTag;

    /**
     * @var static|null
     */
    public $CloseTag;

    /**
     * @var static|null
     */
    public $String;

    /**
     * @var static|null
     */
    public $StringClosedBy;

    /**
     * @var static|null
     */
    public $Heredoc;

    /**
     * True unless the token is a tag, comment, whitespace or inline markup
     */
    public bool $IsCode = true;

    /**
     * True if the token is T_NULL
     */
    public bool $IsNull = false;

    /**
     * True if the token is T_NULL, T_END_ALT_SYNTAX or some other impostor
     */
    public bool $IsVirtual = false;

    /**
     * True if the token is a T_CLOSE_BRACE or T_CLOSE_TAG that coincides with
     * the end of a statement
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
     * @return static[]
     */
    public static function onlyTokenize(string $code, int $flags = 0, Filter ...$filters): array
    {
        $tokens = parent::tokenize($code, $flags);

        if (!$tokens) {
            return $tokens;
        }

        foreach ($filters as $filter) {
            /** @var static[] */
            $tokens = $filter->filterTokens($tokens);
        }

        return $tokens;
    }

    /**
     * @return static[]
     */
    public static function tokenize(string $code, int $flags = 0, ?TokenTypeIndex $tokenTypeIndex = null, Filter ...$filters): array
    {
        $tokens = static::onlyTokenize($code, $flags, ...$filters);

        if (!$tokens) {
            return $tokens;
        }

        if (!$tokenTypeIndex) {
            $tokenTypeIndex = new TokenTypeIndex();
        }

        // Pass 1:
        // - link adjacent tokens
        // - assign token type index
        // - set `OpenTag`, `CloseTag`

        /** @var static|null */
        $prev = null;
        foreach ($tokens as $token) {
            if ($prev) {
                $token->_prev = $prev;
                $prev->_next = $token;
            }

            $token->TokenTypeIndex = $tokenTypeIndex;

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
            if ($token->id === T_OPEN_TAG ||
                    $token->id === T_OPEN_TAG_WITH_ECHO) {
                $token->OpenTag = $token;
                $prev = $token;
                continue;
            }

            if (!$prev || !$prev->OpenTag || $prev->CloseTag) {
                $prev = $token;
                continue;
            }

            $token->OpenTag = $prev->OpenTag;

            if ($token->id !== T_CLOSE_TAG) {
                $prev = $token;
                continue;
            }

            $t = $token;
            do {
                $t->CloseTag = $token;
                $t = $t->_prev;
            } while ($t && $t->OpenTag === $token->OpenTag);

            $prev = $token;
        }

        // Pass 2:
        // - add virtual close brackets after alternative syntax bodies
        // - pair open brackets and tags with their counterparts
        // - link siblings, parents and children

        /** @var static[] */
        $linked = [];
        /** @var static|null */
        $prev = null;
        $index = 0;

        $keys = array_keys($tokens);
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$keys[$i]];

            if (PHP_VERSION_ID < 80000 &&
                    $token->id === T_COMMENT &&
                    substr($token->text, 0, 2) === '#[') {
                $token->id = T_ATTRIBUTE_COMMENT;
            }

            if ($tokenTypeIndex->NotCode[$token->id]) {
                $token->IsCode = false;
            }

            if (($tokenTypeIndex->AltSyntaxContinue[$token->id] ||
                        $tokenTypeIndex->AltSyntaxEnd[$token->id]) &&
                    $prev->id !== T_END_ALT_SYNTAX) {
                $stack = $prev->BracketStack;
                // If the previous token is a close bracket, remove its opener
                // from the top of the stack
                if ($tokenTypeIndex->CloseBracket[$prev->id]) {
                    array_pop($stack);
                }
                $opener = array_pop($stack);
                if (($opener &&
                    $opener->id === T_COLON &&
                    // $opener->BracketStack === $stack &&
                    ($token->is(TokenType::ALT_SYNTAX_END) ||
                        ($token->is(TokenType::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION) &&
                            $token->nextSimpleSibling(2)->id === T_COLON) ||
                        ($token->is(TokenType::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION) &&
                            $token->nextSimpleSibling()->id === T_COLON))) ||
                        $prev->startsAlternativeSyntax()) {
                    $i--;
                    $virtual = new static(T_END_ALT_SYNTAX, '');
                    $virtual->IsVirtual = true;
                    $virtual->_prev = $prev;
                    $virtual->_next = $token;
                    $virtual->TokenTypeIndex = $tokenTypeIndex;
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
            if ($token->id === T_CLOSE_TAG) {
                $t = $prev;
                while ($t->id === T_COMMENT ||
                        $t->id === T_DOC_COMMENT) {
                    $t = $t->_prev;
                }

                if ($t !== $token->OpenTag &&
                        !$t->is([T_COLON, T_SEMICOLON, T_OPEN_BRACE]) &&
                        ($t->id !== T_CLOSE_BRACE || !$t->IsStatementTerminator)) {
                    $token->IsStatementTerminator = true;
                    $token->IsCode = true;
                }
            }

            $token->_prevCode = $prev->IsCode ? $prev : $prev->_prevCode;
            if ($token->IsCode) {
                $t = $prev;
                do {
                    $t->_nextCode = $token;
                    $t = $t->_prev;
                } while ($t && !$t->_nextCode);
            }

            $token->BracketStack = $prev->BracketStack;
            $stackDelta = 0;
            if ($tokenTypeIndex->OpenBracket[$prev->id] ||
                    ($prev->id === T_COLON && $prev->startsAlternativeSyntax())) {
                $token->BracketStack[] = $prev;
                $token->Parent = $prev;
                $stackDelta++;
            } elseif ($tokenTypeIndex->CloseBracketOrEndAltSyntax[$prev->id]) {
                array_pop($token->BracketStack);
                $token->Parent = $prev->Parent;
                $stackDelta--;
            } else {
                $token->Parent = $prev->Parent;
            }

            $token->String = $prev->String;
            $token->Heredoc = $prev->Heredoc;
            if ($tokenTypeIndex->StringDelimiter[$prev->id]) {
                if ($prev->String && $prev->String->StringClosedBy === $prev) {
                    $token->String = $prev->String->String;
                    if ($prev->id === T_END_HEREDOC) {
                        $token->Heredoc = $prev->Heredoc->Heredoc;
                    }
                } else {
                    $token->String = $prev;
                    if ($prev->id === T_START_HEREDOC) {
                        $token->Heredoc = $prev;
                    }
                }
            }

            if ($tokenTypeIndex->StringDelimiter[$token->id] &&
                $token->String &&
                $token->BracketStack === $token->String->BracketStack &&
                (($token->String->id === T_START_HEREDOC && $token->id === T_END_HEREDOC) ||
                    ($token->String->id !== T_START_HEREDOC && $token->String->id === $token->id))) {
                $token->String->StringClosedBy = $token;
            }

            if ($tokenTypeIndex->CloseBracketOrEndAltSyntax[$token->id]) {
                $opener = end($token->BracketStack);
                $opener->ClosedBy = $token;
                $token->OpenedBy = $opener;
                $token->_prevSibling = &$opener->_prevSibling;
                $token->_nextSibling = &$opener->_nextSibling;
                $token->Parent = &$opener->Parent;

                // Treat `$token` as a statement terminator if it's a structural
                // `T_CLOSE_BRACE` that doesn't enclose an anonymous function or
                // class
                if ($token->id !== T_CLOSE_BRACE ||
                        !$token->isStructuralBrace(false)) {
                    $prev = $token;
                    continue;
                }

                /** @var static */
                $_prev = $token->prevSiblingOf(T_FUNCTION, T_CLASS);
                if (!$_prev->IsNull &&
                        $_prev->nextSiblingOf(T_OPEN_BRACE)->ClosedBy === $token) {
                    $_next = $_prev->_nextSibling;
                    if ($_next->id === T_OPEN_PARENTHESIS ||
                            $_next->id === T_OPEN_BRACE ||
                            $_next->id === T_EXTENDS ||
                            $_next->id === T_IMPLEMENTS) {
                        $prev = $token;
                        continue;
                    }
                }

                $token->IsStatementTerminator = true;
            } else {
                // If $token continues the previous context ($stackDelta == 0)
                // or is the first token after a close bracket ($stackDelta <
                // 0), set $token->_prevSibling
                if ($stackDelta <= 0 &&
                        ($prevCode = ($token->_prevCode->OpenedBy ?? null) ?: $token->_prevCode) &&
                        $prevCode->BracketStack === $token->BracketStack) {
                    $token->_prevSibling = $prevCode;
                }

                // Then, if there are gaps between siblings, fill them in
                if ($token->IsCode) {
                    if ($token->_prevSibling &&
                            !$token->_prevSibling->_nextSibling) {
                        $t = $token;
                        do {
                            $t = $t->_prev->OpenedBy ?: $t->_prev;
                            $t->_nextSibling = $token;
                        } while ($t->_prev && $t !== $token->_prevSibling);
                    } elseif (!$token->_prevSibling) {
                        $t = $token->_prev;
                        while ($t && $t->BracketStack === $token->BracketStack) {
                            $t->_nextSibling = $token;
                            $t = $t->_prev;
                        }
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
        /** @var static */
        $current = $this->OpenedBy ?: $this;

        // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
        if ($current->id !== T_OPEN_BRACE) {
            return false;
        }

        /** @var static|null */
        $prev = $current->_prevSibling->_prevSibling ?? null;
        if ($prev && $prev->id === T_MATCH) {
            return $orMatch;
        }

        $lastInner = $current->ClosedBy->_prevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current ||                                           // `{}`
            $lastInner->is([T_COLON, T_SEMICOLON]) ||                               // `{ statement; }`
            $lastInner->IsStatementTerminator ||                                    /* `{ statement ?>...<?php }` */
            ($lastInner->id === T_CLOSE_BRACE && $lastInner->isStructuralBrace());  // `{ { statement; } }`
    }

    /**
     * Get a new T_NULL token
     *
     * @return static
     */
    public function null()
    {
        $token = new static(T_NULL, '');
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
     * @param static|(callable(): static) $token
     * @param (callable(static): bool)|null $condition
     * @return static
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
        if ($this->id !== T_COLON) {
            return false;
        }
        if ($this->ClosedBy) {
            return true;
        }

        return ($this->_prevCode->id === T_CLOSE_PARENTHESIS &&
                $this->_prevCode->_prevSibling->is([
                    ...TokenType::ALT_SYNTAX_START,
                    ...TokenType::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION,
                ])) ||
            $this->_prevCode->is(
                TokenType::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION
            );
    }

    final public function endsAlternativeSyntax(): bool
    {
        return $this->id === T_END_ALT_SYNTAX;
    }

    final public function isCloseBracketOrEndsAlternativeSyntax(): bool
    {
        return $this->id === T_END_ALT_SYNTAX ||
            $this->TokenTypeIndex->CloseBracket[$this->id];
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
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
     */
    final public function skipAnySiblingsOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this->IsCode ? $this : $this->_nextCode;
        while ($t && $t->is($types)) {
            $t = $t->_nextSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Get the first reachable token
     *
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
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
     * @return static
     */
    private function nextSimpleSibling(int $offset = 1)
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
