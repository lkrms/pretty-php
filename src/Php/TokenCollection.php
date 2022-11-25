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
     * @param int|string $type
     */
    public function hasTokenWithType($type): bool
    {
        /** @var Token $token */
        foreach ($this as $token)
        {
            if ($token->is($type))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int|string ...$types
     */
    public function hasTokenWithTypeInList(...$types): bool
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

}
