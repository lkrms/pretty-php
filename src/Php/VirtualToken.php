<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class VirtualToken extends Token
{
    /**
     * @var int
     */
    protected $Index = -1;

    /**
     * @var int
     */
    protected $Type;

    /**
     * @var string
     */
    protected $Code = '';

    /**
     * @var int
     */
    protected $Line = -1;

    /**
     * @var string
     */
    protected $TypeName;

    /**
     * @var bool
     */
    protected $IsVirtual = true;

    /**
     * @param int $type A TokenType::T_* value.
     * @psalm-param TokenType::T_* $type
     */
    public function __construct(int $type, Formatter $formatter)
    {
        $this->Type      = $type;
        $this->TypeName  = TokenType::NAME_MAP[$type];
        $this->Formatter = $formatter;
    }
}
