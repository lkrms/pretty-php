<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

/**
 * @template TToken of CollectibleToken
 * @extends NavigableToken<TToken>
 */
class CollectibleToken extends NavigableToken
{
    final public function prevSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback);
    }

    final public function withPrevSiblingsUntil(callable $callback, bool $testToken = false): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback, true, $testToken);
    }

    final public function nextSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback);
    }

    final public function withNextSiblingsUntil(callable $callback, bool $testToken = false): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback, true, $testToken);
    }

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
