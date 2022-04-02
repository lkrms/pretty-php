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

    /**
     *
     * @param int $index
     * @param array|string $token
     * @param null|PhpToken $prev
     * @param int $bracketLevel
     * @param array $bracketStack
     */
    public function __construct(
        int $index,
        $token,
        ?PhpToken $prev,
        int $bracketLevel,
        array $bracketStack
    )
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

    public function prev(int $offset = 1): PhpToken
    {
        $prev = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $prev = $prev->_prev ?? null;
        }

        return ($prev ?: new PhpNullToken());
    }

    public function next(int $offset = 1): PhpToken
    {
        $next = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $next = $next->_next ?? null;
        }

        return ($next ?: new PhpNullToken());
    }

    public function is($type): bool
    {
        return $this->Type === $type;
    }

    public function isOneOf(...$types): bool
    {
        return in_array($this->Type, $types, true);
    }

    public function isOpenBracket(): bool
    {
        return $this->isOneOf("(", "[", "{", T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES);
    }

    public function isCloseBracket(): bool
    {
        return $this->isOneOf(")", "]", "}");
    }

    public function isOperator()
    {
        // TODO: return false if part of a declaration

        // OPERATOR_EXECUTION is excluded because for formatting purposes,
        // commands between backticks are equivalent to double-quoted strings
        return $this->isOneOf(
            ...PhpTokenType::OPERATOR_ARITHMETIC,
            ...PhpTokenType::OPERATOR_ASSIGNMENT,
            ...PhpTokenType::OPERATOR_BITWISE,
            ...PhpTokenType::OPERATOR_COMPARISON,
            ...PhpTokenType::OPERATOR_TERNARY,
            ...PhpTokenType::OPERATOR_ERROR_CONTROL,
            ...PhpTokenType::OPERATOR_INCREMENT_DECREMENT,
            ...PhpTokenType::OPERATOR_LOGICAL,
            ...PhpTokenType::OPERATOR_STRING,
            ...PhpTokenType::OPERATOR_INSTANCEOF
        );

    }

    public function isUnaryOperator(): bool
    {
        if ($this->isOneOf(
            "~",
            ...PhpTokenType::OPERATOR_ERROR_CONTROL,
            ...PhpTokenType::OPERATOR_INCREMENT_DECREMENT,
            "!",
        ))
        {
            return true;
        }

        // TODO: check if this is a unary "+" or "-", e.g. "$a = -$b"

        return false;
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }
}

