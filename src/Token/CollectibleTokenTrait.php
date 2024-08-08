<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Support\TokenCollection;
use Closure;
use LogicException;

trait CollectibleTokenTrait
{
    use NavigableTokenTrait;

    /**
     * Get the token and any subsequent tokens that form part of a declaration,
     * ignoring anonymous functions and classes
     */
    final public function namedDeclarationParts(): TokenCollection
    {
        return $this->declarationParts(false);
    }

    /**
     * Get the token and any subsequent tokens that form part of a declaration
     */
    final public function declarationParts(bool $allowAnonymous = true): TokenCollection
    {
        $index = $allowAnonymous
            ? $this->TypeIndex->DeclarationPartWithNew
            : $this->TypeIndex->DeclarationPart;

        if (!$index[$this->id]) {
            return new TokenCollection();
        }

        $t = $this;
        while (
            $t->NextSibling && (
                $index[$t->NextSibling->id] || (
                    $allowAnonymous
                    && $t->NextSibling->id === \T_OPEN_PARENTHESIS
                    && $t->id === \T_CLASS
                )
            )
        ) {
            $t = $t->NextSibling;
        }

        if (
            !$allowAnonymous
            && $t->skipPrevSiblingsFrom($this->TypeIndex->Ampersand)->id === \T_FUNCTION
        ) {
            return new TokenCollection();
        }

        return $this->collectSiblings($t);
    }

    /**
     * Get the token and its preceding tokens in the same statement, in document
     * order
     */
    final public function sinceStartOfStatement(): TokenCollection
    {
        $statement = $this->Statement ?? $this;

        return $statement->collect($this);
    }

    /**
     * Get the token and its nested tokens
     */
    final public function outer(): TokenCollection
    {
        return
            ($this->OpenedBy ?? $this)
                ->collect($this->ClosedBy ?? $this);
    }

    /**
     * Get the token's nested tokens
     */
    final public function inner(): TokenCollection
    {
        return
            ($this->OpenedBy ?? $this)
                ->next()
                ->collect(
                    ($this->ClosedBy ?? $this)->prev()
                );
    }

    /**
     * Get the token's nested siblings
     */
    final public function children(): TokenCollection
    {
        return
            ($this->OpenedBy ?? $this)
                ->nextCode()
                ->collectSiblings(
                    ($this->ClosedBy ?? $this)->prevCode()
                );
    }

    /**
     * Get the token and its following tokens up to and including a given token
     *
     * @param static $to
     */
    final public function collect($to): TokenCollection
    {
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
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            $to = $to->OpenedBy ?? $to;
            if ($this->Index > $to->Index) {
                return $tokens;
            }
            if ($current->Parent !== $to->Parent) {
                throw new LogicException('Argument #1 ($to) is not a sibling');
            }
        }
        do {
            $tokens[] = $current;
            if ($to && $current === $to) {
                break;
            }
        } while ($current = $current->NextSibling);

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
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            if ($this->Index < $to->Index) {
                return $tokens;
            }
            $to = $to->OpenedBy ?? $to;
            if ($current->Parent !== $to->Parent) {
                throw new LogicException('Argument #1 ($to) is not a sibling');
            }
        }
        while ($current = $current->PrevSibling) {
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
     * the first that isn't one of the given types
     */
    final public function prevSiblingsWhile(int $type, int ...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     */
    final public function withPrevSiblingsWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get following siblings, up to but not including the first that isn't one
     * of the given types
     */
    final public function nextSiblingsWhile(int $type, int ...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that isn't one of the given types
     *
     * @param bool $testToken If `true` and the token isn't one of the given
     * types, an empty collection is returned. Otherwise, the token is added to
     * the collection regardless.
     */
    final public function withNextSiblingsWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, $testToken, $type, ...$types);
    }

    /**
     * Get preceding siblings in reverse document order, up to but not including
     * the first that satisfies a callback
     *
     * @param Closure(self, TokenCollection): bool $callback
     */
    final public function prevSiblingsUntil(Closure $callback): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that satisfies a callback
     *
     * @param Closure(self, TokenCollection): bool $callback
     * @param bool $testToken If `true` and the token doesn't satisfy the
     * callback, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     */
    final public function withPrevSiblingsUntil(Closure $callback, bool $testToken = false): TokenCollection
    {
        return $this->_prevSiblingsUntil($callback, true, $testToken);
    }

    /**
     * Get following siblings, up to but not including the first that satisfies
     * a callback
     *
     * @param Closure(self, TokenCollection): bool $callback
     */
    final public function nextSiblingsUntil(Closure $callback): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that satisfies a callback
     *
     * @param Closure(self, TokenCollection): bool $callback
     * @param bool $testToken If `true` and the token doesn't satisfy the
     * callback, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     */
    final public function withNextSiblingsUntil(Closure $callback, bool $testToken = false): TokenCollection
    {
        return $this->_nextSiblingsUntil($callback, true, $testToken);
    }

    /**
     * Get parents up to but not including the first that isn't one of the given
     * types
     */
    final public function parentsWhile(int $type, int ...$types): TokenCollection
    {
        return $this->_parentsWhile(false, false, $type, ...$types);
    }

    /**
     * Get the token and its parents up to but not including the first that
     * isn't one of the given types
     */
    final public function withParentsWhile(bool $testToken, int $type, int ...$types): TokenCollection
    {
        return $this->_parentsWhile(true, $testToken, $type, ...$types);
    }

    private function _prevWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->Prev;
        while ($prev && $prev->is($types)) {
            $tokens[] = $prev;
            $prev = $prev->Prev;
        }

        return $tokens;
    }

    private function _nextWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->Next;
        while ($next && $next->is($types)) {
            $tokens[] = $next;
            $next = $next->Next;
        }

        return $tokens;
    }

    private function _prevCodeWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevCode;
        while ($prev && $prev->is($types)) {
            $tokens[] = $prev;
            $prev = $prev->PrevCode;
        }

        return $tokens;
    }

    private function _nextCodeWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextCode;
        while ($next && $next->is($types)) {
            $tokens[] = $next;
            $next = $next->NextCode;
        }

        return $tokens;
    }

    private function _prevSiblingsWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevSibling;
        while ($prev && $prev->is($types)) {
            $tokens[] = $prev;
            $prev = $prev->PrevSibling;
        }

        return $tokens;
    }

    private function _nextSiblingsWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextSibling;
        while ($next && $next->is($types)) {
            $tokens[] = $next;
            $next = $next->NextSibling;
        }

        return $tokens;
    }

    /**
     * @param Closure(self, TokenCollection): bool $callback
     */
    private function _prevSiblingsUntil(Closure $callback, bool $includeToken = false, bool $testToken = false): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevSibling;
        while ($prev && !$callback($prev, $tokens)) {
            $tokens[] = $prev;
            $prev = $prev->PrevSibling;
        }

        return $tokens;
    }

    /**
     * @param Closure(self, TokenCollection): bool $callback
     */
    private function _nextSiblingsUntil(Closure $callback, bool $includeToken = false, bool $testToken = false): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextSibling;
        while ($next && !$callback($next, $tokens)) {
            $tokens[] = $next;
            $next = $next->NextSibling;
        }

        return $tokens;
    }

    private function _parentsWhile(bool $includeToken, bool $testToken, int ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $current = $this->OpenedBy ?? $this;
        $current = $includeToken ? $current : $current->Parent;
        while ($current && $current->is($types)) {
            $tokens[] = $current;
            $current = $current->Parent;
        }

        return $tokens;
    }
}
