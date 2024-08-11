<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Support\TokenCollection;
use Closure;

trait CollectibleTokenTrait
{
    use NavigableTokenTrait;

    /**
     * Get the token and any subsequent tokens that could be part of a
     * non-anonymous declaration
     */
    final public function namedDeclarationParts(): TokenCollection
    {
        return $this->getDeclarationParts(false);
    }

    /**
     * Get the token and any subsequent tokens that could be part of a
     * declaration
     */
    final public function declarationParts(): TokenCollection
    {
        return $this->getDeclarationParts(true);
    }

    private function getDeclarationParts(bool $allowAnonymous): TokenCollection
    {
        $index = $allowAnonymous
            ? $this->Idx->DeclarationPartWithNew
            : $this->Idx->DeclarationPart;

        if (!$index[$this->id]) {
            return new TokenCollection();
        }

        $t = $this;
        while ($t->NextSibling && (
            $index[$t->NextSibling->id] || (
                $allowAnonymous
                && $t->NextSibling->id === \T_OPEN_PARENTHESIS
                && $t->id === \T_CLASS
            )
        )) {
            $t = $t->NextSibling;
        }

        if (
            !$allowAnonymous
            && $t->skipPrevSiblingsFrom($this->Idx->Ampersand)->id === \T_FUNCTION
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
        return $this->Statement
            ? $this->Statement->collect($this)
            : $this->collect($this);
    }

    /**
     * Get the token and its nested tokens
     */
    final public function outer(): TokenCollection
    {
        return ($this->OpenedBy ?? $this)
            ->collect($this->ClosedBy ?? $this);
    }

    /**
     * Get the token's nested tokens
     */
    final public function inner(): TokenCollection
    {
        $t = $this->OpenedBy ?? $this;
        return $t->ClosedBy
            && $t->ClosedBy->Prev
            && $t->Next
            && $t->Next !== $t->ClosedBy
                ? $t->Next->collect($t->ClosedBy->Prev)
                : new TokenCollection();
    }

    /**
     * Get the token's nested siblings
     */
    final public function children(): TokenCollection
    {
        $t = $this->OpenedBy ?? $this;
        return $t->ClosedBy
            && $t->ClosedBy->PrevCode
            && $t->NextCode
            && $t->NextCode !== $t->ClosedBy
                ? $t->NextCode->collectSiblings($t->ClosedBy->PrevCode)
                : new TokenCollection();
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
        if ($this->id === \T_NULL) {
            return $tokens;
        }
        !$to || $to->id !== \T_NULL || $to = null;
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            if ($this->Parent !== $to->Parent) {
                return $tokens;
            }
            $to = $to->OpenedBy ?? $to;
            if ($this->Index > $to->Index) {
                return $tokens;
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
        if ($this->id === \T_NULL) {
            return $tokens;
        }
        !$to || $to->id !== \T_NULL || $to = null;
        $current = $this->OpenedBy ?? $this;
        if ($to) {
            if ($this->Parent !== $to->Parent) {
                return $tokens;
            }
            $to = $to->OpenedBy ?? $to;
            if ($this->Index < $to->Index) {
                return $tokens;
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
     * Get preceding code tokens in reverse document order, up to but not
     * including the first that isn't one of the types in an index
     *
     * @param array<int,bool> $index
     */
    final public function prevCodeWhile(array $index): TokenCollection
    {
        return $this->_prevCodeWhile(false, false, $index);
    }

    /**
     * Get the token and its preceding code tokens in reverse document order, up
     * to but not including the first that isn't one of the types in an index
     *
     * @param bool $testToken If `true` and the token isn't one of the types in
     * `$index`, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     * @param array<int,bool> $index
     */
    final public function withPrevCodeWhile(bool $testToken, array $index): TokenCollection
    {
        return $this->_prevCodeWhile(true, $testToken, $index);
    }

    /**
     * Get following code tokens, up to but not including the first that isn't
     * one of the types in an index
     *
     * @param array<int,bool> $index
     */
    final public function nextCodeWhile(array $index): TokenCollection
    {
        return $this->_nextCodeWhile(false, false, $index);
    }

    /**
     * Get the token and its following code tokens, up to but not including the
     * first that isn't one of the types in an index
     *
     * @param bool $testToken If `true` and the token isn't one of the types in
     * `$index`, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     * @param array<int,bool> $index
     */
    final public function withNextCodeWhile(bool $testToken, array $index): TokenCollection
    {
        return $this->_nextCodeWhile(true, $testToken, $index);
    }

    /**
     * Get preceding siblings in reverse document order, up to but not including
     * the first that isn't one of the types in an index
     *
     * @param array<int,bool> $index
     */
    final public function prevSiblingsWhile(array $index): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, false, $index);
    }

    /**
     * Get the token and its preceding siblings in reverse document order, up to
     * but not including the first that isn't one of the types in an index
     *
     * @param bool $testToken If `true` and the token isn't one of the types in
     * `$index`, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     * @param array<int,bool> $index
     */
    final public function withPrevSiblingsWhile(bool $testToken, array $index): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, $testToken, $index);
    }

    /**
     * Get following siblings, up to but not including the first that isn't one
     * of the types in an index
     *
     * @param array<int,bool> $index
     */
    final public function nextSiblingsWhile(array $index): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, false, $index);
    }

    /**
     * Get the token and its following siblings, up to but not including the
     * first that isn't one of the types in an index
     *
     * @param bool $testToken If `true` and the token isn't one of the types in
     * `$index`, an empty collection is returned. Otherwise, the token is added
     * to the collection regardless.
     * @param array<int,bool> $index
     */
    final public function withNextSiblingsWhile(bool $testToken, array $index): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, $testToken, $index);
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
     * Get parents up to but not including the first that isn't one of the types
     * in an index
     *
     * @param array<int,bool> $index
     */
    final public function parentsWhile(array $index): TokenCollection
    {
        return $this->_parentsWhile(false, false, $index);
    }

    /**
     * Get the token and its parents up to but not including the first that
     * isn't one of the types in an index
     *
     * @param array<int,bool> $index
     */
    final public function withParentsWhile(bool $testToken, array $index): TokenCollection
    {
        return $this->_parentsWhile(true, $testToken, $index);
    }

    /**
     * @param array<int,bool> $index
     */
    private function _prevCodeWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevCode;
        while ($prev && $index[$prev->id]) {
            $tokens[] = $prev;
            $prev = $prev->PrevCode;
        }

        return $tokens;
    }

    /**
     * @param array<int,bool> $index
     */
    private function _nextCodeWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextCode;
        while ($next && $index[$next->id]) {
            $tokens[] = $next;
            $next = $next->NextCode;
        }

        return $tokens;
    }

    /**
     * @param array<int,bool> $index
     */
    private function _prevSiblingsWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $prev = $includeToken ? $this : $this->PrevSibling;
        while ($prev && $index[$prev->id]) {
            $tokens[] = $prev;
            $prev = $prev->PrevSibling;
        }

        return $tokens;
    }

    /**
     * @param array<int,bool> $index
     */
    private function _nextSiblingsWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $next = $includeToken ? $this : $this->NextSibling;
        while ($next && $index[$next->id]) {
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

    /**
     * @param array<int,bool> $index
     */
    private function _parentsWhile(bool $includeToken, bool $testToken, array $index): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($includeToken && !$testToken) {
            $tokens[] = $this;
            $includeToken = false;
        }
        $current = $this->OpenedBy ?? $this;
        $current = $includeToken ? $current : $current->Parent;
        while ($current && $index[$current->id]) {
            $tokens[] = $current;
            $current = $current->Parent;
        }

        return $tokens;
    }
}
