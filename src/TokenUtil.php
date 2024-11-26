<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Salient\Utility\Arr;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;

final class TokenUtil
{
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
            || !$token->Formatter->NewlineBeforeFnDoubleArrows
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
            && $token->Formatter->NewlineBeforeFnDoubleArrows
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

        if ($token->SubType !== null) {
            $t['SubType'] = $token->SubType !== -1
                ? Reflect::getConstantName(TokenSubType::class, $token->SubType)
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
