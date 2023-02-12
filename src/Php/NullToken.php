<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class NullToken extends VirtualToken
{
    /**
     * @var bool
     */
    protected $IsNull = true;

    public function __construct()
    {
        $this->Type     = TokenType::T_NULL;
        $this->TypeName = TokenType::NAME_MAP[$this->Type];
    }
}
