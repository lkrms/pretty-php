<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Pretty\Php\Contract\Filter;
use PhpToken;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * @template TToken of NavigableToken
 */
class NavigableToken extends PhpToken
{
    /**
     * The token's position in an array of token objects
     *
     * Numbering starts at 0.
     *
     */
    public ?int $Index = null;

    /**
     * @var TToken|null
     */
    public $_prev;

    /**
     * @var TToken|null
     */
    public $_next;

    /**
     * @var TToken|null
     */
    public $_prevCode;

    /**
     * @var TToken|null
     */
    public $_nextCode;

    /**
     * @var TToken|null
     */
    public $_prevSibling;

    /**
     * @var TToken|null
     */
    public $_nextSibling;

    /**
     * @var TToken|null
     */
    public $OpenedBy;

    /**
     * @var TToken|null
     */
    public $ClosedBy;

    /**
     * @var TToken[]
     */
    public $BracketStack = [];

    /**
     * True unless the token is a tag, comment, whitespace or inline markup
     *
     */
    public bool $IsCode = true;

    /**
     * True if the token is T_NULL
     *
     */
    public bool $IsNull = false;

    /**
     * True if the token is T_NULL, T_END_ALT_SYNTAX or some other impostor
     *
     */
    public bool $IsVirtual = false;

    /**
     * The original content of the token if its content was changed by setText()
     *
     */
    public ?string $OriginalText = null;

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
    public static function tokenize(string $code, int $flags = 0, Filter ...$filters): array
    {
        $tokens = parent::tokenize($code, $flags);

        if (!$tokens) {
            return $tokens;
        }

        foreach ($filters as $filter) {
            /** @var static[] */
            $tokens = $filter->filterTokens($tokens);
        }

        // Pass 1:
        // - link adjacent tokens

        /** @var static|null */
        $prev = null;
        foreach ($tokens as $token) {
            if ($prev) {
                $token->_prev = $prev;
                $prev->_next = $token;
            }

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
            if ($token->id === T_COMMENT && substr($token->text, 0, 2) === '#[') {
                $token->id = T_ATTRIBUTE_COMMENT;
            }
            if ($token->is(TokenType::NOT_CODE)) {
                $token->IsCode = false;
            }

            if ($token->is(
                [...TokenType::ALT_SYNTAX_CONTINUE, ...TokenType::ALT_SYNTAX_END]
            ) && $prev->id !== T_END_ALT_SYNTAX) {
                $stack = $prev->BracketStack;
                // If the previous token is a close bracket, remove its opener
                // from the top of the stack
                if ($prev->isCloseBracket()) {
                    array_pop($stack);
                }
                $opener = array_pop($stack);
                if (($opener &&
                    $opener->id === T[':'] &&
                    //$opener->BracketStack === $stack &&
                    ($token->is(TokenType::ALT_SYNTAX_END) ||
                        ($token->is(TokenType::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION) &&
                            $token->nextSimpleSibling(2)->id === T[':']) ||
                        ($token->is(TokenType::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION) &&
                            $token->nextSimpleSibling()->id === T[':']))) ||
                        $prev->startsAlternativeSyntax()) {
                    $i--;
                    $virtual = new static(T_END_ALT_SYNTAX, '');
                    $virtual->IsVirtual = true;
                    $virtual->_prev = $prev;
                    $virtual->_next = $token;
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
            if ($prev->isOpenBracket() || $prev->startsAlternativeSyntax()) {
                $token->BracketStack[] = $prev;
                $stackDelta++;
            } elseif ($prev->isCloseBracket() || $prev->endsAlternativeSyntax()) {
                array_pop($token->BracketStack);
                $stackDelta--;
            }

            if ($token->isCloseBracket() || $token->endsAlternativeSyntax()) {
                $opener = end($token->BracketStack);
                $opener->ClosedBy = $token;
                $token->OpenedBy = $opener;
                $token->_prevSibling = &$opener->_prevSibling;
                $token->_nextSibling = &$opener->_nextSibling;
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
     * Get a new T_NULL token
     *
     * @return static
     */
    final public static function null()
    {
        $token = new static(T_NULL, '');
        $token->IsCode = false;
        $token->IsNull = true;
        $token->IsVirtual = true;

        return $token;
    }

    /**
     * True if the token is '(', ')', '[', ']', '{', '}', T_ATTRIBUTE,
     * T_CURLY_OPEN or T_DOLLAR_OPEN_CURLY_BRACES
     *
     */
    final public function isBracket(): bool
    {
        return $this->is([
            T['('],
            T[')'],
            T['['],
            T[']'],
            T['{'],
            T['}'],
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        ]);
    }

    /**
     * True if the token is '(', ')', '[', ']', '{' or '}'
     *
     */
    final public function isStandardBracket(): bool
    {
        return $this->is([
            T['('],
            T[')'],
            T['['],
            T[']'],
            T['{'],
            T['}'],
        ]);
    }

    /**
     * True if the token is '(', '[', '{', T_ATTRIBUTE, T_CURLY_OPEN or
     * T_DOLLAR_OPEN_CURLY_BRACES
     *
     */
    final public function isOpenBracket(): bool
    {
        return $this->is([
            T['('],
            T['['],
            T['{'],
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        ]);
    }

    /**
     * True if the token is ')', ']' or '}'
     *
     */
    final public function isCloseBracket(): bool
    {
        return $this->is([
            T[')'],
            T[']'],
            T['}'],
        ]);
    }

    /**
     * True if the token is '(', '[' or '{'
     *
     */
    final public function isStandardOpenBracket(): bool
    {
        return $this->is([
            T['('],
            T['['],
            T['{'],
        ]);
    }

    final public function startsAlternativeSyntax(): bool
    {
        if ($this->id !== T[':']) {
            return false;
        }
        if ($this->ClosedBy) {
            return true;
        }

        return ($this->_prevCode->id === T[')'] &&
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
     * @param int|string $type
     * @param int|string ...$types
     * @return TToken
     */
    final public function prevOf($type, ...$types)
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
     * @param int|string $type
     * @param int|string ...$types
     * @return TToken
     */
    final public function nextOf($type, ...$types)
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
     * Skip to the next sibling that is not one of the listed types
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param int|string $type
     * @param int|string ...$types
     * @return TToken
     */
    final public function skipAnySiblingsOf($type, ...$types)
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
     * @return TToken
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
     * @return TToken
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
     * Get the next sibling via token traversal, without accounting for PHP's
     * alternative syntax
     *
     * @return TToken
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
