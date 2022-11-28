<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concept\TypedCollection;

final class TokenCollection extends TypedCollection
{
    protected function getItemClass(): string
    {
        return Token::class;
    }

    /**
     * @param int|string ...$types
     */
    public function hasOneOf(...$types): bool
    {
        /** @var Token $token */
        foreach ($this as $token)
        {
            if ($token->isOneOf(...$types))
            {
                return true;
            }
        }

        return false;
    }

    public function hasInnerNewline(): bool
    {
        if (count($this) < 2)
        {
            return false;
        }
        $i = 0;
        /** @var Token $token */
        foreach ($this as $token)
        {
            if (substr_count($token->Code, "\n"))
            {
                return true;
            }
            if (!$i++)
            {
                continue;
            }
            if ($token->hasNewlineBefore())
            {
                return true;
            }
        }

        return false;
    }

}
