<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\DeclarationType;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Apply formatting specific to Symfony's coding standards
 *
 * - Suppress horizontal space before and after '.'
 * - Add a space after 'fn' in arrow functions
 * - Suppress newlines between parameters in function declarations where none
 *   are promoted constructor parameters
 */
final class Symfony implements Preset, TokenRule, ListRule
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
                   ->tokenIndex(new TokenIndex(true))
                   ->heredocIndent(HeredocIndent::NONE)
                   ->importSortOrder(ImportSortOrder::NAME)
                   ->collapseEmptyDeclarationBodies(false)
                   ->collapseDeclareHeaders(false)
                   ->expandHeaders()
                   ->build();
    }

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 100,
            self::PROCESS_LIST => 100,
        ][$method] ?? null;
    }

    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_CONCAT => true,
            \T_FN => true,
        ];
    }

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

    public function processList(Token $parent, TokenCollection $items): void
    {
        if (!$parent->isParameterList()) {
            return;
        }

        foreach ($items as $item) {
            if (
                $item->Flags & TokenFlag::NAMED_DECLARATION
                && $item->Data[TokenData::NAMED_DECLARATION_TYPE] === DeclarationType::PARAM
            ) {
                return;
            }
        }

        $parent->outer()->applyInnerWhitespace(Space::NO_BLANK | Space::NO_LINE);
    }
}
