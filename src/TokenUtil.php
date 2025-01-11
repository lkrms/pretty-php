<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\TokenSubId as SubId;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Contract\HasOperatorPrecedence;
use Lkrms\PrettyPHP\Contract\HasTokenIndex;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Salient\Utility\Arr;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;

final class TokenUtil implements HasOperatorPrecedence, HasTokenIndex
{
    /**
     * @var array<int,array<array{int,int,bool,bool}>|false>
     */
    public const OPERATOR_PRECEDENCE_INDEX = self::OPERATOR_PRECEDENCE
        + self::TOKEN_INDEX;

    /**
     * Check if a newline is allowed before a token
     */
    public static function isNewlineAllowedBefore(Token $token): bool
    {
        if (!$token->Idx->AllowNewlineBefore[$token->id]) {
            return false;
        }

        // Don't allow newlines before `=>` other than in arrow functions
        if ($token->id === \T_DOUBLE_ARROW && (
            !($token->Flags & Flag::FN_DOUBLE_ARROW)
            || !$token->Formatter->NewlineBeforeFnDoubleArrow
        )) {
            return false;
        }

        // Don't allow newlines before `:` other than ternary operators
        if ($token->id === \T_COLON && !($token->Flags & Flag::TERNARY)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a newline is allowed after a token
     */
    public static function isNewlineAllowedAfter(Token $token): bool
    {
        // To allow newlines after attributes, ignore T_ATTRIBUTE itelf and
        // treat its close bracket as T_ATTRIBUTE
        if ($token->id === \T_ATTRIBUTE) {
            return false;
        }

        if (
            $token->OpenBracket
            && $token->OpenBracket->id === \T_ATTRIBUTE
            && $token->Idx->AllowNewlineAfter[\T_ATTRIBUTE]
        ) {
            return true;
        }

        if (!$token->Idx->AllowNewlineAfter[$token->id]) {
            return false;
        }

        if (
            $token->id === \T_CLOSE_BRACE
            && !($token->Flags & Flag::STRUCTURAL_BRACE)
        ) {
            return false;
        }

        // Don't allow newlines after `:` except when they terminate case
        // statements and labels
        if ($token->id === \T_COLON && !$token->isColonStatementDelimiter()) {
            return false;
        }

        // Don't allow newlines after `=>` in arrow functions if disabled
        if (
            $token->Flags & Flag::FN_DOUBLE_ARROW
            && $token->Formatter->NewlineBeforeFnDoubleArrow
        ) {
            return false;
        }

        // Only allow newlines after `implements` and `extends` if they have
        // multiple interfaces
        if (
            ($token->id === \T_IMPLEMENTS || $token->id === \T_EXTENDS)
            && !($token->Flags & Flag::LIST_PARENT)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get the first token in the expression to which a given token belongs by
     * iterating over previous siblings until an operator with lower precedence
     * is found
     *
     * If a precedence other than `-1` is given, iteration stops at the first
     * lower-precedence operator.
     *
     * If the token is not an operator or a precedence of `-1` is given,
     * iteration stops at the first operator.
     */
    public static function getOperatorExpression(Token $token, ?int $precedence = null): Token
    {
        if ($precedence === null) {
            $precedence = self::OPERATOR_PRECEDENCE_INDEX[$token->id]
                ? self::getOperatorPrecedence($token)
                : -1;
        }
        $t = $token;
        while (
            ($prev = $t->PrevSibling)
            && $prev->Statement === $token->Statement
            && $prev->id !== \T_COMMA
        ) {
            if (self::OPERATOR_PRECEDENCE_INDEX[$prev->id]) {
                $prevPrecedence = self::getOperatorPrecedence($prev);
                if ($prevPrecedence !== -1 && (
                    // If called with a non-operator, stop at the first operator
                    $precedence === -1
                    || $prevPrecedence > $precedence
                )) {
                    return $t;
                }
            }
            $t = $prev;
        }
        return $t;
    }

    /**
     * Get the last token in the expression to which a given token belongs by
     * iterating over next siblings until an operator with lower precedence is
     * found
     *
     * If a precedence other than `-1` is given, iteration stops at the first
     * lower-precedence operator.
     *
     * If the token is not an operator or a precedence of `-1` is given,
     * iteration stops at the first operator.
     */
    public static function getOperatorEndExpression(Token $token, ?int $precedence = null): Token
    {
        if ($precedence === null) {
            $precedence = self::OPERATOR_PRECEDENCE_INDEX[$token->id]
                ? self::getOperatorPrecedence($token)
                : -1;
        }
        $ternary2 = self::getTernary2AfterTernary1($token);
        $t = $token;
        while (
            ($next = $t->NextSibling)
            && $next->Statement === $token->Statement
            && $next->id !== \T_COMMA
            && (
                $next !== $token->EndStatement
                || !$token->Idx->StatementDelimiter[$next->id]
            ) && (
                !$ternary2
                || $ternary2->index > $next->index
            )
        ) {
            if (self::OPERATOR_PRECEDENCE_INDEX[$next->id]) {
                $nextPrecedence = self::getOperatorPrecedence($next);
                if ($nextPrecedence !== -1 && $nextPrecedence < 99 && (
                    // If called with a non-operator, stop at the first operator
                    $precedence === -1
                    || $nextPrecedence > $precedence
                )) {
                    return $t->CloseBracket ?? $t;
                }
            }
            $t = $next;
        }
        return $t->CloseBracket ?? $t;
    }

    /**
     * Get the precedence of a given operator, or -1 if it is not an operator
     *
     * Lower numbers indicate higher precedence.
     *
     * @param-out bool $leftAssociative
     * @param-out bool $rightAssociative
     */
    public static function getOperatorPrecedence(
        Token $token,
        ?bool &$leftAssociative = null,
        ?bool &$rightAssociative = null
    ): int {
        if ($precedence = self::OPERATOR_PRECEDENCE_INDEX[$token->id]) {
            foreach ($precedence as [$arity, $precedence, $leftAssoc, $rightAssoc]) {
                if (
                    $arity === 0
                    || ($arity === self::UNARY && $token->inUnaryContext())
                    || ($arity === self::BINARY && !$token->inUnaryContext())
                    || ($arity === self::TERNARY && $token->Flags & Flag::TERNARY)
                ) {
                    $leftAssociative = $leftAssoc;
                    $rightAssociative = $rightAssoc;
                    return $precedence;
                }
            }
        }
        // @codeCoverageIgnoreStart
        $leftAssociative = false;
        $rightAssociative = false;
        return -1;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the precedence of an operator with the given token ID, or -1 if it is
     * not an operator
     *
     * Lower numbers indicate higher precedence.
     *
     * @param-out bool $leftAssociative
     * @param-out bool $rightAssociative
     */
    public static function getPrecedenceOf(
        int $id,
        bool $unary = false,
        bool $ternary = false,
        ?bool &$leftAssociative = null,
        ?bool &$rightAssociative = null
    ): int {
        if ($precedence = self::OPERATOR_PRECEDENCE_INDEX[$id]) {
            foreach ($precedence as [$arity, $precedence, $leftAssoc, $rightAssoc]) {
                if (
                    $arity === 0
                    || ($arity === self::UNARY && $unary)
                    || ($arity === self::BINARY && !$unary)
                    || ($arity === self::TERNARY && $ternary)
                ) {
                    $leftAssociative = $leftAssoc;
                    $rightAssociative = $rightAssoc;
                    return $precedence;
                }
            }
        }
        // @codeCoverageIgnoreStart
        $leftAssociative = false;
        $rightAssociative = false;
        return -1;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the first ternary or null coalescing operator that is one of a given
     * ternary or null coalescing operator's preceding siblings in the same
     * statement
     *
     * If `$token` is part of the first ternary or null coalescing expression in
     * the statement, `null` is returned.
     */
    public static function getTernaryContext(Token $token): ?Token
    {
        $precedence = self::getPrecedenceOf(\T_QUESTION, false, true);
        if ($ternary = $token->Flags & Flag::TERNARY) {
            /** @var Token */
            $before = self::getTernary1($token);
            $short = $before->NextCode === self::getTernary2($token);
        } else {
            $before = $token;
            $short = false;
        }
        $t = $before;
        while (
            (!$ternary || $short)
            && ($t = $t->PrevSibling)
            && $t->Statement === $token->Statement
            && $t->id !== \T_COMMA
        ) {
            if ($t->id === \T_COALESCE) {
                $context = $t;
            } elseif ($t->Flags & Flag::TERNARY) {
                if (
                    self::getTernary1($t) === $t
                    && $t->Data[Data::OTHER_TERNARY]->index
                        < $before->index
                ) {
                    $context = $t;
                    $ternary = true;
                    $short = $t->NextCode === self::getTernary2($t);
                }
            } elseif (self::OPERATOR_PRECEDENCE_INDEX[$t->id]) {
                $prevPrecedence = self::getOperatorPrecedence($t);
                if ($prevPrecedence !== -1 && $prevPrecedence > $precedence) {
                    break;
                }
            }
        }
        return $context ?? null;
    }

    /**
     * Get the first token in the expression to which a given ternary or null
     * coalescing context applies
     *
     * @param Token $context A token returned by
     * {@see TokenUtil::getTernaryContext()}, or the first ternary or null
     * coalescing operator in a statement.
     */
    public static function getTernaryExpression(Token $context): Token
    {
        $precedence = self::getPrecedenceOf(\T_QUESTION, false, true);
        $t = $context;
        while (
            ($prev = $t->PrevSibling)
            && $prev->Statement === $context->Statement
            && $prev->id !== \T_COMMA
        ) {
            if ($prev->Flags & Flag::TERNARY) {
                return $t;
            }
            if (self::OPERATOR_PRECEDENCE_INDEX[$prev->id]) {
                $prevPrecedence = self::getOperatorPrecedence($prev);
                if ($prevPrecedence !== -1 && $prevPrecedence > $precedence) {
                    return $t;
                }
            }
            $t = $prev;
        }
        return $t;
    }

    /**
     * Get the last token in the expression to which a given ternary or null
     * coalescing operator applies
     */
    public static function getTernaryEndExpression(Token $token): Token
    {
        $precedence = self::getPrecedenceOf(\T_QUESTION, false, true);
        $t = self::getTernary2($token) ?? $token;
        while (
            ($next = $t->NextSibling)
            && $next->Statement === $token->Statement
            && $next->id !== \T_COMMA
            && (
                $next !== $token->EndStatement
                || !$token->Idx->StatementDelimiter[$next->id]
            )
        ) {
            if ($next->Flags & Flag::TERNARY) {
                if ($next->id === \T_COLON) {
                    return $t->CloseBracket ?? $t;
                }
                $next = $next->Data[Data::OTHER_TERNARY];
            } elseif (self::OPERATOR_PRECEDENCE_INDEX[$next->id]) {
                $nextPrecedence = self::getOperatorPrecedence($next);
                if ($nextPrecedence !== -1 && $nextPrecedence < 99 && $nextPrecedence > $precedence) {
                    return $t->CloseBracket ?? $t;
                }
            }
            $t = $next;
        }
        return $t->CloseBracket ?? $t;
    }

    /**
     * Get the first ternary operator for the given ternary operator, or null if
     * it is not a ternary operator
     */
    public static function getTernary1(Token $token): ?Token
    {
        return $token->Flags & Flag::TERNARY
            ? ($token->id === \T_QUESTION
                ? $token
                : $token->Data[Data::OTHER_TERNARY])
            : null;
    }

    /**
     * Get the second ternary operator for the given ternary operator, or null
     * if it is not a ternary operator
     */
    public static function getTernary2(Token $token): ?Token
    {
        return $token->Flags & Flag::TERNARY
            ? ($token->id === \T_COLON
                ? $token
                : $token->Data[Data::OTHER_TERNARY])
            : null;
    }

    /**
     * Get the second ternary operator of the expression to which the given
     * token belongs, or null if it is not between the first and second
     * operators of a ternary expression
     */
    public static function getTernary2AfterTernary1(Token $token): ?Token
    {
        $t = $token;
        do {
            $t = $t->prevSiblingOf(\T_QUESTION, true);
            if ($t->id === \T_NULL) {
                return null;
            }
        } while (!($t->Flags & Flag::TERNARY));
        $other = $t->Data[Data::OTHER_TERNARY];
        return $other->index > $token->index
            ? $other
            : null;
    }

    public static function getWhitespace(int $type): string
    {
        if ($type & Space::BLANK) {
            return "\n\n";
        }
        if ($type & Space::LINE) {
            return "\n";
        }
        if ($type & Space::SPACE) {
            return ' ';
        }
        return '';
    }

    /**
     * Convert a backtick-enclosed substring to a double-quoted equivalent
     */
    public static function unescapeBackticks(string $text): string
    {
        // Escape '\"' and '"', unescape '\`'
        return Regex::replaceCallback(
            '/((?<!\\\\)(?:\\\\\\\\)*)(\\\\?"|\\\\`)/',
            fn($matches) =>
                $matches[1] . (
                    $matches[2] === '\"'
                        ? '\\\\\\"'
                        : ($matches[2] === '"'
                            ? '\"'
                            : '`')
                ),
            $text,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function serialize(Token $token): array
    {
        $t['id'] = $token->getTokenName();
        $t['text'] = $token->text;
        $t['line'] = $token->line;
        $t['pos'] = $token->pos;
        $t['column'] = $token->column;

        if ($token->subId !== null) {
            $t['subId'] = $token->subId !== -1
                ? Reflect::getConstantName(SubId::class, $token->subId)
                : '<unknown>';
        }

        $t['PrevSibling'] = $token->PrevSibling;
        $t['NextSibling'] = $token->NextSibling;
        $t['Statement'] = $token->Statement;
        $t['EndStatement'] = $token->EndStatement;
        $t['Parent'] = $token->Parent;
        $t['String'] = $token->String;
        $t['Heredoc'] = $token->Heredoc;

        if ($token->Flags) {
            static $tokenFlags;
            $tokenFlags ??= Reflect::getConstants(Flag::class);
            $flags = [];
            /** @var int $value */
            foreach ($tokenFlags as $name => $value) {
                if (($token->Flags & $value) === $value) {
                    $flags[] = $name;
                }
            }
            if ($flags) {
                $t['Flags'] = implode('|', $flags);
            }
        }

        if (isset($token->Data)) {
            static $dataTypes;
            $dataTypes ??= array_flip(self::getTokenDataValues());
            foreach ($token->Data as $type => $value) {
                $t['Data'][$dataTypes[$type] ?? $type] =
                    $value instanceof Token
                        ? self::describe($value)
                        : ($value instanceof TokenCollection
                            ? $value->toString(' ')
                            : (is_array($value) ? count($value) : $value));
            }
        }

        $t['ExpandedText'] = $token->ExpandedText;
        $t['OriginalText'] = $token->OriginalText;
        $t['TagIndent'] = $token->TagIndent;
        $t['PreIndent'] = $token->PreIndent;
        $t['Indent'] = $token->Indent;
        $t['Deindent'] = $token->Deindent;
        $t['HangingIndent'] = $token->HangingIndent;
        $t['LinePadding'] = $token->LinePadding;
        $t['LineUnpadding'] = $token->LineUnpadding;
        $t['Padding'] = $token->Padding;
        $t['HeredocIndent'] = $token->HeredocIndent;
        $t['AlignedWith'] = $token->AlignedWith;

        if ($token->Whitespace) {
            static $whitespaceFlags;
            $whitespaceFlags ??= Arr::unset(
                Reflect::getConstants(Space::class),
                'SPACE',
                'LINE',
                'BLANK',
                'NO_SPACE',
                'NO_LINE',
                'NO_BLANK',
                'CRITICAL_SPACE',
                'CRITICAL_LINE',
                'CRITICAL_BLANK',
                'CRITICAL_NO_SPACE',
                'CRITICAL_NO_LINE',
                'CRITICAL_NO_BLANK',
            );
            $whitespace = [];
            $tokenValue = $token->Whitespace;
            /** @var int $value */
            foreach ($whitespaceFlags as $name => $value) {
                if (($tokenValue & $value) === $value) {
                    $whitespace[] = $name;
                    $tokenValue &= ~$value;
                }
            }
            if ($whitespace) {
                $t['Whitespace'] = implode('|', $whitespace);
            }
        }

        $t['OutputLine'] = $token->OutputLine;
        $t['OutputPos'] = $token->OutputPos;
        $t['OutputColumn'] = $token->OutputColumn;

        foreach ($t as $key => &$value) {
            if (
                $value === null
                || ($value === 0 && Str::endsWith($key, ['Indent', 'Padding'], true))
                || ($value === -1 && Str::startsWith($key, ['line', 'pos', 'column', 'Output']))
            ) {
                unset($t[$key]);
                continue;
            }
            if ($value instanceof Token) {
                $value = self::describe($value);
                continue;
            }
        }
        unset($value);

        return $t;
    }

    /**
     * @return array<string,int>
     */
    private static function getTokenDataValues(): array
    {
        /** @var array<string,int> */
        return Reflect::getConstants(Data::class);
    }

    public static function describe(Token $token): string
    {
        if ($token->Idx->Virtual[$token->id]) {
            $realPrev = $token->skipPrevFrom($token->Idx->Virtual);
            if ($token->Data[Data::BOUND_TO] === $realPrev) {
                $payload = $realPrev;
                $suffix = '<<(virtual)';
            } else {
                $payload = $token->skipNextFrom($token->Idx->Virtual);
                $prefix = '(virtual)>>';
            }
        }

        return sprintf(
            'T%d:L%d:%s',
            $token->index,
            $token->line,
            ($prefix ?? '')
                . Str::ellipsize(var_export(($payload ?? $token)->text, true), 20)
                . ($suffix ?? ''),
        );
    }

    private function __construct() {}
}
