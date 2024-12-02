<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubId;
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
    private const OPERATOR_PRECEDENCE_INDEX = self::OPERATOR_PRECEDENCE
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
            !($token->Flags & TokenFlag::FN_DOUBLE_ARROW)
            || !$token->Formatter->NewlineBeforeFnDoubleArrow
        )) {
            return false;
        }

        // Don't allow newlines before `:` other than ternary operators
        if ($token->id === \T_COLON && !($token->Flags & TokenFlag::TERNARY_OPERATOR)) {
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
            $token->OpenedBy
            && $token->OpenedBy->id === \T_ATTRIBUTE
            && $token->Idx->AllowNewlineAfter[\T_ATTRIBUTE]
        ) {
            return true;
        }

        if (!$token->Idx->AllowNewlineAfter[$token->id]) {
            return false;
        }

        if (
            $token->id === \T_CLOSE_BRACE
            && !($token->Flags & TokenFlag::STRUCTURAL_BRACE)
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
            $token->Flags & TokenFlag::FN_DOUBLE_ARROW
            && $token->Formatter->NewlineBeforeFnDoubleArrow
        ) {
            return false;
        }

        // Only allow newlines after `implements` and `extends` if they have
        // multiple interfaces
        if (
            ($token->id === \T_IMPLEMENTS || $token->id === \T_EXTENDS)
            && !($token->Flags & TokenFlag::LIST_PARENT)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get the first token in the expression to which a given operator applies
     * by iterating over previous siblings until an operator with lower
     * precedence is found
     */
    public static function getOperatorExpression(Token $token): Token
    {
        $precedence = self::OPERATOR_PRECEDENCE_INDEX[$token->id]
            ? self::getOperatorPrecedence($token)
            : -1;
        $t = $token;
        while (
            ($prev = $t->PrevSibling)
            && $prev->Statement === $token->Statement
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
     * Get the last token in the expression to which a given operator applies by
     * iterating over next siblings until an operator with lower precedence is
     * found
     */
    public static function getOperatorEndExpression(Token $token): Token
    {
        $precedence = self::OPERATOR_PRECEDENCE_INDEX[$token->id]
            ? self::getOperatorPrecedence($token)
            : -1;
        $t = $token;
        while (
            ($next = $t->NextSibling)
            && $next->Statement === $token->Statement
        ) {
            if (self::OPERATOR_PRECEDENCE_INDEX[$next->id]) {
                $nextPrecedence = self::getOperatorPrecedence($next);
                if ($nextPrecedence !== -1 && (
                    // If called with a non-operator, stop at the first operator
                    $precedence === -1
                    || $nextPrecedence > $precedence
                )) {
                    return $t->ClosedBy ?? $t;
                }
            }
            $t = $next;
        }
        return $t->ClosedBy ?? $t;
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
                    || ($arity === self::TERNARY && $token->Flags & TokenFlag::TERNARY_OPERATOR)
                ) {
                    $leftAssociative = $leftAssoc;
                    $rightAssociative = $rightAssoc;
                    return $precedence;
                }
            }
        }
        $leftAssociative = false;
        $rightAssociative = false;
        return -1;
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
        $before = self::getTernary1($token) ?? $token;
        $t = $before;
        while (
            ($t = $t->PrevSibling)
            && $t->Statement === $token->Statement
        ) {
            if ((
                $t->id === \T_COALESCE
                && $t->Index < $before->Index
            ) || (
                $t->Flags & TokenFlag::TERNARY_OPERATOR
                && self::getTernary1($t) === $t
                && $t->Data[TokenData::OTHER_TERNARY_OPERATOR]->Index
                    < $before->Index
            )) {
                $context = $t;
            }
        }
        return $context ?? null;
    }

    /**
     * Get the first ternary operator for the given ternary operator, or null if
     * it is not a ternary operator
     */
    public static function getTernary1(Token $token): ?Token
    {
        return $token->Flags & TokenFlag::TERNARY_OPERATOR
            ? ($token->id === \T_QUESTION
                ? $token
                : $token->Data[TokenData::OTHER_TERNARY_OPERATOR])
            : null;
    }

    /**
     * Get the second ternary operator for the given ternary operator, or null
     * if it is not a ternary operator
     */
    public static function getTernary2(Token $token): ?Token
    {
        return $token->Flags & TokenFlag::TERNARY_OPERATOR
            ? ($token->id === \T_COLON
                ? $token
                : $token->Data[TokenData::OTHER_TERNARY_OPERATOR])
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

        if ($token->SubId !== null) {
            $t['SubId'] = $token->SubId !== -1
                ? Reflect::getConstantName(TokenSubId::class, $token->SubId)
                : '<unknown>';
        }

        $t['PrevSibling'] = $token->PrevSibling;
        $t['NextSibling'] = $token->NextSibling;
        $t['Statement'] = $token->Statement;
        $t['EndStatement'] = $token->EndStatement;
        $t['Expression'] = $token->Expression;
        $t['EndExpression'] = $token->EndExpression;
        $t['Parent'] = $token->Parent;
        $t['String'] = $token->String;
        $t['Heredoc'] = $token->Heredoc;

        if ($token->Flags) {
            static $tokenFlags;
            $tokenFlags ??= Reflect::getConstants(TokenFlag::class);
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
                            : $value);
            }
        }

        $t['ExpandedText'] = $token->ExpandedText;
        $t['OriginalText'] = $token->OriginalText;
        $t['TagIndent'] = $token->TagIndent;
        $t['PreIndent'] = $token->PreIndent;
        $t['Indent'] = $token->Indent;
        $t['Deindent'] = $token->Deindent;
        $t['HangingIndent'] = $token->HangingIndent;
        $t['HangingIndentToken'] = $token->HangingIndentToken;

        foreach ($token->HangingIndentContext as $i => $entry) {
            foreach ($entry as $j => $entry) {
                $t['HangingIndentContext'][$i][$j] = $entry ? self::describe($entry) : null;
            }
        }

        $t['HangingIndentParent'] = $token->HangingIndentParent;
        $t['HangingIndentParentLevels'] = $token->HangingIndentParentLevels;
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
            if ($value === null || $value === []) {
                unset($t[$key]);
                continue;
            }
            if ($value instanceof Token) {
                $value = self::describe($value);
                continue;
            }
            if (Arr::of($value, Token::class)) {
                /** @var Token[] $value */
                foreach ($value as &$token) {
                    $token = self::describe($token);
                }
                unset($token);
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
        return Reflect::getConstants(TokenData::class);
    }

    public static function describe(Token $token): string
    {
        return sprintf(
            'T%d:L%d:%s',
            $token->Index,
            $token->line,
            Str::ellipsize(var_export($token->text, true), 20),
        );
    }

    private function __construct() {}
}
