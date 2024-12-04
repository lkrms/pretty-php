<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

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
            } elseif ($token->hasNewlineBefore()) {
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

        $code = $renderer->render($from, $to, $softTabs);

        $after = $renderer->renderWhitespaceAfter($to);
        if ($after !== '') {
            $afterLength = strlen($after);
            if ($trimAfter) {
                if (substr($code, -$afterLength) === $after) {
                    // Remove trailing whitespace if the renderer added it
                    $code = substr($code, 0, -$afterLength);
                }
            } elseif (substr($code, -$afterLength) !== $after) {
                $code .= $after;
            }
        }

        if ($trimBefore) {
            $before = $renderer->renderWhitespaceBefore($from, $softTabs);
            return $before === ''
                ? $code
                : substr($code, strlen($before));
        }

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
            $text[] = $token->text;
        }
        return implode($delimiter, $text);
    }

    /**
     * @return $this
     */
    public function applyWhitespace(int $whitespace)
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
    public function applyInnerWhitespace(int $whitespace)
    {
        $this->assertCollected();
        if (($whitespace & 0b0111000111000111000111) !== $whitespace) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid $whitespace (AFTER bits cannot be set)');
            // @codeCoverageIgnoreEnd
        }
        if ($this->count() < 2) {
            return $this;
        }
        $remove = $whitespace << 6 & 0b111111000000;
        $ignore = true;
        foreach ($this->Items as $token) {
            if ($ignore) {
                $ignore = false;
            } else {
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
