<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

/**
 * @property-read int $Index
 * @property-read int|string $Type
 * @property-read int $Line
 * @property-read string $TypeName
 * @property Token|null $OpenTag
 * @property Token|null $CloseTag
 * @property Token|null $OpenedBy
 * @property Token|null $ClosedBy
 * @property string $Code
 * @property int $PreIndent
 * @property int $Indent
 * @property int $Deindent
 * @property int $HangingIndent
 * @property bool $PinToCode
 * @property int $LinePadding
 * @property int $LineUnpadding
 * @property int $Padding
 * @property int $WhitespaceBefore
 * @property int $WhitespaceAfter
 * @property int $WhitespaceMaskPrev
 * @property int $WhitespaceMaskNext
 */
class Token implements JsonSerializable
{
    private const ALLOW_READ = [
        'Index',
        'Type',
        'Line',
        'TypeName',
        'OpenTag',
        'CloseTag',
        'OpenedBy',
        'ClosedBy',
    ];

    private const ALLOW_WRITE = [
        'Code',
        'PreIndent',
        'Indent',
        'Deindent',
        'HangingIndent',
        'PinToCode',
        'LinePadding',
        'LineUnpadding',
        'Padding',
        'WhitespaceBefore',
        'WhitespaceAfter',
        'WhitespaceMaskPrev',
        'WhitespaceMaskNext',
    ];

    private const ALLOW_WRITE_IF_NULL = [
        'OpenedBy',
        'ClosedBy',
    ];

    /**
     * @var int
     */
    protected $Index;

    /**
     * @var int|string
     */
    protected $Type;

    /**
     * @var string
     */
    protected $Code;

    /**
     * @var int
     */
    protected $Line;

    /**
     * @var Token[]
     */
    public $BracketStack;

    /**
     * @var string
     */
    protected $TypeName;

    /**
     * @var Token|null
     */
    private $OpenTag;

    /**
     * @var Token|null
     */
    private $CloseTag;

    /**
     * @var Token|null
     */
    private $OpenedBy;

    /**
     * @var Token|null
     */
    private $ClosedBy;

    /**
     * @var bool
     */
    public $IsCode = false;

    /**
     * @var array<array<string,mixed>>
     */
    public $Log = [];

    /**
     * @var int
     */
    private $PreIndent = 0;

    /**
     * @var int
     */
    private $Indent = 0;

    /**
     * @var int
     */
    private $Deindent = 0;

    /**
     * @var int
     */
    private $HangingIndent = 0;

    /**
     * @var bool
     */
    public $IsHangingParent;

    /**
     * @var bool
     */
    public $IsOverhangingParent;

    /**
     * Tokens responsible for each level of hanging indentation applied to the
     * token
     *
     * @var Token[]
     */
    public $IndentStack = [];

    /**
     * Parent tokens associated with hanging indentation levels applied to the
     * token
     *
     * @var Token[]
     */
    public $IndentParentStack = [];

    /**
     * The context of each level of hanging indentation applied to the token
     *
     * Only used by
     * {@see \Lkrms\Pretty\Php\Rule\AddHangingIndentation::processToken()}.
     *
     * @var array<array<Token[]|Token>>
     */
    public $IndentBracketStack = [];

    /**
     * Entries represent parent tokens and the collapsible ("overhanging")
     * levels of indentation applied to the token on their behalf
     *
     * Parent token index => collapsible indentation levels applied
     *
     * @var array<int,int>
     */
    public $OverhangingParents = [];

    /**
     * @var bool
     */
    private $PinToCode = false;

    /**
     * @var int
     */
    private $LinePadding = 0;

    /**
     * @var int
     */
    private $LineUnpadding = 0;

    /**
     * @var int
     */
    private $Padding = 0;

    /**
     * @var Token|null
     */
    public $AlignedWith;

    /**
     * @var Token|null
     */
    public $ChainOpenedBy;

    /**
     * @var Token|null
     */
    public $HeredocOpenedBy;

    /**
     * @var Token|null
     */
    public $StringOpenedBy;

    /**
     * @var bool
     */
    protected $IsNull = false;

    /**
     * @var bool
     */
    protected $IsVirtual = true;

    /**
     * @var int
     */
    private $WhitespaceBefore = WhitespaceType::NONE;

    /**
     * @var int
     */
    private $WhitespaceAfter = WhitespaceType::NONE;

    /**
     * @var int
     */
    private $WhitespaceMaskPrev = WhitespaceType::ALL;

    /**
     * @var int
     */
    private $WhitespaceMaskNext = WhitespaceType::ALL;

    /**
     * @var Formatter
     */
    protected $Formatter;

    /**
     * @var Token|null
     */
    private $_prev;

    /**
     * @var Token|null
     */
    private $_next;

    /**
     * @var array<int,bool>
     */
    private $IsStartOfExpression = [];

    /**
     * @var array<int,bool>
     */
    private $IsEndOfExpression = [];

    /**
     * @var array<int,Token[]>
     */
    private $EndOfExpression = [];

    /**
     * @var array<int,Token[]>
     */
    private $StartOfExpression = [];

    /**
     * @var bool
     */
    public $IsStartOfDeclaration = false;

    /**
     * @var bool
     */
    public $IsCloseTagStatementTerminator = false;

    /**
     * @param string|array{0:int,1:string,2:int} $token
     * @param Token[] $bracketStack
     */
    public function __construct(int $index, $token, ?Token $prev, array $bracketStack, Formatter $formatter)
    {
        if (is_array($token)) {
            [$this->Type, $this->Code, $this->Line] = $token;
            if ($this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS)) {
                $this->Code = rtrim($this->Code);
            } elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS)) {
                $this->Code = ltrim($this->Code);
            } elseif (!$this->isOneOf(...TokenType::DO_NOT_MODIFY)) {
                $this->Code = trim($this->Code);
            }
        } else {
            $this->Type = $this->Code = $token;

            // To get the original line number, add the last known line number
            // to the number of newlines since, using `$formatter->PlainTokens`
            // in case there was whitespace between `$prev` and `$this`
            $lastLine = 1;
            $code     = '';
            $i        = $index;

            while ($i--) {
                $plain = $formatter->PlainTokens[$i];
                $code  = ($plain[1] ?? $plain) . $code;
                if (is_array($plain)) {
                    $lastLine = $plain[2];
                    break;
                }
            }

            $this->Line = $lastLine + substr_count($code, "\n");
        }

        $this->Index        = $index;
        $this->BracketStack = $bracketStack;
        $this->TypeName     = is_int($this->Type) ? token_name($this->Type) : $this->Type;
        $this->Formatter    = $formatter;
        $this->IsVirtual    = false;

        if (!$this->isOneOf(...TokenType::NOT_CODE)) {
            $this->IsCode = true;
        }

        if ($this->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) {
            $this->OpenTag = $this;
        }

        if (!$prev) {
            return;
        }

        $this->_prev = $prev;
        $prev->_next = $this;

        /**
         * Intended outcome:
         *
         * ```php
         * <?php            // OpenTag = itself, CloseTag = Token
         * $foo = 'bar';    // OpenTag = Token,  CloseTag = Token
         * ?>               // OpenTag = Token,  CloseTag = itself
         * ```
         *
         * `CloseTag` is `null` if there's no T_CLOSE_TAG
         */
        if (!$this->OpenTag && $prev->OpenTag && !$prev->CloseTag) {
            $this->OpenTag = $prev->OpenTag;
            if ($this->is(T_CLOSE_TAG)) {
                $t = $this;
                do {
                    $t->CloseTag = $this;
                    $t           = $t->_prev;
                } while ($t && $t->OpenTag === $this->OpenTag);

                // TODO: use BracketStack for a more robust assessment?
                $t = $prev;
                while ($t->isOneOf(...TokenType::COMMENT)) {
                    $t = $t->prev();
                }
                if ($t->Index > $this->OpenTag->Index &&
                        !$t->isOneOf('(', ',', ':', ';', '[', '{')) {
                    $this->IsCode                        = true;
                    $this->IsCloseTagStatementTerminator = true;
                }
            }
        }
    }

    /**
     * Update _prev and _next as needed to insert $this between $token and its
     * current predecessor
     *
     */
    final protected function insertAt(Token $token): void
    {
        $this->_prev        = $token->_prev;
        $this->_next        = $token;
        $this->_prev->_next = $this;
        $this->_next->_prev = $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $a = get_object_vars($this);
        unset(
            $a['Index'],
            $a['Type'],
            $a['BracketStack'],
            $a['OpenTag'],
            $a['CloseTag'],
            $a['IsCode'],
            $a['IndentStack'],
            $a['IndentParentStack'],
            $a['IndentBracketStack'],
            $a['OverhangingParents'],
            $a['AlignedWith'],
            $a['ChainOpenedBy'],
            $a['HeredocOpenedBy'],
            $a['StringOpenedBy'],
            $a['Formatter'],
            $a['_prev'],
            $a['_next'],
            $a['EndOfExpression'],
            $a['StartOfExpression'],
        );
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($a['WhitespaceBefore']);
        $a['WhitespaceAfter']  = WhitespaceType::toWhitespace($a['WhitespaceAfter']);
        if (empty($a['Log'])) {
            unset($a['Log']);
        }
        array_walk_recursive($a, function (&$value) {
            if ($value instanceof Token) {
                $value = $value->Index . ':' . Convert::ellipsize($value->Code, 20);
            }
        });

        return $a;
    }

    final public function canonical(): Token
    {
        return $this->OpenedBy ?: $this;
    }

    final public function wasFirstOnLine(): bool
    {
        if ($this->IsVirtual) {
            return false;
        }
        do {
            $prev = $this->prev();
            if ($prev->IsNull) {
                return true;
            }
        } while ($prev->IsVirtual);
        $prevPlain    = $this->Formatter->PlainTokens[$prev->Index];
        $prevCode     = $prevPlain[1] ?? $prevPlain;
        $prevNewlines = substr_count($prevCode, "\n");

        return $this->Line > ($prev->Line + $prevNewlines) ||
            $prevCode[-1] === "\n";
    }

    final public function wasLastOnLine(): bool
    {
        if ($this->IsVirtual) {
            return false;
        }
        do {
            $next = $this->next();
            if ($next->IsNull) {
                return true;
            }
        } while ($next->IsVirtual);
        $plain    = $this->Formatter->PlainTokens[$this->Index];
        $code     = $plain[1] ?? $plain;
        $newlines = substr_count($code, "\n");

        return ($this->Line + $newlines) < $next->Line ||
            $code[-1] === "\n";
    }

    public function wasBetweenTokensOnLine(bool $canHaveInnerNewline = false): bool
    {
        return !$this->wasFirstOnLine() &&
            !$this->wasLastOnLine() &&
            ($canHaveInnerNewline || !$this->hasNewline());
    }

    public function prev(int $offset = 1): Token
    {
        $p = $this;
        for ($i = 0; $i < $offset; $i++) {
            $p = $p->_prev ?? null;
        }

        return $p ?: new NullToken();
    }

    public function next(int $offset = 1): Token
    {
        $n = $this;
        for ($i = 0; $i < $offset; $i++) {
            $n = $n->_next ?? null;
        }

        return $n ?: new NullToken();
    }

    /**
     * @param int|string ...$types
     */
    public function prevWhile(...$types): TokenCollection
    {
        return $this->_prevWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withPrevWhile(...$types): TokenCollection
    {
        return $this->_prevWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $p = $with ? $this : $this->prev();
        while ($p->isOneOf(...$types)) {
            $c[] = $p;
            $p   = $p->prev();
        }

        return $c;
    }

    /**
     * @param int|string ...$types
     */
    public function nextWhile(...$types): TokenCollection
    {
        return $this->_nextWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withNextWhile(...$types): TokenCollection
    {
        return $this->_nextWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $n = $with ? $this : $this->next();
        while ($n->isOneOf(...$types)) {
            $c[] = $n;
            $n   = $n->next();
        }

        return $c;
    }

    public function prevCode(int $offset = 1): Token
    {
        $p = $this;
        for ($i = 0; $i < $offset; $i++) {
            do {
                $p = $p->_prev ?? null;
            } while ($p && !$p->IsCode);
        }

        return $p ?: new NullToken();
    }

    public function nextCode(int $offset = 1): Token
    {
        $n = $this;
        for ($i = 0; $i < $offset; $i++) {
            do {
                $n = $n->_next ?? null;
            } while ($n && !$n->IsCode);
        }

        return $n ?: new NullToken();
    }

    /**
     * @param int|string ...$types
     */
    public function prevCodeWhile(...$types): TokenCollection
    {
        return $this->_prevCodeWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withPrevCodeWhile(...$types): TokenCollection
    {
        return $this->_prevCodeWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevCodeWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $p = $with ? $this : $this->prevCode();
        while ($p->isOneOf(...$types)) {
            $c[] = $p;
            $p   = $p->prevCode();
        }

        return $c;
    }

    /**
     * @param int|string ...$types
     */
    public function nextCodeWhile(...$types): TokenCollection
    {
        return $this->_nextCodeWhile(false, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    public function withNextCodeWhile(...$types): TokenCollection
    {
        return $this->_nextCodeWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextCodeWhile(bool $with, ...$types): TokenCollection
    {
        $c = new TokenCollection();
        $n = $with ? $this : $this->nextCode();
        while ($n->isOneOf(...$types)) {
            $c[] = $n;
            $n   = $n->nextCode();
        }

        return $c;
    }

    public function prevSibling(int $offset = 1): Token
    {
        $prev = $_this = $this->canonical();
        for ($i = 0; $i < $offset; $i++) {
            do {
                $prev = $prev->_prev ?? null;
            } while ($prev && !$prev->IsCode);
            if ($prev->OpenedBy ?? null) {
                $prev = $prev->OpenedBy;
            }
            if (($prev->BracketStack ?? null) !== $_this->BracketStack) {
                $prev = null;
            }
            if (!$prev) {
                break;
            }
        }

        return $prev ?: new NullToken();
    }

    public function nextSibling(int $offset = 1): Token
    {
        $next = $_this = $this->canonical();
        for ($i = 0; $i < $offset; $i++) {
            if ($next->ClosedBy ?? null) {
                $next = $next->ClosedBy;
            }
            do {
                $next = $next->_next ?? null;
            } while ($next && !$next->IsCode);
            if (($next->BracketStack ?? null) !== $_this->BracketStack ||
                    $next->isCloseBracket()) {
                $next = null;
            }
            if (!$next) {
                break;
            }
        }

        return $next ?: new NullToken();
    }

    /**
     * @param int|string ...$types
     */
    public function prevSiblingOf(...$types): ?Token
    {
        $prev = $this;
        do {
            $prev = $prev->prevSibling();
            if ($prev->isNull()) {
                return null;
            }
        } while (!$prev->isOneOf(...$types));

        return $prev;
    }

    /**
     * @param int|string ...$types
     */
    public function nextSiblingOf(...$types): ?Token
    {
        $next = $this;
        do {
            $next = $next->nextSibling();
            if ($next->isNull()) {
                return null;
            }
        } while (!$next->isOneOf(...$types));

        return $next;
    }

    /**
     * Collect the token's siblings up to but not including the last that isn't
     * one of the listed types
     *
     * Tokens are collected in order from the closest sibling to the farthest.
     *
     * @param int|string ...$types
     */
    public function prevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the last that
     * isn't one of the listed types
     *
     * Tokens are collected in order from the closest sibling to the farthest.
     *
     * @param int|string ...$types
     */
    public function withPrevSiblingsWhile(...$types): TokenCollection
    {
        return $this->_prevSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _prevSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $prev   = $includeToken ? $this : $this->prevSibling();
        while ($prev->isOneOf(...$types)) {
            $tokens[] = $prev;
            $prev     = $prev->prevSibling();
        }

        return $tokens;
    }

    /**
     * Collect the token's siblings up to but not including the first that isn't
     * one of the listed types
     *
     * @param int|string ...$types
     */
    public function nextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(false, ...$types);
    }

    /**
     * Collect the token and its siblings up to but not including the first that
     * isn't one of the listed types
     *
     * @param int|string ...$types
     */
    public function withNextSiblingsWhile(...$types): TokenCollection
    {
        return $this->_nextSiblingsWhile(true, ...$types);
    }

    /**
     * @param int|string ...$types
     */
    private function _nextSiblingsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $next   = $includeToken ? $this : $this->nextSibling();
        while ($next->isOneOf(...$types)) {
            $tokens[] = $next;
            $next     = $next->nextSibling();
        }

        return $tokens;
    }

    public function parent(): Token
    {
        $current = $this->canonical();

        return end($current->BracketStack) ?: new NullToken();
    }

    /**
     * Collect the token's parents up to but not including the first that isn't
     * one of the listed types
     *
     * @param bool $includeToken If `true`, collect the token itself. If it
     * isn't one of the listed types, an empty collection is returned.
     * @param int|string ...$types
     */
    public function parentsWhile(bool $includeToken = false, ...$types): TokenCollection
    {
        $tokens = new TokenCollection();
        $next   = $this->canonical();
        $next   = $includeToken ? $next : $next->parent();
        while ($next->isOneOf(...$types)) {
            $tokens[] = $next;
            $next     = $next->parent();
        }

        return $tokens;
    }

    public function outer(): TokenCollection
    {
        return $this->canonical()->collect($this->ClosedBy ?: $this);
    }

    public function inner(): TokenCollection
    {
        return $this->canonical()->next()->collect(($this->ClosedBy ?: $this)->prev());
    }

    public function innerSiblings(): TokenCollection
    {
        return $this->canonical()->nextCode()->collectSiblings(($this->ClosedBy ?: $this)->prevCode());
    }

    public function isCode(): bool
    {
        return $this->IsCode;
    }

    public function isNull(): bool
    {
        return $this->IsNull;
    }

    public function isVirtual(): bool
    {
        return $this->IsVirtual;
    }

    public function startOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineBefore() && !($prev = $current->prev())->isNull()) {
            $current = $prev;
        }

        return $current;
    }

    public function endOfLine(): Token
    {
        $current = $this;
        while (!$current->hasNewlineAfter() && !($next = $current->next())->isNull()) {
            $current = $next;
        }

        return $current;
    }

    /**
     * Get the number of characters since the closest alignment token or the
     * start of the line, whichever is encountered first
     *
     * An alignment token is a token where {@see Token::$AlignedWith} is set.
     * Whitespace at the start of the line is ignored. Code in the token itself
     * is included.
     *
     */
    public function alignmentOffset(): int
    {
        $start = $this->startOfLine();
        $start = $start->collect($this)
                       ->reverse()
                       ->find(fn(Token $t, ?Token $prev, ?Token $next) =>
                           ($t->AlignedWith && $t->AlignedWith !== $this) ||
                               ($next && $next === $this->AlignedWith))
                           ?: $start;

        return mb_strlen($start->collect($this)->render(true))
            - ($start->hasNewlineBefore() ? $start->LineUnpadding : 0);
    }

    public function startOfStatement(): Token
    {
        $current = ($this->is(';') || $this->isCloseTagStatementTerminator()
                ? $this->prevCode()->OpenedBy
                : null)
            ?: $this->canonical();
        while (!($prev = $current->prevCode())->isStatementPrecursor() &&
                !$prev->isNull()) {
            $last    = $current;
            $current = $current->prevSibling();
        }
        $current = $current->isNull() ? ($last ?? $current) : $current;

        return $current;
    }

    public function endOfStatement(): Token
    {
        $current = $this->canonical();
        while (!$current->isStatementTerminator() && !$current->nextCode()->isNull()) {
            $last    = $current;
            $current = $current->nextSibling();
            if (!$current->isStatementTerminator() &&
                    ($prev = $current->prevCode())->isStatementTerminator()) {
                return $prev;
            }
        }
        $current = $current->isNull() ? ($last ?? $current) : $current;

        return $current->ClosedBy ?: $current;
    }

    /**
     * @param int $ignore A bitmask of {@see TokenBoundary} values
     */
    public function isStartOfExpression(int $ignore = TokenBoundary::COMPARISON): bool
    {
        return $this->IsCode &&
            !$this->OpenedBy &&
            !$this->isStatementPrecursor('(', '[', '{') &&
            (($prev = $this->prevCode())->isNull() ||
                $prev->isStatementPrecursor() ||
                $prev->isTernaryOperator() ||
                $prev->isOneOf(
                    ...TokenBoundary::getTokenTypes(TokenBoundary::ALL & ~$ignore),
                    ...TokenType::OPERATOR_DOUBLE_ARROW
                ));
    }

    /**
     * @param int $ignore A bitmask of {@see TokenBoundary} values
     */
    public function startOfExpression(int $ignore = TokenBoundary::COMPARISON): Token
    {
        $current = $this->canonical();
        if ($current->IsStartOfExpression[$ignore] ?? null) {
            return $current;
        }
        // Don't return delimiters as separate expressions
        if ($current->isStatementPrecursor('(', '[', '{')) {
            $current = $current->prevCode();
        }
        $types = [
            ...TokenBoundary::getTokenTypes(TokenBoundary::ALL & ~$ignore),
            ...TokenType::OPERATOR_DOUBLE_ARROW,
        ];
        $ternary = !$this->isTernaryOperator();
        while (!(($prev = $current->prevCode())->isNull() ||
                $prev->isOneOf(...$types) ||
                $prev->isStatementPrecursor() ||
                ($ternary && $prev->isTernaryOperator()))) {
            $current = $current->prevSibling();
        }
        $current->IsStartOfExpression[$ignore] = true;

        return $current;
    }

    /**
     * Get the sibling at the end of the expression to which the token belongs
     *
     * Statement separators do not form part of expressions and are not returned
     * by this method.
     *
     * @param int $ignore A bitmask of {@see TokenBoundary} values
     */
    public function endOfExpression(int $ignore = TokenBoundary::COMPARISON): Token
    {
        $current = $this->canonical();
        if (($current->ClosedBy ?: $current)->IsEndOfExpression[$ignore] ?? null) {
            return $current->ClosedBy ?: $current;
        }
        if (!$current->isTernaryOperator() &&
                ($prev = ($start = $current->startOfExpression($ignore))->prevCode())->is('?') &&
                $prev->isTernaryOperator() &&
                $next = $prev->nextSiblingOf(':')) {
            return $this->_endOfExpression($ignore, $next->prevCode(), $start);
        }
        $ignoreTokens = [];
        if ($current->inSwitchCase()) {
            if ($token = $current->startOfStatement()->nextSiblingOf(':', ';', T_CLOSE_TAG)) {
                $ignoreTokens[] = $token;
            }
        }
        while (!($next = $current->nextSibling())->isNull() &&
            (!$next->isStatementPrecursor('(', '[', '{') ||
                in_array($next, $ignoreTokens, true))) {
            $current = $next;
            if (($prev = $current->prevCode())->isStatementTerminator()) {
                return $this->_endOfExpression($ignore, $prev, $start ?? null);
            }
        }

        return $this->_endOfExpression($ignore, $current->ClosedBy ?: $current, $start ?? null);
    }

    private function _endOfExpression(int $ignore, Token $end, ?Token $start = null): Token
    {
        if ($start) {
            $start->EndOfExpression[$ignore][] = $end;
            $end->StartOfExpression[$ignore][] = $start;
        }
        $end->IsEndOfExpression[$ignore] = true;

        return $end;
    }

    public function adjacent(bool $controlStructureOnly = false): ?Token
    {
        $_this = $this->canonical();
        if (!$_this->isOneOf('(', '[', '{')) {
            return null;
        }
        /** @var Token */
        $outer = $_this->ClosedBy->withNextCodeWhile(')', ']', '}')->last();
        if (($end = $outer->endOfExpression())->isNull() ||
                ($next = $outer->nextCode())->isNull() ||
                ($end->Index <= $next->Index)) {
            return null;
        }
        if (!$controlStructureOnly ||
            ($outer->is(')') &&
                $next->prevSibling(2)->isOneOf(
                    ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES
                ))) {
            return $next;
        }

        return null;
    }

    public function adjacentBeforeNewline(bool $requireAlignedWith = true): ?Token
    {
        $current =
            $this->isOneOf('(', ')', '[', ']', '{', '}')
                ? ($this->ClosedBy ?: $this)
                : $this->parent()->ClosedBy;
        if (!$current) {
            return null;
        }
        $eol   = $this->endOfLine();
        $outer = $current->withNextCodeWhile(')', ']', '}')
                         ->filter(fn(Token $t) => $t->Index <= $eol->Index)
                         ->last();
        if (!$outer ||
                ($next = $outer->nextCode())->isNull() ||
                ($next->Index > $eol->Index) ||
                ($end = $outer->endOfExpression())->isNull() ||
                ($end->Index <= $next->Index)) {
            return null;
        }

        if ($requireAlignedWith &&
            !$next->collect($this->endOfLine())
                  ->find(fn(Token $item) => (bool) $item->AlignedWith)) {
            return null;
        }

        return $next;
    }

    public function withAdjacentBeforeNewline(?Token $from = null, bool $requireAlignedWith = true): TokenCollection
    {
        if ($adjacent = $this->adjacentBeforeNewline($requireAlignedWith)) {
            $until = $adjacent->endOfExpression();
        }

        return ($from ?: $this)->collect($until ?? $this);
    }

    public function declarationParts(): TokenCollection
    {
        return $this->startOfExpression()->withNextSiblingsWhile(...TokenType::DECLARATION_PART);
    }

    public function sinceStartOfStatement(): TokenCollection
    {
        return $this->startOfStatement()->collect($this);
    }

    public function effectiveWhitespaceBefore(): int
    {
        // If this is a comment pinned to the code below it ...
        if ($this->PinToCode &&
            // and the previous token isn't a pinned comment (or if it is, it
            // has a different type and is therefore distinct) ...
            (!$this->prev()->PinToCode || !$this->isSameTypeAs($this->prev())) &&
            // and there are no comments between this and the next code token
            // that aren't pinned or have a different type, then ...
            !count($this->next()
                        ->collect(($next = $this->nextCode())->prev())
                        ->filter(fn(Token $t) => !$t->PinToCode || !$this->isSameTypeAs($t)))) {
            // Combine this token's effective whitespace with the next code
            // token's effective whitespace
            return ($this->_effectiveWhitespaceBefore()
                    | $next->_effectiveWhitespaceBefore())
                & $this->prev()->WhitespaceMaskNext & $this->WhitespaceMaskPrev;
        }
        if (!$this->PinToCode && $this->prev()->PinToCode && $this->IsCode) {
            return ($this->_effectiveWhitespaceBefore() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceBefore();
    }

    private function _effectiveWhitespaceBefore(): int
    {
        return ($this->WhitespaceBefore | $this->prev()->WhitespaceAfter)
            & $this->prev()->WhitespaceMaskNext & $this->WhitespaceMaskPrev;
    }

    public function effectiveWhitespaceAfter(): int
    {
        if ($this->PinToCode && ($next = $this->next())->IsCode && !$next->PinToCode) {
            return ($this->_effectiveWhitespaceAfter() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceAfter();
    }

    private function _effectiveWhitespaceAfter(): int
    {
        return ($this->WhitespaceAfter | $this->next()->WhitespaceBefore)
            & $this->next()->WhitespaceMaskPrev & $this->WhitespaceMaskNext;
    }

    public function hasNewlineBefore(): bool
    {
        return (bool) ($this->effectiveWhitespaceBefore()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool) ($this->effectiveWhitespaceAfter()
            & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasBlankLineBefore(): bool
    {
        return (bool) ($this->effectiveWhitespaceBefore()
            & WhitespaceType::BLANK);
    }

    public function hasBlankLineAfter(): bool
    {
        return (bool) ($this->effectiveWhitespaceAfter()
            & WhitespaceType::BLANK);
    }

    public function hasWhitespaceBefore(): bool
    {
        return (bool) $this->effectiveWhitespaceBefore();
    }

    public function hasWhitespaceAfter(): bool
    {
        return (bool) $this->effectiveWhitespaceAfter();
    }

    public function hasNewline(): bool
    {
        return strpos($this->Code, "\n") !== false;
    }

    /**
     * There's a newline between this token and the next code token
     *
     */
    public function hasNewlineAfterCode(): bool
    {
        return $this->hasNewlineAfter() ||
            (!$this->next()->IsCode &&
                $this->next()
                     ->collect($this->nextCode())
                     ->find(fn(Token $t) =>
                         $t->hasNewlineBefore()));
    }

    /**
     * @param int|string $type
     */
    public function is($type): bool
    {
        return $this->Type === $type;
    }

    /**
     * @param int|string ...$types
     */
    public function isOneOf(...$types): bool
    {
        return in_array($this->Type, $types, true);
    }

    public function prevStatementStart(): Token
    {
        $prev = $this->startOfStatement()->prevSibling();
        while ($prev->is(';')) {
            $prev = $prev->prevSibling();
        }

        return $prev->startOfStatement();
    }

    public function isStatementTerminator(): bool
    {
        return $this->is(';') ||
            $this->isCloseTagStatementTerminator() ||
            ($this->is('}') && $this->isStructuralBrace()) ||
            ($this->OpenedBy && $this->OpenedBy->is(T_ATTRIBUTE));
    }

    /**
     * @param int|string ...$except
     */
    public function isStatementPrecursor(...$except): bool
    {
        if ($except && $this->isOneOf(...$except)) {
            return false;
        }

        return $this->isOneOf('(', ';', '[') ||
            $this->isCloseTagStatementTerminator() ||
            $this->isStructuralBrace() ||
            ($this->OpenedBy && $this->OpenedBy->is(T_ATTRIBUTE)) ||
            ($this->is(',') &&
                (($parent = $this->parent())->isOneOf('(', '[') ||
                    ($parent->is('{') && (!$parent->isStructuralBrace() || $parent->prevSibling(2)->is(T_MATCH))))) ||
            $this->startsAlternativeSyntax() ||
            ($this->is(':') && ($this->inSwitchCase() || $this->inLabel()));
    }

    /**
     * Token is a T_CLOSE_TAG that may also be a statement terminator
     */
    public function isCloseTagStatementTerminator(): bool
    {
        return $this->IsCloseTagStatementTerminator;
    }

    public function inSwitchCase(): bool
    {
        return $this->IsCode &&
            $this->startOfStatement()->isOneOf(T_CASE, T_DEFAULT) &&
            $this->parent()->prevSibling(2)->is(T_SWITCH);
    }

    public function inLabel(): bool
    {
        $current = $this->is(':') ? $this->prevCode() : $this;

        return $current->is(T_STRING) &&
            $current->nextCode()->is(':') &&
            (($prev = $current->prevCode())->isStatementTerminator() || $prev->isNull());
    }

    public function isArrayOpenBracket(): bool
    {
        return $this->is('[') ||
            ($this->is('(') && $this->prevCode()->is(T_ARRAY));
    }

    public function isBrace(): bool
    {
        return $this->is('{') || ($this->is('}') && $this->OpenedBy->is('{'));
    }

    public function isStructuralBrace(): bool
    {
        if (!$this->isBrace()) {
            return false;
        }
        $_this     = $this->canonical();
        $lastInner = $_this->ClosedBy->prevCode();
        $parent    = $_this->parent();

        return ($lastInner === $_this ||                                        // `{}`
                $lastInner->isOneOf(':', ';') ||                                // `{ statement; }`
                $lastInner->isCloseTagStatementTerminator() ||                  /* `{ statement ?>...<?php }` */
                ($lastInner->is('}') && $lastInner->isStructuralBrace())) &&    // `{ { statement; } }`
            !(($parent->isNull() ||
                    $parent->prevSiblingsWhile(...TokenType::DECLARATION_PART)->hasOneOf(T_NAMESPACE)) &&
                $parent->prevSiblingsWhile(...TokenType::DECLARATION_PART)->hasOneOf(T_USE));
    }

    public function isOpenBracket(): bool
    {
        return $this->isOneOf('(', '[', '{', T_ATTRIBUTE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES);
    }

    public function isCloseBracket(): bool
    {
        return $this->isOneOf(')', ']', '}');
    }

    public function startsAlternativeSyntax(): bool
    {
        return $this->is(':') &&
            ((($prev = $this->prevCode())->is(')') && $prev->prevSibling()->isOneOf(
                ...TokenType::CAN_START_ALTERNATIVE_SYNTAX,
                ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITH_EXPRESSION
            )) || $this->prevCode()->isOneOf(
                ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITHOUT_EXPRESSION
            ));
    }

    public function endsAlternativeSyntax(): bool
    {
        // PHP's alternative syntax has no `}` equivalent, so a virtual token is
        // inserted where it should be
        if ($this->is(TokenType::T_VIRTUAL) && $this->OpenedBy && $this->OpenedBy->is(':')) {
            return true;
        }
        if ($this->prev()->is(TokenType::T_VIRTUAL)) {
            return false;
        }

        // Subsequent tokens may not be available yet, so the approach used in
        // startsAlternativeSyntax() won't work here
        $bracketStack = $this->BracketStack;

        return ($opener = array_pop($bracketStack)) && $opener->is(':') && $opener->BracketStack === $bracketStack &&
            $this->isOneOf(
                ...TokenType::ENDS_ALTERNATIVE_SYNTAX,
                ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITH_EXPRESSION,
                ...TokenType::CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITHOUT_EXPRESSION
            );
    }

    public function isOneLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->isOneOf(...TokenType::COMMENT) && !$this->hasNewline()
            : $this->is(T_COMMENT) && preg_match('@^(//|#)@', $this->Code);
    }

    public function isMultiLineComment(bool $anyType = false): bool
    {
        return $anyType
            ? $this->isOneOf(...TokenType::COMMENT) && $this->hasNewline()
            : ($this->is(T_DOC_COMMENT) ||
                ($this->is(T_COMMENT) && preg_match('@^/\*@', $this->Code)));
    }

    public function isOperator(): bool
    {
        return $this->isOneOf(...TokenType::ALL_OPERATOR);
    }

    public function isTernaryOperator(): bool
    {
        // We can't just do this, because nullable method return types tend to
        // be followed by unrelated colons:
        //
        //     return ($this->is('?') && ($this->nextSiblingOf(':'))) ||
        //         ($this->is(':') && ($this->prevSiblingOf('?')));
        //
        switch ($this->Type) {
            case '?':
                while (!($current = ($current ?? $this)->nextSibling())->IsNull &&
                        !$current->isStatementTerminator() &&
                        !$current->prevCode()->isStatementTerminator()) {
                    if ($current->is(':')) {
                        return true;
                    }
                }
                break;

            case ':':
                $current = $this;
                while (!($prev = $current->prevCode())->isStatementPrecursor() &&
                        !$prev->IsNull) {
                    $current = $current->prevSibling();
                    if ($current->is('?')) {
                        return true;
                    }
                }
                break;
        }

        return false;
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isUnaryOperator(): bool
    {
        return $this->isOneOf(
            '~',
            '!',
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT
        ) || (
            $this->isOneOf('+', '-') &&
                $this->inUnaryContext()
        );
    }

    public function inUnaryContext(): bool
    {
        $prev = $this->prevCode();

        return $prev->isOneOf(
            '(',
            ',',
            ';',
            '[',
            '{',
            '}',
            T_CLOSE_TAG,
            ...TokenType::OPERATOR_ARITHMETIC,
            ...TokenType::OPERATOR_ASSIGNMENT,
            ...TokenType::OPERATOR_BITWISE,
            ...TokenType::OPERATOR_COMPARISON,
            ...TokenType::OPERATOR_LOGICAL,
            ...TokenType::OPERATOR_STRING,
            ...TokenType::OPERATOR_DOUBLE_ARROW,
            ...TokenType::CAST,
            ...TokenType::KEYWORD,
        ) || $prev->isTernaryOperator();
    }

    /**
     * @param int|string ...$types
     */
    public function isDeclaration(...$types): bool
    {
        if (!$this->IsCode) {
            return false;
        }
        $parts = $this->declarationParts();

        return $parts->hasOneOf(...TokenType::DECLARATION) &&
            (!$types || $parts->hasOneOf(...$types));
    }

    public function inFunctionDeclaration(): bool
    {
        $parent = $this->parent();

        return $parent->is('(') &&
            ($parent->isDeclaration(T_FUNCTION) || $parent->prevCode()->is(T_FN));
    }

    public function indent(): int
    {
        return $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($indent = $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
            : '';
    }

    public function renderWhitespaceBefore(bool $softTabs = false): string
    {
        $whitespaceBefore = $this->effectiveWhitespaceBefore();

        return WhitespaceType::toWhitespace($whitespaceBefore)
            . ($whitespaceBefore & (WhitespaceType::LINE | WhitespaceType::BLANK)
                ? (($indent = $this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent)
                        ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $indent)
                        : '')
                    . (($padding = $this->LinePadding - $this->LineUnpadding + $this->Padding)
                        ? str_repeat(' ', $padding)
                        : '')
                : ($this->Padding ? str_repeat(' ', $this->Padding) : ''));
    }

    public function render(bool $softTabs = false): string
    {
        if ($this->HeredocOpenedBy) {
            // Render heredocs in one go so we can safely trim empty lines
            if ($this->HeredocOpenedBy !== $this) {
                return '';
            }
            $heredoc = '';
            $current = $this;
            do {
                $heredoc .= $current->Code;
                $current  = $current->next();
            } while ($current->HeredocOpenedBy === $this);
            if (in_array(ReindentHeredocs::class, $this->Formatter->Rules)) {
                $indent = $this->renderIndent($softTabs);
                $start  = $this->startOfLine();
                if ($padding = str_repeat(' ', $start->LinePadding - $start->LineUnpadding + $start->Padding)) {
                    if (($indent[0] ?? null) === "\t") {
                        $heredoc = str_replace("\n$indent", "\n" . ($newIndent = $this->renderIndent(true)), $heredoc);
                        $indent  = $newIndent;
                    }
                    $heredoc = str_replace("\n$indent", "\n$indent$padding", $heredoc);
                }
                $regex   = preg_quote("$indent$padding", '/');
                $heredoc = preg_replace("/\\n$regex\$/m", "\n", $heredoc);
            }
        } elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY)) {
            return $this->Code;
        } elseif ($this->isMultiLineComment(true)) {
            $comment = $this->renderComment($softTabs);
        }

        if (!$this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS)) {
            $code = WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore());
            if (($code[0] ?? null) === "\n") {
                if ($this->PreIndent + $this->Indent + $this->HangingIndent - $this->Deindent) {
                    $code .= $this->renderIndent($softTabs);
                }
                if ($this->LinePadding - $this->LineUnpadding) {
                    $code .= str_repeat(' ', $this->LinePadding - $this->LineUnpadding);
                }
            }
            if ($this->Padding) {
                $code .= str_repeat(' ', $this->Padding);
            }
        }

        $code = ($code ?? '') . ($heredoc ?? $comment ?? $this->Code);

        if ((is_null($this->_next) || $this->next()->isOneOf(...TokenType::DO_NOT_MODIFY)) &&
                !$this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS)) {
            $code .= WhitespaceType::toWhitespace($this->effectiveWhitespaceAfter());
        }

        return $code;
    }

    private function renderComment(bool $softTabs = false): string
    {
        // Remove trailing whitespace from each line
        $code = preg_replace('/\h+$/m', '', $this->Code);
        switch ($this->Type) {
            case T_COMMENT:
                if (!$this->isMultiLineComment() ||
                        preg_match('/\n\h*(?!\*)(\S|$)/', $code)) {
                    return $code;
                }
            case T_DOC_COMMENT:
                $start  = $this->startOfLine();
                $indent =
                    "\n" . ($start === $this
                        ? $this->renderIndent($softTabs)
                            . str_repeat(' ', $this->LinePadding - $this->LineUnpadding + $this->Padding)
                        : ltrim($start->renderWhitespaceBefore(), "\n")
                            . str_repeat(' ', mb_strlen($start->collect($this->prev())->render($softTabs))
                                + strlen(WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore()))
                                + $this->Padding));

                return preg_replace([
                    '/\n\h*(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
                    '/\n\h*\*?$/m',
                    '/\n\h*\*\//',
                ], [
                    $indent . ' * ',
                    $indent . ' *',
                    $indent . ' */',
                ], $code);
        }

        throw new RuntimeException('Not a T_COMMENT or T_DOC_COMMENT');
    }

    public function collect(Token $to): TokenCollection
    {
        return TokenCollection::collect($this, $to);
    }

    public function collectSiblings(Token $to = null): TokenCollection
    {
        $tokens = new TokenCollection();
        if ($to && ($this->Index > $to->Index || $to->isNull())) {
            return $tokens;
        }
        $from = $this->canonical();
        if ($to && !$from->isSibling($to)) {
            throw new RuntimeException('Argument #1 ($to) is not a sibling');
        }
        while (!$from->isNull()) {
            $tokens[] = $from;
            if ($to && ($from === $to || $from === $to->OpenedBy)) {
                break;
            }
            $from = $from->nextSibling();
        }

        return $tokens;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if (!in_array($name, [...self::ALLOW_READ, ...self::ALLOW_WRITE])) {
            throw new RuntimeException('Cannot access property ' . static::class . '::$' . $name);
        }

        return $this->$name;
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if (!in_array($name, self::ALLOW_WRITE) &&
                !(is_null($this->$name) && in_array($name, self::ALLOW_WRITE_IF_NULL))) {
            throw new RuntimeException('Cannot access property ' . static::class . '::$' . $name);
        }
        if ($this->$name === $value) {
            return;
        }
        if ($this->Formatter->Debug && ($service = $this->Formatter->RunningService)) {
            $this->Log[] = [
                'service' => $service,
                'value'   => $name,
                'from'    => $this->$name,
                'to'      => $value,
            ];
        }
        $this->$name = $value;
    }

    public function __toString(): string
    {
        return (string) $this->Index;
    }

    private function isSameTypeAs(Token $token): bool
    {
        return $this->Type === $token->Type &&
            (!$this->isOneOf(...TokenType::COMMENT) ||
                $this->isMultiLineComment() === $token->isMultiLineComment());
    }

    private function isSibling(Token $token): bool
    {
        $token = $token->canonical();

        return $token->BracketStack === $this->BracketStack;
    }
}
