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
        return $this->find(
            fn(Token $t) => $t->isOneOf(...$types)
        ) !== false;
    }

    /**
     * @param int|string ...$types
     */
    public function getAnyOf(...$types): TokenCollection
    {
        return $this->filter(
            fn(Token $t) => $t->isOneOf(...$types)
        );
    }

    /**
     * @param int|string ...$types
     */
    public function getFirstOf(...$types): ?Token
    {
        return $this->find(
            fn(Token $t) => $t->isOneOf(...$types)
        ) ?: null;
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
    public function hasOuterNewline(): bool
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
     * Return true if the collection will render over multiple lines, not
     * including the content of the first or last token
     */
    public function hasInnerNewline(): bool
    {
        if (!$this->Collected) {
            throw new RuntimeException('Collection not created by ' . static::class . '::collect()');
        }
        [$i, $count] = [0, count($this)];
        /** @var Token $token */
        foreach ($this as $token) {
            // Ignore the first token
            if (!$i++) {
                continue;
            }
            if ($token->hasNewlineBefore()) {
                return true;
            }
            // Ignore the content of the last token
            if ($i === $count) {
                continue;
            }
            if (substr_count($token->Code, "\n")) {
                return true;
            }
        }

        return false;
    }

    public function render(bool $softTabs = false, bool $trim = true): string
    {
        $code = '';
        /** @var Token $token */
        foreach ($this as $token) {
            $code .= $token->render($softTabs);
            if ($trim && ($before = $token->renderWhitespaceBefore($softTabs))) {
                $code = substr($code, strlen($before));
                $trim = false;
            }
        }

        return $code;
    }
}
