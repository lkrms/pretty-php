<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concept\TypedCollection;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

/**
 * @extends TypedCollection<Token>
 */
final class TokenCollection extends TypedCollection
{
    /**
     * @var bool
     */
    private $Collected = false;

    protected function getItemClass(): string
    {
        return Token::class;
    }

    public static function collect(Token $from, Token $to): TokenCollection
    {
        $tokens            = new TokenCollection();
        $tokens->Collected = true;

        if ($from->Index > $to->Index || $from->isNull() || $to->isNull()) {
            return $tokens;
        }

        $tokens[] = $from;
        while ($from !== $to) {
            $tokens[] = $from = $from->next();
        }

        return $tokens;
    }

    /**
     * @param int|string ...$types
     */
    public function hasOneOf(...$types): bool
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->isOneOf(...$types)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int|string ...$types
     */
    public function getAnyOf(...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->isOneOf(...$types)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * @param int|string ...$types
     */
    public function getFirstOf(...$types): ?Token
    {
        /** @var Token $token */
        foreach ($this as $token) {
            if ($token->isOneOf(...$types)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @param int|string ...$types
     */
    public function getLastOf(...$types): ?Token
    {
        return $this->reverse()->getFirstOf(...$types);
    }

    /**
     * @return array<int|string>
     */
    public function getTypes(): array
    {
        /** @var Token $token */
        foreach ($this as $token) {
            $types[] = $token->Type;
        }

        return $types ?? [];
    }

    /**
     * Return true if the collection will render over multiple lines, not
     * including whitespace before the first or after the last token
     */
    public function hasInnerNewline(): bool
    {
        if (!$this->Collected) {
            throw new RuntimeException('Collection not created by ' . static::class . '::collect()');
        }
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token) {
            if (substr_count($token->Code, "\n")) {
                return true;
            }
            if (!$i++) {
                continue;
            }
            if ($token->hasNewlineBefore()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @psalm-param callable(Token $token) $callback
     * @param callable $callback
     * ```php
     * callable(Token $token)
     * ```
     * @return $this
     */
    public function withEach(callable $callback)
    {
        foreach ($this as $token) {
            $callback($token);
        }

        return $this;
    }

    /**
     * @psalm-param callable(Token $token, bool &$return): bool $filter
     * @param callable $filter
     * ```php
     * callable(Token $token, bool &$return): bool
     * ```
     */
    public function filter(callable $filter): TokenCollection
    {
        $tokens = new TokenCollection();
        $return = false;
        /** @var Token $token */
        foreach ($this as $token) {
            if ($filter($token, $return)) {
                $tokens[] = $token;
            }
            if ($return) {
                return $tokens;
            }
        }

        return $tokens;
    }

    public function render(): string
    {
        [$code, $i] = ['', 0];
        /** @var Token $token */
        foreach ($this as $token) {
            $code .= $token->render();
            if (!$i++) {
                $before = WhitespaceType::toWhitespace($token->effectiveWhitespaceBefore());
                if ($before) {
                    $code = substr($code, strlen($before));
                }
            }
        }

        return $code;
    }
}
