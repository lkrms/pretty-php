<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concept\TypedCollection;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

/**
 * A collection of Tokens
 *
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
        $tokens = new TokenCollection();
        $tokens->Collected = true;
        if ($from->Index > $to->Index || $from->IsNull || $to->IsNull) {
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
            fn(Token $t) => $t->is($types)
        ) !== false;
    }

    /**
     * @param int|string ...$types
     */
    public function getAnyOf(...$types): TokenCollection
    {
        return $this->filter(
            fn(Token $t) => $t->is($types)
        );
    }

    /**
     * @param int|string ...$types
     */
    public function getFirstOf(...$types): ?Token
    {
        return $this->find(
            fn(Token $t) => $t->is($types)
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
            $types[] = $token->id;
        }

        return $types ?? [];
    }

    /**
     * Return true if the collection will render over multiple lines, not
     * including leading or trailing whitespace
     *
     */
    public function hasNewline(): bool
    {
        if (!$this->Collected) {
            throw new RuntimeException('Collection not created by ' . static::class . '::collect()');
        }
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
     * Render tokens in the collection as-is, optionally removing leading
     * whitespace from the first token
     *
     */
    public function render(bool $softTabs = false, bool $trim = true): string
    {
        if (!$this->Collected) {
            throw new RuntimeException('Collection not created by ' . static::class . '::collect()');
        }
        $code = '';
        /** @var Token $token */
        foreach ($this as $token) {
            $code .= $token->render($softTabs);
            if ($trim && ($before = $token->renderWhitespaceBefore($softTabs))) {
                $code = substr($code, strlen($before));
            }
            $trim = false;
        }

        return $code;
    }

    /**
     * @return $this
     */
    public function addWhitespaceBefore(int $type, bool $critical = false)
    {
        if ($critical) {
            return $this->forEach(
                function (Token $t) use ($type) {
                    $t->CriticalWhitespaceBefore |= $type;
                    $t->WhitespaceMaskPrev |= $type;
                    $t->prev()->WhitespaceMaskNext |= $type;
                }
            );
        }

        return $this->forEach(
            function (Token $t) use ($type) {
                $t->WhitespaceBefore |= $type;
                $t->WhitespaceMaskPrev |= $type;
                $t->prev()->WhitespaceMaskNext |= $type;
            }
        );
    }

    /**
     * @return $this
     */
    public function addWhitespaceAfter(int $type, bool $critical = false)
    {
        if ($critical) {
            return $this->forEach(
                function (Token $t) use ($type) {
                    $t->CriticalWhitespaceAfter |= $type;
                    $t->WhitespaceMaskNext |= $type;
                    $t->next()->WhitespaceMaskPrev |= $type;
                }
            );
        }

        return $this->forEach(
            function (Token $t) use ($type) {
                $t->WhitespaceAfter |= $type;
                $t->WhitespaceMaskNext |= $type;
                $t->next()->WhitespaceMaskPrev |= $type;
            }
        );
    }

    /**
     * @return $this
     */
    public function maskWhitespaceBefore(int $mask)
    {
        return $this->forEach(
            function (Token $t) use ($mask) {
                $t->WhitespaceMaskPrev &= $mask;
                $t->prev()->WhitespaceMaskNext &= $mask;
            }
        );
    }

    /**
     * @return $this
     */
    public function maskInnerWhitespace(int $mask)
    {
        if (!$this->Collected) {
            throw new RuntimeException('Collection not created by ' . static::class . '::collect()');
        }
        switch ($this->count()) {
            case 0:
            case 1:
                return $this;

            default:
                $this->nth(2)
                     ->collect($this->nth(-2))
                     ->forEach(
                         function (Token $t) use ($mask) {
                             $t->WhitespaceMaskPrev &= $mask;
                             $t->WhitespaceMaskNext &= $mask;
                         }
                     );
            case 2:
                $this->first()->WhitespaceMaskNext &= $mask;
                $this->last()->WhitespaceMaskPrev &= $mask;

                return $this;
        }
    }
}
