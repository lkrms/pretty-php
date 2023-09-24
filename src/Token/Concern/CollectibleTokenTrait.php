<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token\Concern;

use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;
use LogicException;

trait CollectibleTokenTrait
{
    /**
     * Optionally skip to the next declaration token in the same expression,
     * then get the token and any subsequent declaration tokens
     */
    final public function declarationParts(
        bool $allowAnonymous = true,
        bool $skipToDeclaration = true
    ): TokenCollection {
        $index =
            $allowAnonymous
                ? $this->TokenTypeIndex->DeclarationPartWithNew
                : $this->TokenTypeIndex->DeclarationPart;

        /** @var Token */
        $t = $this;

        if ($skipToDeclaration) {
            while (!$index[$t->id]) {
                $t = $t->_nextSibling;
                if (!$t || $t->Expression !== $this->Expression) {
                    return new TokenCollection();
                }
            }
        }

        $from = $t;
        while ($t->_nextSibling &&
            ($index[$t->_nextSibling->id] ||
                ($allowAnonymous &&
                    $t->_nextSibling->id === T_OPEN_PARENTHESIS &&
                    $t->id === T_CLASS))) {
            $t = $t->_nextSibling;
        }

        if (!$allowAnonymous && $t->id === T_FUNCTION) {
            return new TokenCollection();
        }

        return $from->collectSiblings($t);
    }

    /**
     * Get the token and its following tokens up to and including a given token
     *
     * @param static $to
     */
    final public function collect($to): TokenCollection
    {
        /**
         * @var Token $this
         * @var Token $to
         */
        return TokenCollection::collect($this, $to);
    }

    /**
     * Get the token and its following siblings, optionally stopping at a given
     * sibling
     *
     * @param static|null $to
     */
    final public function collectSiblings($to = null): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($this->IsNull) {
            return $tokens;
        }
        !$to || !$to->IsNull || $to = null;
        $current = $this->OpenedBy ?: $this;
        if ($to) {
            $to = $to->OpenedBy ?: $to;
            if ($this->Index > $to->Index) {
                return $tokens;
            }
            if ($current->BracketStack !== $to->BracketStack) {
                throw new LogicException('Argument #1 ($to) is not a sibling');
            }
        }
        do {
            /** @var Token $current */
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        } while ($current = $current->_nextSibling);

        return $tokens;
    }

    /**
     * Get preceding siblings in reverse document order, optionally stopping at
     * a given sibling
     *
     * @param static|null $to
     */
    final public function prevSiblings($to = null): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($this->IsNull) {
            return $tokens;
        }
        !$to || !$to->IsNull || $to = null;
        $current = $this->OpenedBy ?: $this;
        if ($to) {
            if ($this->Index < $to->Index) {
                return $tokens;
            }
            $to = $to->OpenedBy ?: $to;
            if ($current->BracketStack !== $to->BracketStack) {
                throw new LogicException('Argument #1 ($to) is not a sibling');
            }
        }
        while ($current = $current->_prevSibling) {
            /** @var Token $current */
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        }

        return $tokens;
    }

    /**
     * Get preceding tokens in reverse document order, up to but not including
     * the first that isn't one of the given types
     */
    final public function prevWhile(int $type, int ...$types): TokenCollection
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
     */
    final public function withPrevWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_prevWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get following tokens, up to but not including the first that isn't one of
     * the given types
     */
    final public function nextWhile(int $type, int ...$types): TokenCollection
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
     */
    final public function withNextWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_nextWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get preceding code tokens in reverse document order, up to but not
     * including the first that isn't one of the given types
     */
    final public function prevCodeWhile(int $type, int ...$types): TokenCollection
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
     */
    final public function withPrevCodeWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_prevCodeWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get following code tokens, up to but not including the first that isn't
     * one of the given types
     */
    final public function nextCodeWhile(int $type, int ...$types): TokenCollection
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
     */
    final public function withNextCodeWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_nextCodeWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get preceding siblings in reverse document order, up to but not including
     * the first that satisfies a callback
     *
     * @param callable(self, TokenCollection): bool $callback
     */
    final public function prevSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that satisfies a callback
     *
     * @param callable(self, TokenCollection): bool $callback
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
     * @param callable(self, TokenCollection): bool $callback
     */
    final public function nextSiblingsUntil(callable $callback): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that satisfies a callback
     *
     * @param callable(self, TokenCollection): bool $callback
     * @param bool $testToken If `true` and the token doesn't satisfy the
     * callback, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     */
    final public function withNextSiblingsUntil(callable $callback, bool $testToken = false): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback, true, $testToken);
    }

    private function _prevWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
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

    private function _nextWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
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

    private function _prevCodeWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
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

    private function _nextCodeWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
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
     * @param callable(self, TokenCollection): bool $callback
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
     * @param callable(self, TokenCollection): bool $callback
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
