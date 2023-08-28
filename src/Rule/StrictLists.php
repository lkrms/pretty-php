<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Arrange items in lists horizontally or vertically by replicating the
 * arrangement of the first and second items
 *
 * Newlines are added before the first item in vertical lists. Newlines before
 * the first item in horizontal lists are removed when strict PSR-12 compliance
 * is enabled.
 *
 */
final class StrictLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_LIST:
                return 370;

            default:
                return null;
        }
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
