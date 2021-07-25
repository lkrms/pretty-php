<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;

class PhpToken implements JsonSerializable
{
    /**
     * @var int
     */
    public $Index;

    /**
     * @var int|string
     */
    public $Type;

    /**
     * @var string
     */
    public $Code;

    /**
     * @var int
     */
    public $Line;

    /**
     * @var int
     */
    public $BracketLevel;

    /**
     * @var array
     */
    public $BracketStack;

    /**
     * @var string
     */
    public $TypeName;

    /**
     * @var PhpToken
     */
    public $OpenedBy;

    /**
     * @var PhpToken
     */
    public $ClosedBy;

    /**
     * @var PhpToken
     */
    private $_prev;

    /**
     * @var PhpToken
     */
    private $_next;

    //
    // Operators
    //
    private const ARITHMETIC = [
        "+",
        "-",
        "*",
        "/",
        "%",
        T_POW,
    ];

    private const ASSIGNMENT = [
        "=",
        T_PLUS_EQUAL,
        T_MINUS_EQUAL,
        T_MUL_EQUAL,
        T_DIV_EQUAL,
        T_MOD_EQUAL,
        T_POW_EQUAL,
        T_AND_EQUAL,
        T_OR_EQUAL,
        T_XOR_EQUAL,
        T_SL_EQUAL,
        T_SR_EQUAL,
        T_CONCAT_EQUAL,
        T_COALESCE_EQUAL,
    ];

    private const BITWISE = [
        "&",
        "|",
        "^",
        "~",
        T_SL,
        T_SR,
    ];

    private const COMPARISON = [
        "<",
        ">",
        T_IS_EQUAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_EQUAL,
        T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL,
        T_SPACESHIP,
    ];

    private const ERROR_CONTROL = [
        "@",
    ];

    private const EXECUTION = [
        "`",
    ];

    private const INCREMENT_DECREMENT = [
        T_INC,
        T_DEC,
    ];

    private const LOGICAL = [
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
        "!",
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
    ];

    private const STRING = [
        "."
    ];

    //
    // Type-casting
    //
    private const CAST = [
        T_ARRAY_CAST,
        T_BOOL_CAST,
        T_DOUBLE_CAST,
        T_INT_CAST,
        T_OBJECT_CAST,
        T_STRING_CAST,
        T_UNSET_CAST,
    ];

    /**
     * @var array
     */
    private static $_operators;

    public function __construct(int $index, $token, ?PhpToken $prev, int $bracketLevel, array $bracketStack)
    {
        if (is_array($token))
        {
            list ($this->Type, $this->Code, $this->Line) = $token;
        }
        else
        {
            $this->Type = $this->Code = $token;
            $this->Line = $prev->Line ?? 1;
        }

        $this->Index        = $index;
        $this->BracketLevel = $bracketLevel;
        $this->BracketStack = $bracketStack;
        $this->TypeName     = is_int($this->Type) ? token_name($this->Type) : $this->Type;

        if ($prev)
        {
            $this->_prev        = $prev;
            $this->_prev->_next = $this;
        }
    }

    public function jsonSerialize()
    {
        $a = get_object_vars($this);

        foreach ($a["BracketStack"] as & $t)
        {
            $t = $t->Index;
        }

        $a["OpenedBy"] = $a["OpenedBy"]->Index ?? null;
        $a["ClosedBy"] = $a["ClosedBy"]->Index ?? null;
        $a["_prev"]    = $a["_prev"]->Index ?? null;
        $a["_next"]    = $a["_next"]->Index ?? null;

        return $a;
    }

    public function Prev(int $offset = 1): PhpToken
    {
        $prev = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $prev = $prev->_prev ?? null;
        }

        return ($prev ?: new PhpNullToken());
    }

    public function Next(int $offset = 1): PhpToken
    {
        $next = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $next = $next->_next ?? null;
        }

        return ($next ?: new PhpNullToken());
    }

    public function Is($type): bool
    {
        return $this->Type === $type;
    }

    public function IsOneOf(array $types): bool
    {
        return in_array($this->Type, $types, true);
    }

    public function IsOpenBracket(): bool
    {
        return $this->IsOneOf( [
            "(",
            "[",
            "{",
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        ]);
    }

    public function IsCloseBracket(): bool
    {
        return $this->IsOneOf( [
            ")",
            "]",
            "}",
        ]);
    }

    /**
     * "Standalone" means "not anchored to anything", i.e. amenable to leading
     * and trailing whitespace
     */
    public function IsStandaloneOperator(): bool
    {
        if ( ! self::$_operators)
        {
            self::$_operators = array_merge(self::ARITHMETIC, self::ASSIGNMENT, self::BITWISE, self::COMPARISON, self::LOGICAL, self::STRING);
        }

        return $this->IsOneOf(self::$_operators);
    }

    public function IsTerminator(): bool
    {
        return $this->Is(";") || ($this->Is(T_CLOSE_TAG) && ! $this->Prev()->Is(";"));
    }
}

