<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

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
        return Formatter::build()
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

    public static function getTokens(TokenIndex $idx): array
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
                    /** @var Token */
                    $next = $token->Next;
                    if ($next->id === \T_LOGICAL_NOT) {
                        continue 2;
                    }
                    $token->applyWhitespace(Space::SPACE_AFTER);
                    continue 2;

                case \T_CONCAT:
                    $token->Whitespace |= Space::NO_SPACE_BEFORE | Space::NO_SPACE_AFTER;
                    continue 2;

                case \T_FN:
                    $token->applyWhitespace(Space::SPACE_AFTER);
                    continue 2;
            }
        }
    }
}
