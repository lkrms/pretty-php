<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Token;
use Salient\Collection\Collection;
use Salient\Utility\Exception\ShouldNotHappenException;
use InvalidArgumentException;
use Stringable;

/**
 * @internal
 *
 * @extends Collection<array-key,Token>
 */
final class TokenCollection extends Collection implements Stringable
{
    private bool $Collected = false;

    public static function collect(Token $from, Token $to): self
    {
        $tokens = [];
        if (
            $from->id !== \T_NULL
            && $to->id !== \T_NULL
            && $from->index <= $to->index
        ) {
            $t = $from;
            do {
                $tokens[] = $t;
            } while ($t !== $to && ($t = $t->Next));
        }
        $instance = new self($tokens);
        $instance->Collected = true;
        return $instance;
    }

    /**
     * @param Token[] $collected Tokens collected by iterating over
     * {@see Token::$Next}.
     */
    public static function from(array $collected): self
    {
        $instance = new self($collected);
        $instance->Collected = true;
        return $instance;
    }

    /**
     * @phpstan-assert-if-true non-empty-array<array-key,Token> $this->all()
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneOf(int $id): bool
    {
        foreach ($this->Items as $token) {
            if ($token->id === $id) {
                return true;
            }
        }
        return false;
    }

    public function getAnyOf(int $id): self
    {
        $tokens = [];
        foreach ($this->Items as $token) {
            if ($token->id === $id) {
                $tokens[] = $token;
            }
        }
        return $this->maybeReplaceItems($tokens);
    }

    public function getFirstOf(int $id): ?Token
    {
        foreach ($this->Items as $token) {
            if ($token->id === $id) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @param array<int,bool> $index
     * @phpstan-assert-if-true non-empty-array<array-key,Token> $this->all()
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneFrom(array $index): bool
    {
        foreach ($this->Items as $token) {
            if ($index[$token->id]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,bool> $index
     * @phpstan-assert-if-true non-empty-array<array-key,Token> $this->all()
     * @phpstan-assert-if-true !null $this->first()
     * @phpstan-assert-if-true !null $this->last()
     */
    public function hasOneNotFrom(array $index): bool
    {
        foreach ($this->Items as $token) {
            if (!$index[$token->id]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,bool> $index
     */
    public function getAnyFrom(array $index): self
    {
        $tokens = [];
        foreach ($this->Items as $token) {
            if ($index[$token->id]) {
                $tokens[] = $token;
            }
        }
        return $this->maybeReplaceItems($tokens);
    }

    /**
     * @param array<int,bool> $index
     */
    public function getFirstFrom(array $index): ?Token
    {
        foreach ($this->Items as $token) {
            if ($index[$token->id]) {
                return $token;
            }
        }
        return null;
    }

    /**
     * @param array<int,bool> $index
     */
    public function getFirstNotFrom(array $index): ?Token
    {
        foreach ($this->Items as $token) {
            if (!$index[$token->id]) {
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
        $ids = [];
        foreach ($this->Items as $token) {
            $ids[] = $token->id;
        }
        return $ids;
    }

    /**
     * Check if there is a newline before one of the tokens in the collection
     */
    public function tokenHasNewlineBefore(): bool
    {
        foreach ($this->Items as $token) {
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
        foreach ($this->Items as $token) {
            if ($closedBy && $token->CloseBracket) {
                $token = $token->CloseBracket;
            }
            if ($token->hasNewlineAfter()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if, between a token in the collection and its next code token,
     * there's a newline between tokens
     */
    public function tokenHasNewlineBeforeNextCode(bool $closedBy = false): bool
    {
        foreach ($this->Items as $token) {
            if ($closedBy && $token->CloseBracket) {
                $token = $token->CloseBracket;
            }
            if ($token->hasNewlineBeforeNextCode()) {
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
        $ignore = true;
        foreach ($this->Items as $token) {
            if ($ignore) {
                $ignore = false;
            } elseif ($token->hasNewlineBefore()) {
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
        $ignore = true;
        foreach ($this->Items as $token) {
            if ($ignore) {
                $ignore = false;
            } elseif ($token->hasBlankBefore()) {
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

        $ignore = true;
        foreach ($this->Items as $token) {
            if (strpos($token->text, "\n") !== false) {
                return true;
            }
            if ($ignore) {
                $ignore = false;
            } elseif (
                !$token->Idx->Virtual[$token->id]
                && $token->hasNewlineBefore()
            ) {
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

        $from = reset($this->Items);
        $to = end($this->Items);
        $renderer = $from->Formatter->Renderer;

        $code = $renderer->render(
            $from,
            $to,
            $softTabs,
            false,
            $trimBefore,
            $trimAfter
        );

        return ltrim($code, "\n");
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(string $delimiter = ''): string
    {
        $text = [];
        foreach ($this->Items as $token) {
            if (!$token->Idx->Virtual[$token->id]) {
                $text[] = $token->text;
            }
        }
        return implode($delimiter, $text);
    }

    /**
     * @return $this
     */
    public function setTokenWhitespace(int $whitespace): self
    {
        foreach ($this->Items as $token) {
            $token->Whitespace |= $whitespace;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function applyTokenWhitespace(int $whitespace): self
    {
        // Shift *_BEFORE and *_AFTER to their NO_* counterparts, then clear
        // other bits
        $remove = $whitespace << 6 & 0b111111000000;
        foreach ($this->Items as $token) {
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
    public function setInnerWhitespace(int $whitespace): self
    {
        $this->assertCollected();

        if (($whitespace & 0b111000111000111000111) !== $whitespace) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid $whitespace (AFTER bits cannot be set)');
            // @codeCoverageIgnoreEnd
        }
        if ($this->count() < 2) {
            return $this;
        }
        $ignore = true;
        foreach ($this->Items as $token) {
            if ($ignore) {
                $ignore = false;
            } elseif (
                !$token->Idx->Virtual[$token->id]
                || $token->Data[Data::BOUND_TO]->index > $token->index
            ) {
                $token->Whitespace |= $whitespace;
            }
        }
        return $this;
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
            throw new ShouldNotHappenException(sprintf(
                'Tokens were not collected by %s::collect()',
                static::class,
            ));
            // @codeCoverageIgnoreEnd
        }
    }
}
