<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;

/**
 * Arrange items in lists horizontally or vertically
 *
 * @api
 */
final class StrictLists implements ListRule
{
    use ListRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 370,
        ][$method] ?? null;
    }

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * Items in lists are arranged horizontally or vertically by replicating the
     * arrangement of the first and second items.
     */
    public function processList(Token $parent, TokenCollection $items): void
    {
        if ($items->count() < 2) {
            return;
        }

        /** @var Token */
        $second = $items->nth(2);
        if ($second->hasNewlineBefore()) {
            $items->applyWhitespace(Space::LINE_BEFORE);
        } else {
            $items->applyWhitespace(Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE);
        }
    }
}
