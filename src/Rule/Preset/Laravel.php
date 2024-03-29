<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\BlankLineBeforeReturn;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;

/**
 * Apply Laravel's code style
 *
 * Specifically:
 * - Add a space after '!' unless it appears before another '!'
 * - Suppress horizontal space before and after '.'
 * - Add a space after 'fn' in arrow functions
 */
final class Laravel implements Preset, TokenRule
{
    use TokenRuleTrait;

    public static function getFormatter(int $flags = 0): Formatter
    {
        return (new FormatterBuilder())
                   ->enable([
                       BlankLineBeforeReturn::class,
                       self::class,
                   ])
                   ->flags($flags)
                   ->heredocIndent(HeredocIndent::NONE)
                   ->go();
    }

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 100;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_LOGICAL_NOT,
            \T_CONCAT,
            \T_FN,
        ];
    }

    public function processToken(Token $token): void
    {
        switch ($token->id) {
            case \T_LOGICAL_NOT:
                if (($token->Next->id ?? null) === \T_LOGICAL_NOT) {
                    return;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                return;

            case \T_CONCAT:
                $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
                return;

            case \T_FN:
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                return;
        }
    }
}
