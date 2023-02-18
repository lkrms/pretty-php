<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

final class NullToken extends Token
{
    public ?int $Index = -1;

    public bool $IsNull = true;

    public bool $IsVirtual = true;

    /**
     * @return static
     */
    public static function create()
    {
        return new static(T_NULL, '');
    }
}
