<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;
use Salient\Collection\AbstractTypedList;
use LogicException;
use Stringable;

/**
 * @extends AbstractTypedList<Token>
 */
final class TokenCollection extends AbstractTypedList implements Stringable
{
    private bool $Collected = false;

    /**
     * @return static
     */
    public static function collect(Token $from, Token $to)
    {
        if (
            $from->id !== \T_NULL
            && $to->id !== \T_NULL
            && $from->Index <= $to->Index
        ) {
            do {
                $tokens[] = $from;
            } while ($from !== $to && ($from = $from->Next));
        }

        $instance = new self($tokens ?? []);
        $instance->Collected = true;
        return $instance;
    }

    /**
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneOf(int $type): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return static
     */
    public function getAnyOf(int $type)
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $type) {
                $tokens[] = $token;
            }
        }
        return $this->maybeReplaceItems($tokens ?? [], true);
    }

    public function getFirstOf(int $type): ?Token
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $type) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @param array<int,bool> $index
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneFrom(array $index): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($index[$token->id]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,bool> $index
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneNotFrom(array $index): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if (!$index[$token->id]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,bool> $index
     * @return static
     */
    public function getAnyFrom(array $index)
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($index[$token->id]) {
                $tokens[] = $token;
            }
        }
        return $this->maybeReplaceItems($tokens ?? [], true);
    }

    /**
     * @param array<int,bool> $index
     */
    public function getFirstFrom(array $index): ?Token
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($index[$token->id]) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @return list<int>
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
     * Check if there is a newline before one of the tokens in the collection
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
     * Check if there is a newline after one of the tokens in the collection
     */
    public function tokenHasNewlineAfter(bool $closedBy = false): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($closedBy && $token->ClosedBy) {
                $token = $token->ClosedBy;
            }
            if ($token->hasNewlineAfter()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if any tokens in the collection are separated by one or more line
     * breaks
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
     * Check if any tokens in the collection are separated by a blank line
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
     * Check if the collection will render over multiple lines, not including
     * leading or trailing whitespace
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
     * Render tokens in the collection, optionally removing whitespace before
     * the first and after the last token
     *
     * Leading newlines are always trimmed.
     */
    public function render(
        bool $softTabs = false,
        bool $trimBefore = true,
        bool $trimAfter = true
    ): string {
        $this->assertCollected();

        if (!$this->Items) {
            return '';
        }

        $first = reset($this->Items);
        $last = end($this->Items);

        $renderer = $first->Formatter->Renderer;
        $code = $renderer->render($first, $last, $softTabs);
        if (
            !$trimAfter
            && ($after = $renderer->renderWhitespaceAfter($last))
        ) {
            $code .= $after;
        }
        if (
            $trimBefore
            && ($before = $renderer->renderWhitespaceBefore($first, $softTabs))
        ) {
            return substr($code, strlen($before));
        }
        return ltrim($code, "\n");
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(string $delimiter = ''): string
    {
        /** @var Token $token */
        foreach ($this as $token) {
            $code[] = $token->text;
        }
        return implode($delimiter, $code ?? []);
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
            if ($token->Prev) {
                $token->Prev->WhitespaceMaskNext |= $type;
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
            if ($token->Next) {
                $token->Next->WhitespaceMaskPrev |= $type;
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
                if ($token->Prev) {
                    $token->Prev->CriticalWhitespaceMaskNext &= $mask;
                }
            }
            return $this;
        }

        /** @var Token $token */
        foreach ($this as $token) {
            $token->WhitespaceMaskPrev &= $mask;
            if ($token->Prev) {
                $token->Prev->WhitespaceMaskNext &= $mask;
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

    /**
     * @internal
     */
    public function __clone()
    {
        $this->Collected = false;
    }

    /**
     * @inheritDoc
     */
    protected function handleItemsReplaced(): void
    {
        $this->Collected = false;
    }

    private function assertCollected(): void
    {
        if (!$this->Collected) {
            // @codeCoverageIgnoreStart
            throw new LogicException(sprintf(
                'Tokens were not collected by %s::collect()',
                static::class,
            ));
            // @codeCoverageIgnoreEnd
        }
    }
}
