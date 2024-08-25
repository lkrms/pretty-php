<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Utility\Arr;
use Salient\Utility\Str;

final class TokenUtility
{
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
                ? TokenSubType::toName($token->SubType)
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
            $flags = [];
            foreach (TokenFlag::cases() as $name => $value) {
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
            $dataTypes ??= array_flip(TokenData::cases());
            foreach ($token->Data as $type => $value) {
                $t['Data'][$dataTypes[$type] ?? $type] =
                    $value instanceof Token
                        ? self::identify($value)
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
        $t['HangingIndentStack'] = $token->HangingIndentStack;

        foreach ($token->HangingIndentContextStack as $i => $entry) {
            foreach ($entry as $j => $entry) {
                if (is_array($entry)) {
                    foreach ($entry as $k => $entry) {
                        if ($entry) {
                            $entry = self::identify($entry);
                        }
                        $t['HangingIndentContextStack'][$i][$j][$k] = $entry;
                    }
                    continue;
                }
                $t['HangingIndentContextStack'][$i][$j] = self::identify($entry);
            }
        }

        $t['HangingIndentParentStack'] = $token->HangingIndentParentStack;
        $t['HangingIndentParentLevels'] = $token->HangingIndentParentLevels;
        $t['LinePadding'] = $token->LinePadding;
        $t['LineUnpadding'] = $token->LineUnpadding;
        $t['Padding'] = $token->Padding;
        $t['HeredocIndent'] = $token->HeredocIndent;
        $t['AlignedWith'] = $token->AlignedWith;
        $t['WhitespaceBefore'] = WhitespaceType::toWhitespace($token->WhitespaceBefore);
        $t['WhitespaceAfter'] = WhitespaceType::toWhitespace($token->WhitespaceAfter);
        $t['WhitespaceMaskPrev'] = $token->WhitespaceMaskPrev;
        $t['WhitespaceMaskNext'] = $token->WhitespaceMaskNext;
        $t['CriticalWhitespaceBefore'] = $token->CriticalWhitespaceBefore;
        $t['CriticalWhitespaceAfter'] = $token->CriticalWhitespaceAfter;
        $t['CriticalWhitespaceMaskPrev'] = $token->CriticalWhitespaceMaskPrev;
        $t['CriticalWhitespaceMaskNext'] = $token->CriticalWhitespaceMaskNext;
        $t['OutputLine'] = $token->OutputLine;
        $t['OutputPos'] = $token->OutputPos;
        $t['OutputColumn'] = $token->OutputColumn;

        foreach ($t as $key => &$value) {
            if ($value === null || $value === []) {
                unset($t[$key]);
                continue;
            }
            if ($value instanceof Token) {
                $value = self::identify($value);
                continue;
            }
            if (Arr::of($value, Token::class)) {
                /** @var Token[] $value */
                foreach ($value as &$token) {
                    $token = self::identify($token);
                }
                unset($token);
            }
        }
        unset($value);

        return $t;
    }

    public static function identify(Token $token): string
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
