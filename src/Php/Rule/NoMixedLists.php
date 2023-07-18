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
 * Newlines are added before the first item in vertical lists. Newlines before
 * the first item in horizontal lists are removed when strict PSR-12 compliance
 * is enabled.
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
        if ($items->count() < 2) {
            return;
        }
        if ($items->nth(2)->hasNewlineBefore()) {
            $items->addWhitespaceBefore(WhitespaceType::LINE);
        } else {
            // Leave the first item alone unless strict PSR-12 compliance is
            // enabled
            if ($owner->ClosedBy && !$this->Formatter->Psr12Compliance) {
                $items->shift();
            }
            $items->maskWhitespaceBefore(~WhitespaceType::BLANK & ~WhitespaceType::LINE);
        }
    }
}
