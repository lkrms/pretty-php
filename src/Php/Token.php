<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Pretty\WhitespaceType;

class Token implements JsonSerializable
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
     * @var Token[]
     */
    public $BracketStack;

    /**
     * @var string
     */
    public $TypeName;

    /**
     * @var Token|null
     */
    public $OpenedBy;

    /**
     * @var Token|null
     */
    public $ClosedBy;

    /**
     * @var string[]
     */
    public $Tags = [];

    /**
     * @var int
     */
    public $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * @var int
     */
    public $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * @var Token|null
     */
    private $_prev;

    /**
     * @var Token|null
     */
    private $_next;

    /**
     * @param array|string $token
     * @param Token[] $bracketStack
     */
    public function __construct(
        int $index,
        $token,
        ?Token $prev,
        int $bracketLevel,
        array $bracketStack,
        array $plainTokens
    ) {
        if (is_array($token))
        {
            list ($this->Type, $this->Code, $this->Line) = $token;
            if ($this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS))
            {
                $code = rtrim($this->Code);
            }
            elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS))
            {
                $code = ltrim($this->Code);
            }
            elseif (!$this->isOneOf(...TokenType::DO_NOT_MODIFY))
            {
                $code = trim($this->Code);
            }
            if (isset($code) && $code !== $this->Code)
            {
                $this->Code   = $code;
                $this->Tags[] = "trimmed";
            }
        }
        else
        {
            $this->Type = $this->Code = $token;

            // To get the original line number, add the last known line number
            // to the number of newlines since. Use `$plainTokens` because there
            // may have been whitespace between `$prev` and `$this`.
            $lastLine = 1;
            $code     = "";
            $i        = $index;

            while ($i--)
            {
                $plain = $plainTokens[$i];
                $code  = ($plain[1] ?? $plain) . $code;
                if (is_array($plain))
                {
                    $lastLine = $plain[2];
                    break;
                }
            }

            $this->Line = $lastLine + substr_count($code, "\n");
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

    /**
     * @param Token[] $tokens
     * @param int|string $type
     */
    public static function has(array $tokens, $type): bool
    {
        foreach ($tokens as $token)
        {
            if ($token->is($type))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Token[] $tokens
     * @param int|string ...$types
     */
    public static function hasOneOf(array $tokens, ...$types): bool
    {
        foreach ($tokens as $token)
        {
            if ($token->isOneOf(...$types))
            {
                return true;
            }
        }

        return false;
    }

    public function jsonSerialize(): array
    {
        $a = get_object_vars($this);
        foreach ($a["BracketStack"] as &$t)
        {
            $t = $t->Index;
        }
        $a["OpenedBy"] = $a["OpenedBy"]->Index ?? null;
        $a["ClosedBy"] = $a["ClosedBy"]->Index ?? null;
        $a["_prev"]    = $a["_prev"]->Index ?? null;
        $a["_next"]    = $a["_next"]->Index ?? null;
        if (empty($a["Tags"]))
        {
            unset($a["Tags"]);
        }
        return $a;
    }

    public function prev(int $offset = 1): Token
    {
        $prev = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $prev = $prev->_prev ?? null;
        }

        return ($prev ?: new NullToken());
    }

    public function next(int $offset = 1): Token
    {
        $next = $this;

        for ($i = 0; $i < $offset; $i++)
        {
            $next = $next->_next ?? null;
        }

        return ($next ?: new NullToken());
    }

    /**
     * @return Token[]
     */
    public function outer(): array
    {
        $current = $this->OpenedBy ?: $this;
        $last    = $this->ClosedBy ?: $this;

        $tokens[] = $current;
        while ($current !== $last)
        {
            $tokens[] = $current = $current->next();
        }

        return $tokens;
    }

    /**
     * @return Token[]
     */
    public function sinceLastStatement(): array
    {
        $current = $this->prev();
        while (!$current->startsNewStatement() && !($current instanceof NullToken))
        {
            if ($current->isCloseBracket())
            {
                $tokens  = array_merge($tokens ?? [], array_reverse($current->outer()));
                $current = $current->OpenedBy->prev();
                continue;
            }
            $tokens[] = $current;
            $current  = $current->prev();
        }

        return array_reverse($tokens ?? []);
    }

    /**
     * @todo Reimplement after building keyword token list
     * @return Token[]
     */
    public function wordsSinceLastStatement(): array
    {
        foreach ($this->sinceLastStatement() as $token)
        {
            if ($token->isOpenBracket())
            {
                break;
            }
            $tokens[] = $token;
        }
        return $tokens ?? [];
    }

    public function hasNewlineBefore(): bool
    {
        return (bool)(($this->WhitespaceBefore | $this->prev()->WhitespaceAfter) & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool)(($this->WhitespaceAfter | $this->next()->WhitespaceBefore) & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasWhitespaceBefore(): bool
    {
        return (bool)($this->WhitespaceBefore | $this->prev()->WhitespaceAfter);
    }

    public function hasWhitespaceAfter(): bool
    {
        return (bool)($this->WhitespaceAfter | $this->next()->WhitespaceBefore);
    }

    public function is($type): bool
    {
        return $this->Type === $type;
    }

    public function isOneOf(...$types): bool
    {
        return in_array($this->Type, $types, true);
    }

    public function startsNewStatement(): bool
    {
        return $this->isOneOf(";", "{", "}");
    }

    public function isOpenBracket(): bool
    {
        return $this->isOneOf("(", "[", "{", T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES);
    }

    public function isCloseBracket(): bool
    {
        return $this->isOneOf(")", "]", "}");
    }

    public function isOneLineComment(): bool
    {
        return $this->is(T_COMMENT) && preg_match('@^(//|#)@', $this->Code);
    }

    public function isOperator()
    {
        // TODO: return false if part of a declaration

        // OPERATOR_EXECUTION is excluded because for formatting purposes,
        // commands between backticks are equivalent to double-quoted strings
        return $this->isOneOf(
            ...TokenType::OPERATOR_ARITHMETIC,
            ...TokenType::OPERATOR_ASSIGNMENT,
            ...TokenType::OPERATOR_BITWISE,
            ...TokenType::OPERATOR_COMPARISON,
            ...TokenType::OPERATOR_TERNARY,
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT,
            ...TokenType::OPERATOR_LOGICAL,
            ...TokenType::OPERATOR_STRING,
            ...TokenType::OPERATOR_INSTANCEOF
        );

    }

    public function isUnaryOperator(): bool
    {
        if ($this->isOneOf(
            "~",
            "!",
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
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

    public function isDeclaration(): bool
    {
        return self::hasOneOf($this->wordsSinceLastStatement(), ...TokenType::DECLARATION);
    }

    public function render(): string
    {
        if ($this->isOneOf(...TokenType::DO_NOT_MODIFY))
        {
            return $this->Code;
        }

        $code = $this->Code;
        if (!$this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS))
        {
            $code = WhitespaceType::toWhitespace($this->WhitespaceBefore | $this->prev()->WhitespaceAfter) . $code;
        }
        if (is_null($this->_next) && !$this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS))
        {
            $code .= WhitespaceType::toWhitespace($this->WhitespaceAfter | WhitespaceType::LINE);
        }

        return $code;
    }
}
