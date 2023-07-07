<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

/**
 * @template TToken of CollectibleToken
 * @extends NavigableToken<TToken>
 */
class CollectibleToken extends NavigableToken
{
    /**
     * Get preceding tokens in reverse document order, up to but not including
     * the first that isn't one of the given types
     *
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function prevWhile($type, ...$types): TokenCollection
    {
        return $this->_prevWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its preceding tokens in reverse document order, up to
     * but not including the first that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function withPrevWhile(bool $testToken, $type, ...$types): TokenCollection
    {
        return $this->_prevWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get following tokens, up to but not including the first that isn't one of
     * the given types
     *
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function nextWhile($type, ...$types): TokenCollection
    {
        return $this->_nextWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its following tokens, up to but not including the first
     * that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function withNextWhile(bool $testToken, $type, ...$types): TokenCollection
    {
        return $this->_nextWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get preceding code tokens in reverse document order, up to but not
     * including the first that isn't one of the given types
     *
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function prevCodeWhile($type, ...$types): TokenCollection
    {
        return $this->_prevCodeWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its preceding code tokens in reverse document order, up
     * to but not including the first that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function withPrevCodeWhile(bool $testToken, $type, ...$types): TokenCollection
    {
        return $this->_prevCodeWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get following code tokens, up to but not including the first that isn't
     * one of the given types
     *
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function nextCodeWhile($type, ...$types): TokenCollection
    {
        return $this->_nextCodeWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its following code tokens, up to but not including the
     * first that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     * @param int|string $type
     * @param int|string ...$types
     */
    final public function withNextCodeWhile(bool $testToken, $type, ...$types): TokenCollection
    {
        return $this->_nextCodeWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get preceding siblings in reverse document order, up to but not including
     * the first that satisfies a callback
     *
     * @param callable(TToken, TokenCollection): bool $callback
     */
    final public function prevSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that satisfies a callback
     *
     * @param callable(TToken, TokenCollection): bool $callback
     * @param bool $testToken If `true` and the token doesn't satisfy the
     * callback, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     */
    final public function withPrevSiblingsUntil(callable $callback, bool $testToken = false): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback, true, $testToken);
    }

    /**
     * Get following siblings, up to but not including the first that satisfies
     * a callback
     *
     * @param callable(TToken, TokenCollection): bool $callback
     */
    final public function nextSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that satisfies a callback
     *
     * @param callable(TToken, TokenCollection): bool $callback
     * @param bool $testToken If `true` and the token doesn't satisfy the
     * callback, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     */
    final public function withNextSiblingsUntil(callable $callback, bool $testToken = false): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback, true, $testToken);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevWhile(bool $includeToken, bool $testToken, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->_prev;
        while ($prev && $prev->is($types)) {
            /** @var Token $prev */
            $tokens[] = $prev;
            $prev = $prev->_prev;
        }

        return $tokens;
    }

    /**
     * @param int|string ...$types
     */
    private function _nextWhile(bool $includeToken, bool $testToken, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->_next;
        while ($next && $next->is($types)) {
            /** @var Token $next */
            $tokens[] = $next;
            $next = $next->_next;
        }

        return $tokens;
    }

    /**
     * @param int|string ...$types
     */
    private function _prevCodeWhile(bool $includeToken, bool $testToken, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->_prevCode;
        while ($prev && $prev->is($types)) {
            /** @var Token $prev */
            $tokens[] = $prev;
            $prev = $prev->_prevCode;
        }

        return $tokens;
    }

    /**
     * @param int|string ...$types
     */
    private function _nextCodeWhile(bool $includeToken, bool $testToken, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->_nextCode;
        while ($next && $next->is($types)) {
            /** @var Token $next */
            $tokens[] = $next;
            $next = $next->_nextCode;
        }

        return $tokens;
    }

    /**
     * @param callable(TToken, TokenCollection): bool $callback
     */
    private function _prevSiblingsUntil(callable $callback, bool $includeToken = false, bool $testToken = false): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->_prevSibling;
        while ($prev && !$callback($prev, $tokens)) {
            /** @var Token $prev */
            $tokens[] = $prev;
            $prev = $prev->_prevSibling;
        }

        return $tokens;
    }

    /**
     * @param callable(TToken, TokenCollection): bool $callback
     */
    private function _nextSiblingsUntil(callable $callback, bool $includeToken = false, bool $testToken = false): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            /** @var Token $this */
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->_nextSibling;
        while ($next && !$callback($next, $tokens)) {
            /** @var Token $next */
            $tokens[] = $next;
            $next = $next->_nextSibling;
        }

        return $tokens;
    }
}
