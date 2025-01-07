<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;

/**
 * Apply the Laravel code style
 *
 * @api
 */
final class Laravel implements Preset, TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 440,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_CONCAT => true,
            \T_LOGICAL_NOT => true,
            \T_FN => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Trailing spaces are added to:
     *
     * - `!` operators
     * - `fn` in arrow functions
     *
     * Leading and trailing spaces are suppressed for `.` operators.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_CONCAT) {
                $token->Whitespace |= Space::NO_SPACE_BEFORE | Space::NO_SPACE_AFTER;
                continue;
            }

            if ($token->id === \T_LOGICAL_NOT) {
                /** @var Token */
                $next = $token->Next;
                if ($next->id === \T_LOGICAL_NOT) {
                    continue;
                }
            }

            $token->applyWhitespace(Space::SPACE_AFTER);
        }
    }
}
