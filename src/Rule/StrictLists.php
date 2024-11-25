<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;

/**
 * Arrange items in lists horizontally or vertically by replicating the
 * arrangement of the first and second items
 *
 * Newlines are added before the first item in vertical lists. Newlines before
 * the first item in horizontal lists are removed when strict PSR-12 compliance
 * is enabled.
 */
final class StrictLists implements ListRule
{
    use ListRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 370,
        ][$method] ?? null;
    }

    public function processList(Token $parent, TokenCollection $items): void
    {
        if ($items->count() < 2) {
            return;
        }
        if ($items->nth(2)->hasNewlineBefore()) {
            $items->applyWhitespace(Space::LINE_BEFORE);
        } else {
            // Leave the first item alone unless strict PSR-12 compliance is
            // enabled
            if ($parent->ClosedBy && !$this->Formatter->Psr12) {
                $items->shift();
            }
            $items->applyWhitespace(Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE);
        }
    }
}
