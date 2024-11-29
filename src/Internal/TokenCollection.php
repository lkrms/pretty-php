<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;
use Salient\Collection\Collection;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * @extends Collection<array-key,Token>
 */
final class TokenCollection extends Collection implements Stringable
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
    public function hasOneOf(int $id): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return static
     */
    public function getAnyOf(int $id)
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $id) {
                $tokens[] = $token;
            }
        }
        return $this->maybeReplaceItems($tokens ?? [], true);
    }

    public function getFirstOf(int $id): ?Token
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->id === $id) {
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
    public function getIds(): array
    {
        /** @var Token $token */
        foreach ($this as $token) {
            $ids[] = $token->id;
        }

        return $ids ?? [];
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
    public function applyWhitespace(int $whitespace)
    {
        // Shift *_BEFORE and *_AFTER to their NO_* counterparts, then clear
        // other bits
        $remove = $whitespace << 6 & 0b111111000000;

        /** @var Token $token */
        foreach ($this as $token) {
            $token->Whitespace |= $whitespace;
            if ($remove) {
                // @phpstan-ignore argument.type
                $token->removeWhitespace($remove);
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function applyInnerWhitespace(int $whitespace)
    {
        $this->assertCollected();

        if (($whitespace & 0b0111000111000111000111) !== $whitespace) {
            throw new InvalidArgumentException('Invalid $whitespace (AFTER bits cannot be set)');
        }

        if ($this->count() < 2) {
            return $this;
        }

        $remove = $whitespace << 6 & 0b111111000000;

        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($i++) {
                $token->Whitespace |= $whitespace;
                if ($remove) {
                    // @phpstan-ignore argument.type
                    $token->removeWhitespace($remove);
                }
            }
        }

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
