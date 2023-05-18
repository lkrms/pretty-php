<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use PhpToken;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * @template TToken of NavigableToken
 */
class NavigableToken extends PhpToken
{
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
     * @var TToken[]
     */
    public $BracketStack = [];

    /**
     * @var Formatter|null
     */
    protected $Formatter;

    public ?string $OriginalText = null;

    /**
     * @return static[]
     */
    public static function tokenize(string $code, int $flags = 0): array
    {
        $tokens = parent::tokenize($code, $flags);

        return $tokens;
    }

    /**
     * @param static[] $tokens
     * @return static[]
     */
    public static function prepareTokens(array $tokens, Formatter $formatter): array
    {
        /** @var static|null */
        $last = null;
        foreach ($tokens as $token) {
            if ($last) {
                $token->_prev = $last;
                $last->_next = $token;
            }
            $token->Formatter = $formatter;
            $last = $token;
        }

        return $tokens;
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
    final public function isStrictOpenBracket(): bool
    {
        return $this->is([
            T['('],
            T['['],
            T['{'],
        ]);
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
     * @param int|string ...$types
     * @return TToken|NullToken
     */
    final public function prevOf(...$types)
    {
        $t = $this;
        while ($t = $t->_prev) {
            if ($t->is($types)) {
                return $t;
            }
        }

        return NullToken::create();
    }

    /**
     * Get the next token that is one of the listed types
     *
     * @param int|string ...$types
     * @return TToken|NullToken
     */
    final public function nextOf(...$types)
    {
        $t = $this;
        while ($t = $t->_next) {
            if ($t->is($types)) {
                return $t;
            }
        }

        return NullToken::create();
    }

    /**
     * Get the last reachable token
     *
     * @return TToken
     */
    final public function last()
    {
        $current = reset($this->BracketStack) ?: $this;
        while ($current->_nextSibling) {
            $current = $current->_nextSibling;
        }

        return $current;
    }

    /**
     * Get the next sibling via token traversal, without accounting for PHP's
     * alternative syntax
     *
     * @return TToken|NullToken
     */
    protected function nextSimpleSibling(int $offset = 1)
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

        return NullToken::create();
    }
}
