<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use JsonSerializable;
use Lkrms\Pretty\WhitespaceType;
use RuntimeException;

/**
 * @property-read int $Index
 * @property-read int|string $Type
 * @property-read int $Line
 * @property-read string $TypeName
 * @property Token|null $OpenedBy
 * @property Token|null $ClosedBy
 * @property string $Code
 * @property int $Indent
 * @property int $Deindent
 * @property int $HangingIndent
 * @property bool $PinToCode
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
        'OpenedBy',
        'ClosedBy',
    ];

    private const ALLOW_WRITE = [
        'Code',
        'Indent',
        'Deindent',
        'HangingIndent',
        'PinToCode',
        'Padding',
        'WhitespaceBefore',
        'WhitespaceAfter',
        'WhitespaceMaskPrev',
        'WhitespaceMaskNext',
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
    private $OpenedBy;

    /**
     * @var Token|null
     */
    private $ClosedBy;

    /**
     * @var array<string,true>
     */
    public $Tags = [];

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
     * @var Token[]
     */
    public $IndentStack = [];

    /**
     * @var array<Token[]>
     */
    public $IndentBracketStack = [];

    /**
     * @var bool
     */
    private $PinToCode = false;

    /**
     * @var int
     */
    private $Padding = 0;

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
     * @param string|array{0:int,1:string,2:int} $token
     * @param Token[] $bracketStack
     */
    public function __construct(int $index, $token, ?Token $prev, array $bracketStack, Formatter $formatter)
    {
        if (is_array($token)) {
            list($this->Type, $this->Code, $this->Line) = $token;
            if ($this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS)) {
                $code = rtrim($this->Code);
            } elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY_RHS)) {
                $code = ltrim($this->Code);
            } elseif (!$this->isOneOf(...TokenType::DO_NOT_MODIFY)) {
                $code = trim($this->Code);
            }
            if (isset($code) && $code !== $this->Code) {
                $this->Code            = $code;
                $this->Tags['Trimmed'] = true;
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

        if ($prev) {
            $this->_prev        = $prev;
            $this->_prev->_next = $this;
        }
    }

    /**
     * Update _prev and _next as needed to insert $this between $token and its
     * current predecessor
     *
     */
    protected function insertBefore(Token $token): void
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
        foreach ($a['BracketStack'] as &$t) {
            $t = $t->Index;
        }
        foreach ($a['IndentStack'] as &$t) {
            $t = $t->Index;
        }
        foreach ($a['IndentBracketStack'] as &$bracketStack) {
            foreach ($bracketStack as &$t) {
                $t = $t->Index;
            }
        }
        foreach ($a['EndOfExpression'] as &$tokens) {
            foreach ($tokens as &$t) {
                $t = $t->Index;
            }
        }
        foreach ($a['StartOfExpression'] as &$tokens) {
            foreach ($tokens as &$t) {
                $t = $t->Index;
            }
        }
        $a['OpenedBy'] = $a['OpenedBy']->Index ?? null;
        $a['ClosedBy'] = $a['ClosedBy']->Index ?? null;
        $a['_prev']    = $a['_prev']->Index ?? null;
        $a['_next']    = $a['_next']->Index ?? null;
        unset(
            $a['Index'],
            $a['Type'],
            //$a['BracketStack'],
            //$a['IndentStack'],
            //$a['IndentBracketStack'],
            $a['ChainOpenedBy'],
            $a['HeredocOpenedBy'],
            $a['StringOpenedBy'],
            $a['Formatter'],
            //$a['_prev'],
            //$a['_next'],
            //$a['EndOfExpression'],
            //$a['StartOfExpression'],
        );
        $a['WhitespaceBefore'] = WhitespaceType::toWhitespace($a['WhitespaceBefore']);
        $a['WhitespaceAfter']  = WhitespaceType::toWhitespace($a['WhitespaceAfter']);
        if (empty($a['Tags'])) {
            unset($a['Tags']);
        }

        return $a;
    }

    public function wasFirstOnLine(): bool
    {
        $prev = $this->prev();

        return $this->Line > $prev->Line || $prev->isNull();
    }

    public function wasLastOnLine(): bool
    {
        $next = $this->next();

        return $this->Line < $next->Line || $next->isNull();
    }

    public function wasBetweenTokensOnLine(): bool
    {
        return !$this->wasFirstOnLine() && !$this->wasLastOnLine();
    }

    public function prev(int $offset = 1): Token { $p = $this; for ($i = 0; $i < $offset; $i++) { $p = $p->_prev ?? null; } return $p ?: new NullToken(); }

    public function next(int $offset = 1): Token { $n = $this; for ($i = 0; $i < $offset; $i++) { $n = $n->_next ?? null; } return $n ?: new NullToken(); }

    public function prevCode(int $offset = 1): Token { $p = $this; for ($i = 0; $i < $offset; $i++) { do { $p = $p->_prev ?? null; } while ($p && !$p->isCode()); } return $p ?: new NullToken(); }

    public function nextCode(int $offset = 1): Token { $n = $this; for ($i = 0; $i < $offset; $i++) { do { $n = $n->_next ?? null; } while ($n && !$n->isCode()); } return $n ?: new NullToken(); }

    /**
     * @param int|string ...$types
     */
    public function prevCodeWhile(...$types): TokenCollection { return $this->_prevCodeWhile(false, ...$types); }

    /**
     * @param int|string ...$types
     */
    public function withPrevCodeWhile(...$types): TokenCollection { return $this->_prevCodeWhile(true, ...$types); }

    /**
     * @param int|string ...$types
     */
    private function _prevCodeWhile(bool $with, ...$types): TokenCollection { $c = new TokenCollection(); $p = $with ? $this : $this->prevCode(); while ($p->isOneOf(...$types)) { $c[] = $p; $p = $p->prevCode(); } return $c; }

    /**
     * @param int|string ...$types
     */
    public function nextCodeWhile(...$types): TokenCollection { return $this->_nextCodeWhile(false, ...$types); }

    /**
     * @param int|string ...$types
     */
    public function withNextCodeWhile(...$types): TokenCollection { return $this->_nextCodeWhile(true, ...$types); }

    /**
     * @param int|string ...$types
     */
    private function _nextCodeWhile(bool $with, ...$types): TokenCollection { $c = new TokenCollection(); $n = $with ? $this : $this->nextCode(); while ($n->isOneOf(...$types)) { $c[] = $n; $n = $n->nextCode(); } return $c; }

    public function prevSibling(int $offset = 1): Token
    {
        $prev = $_this = $this->canonicalThis(__METHOD__);
        for ($i = 0; $i < $offset; $i++) {
            do {
                $prev = $prev->_prev ?? null;
            } while ($prev && !$prev->isCode());
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
        $next = $_this = $this->canonicalThis(__METHOD__);
        for ($i = 0; $i < $offset; $i++) {
            if ($next->ClosedBy ?? null) {
                $next = $next->ClosedBy;
            }
            do {
                $next = $next->_next ?? null;
            } while ($next && !$next->isCode());
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
        $current = $this->canonicalThis(__METHOD__);

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
        $next   = $this->canonicalThis(__METHOD__);
        $next   = $includeToken ? $next : $next->parent();
        while ($next->isOneOf(...$types)) {
            $tokens[] = $next;
            $next     = $next->parent();
        }

        return $tokens;
    }

    public function outer(): TokenCollection
    {
        return $this->canonicalThis(__METHOD__)->collect($this->ClosedBy ?: $this);
    }

    public function inner(): TokenCollection
    {
        return $this->canonicalThis(__METHOD__)->next()->collect(($this->ClosedBy ?: $this)->prev());
    }

    public function innerSiblings(): TokenCollection
    {
        return $this->canonicalThis(__METHOD__)->nextCode()->collectSiblings(($this->ClosedBy ?: $this)->prevCode());
    }

    public function isCode(): bool
    {
        return !$this->isNotCode();
    }

    public function isNotCode(): bool
    {
        return $this->isOneOf(...TokenType::NOT_CODE) &&
            !$this->isCloseTagStatementTerminator();
    }

    public function isNull(): bool
    {
        return false;
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

    public function startOfStatement(): Token
    {
        $current = ($this->is(';') || $this->isCloseTagStatementTerminator()
                ? $this->prevCode()->OpenedBy
                : null)
            ?: $this->canonicalThis(__METHOD__);
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
        $current = $this->canonicalThis(__METHOD__);
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
        return $this->isCode() &&
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
        $current = $this->canonicalThis(__METHOD__);
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
     * @param int $ignore A bitmask of {@see TokenBoundary} values
     */
    public function endOfExpression(int $ignore = TokenBoundary::COMPARISON): Token
    {
        $current = $this->canonicalThis(__METHOD__);
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

    public function hasAdjacentBlock(): bool
    {
        $_this = $this->canonicalThis(__METHOD__);
        if (!$_this->isOneOf('(', '[')) {
            return false;
        }
        /** @var Token */
        $lastOuterBracket = $_this->ClosedBy->withNextCodeWhile(')', ']')->last();
        [$end, $next]     = [
            $lastOuterBracket->endOfStatement(),
            $lastOuterBracket->nextCode(),
        ];

        return !$end->isNull() && !$next->isNull() && $end->Index > $next->Index;
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
        if ($this->PinToCode &&
                (!$this->prev()->PinToCode || !$this->isSameTypeAs($this->prev())) &&
                !count($this->next()
                            ->collect(($next = $this->nextCode())->prev())
                            ->filter(fn(Token $t) => !$t->PinToCode || !$this->isSameTypeAs($t)))) {
            return $this->_effectiveWhitespaceBefore() | $next->_effectiveWhitespaceBefore();
        }
        if (!$this->PinToCode && $this->prev()->PinToCode && $this->isCode()) {
            return ($this->_effectiveWhitespaceBefore() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceBefore();
    }

    private function _effectiveWhitespaceBefore(): int
    {
        return ($this->WhitespaceBefore | $this->prev()->WhitespaceAfter) & $this->prev()->WhitespaceMaskNext & $this->WhitespaceMaskPrev;
    }

    public function effectiveWhitespaceAfter(): int
    {
        if ($this->PinToCode && ($next = $this->next())->isCode() && !$next->PinToCode) {
            return ($this->_effectiveWhitespaceAfter() | WhitespaceType::LINE) & ~WhitespaceType::BLANK;
        }

        return $this->_effectiveWhitespaceAfter();
    }

    private function _effectiveWhitespaceAfter(): int
    {
        return ($this->WhitespaceAfter | $this->next()->WhitespaceBefore) & $this->next()->WhitespaceMaskPrev & $this->WhitespaceMaskNext;
    }

    public function hasNewlineBefore(): bool
    {
        return (bool) ($this->effectiveWhitespaceBefore() & (WhitespaceType::LINE | WhitespaceType::BLANK));
    }

    public function hasNewlineAfter(): bool
    {
        return (bool) ($this->effectiveWhitespaceAfter() & (WhitespaceType::LINE | WhitespaceType::BLANK));
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

    public function hasNewlineAfterCode(): bool
    {
        return $this->hasNewlineAfter() ||
            (!$this->next()->isCode() &&
                $this->collect($this->nextCode())->hasInnerNewline());
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
        if (!$this->is(T_CLOSE_TAG)) {
            return false;
        }
        $prev = $this->prev();
        while ($prev->isOneOf(...TokenType::COMMENT)) {
            $prev = $prev->prev();
        }

        return !$prev->isOneOf(';', TokenType::T_NULL) &&
            $prev->Index > $this->OpenedBy->Index;
    }

    public function inSwitchCase(): bool
    {
        return $this->isCode() &&
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

    public function isBrace(): bool
    {
        return $this->is('{') || ($this->is('}') && $this->OpenedBy->is('{'));
    }

    public function isStructuralBrace(): bool
    {
        if (!$this->isBrace()) {
            return false;
        }
        $_this     = $this->canonicalThis(__METHOD__);
        $lastInner = $_this->ClosedBy->prevCode();
        $parent    = $_this->parent();

        return ($lastInner === $_this ||                                        // `{}`
                $lastInner->isOneOf(';') ||                                     // `{ statement; }`
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
        // OPERATOR_EXECUTION is excluded because for formatting purposes,
        // commands between backticks are equivalent to double-quoted strings
        return $this->isOneOf(
            ...TokenType::OPERATOR_ARITHMETIC,
            ...TokenType::OPERATOR_ASSIGNMENT,
            ...TokenType::OPERATOR_BITWISE,
            ...TokenType::OPERATOR_COMPARISON,
            ...TokenType::OPERATOR_ERROR_CONTROL,
            ...TokenType::OPERATOR_INCREMENT_DECREMENT,
            ...TokenType::OPERATOR_LOGICAL,
            ...TokenType::OPERATOR_STRING,
            ...TokenType::OPERATOR_DOUBLE_ARROW,
            ...TokenType::OPERATOR_INSTANCEOF
        ) || $this->isTernaryOperator();
    }

    public function isTernaryOperator(): bool
    {
        // We can't just do this, because nullable method return types tend to
        // be followed by unrelated colons:
        //
        //     return ($this->is('?') && ($this->nextSiblingOf(':'))) ||
        //         ($this->is(':') && ($this->prevSiblingOf('?')));
        //
        // TODO: avoid collecting siblings, cache the result?
        return ($this->is('?') && ($this->collectSiblings($this->endOfStatement())->hasOneOf(':'))) ||
            ($this->is(':') && ($this->startOfStatement()->collectSiblings($this)->hasOneOf('?')));
    }

    public function isBinaryOrTernaryOperator(): bool
    {
        return $this->isOperator() && !$this->isUnaryOperator();
    }

    public function isUnaryOperator(): bool
    {
        return $this->isOneOf(
            '~', '!',
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
            '(', ',', ';', '[', '{', '}',
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
        if (!$this->isCode()) {
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
        return $this->Indent + $this->HangingIndent - $this->Deindent;
    }

    public function renderIndent(bool $softTabs = false): string
    {
        return ($this->Indent + $this->HangingIndent - $this->Deindent)
            ? str_repeat($softTabs ? $this->Formatter->SoftTab : $this->Formatter->Tab, $this->Indent + $this->HangingIndent - $this->Deindent)
            : '';
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
            $indent = $this->renderIndent($softTabs);
            if ($padding = str_repeat(' ', $this->startOfLine()->Padding)) {
                $heredoc = str_replace("\n$indent", "\n$indent$padding", $heredoc);
            }
            $heredoc = str_replace("\n$indent$padding\n", "\n\n", $heredoc);
        } elseif ($this->isOneOf(...TokenType::DO_NOT_MODIFY)) {
            return $this->Code;
        } elseif ($this->isMultiLineComment(true)) {
            $comment = $this->renderComment($softTabs);
        }

        if (!$this->isOneOf(...TokenType::DO_NOT_MODIFY_LHS)) {
            $code = WhitespaceType::toWhitespace($this->effectiveWhitespaceBefore());
            if (substr($code, -1) === "\n" && ($this->Indent + $this->HangingIndent - $this->Deindent)) {
                $code .= $this->renderIndent($softTabs);
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
            case T_DOC_COMMENT:
                $indent = "\n" . $this->renderIndent($softTabs);

                return preg_replace([
                    '/\n\h*(?:\* |\*(?!\/)(?=[\h\S])|(?=[^\s*]))/',
                    '/\n\h*\*?$/m',
                    '/\n\h*\*\//',
                ], [
                    $indent . ' * ',
                    $indent . ' *',
                    $indent . ' */',
                ], $code);

            case T_COMMENT:
                return $code;
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
        $from = $this->canonicalThis(__METHOD__);
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
                !(is_null($this->$name) && in_array($name, self::ALLOW_READ))) {
            throw new RuntimeException('Cannot access property ' . static::class . '::$' . $name);
        }
        if ($this->$name === $value) {
            return;
        }
        if ($this->Formatter->Debug && ($service = $this->Formatter->RunningService)) {
            $this->Tags[$service . ':' . $name . ':' . $this->$name . ':' . $value] = true;
        }
        $this->$name = $value;
    }

    public function __toString(): string
    {
        return (string) $this->Index;
    }

    private function canonicalThis(string $method): Token
    {
        if ($this->isNotCode()) {
            throw new RuntimeException(sprintf('%s cannot be called on %s tokens', $method, $this->TypeName));
        }

        return $this->OpenedBy ?: $this;
    }

    private function isSameTypeAs(Token $token): bool
    {
        return $this->Type === $token->Type &&
            (!$this->isOneOf(...TokenType::COMMENT) ||
                $this->isMultiLineComment() === $token->isMultiLineComment());
    }

    private function isSibling(Token $token): bool
    {
        $token = $token->canonicalThis(__METHOD__);

        return $token->BracketStack === $this->BracketStack;
    }
}
