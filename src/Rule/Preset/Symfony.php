<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Apply the Symfony code style
 *
 * @api
 */
final class Symfony implements Preset, TokenRule, ListRule
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
                   ->tokenIndex(new TokenIndex(true))
                   ->heredocIndent(HeredocIndent::NONE)
                   ->importSortOrder(ImportSortOrder::NAME)
                   ->collapseEmptyDeclarationBodies(false)
                   ->collapseDeclareHeaders(false)
                   ->expandHeaders()
                   ->build();
    }

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 400,
            self::PROCESS_LIST => 400,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_CONCAT => true,
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
     * Trailing spaces are added to `fn` in arrow functions.
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

            $token->applyWhitespace(Space::SPACE_AFTER);
        }
    }

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * Newlines are suppressed between parameters in function declarations that
     * have no promoted constructor parameters.
     */
    public function processList(Token $parent, TokenCollection $items, Token $lastChild): void
    {
        if (!$parent->isParameterList()) {
            return;
        }

        foreach ($items as $item) {
            if ($item->Flags & TokenFlag::DECLARATION) {
                return;
            }
        }

        $parent->outer()->applyInnerWhitespace(Space::NO_BLANK | Space::NO_LINE);
    }
}
