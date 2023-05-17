<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use PhpToken;

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

    public ?string $OriginalText = null;

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
     * @return TToken
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
     * @return TToken
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
}
