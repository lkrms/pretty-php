<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class PhpNullToken extends Token
{
    function __construct()
    {
        $this->Type = $this->Code = "";
        $this->Line = -1;

        $this->Index        = -1;
        $this->BracketLevel = -1;
        $this->BracketStack = [];
        $this->TypeName     = "";
    }
}
