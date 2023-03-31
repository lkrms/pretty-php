<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

/**
 * Arrange items in lists horizontally or vertically by replicating the
 * arrangement of the first and second items
 *
 * This rule also:
 * - adds line breaks before the first item in vertical lists
 * - removes line breaks before the first item in horizontal lists when
 *   converting from a mixed list
 *
 */
final class NoMixedLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 370;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if ($items->count() < ($owner->ClosedBy ? 3 : 2)) {
            return;
        }
        if ($items->nth(2)->hasNewlineBefore()) {
            $items->addWhitespaceBefore(WhitespaceType::LINE);
        } else {
            // Leave the first item alone if the list is already completely
            // horizontal
            if ($owner->ClosedBy &&
                !$items->find(fn(Token $t, ?Token $next, ?Token $prev) =>
                                  $prev && $t->hasNewlineBefore())) {
                $items->shift();
            }
            $items->maskWhitespaceBefore(~WhitespaceType::BLANK & ~WhitespaceType::LINE);
        }
    }
}
