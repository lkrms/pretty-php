<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Token\TokenCollection;

/**
 * Explode arrays and arguments with a trailing comma into one item per line
 *
 */
final class MagicLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 360;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if (!$owner->ClosedBy) {
            return;
        }

        if ($owner->ClosedBy->prevCode()->id === T_COMMA) {
            $items[] = $owner->ClosedBy;
            $items->addWhitespaceBefore(WhitespaceType::LINE, true);
        }
    }
}