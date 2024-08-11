<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Exception\InvalidTokenException;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;
use Closure;

trait NavigableTokenTrait
{
    /**
     * The token's position (0-based) in an array of token objects
     */
    public int $Index = -1;

    public ?Token $Prev = null;
    public ?Token $Next = null;
    public ?Token $PrevCode = null;
    public ?Token $NextCode = null;
    public ?Token $PrevSibling = null;
    public ?Token $NextSibling = null;
    public ?Token $Statement = null;
    public ?Token $EndStatement = null;

    /**
     * The token at the start of the token's expression, or false if the token
     * is an expression delimiter
     *
     * @var Token|false|null
     */
    public $Expression = null;

    /**
     * The token at the end of the token's expression
     *
     * If the token is an expression delimiter and {@see Token::$NextSibling} is
     * the token at the start of an expression, {@see Token::$EndExpression} is
     * the token at the end of that expression, otherwise it is the token
     * itself.
     */
    public ?Token $EndExpression = null;

    public ?Token $OpenedBy = null;
    public ?Token $ClosedBy = null;
    public ?Token $Parent = null;
    public int $Depth = 0;
    public ?Token $OpenTag = null;
    public ?Token $CloseTag = null;
    public ?Token $String = null;
    public ?Token $StringClosedBy = null;
    public ?Token $Heredoc = null;
    /** @var int-mask-of<TokenFlag::*> */
    public int $Flags = 0;

    /**
     * @var array<TokenData::*,mixed>
     * @phpstan-var array{string,int,Token}
     */
    public array $Data;

    public ?Token $OtherTernaryOperator = null;
    public ?Token $ChainOpenedBy = null;

    /**
     * True if the token is a T_NULL
     *
     * @todo Remove this property
     */
    public bool $IsNull = false;

    /**
     * The original content of the token after expanding tabs if CollectColumn
     * found tabs to expand
     */
    public ?string $ExpandedText = null;

    /**
     * The original content of the token if its content was changed by setText()
     */
    public ?string $OriginalText = null;

    /**
     * The formatter to which the token belongs
     *
     * @readonly
     */
    public Formatter $Formatter;

    /**
     * Token type index
     *
     * @readonly
     */
    public TokenTypeIndex $Idx;

    /**
     * @return static[]
     */
    public static function tokenize(string $code, int $flags = 0, Filter ...$filters): array
    {
        return self::filter(parent::tokenize($code, $flags), ...$filters);
    }

    /**
     * Same as tokenize(), but returns lower-cost GenericToken instances
     *
     * @return GenericToken[]
     */
    public static function tokenizeForComparison(string $code, int $flags = 0, Filter ...$filters): array
    {
        return self::filter(GenericToken::tokenize($code, $flags), ...$filters);
    }

    /**
     * @template T of GenericToken
     *
     * @param T[] $tokens
     * @return T[]
     */
    private static function filter(array $tokens, Filter ...$filters): array
    {
        if (!$tokens || !$filters) {
            return $tokens;
        }

        foreach ($filters as $filter) {
            $tokens = $filter->filterTokens($tokens);
        }

        return $tokens;
    }

    /**
     * Check if the token is a brace that delimits a code block
     *
     * Returns `false` for braces in:
     *
     * - expressions (e.g. `$object->{$property}`)
     * - strings (e.g. `"{$object->property}"`)
     * - alias/import statements (e.g. `use A\{B, C}`)
     *
     * Returns `true` for braces in:
     *
     * - trait adaptations
     * - `match` expressions (if `$orMatch` is `true`)
     */
    final public function isStructuralBrace(bool $orMatch = false): bool
    {
        /** @var Token $this */
        $current = $this->OpenedBy === null ? $this : $this->OpenedBy;

        // Exclude T_CURLY_OPEN and T_DOLLAR_OPEN_CURLY_BRACES
        if ($current->id !== \T_OPEN_BRACE) {
            return false;
        }

        if (
            $current->PrevSibling
            && $current->PrevSibling->PrevSibling
            && $current->PrevSibling->PrevSibling->id === \T_MATCH
        ) {
            return $orMatch;
        }

        /** @var Token */
        $lastInner = $current->ClosedBy->PrevCode;

        // Braces cannot be empty in expression (dereferencing) contexts, but
        // trait adaptation braces can be
        return $lastInner === $current                                                  // `{}`
            || $lastInner->id === \T_SEMICOLON                                          // `{ statement; }`
            || $lastInner->id === \T_COLON                                              // `{ label: }`
            || ($lastInner->Flags & TokenFlag::STATEMENT_TERMINATOR)                    /* `{ statement ?>...<?php }` */
            || ($lastInner->id === \T_CLOSE_BRACE && $lastInner->isStructuralBrace());  // `{ { statement; } }`
    }

    /**
     * Check if the token is a T_WHILE that belongs to a do ... while structure
     */
    final public function isWhileAfterDo(): bool
    {
        /** @var Token $this */
        if (
            $this->id !== \T_WHILE
            || !$this->PrevSibling
            || !$this->PrevSibling->PrevSibling
        ) {
            return false;
        }

        // Test for enclosed and unenclosed bodies, e.g.
        // - `do { ... } while ();`
        // - `do statement; while ();`

        if ($this->PrevSibling->PrevSibling->id === \T_DO) {
            return true;
        }

        // Starting from the previous sibling because `do` immediately before
        // `while` cannot be part of the same structure, look for a previous
        // `T_DO` the token could form a control structure with
        $do = $this->PrevSibling
                   ->prevSiblingFrom($this->Idx->T_DO)
                   ->orNull();
        if (!$do) {
            return false;
        }
        // Now look for its `T_WHILE` counterpart, starting from the first token
        // it could be and allowing for nested unenclosed `T_WHILE` loops, e.g.
        // `do while () while (); while ();`
        $tokens = $do->NextSibling->NextSibling->collectSiblings($this);
        foreach ($tokens as $token) {
            if (
                $token->id === \T_WHILE
                && $token->PrevSibling->PrevSibling->id !== \T_WHILE
            ) {
                return $token === $this;
            }
        }
        return false;
    }

    /**
     * Get a new T_NULL token
     *
     * @return Token
     */
    public function null()
    {
        $token = new static(\T_NULL, '');
        $token->IsNull = true;
        if (isset($this->Idx)) {
            $token->Idx = $this->Idx;
        }
        return $token;
    }

    /**
     * Get the token if it is not null, otherwise get a fallback token
     *
     * @param Token|(Closure(): Token) $token
     * @return Token
     */
    public function or($token)
    {
        if (!$this->IsNull) {
            return $this;
        }
        if ($token instanceof Closure) {
            return $token();
        }
        return $token;
    }

    /**
     * Get the token if it is not null
     *
     * Returns `null` if the token is a null token.
     *
     * @return $this|null
     */
    public function orNull()
    {
        if ($this->IsNull) {
            return null;
        }
        return $this;
    }

    /**
     * Get the token if it is not null, otherwise throw an InvalidTokenException
     *
     * @return $this|never
     */
    public function orThrow()
    {
        if ($this->IsNull) {
            throw new InvalidTokenException($this);
        }
        return $this;
    }

    public function getTokenName(): ?string
    {
        return parent::getTokenName() ?: CustomToken::toName($this->id);
    }

    /**
     * Update the content of the token, setting OriginalText if needed
     *
     * @return $this
     */
    final public function setText(string $text)
    {
        if ($this->text !== $text) {
            if ($this->OriginalText === null) {
                $this->OriginalText = $this->text;
            }
            $this->text = $text;
        }
        return $this;
    }

    /**
     * Get the previous token that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function prevFrom(array $index)
    {
        $t = $this;
        while ($t = $t->Prev) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next token that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function nextFrom(array $index)
    {
        $t = $this;
        while ($t = $t->Next) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function prevSiblingFrom(array $index)
    {
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is one of the types in an index
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function nextSiblingFrom(array $index)
    {
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($index[$t->id]) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Skip to the next sibling that is not one of the types in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function skipSiblingsFrom(array $index)
    {
        $t = $this->Flags & TokenFlag::CODE ? $this : $this->NextCode;
        while ($t && $index[$t->id]) {
            $t = $t->NextSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Skip to the previous sibling that is not one of the types in an index
     *
     * The token returns itself if it satisfies the criteria.
     *
     * @param array<int,bool> $index
     * @return Token
     */
    final public function skipPrevSiblingsFrom(array $index)
    {
        $t = $this->Flags & TokenFlag::CODE ? $this : $this->PrevCode;
        while ($t && $index[$t->id]) {
            $t = $t->PrevSibling;
        }
        return $t ?: $this->null();
    }

    /**
     * Get the previous token that is one of the listed types
     *
     * @return Token
     */
    final public function prevOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->Prev) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next token that is one of the listed types
     *
     * @return Token
     */
    final public function nextOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->Next) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the previous sibling that is one of the listed types
     *
     * @return Token
     */
    final public function prevSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->PrevSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the next sibling that is one of the listed types
     *
     * @return Token
     */
    final public function nextSiblingOf(int $type, int ...$types)
    {
        array_unshift($types, $type);
        $t = $this;
        while ($t = $t->NextSibling) {
            if ($t->is($types)) {
                return $t;
            }
        }
        return $this->null();
    }

    /**
     * Get the first reachable token
     *
     * @return Token
     */
    final public function first()
    {
        $current = $this;
        while ($current->Parent) {
            $current = $current->Parent;
        }
        while ($current->Prev) {
            $current = $current->PrevSibling ?: $current->Prev;
        }
        return $current;
    }

    /**
     * Get the last reachable token
     *
     * @return Token
     */
    final public function last()
    {
        $current = $this;
        while ($current->Parent) {
            $current = $current->Parent;
        }
        while ($current->Next) {
            $current = $current->NextSibling ?: $current->Next;
        }
        return $current;
    }

    /**
     * Get the token at the beginning of the token's original line
     *
     * @return Token
     */
    final public function startOfOriginalLine()
    {
        $current = $this;
        while (($current->Prev->line ?? null) === $this->line) {
            $current = $current->Prev;
        }
        return $current;
    }

    /**
     * Get the token at the end of the token's original line
     *
     * @return Token
     */
    final public function endOfOriginalLine()
    {
        $current = $this;
        while (($current->Next->line ?? null) === $this->line) {
            $current = $current->Next;
        }
        return $current;
    }

    /**
     * Get the next sibling via token traversal, without accounting for PHP's
     * alternative syntax
     *
     * @return Token
     */
    final public function nextSimpleSibling(int $offset = 1)
    {
        $depth = 0;
        $t = $this;
        while ($t->Next) {
            if ($this->Idx->OpenBracket[$t->id]) {
                $depth++;
            } elseif ($this->Idx->CloseBracket[$t->id]) {
                $depth--;
            }
            $t = $t->Next;
            if (!$depth) {
                $offset--;
                if (!$offset) {
                    return $t;
                }
            }
        }
        return $this->null();
    }

    /**
     * Throw an InvalidTokenException
     *
     * @return never
     */
    final protected function throw(): void
    {
        throw new InvalidTokenException($this);
    }
}
