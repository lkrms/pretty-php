<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\Concept\LooselyTypedCollection;
use Lkrms\PrettyPHP\Token\Token;
use LogicException;
use Stringable;

/**
 * A collection of Tokens
 *
 * @extends LooselyTypedCollection<int,Token>
 */
final class TokenCollection extends LooselyTypedCollection implements Stringable
{
    protected const ITEM_CLASS = Token::class;

    /**
     * @var bool
     */
    private $Collected = false;

    public static function collect(Token $from, Token $to): self
    {
        if ($from->Index <= $to->Index && !$from->IsNull && !$to->IsNull) {
            $tokens[] = $from;
            while ($from !== $to && $from->_next) {
                $tokens[] = $from = $from->_next;
            }
        }
        $instance = new self($tokens ?? []);
        $instance->Collected = true;

        return $instance;
    }

    public function hasOneOf(int $type, int ...$types): bool
    {
        array_unshift($types, $type);
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->is($types)) {
                return true;
            }
        }
        return false;
    }

    public function getAnyOf(int $type, int ...$types): self
    {
        array_unshift($types, $type);
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->is($types)) {
                $tokens[] = $token;
            }
        }
        $instance = new self($tokens ?? []);
        $instance->Collected = $this->Collected;

        return $instance;
    }

    public function getFirstOf(int $type, int ...$types): ?Token
    {
        array_unshift($types, $type);
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->is($types)) {
                return $token;
            }
        }
        return null;
    }

    public function getLastOf(int $type, int ...$types): ?Token
    {
        return $this->reverse()->getFirstOf($type, ...$types);
    }

    /**
     * @return int[]
     */
    public function getTypes(): array
    {
        /** @var Token $token */
        foreach ($this as $token) {
            $types[] = $token->id;
        }

        return $types ?? [];
    }

    /**
     * True if there is a newline before one of the tokens in the collection
     *
     */
    public function tokenHasNewlineBefore(): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->hasNewlineBefore()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if there is a newline after one of the tokens in the collection
     *
     */
    public function tokenHasNewlineAfter(): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->hasNewlineAfter()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if any tokens in the collection are separated by one or more line
     * breaks
     *
     */
    public function hasNewlineBetweenTokens(): bool
    {
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($i++ && $token->hasNewlineBefore()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if any tokens in the collection are separated by a blank line
     *
     */
    public function hasBlankLineBetweenTokens(): bool
    {
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($i++ && $token->hasBlankLineBefore()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the collection will render over multiple lines, not including
     * leading or trailing whitespace
     *
     */
    public function hasNewline(): bool
    {
        $this->assertCollected();

        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if (strpos($token->text, "\n") !== false) {
                return true;
            }
            if ($i++ && $token->hasNewlineBefore()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render tokens in the collection, optionally removing leading
     * whitespace from the first token
     *
     * Leading newlines are always trimmed.
     *
     */
    public function render(bool $softTabs = false, bool $trim = true): string
    {
        $this->assertCollected();

        $first = $this->first();
        $last = $this->last();
        $code = $first->render($softTabs, $last);
        if ($trim) {
            if ($before = $first->renderWhitespaceBefore($softTabs, true)) {
                return substr($code, strlen($before));
            }
            return $code;
        }
        return ltrim($code, "\n");
    }

    public function __toString(): string
    {
        $code = '';

        /** @var Token $token */
        foreach ($this as $token) {
            $code .= $token->text;
        }

        return $code;
    }

    /**
     * @return $this
     */
    public function addWhitespaceBefore(int $type, bool $critical = false)
    {
        if ($critical) {
            /** @var Token $token */
            foreach ($this as $token) {
                $token->CriticalWhitespaceBefore |= $type;
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceBefore |= $type;
            $token->WhitespaceMaskPrev |= $type;
            if ($token->_prev) {
                $token->_prev->WhitespaceMaskNext |= $type;
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function addWhitespaceAfter(int $type, bool $critical = false)
    {
        if ($critical) {
            /** @var Token $token */
            foreach ($this as $token) {
                $token->CriticalWhitespaceAfter |= $type;
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceAfter |= $type;
            $token->WhitespaceMaskNext |= $type;
            if ($token->_next) {
                $token->_next->WhitespaceMaskPrev |= $type;
            }
        }
        return $this;
    }

    /**
     * Use T_AND_EQUAL ('&=') to apply a mask to all WhitespaceMaskPrev and
     * WhitespaceMaskNext values that cover whitespace before tokens in the
     * collection
     *
     * If `$critical` is set, operate on CriticalWhitespaceMaskPrev and
     * CriticalWhitespaceMaskNext instead.
     *
     * @return $this
     */
    public function maskWhitespaceBefore(int $mask, bool $critical = false)
    {
        if ($critical) {
            /** @var Token $token */
            foreach ($this as $token) {
                $token->CriticalWhitespaceMaskPrev &= $mask;
                if ($token->_prev) {
                    $token->_prev->CriticalWhitespaceMaskNext &= $mask;
                }
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceMaskPrev &= $mask;
            if ($token->_prev) {
                $token->_prev->WhitespaceMaskNext &= $mask;
            }
        }
        return $this;
    }

    /**
     * Use T_AND_EQUAL ('&=') to apply a mask to all inward-facing
     * WhitespaceMaskPrev and WhitespaceMaskNext values in the collection
     *
     * If `$critical` is set, operate on CriticalWhitespaceMaskPrev and
     * CriticalWhitespaceMaskNext instead.
     *
     * @return $this
     */
    public function maskInnerWhitespace(int $mask, bool $critical = false)
    {
        $this->assertCollected();

        $count = $this->count();
        if ($count < 2) {
            return $this;
        }

        if ($critical) {
            if ($count > 2) {
                foreach ($this->nth(2)->collect($this->nth(-2)) as $token) {
                    $token->CriticalWhitespaceMaskPrev &= $mask;
                    $token->CriticalWhitespaceMaskNext &= $mask;
                }
            }

            $this->first()->CriticalWhitespaceMaskNext &= $mask;
            $this->last()->CriticalWhitespaceMaskPrev &= $mask;

            return $this;
        }

        if ($count > 2) {
            foreach ($this->nth(2)->collect($this->nth(-2)) as $token) {
                $token->WhitespaceMaskPrev &= $mask;
                $token->WhitespaceMaskNext &= $mask;
            }
        }

        $this->first()->WhitespaceMaskNext &= $mask;
        $this->last()->WhitespaceMaskPrev &= $mask;

        return $this;
    }

    private function assertCollected(): void
    {
        if (!$this->Collected) {
            throw new LogicException(sprintf('Not collected by %s::collect()', static::class));
        }
        if ($this->isMutant()) {
            throw new LogicException(sprintf('Modified since collection by %s::collect()', static::class));
        }
    }
}
