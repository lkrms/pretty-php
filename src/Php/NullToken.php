<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class NullToken extends Token
{
    public function __construct()
    {
        $this->Type = $this->Code = '';
        $this->Line = -1;

        $this->Index        = -1;
        $this->BracketStack = [];
        $this->TypeName     = '';
    }

    public function isNull(): bool
    {
        return true;
    }
}
