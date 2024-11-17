<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply formatting specific to Symfony's coding standards
 *
 * - Suppress horizontal space before and after '.'
 * - Add a space after 'fn' in arrow functions
 * - Add a newline before parameters in constructor declarations where one or
 *   more are promoted
 * - Suppress newlines between parameters in other function declarations
 */
final class Symfony implements Preset, TokenRule, ListRule
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
                   ->tokenTypeIndex(new TokenTypeIndex(true))
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

    public static function getTokenTypes(TokenTypeIndex $idx): array
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
                $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
                continue;
            }
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
        }
    }

    public function processList(Token $parent, TokenCollection $items): void
    {
        if (!$parent->isParameterList()) {
            return;
        }

        foreach ($items as $item) {
            if ($this->Idx->Visibility[$item->id]) {
                foreach ($items as $item) {
                    $item->WhitespaceBefore |= WhitespaceType::LINE;
                }
                return;
            }
        }

        $parent->outer()->maskInnerWhitespace(~WhitespaceType::BLANK & ~WhitespaceType::LINE);
    }
}
