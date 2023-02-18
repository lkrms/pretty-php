<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

final class VirtualToken extends Token
{
    public ?int $Index = -1;

    public bool $IsVirtual = true;

    /**
     * @param int $type A TokenType::T_* value.
     * @psalm-param TokenType::T_* $type
     * @return static
     */
    public static function create(int $type)
    {
        return new static($type, '');
    }
}
