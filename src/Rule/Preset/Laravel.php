<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Lkrms\PrettyPHP\TokenTypeIndex;

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
                       BlankBeforeReturn::class,
                       self::class,
                   ])
                   ->flags($flags)
                   ->heredocIndent(HeredocIndent::NONE)
                   ->build();
    }

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 100,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_LOGICAL_NOT => true,
            \T_CONCAT => true,
            \T_FN => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            switch ($token->id) {
                case \T_LOGICAL_NOT:
                    if (($token->Next->id ?? null) === \T_LOGICAL_NOT) {
                        continue 2;
                    }
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                    continue 2;

                case \T_CONCAT:
                    $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
                    continue 2;

                case \T_FN:
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                    continue 2;
            }
        }
    }
}
