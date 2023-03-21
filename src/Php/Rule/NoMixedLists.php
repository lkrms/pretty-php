<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Arrange items in lists horizontally or vertically by replicating the
 * arrangement of the first and second items
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
        if ($items->count() < 3) {
            return;
        }
        if ($items->nth(2)->hasNewlineBefore()) {
            $items->shift();
            $items->addWhitespaceBefore(WhitespaceType::LINE);
        } else {
            $items->shift();
            $items->maskWhitespaceBefore(~WhitespaceType::BLANK & ~WhitespaceType::LINE);
        }
    }
}
