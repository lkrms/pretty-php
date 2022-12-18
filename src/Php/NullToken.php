<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class NullToken extends Token
{
    public function __construct()
    {
        $this->Type = TokenType::T_NULL;
        $this->Code = '';
        $this->Line = -1;

        $this->Index        = -1;
        $this->BracketStack = [];
        $this->TypeName     = TokenType::class . '::T_NULL';
    }

    public function isNull(): bool
    {
        return true;
    }
}
