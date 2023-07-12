<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

/**
 * Explode arrays and arguments with a trailing comma into one item per line
 *
 */
final class ApplyMagicComma implements ListRule
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

        if ($owner->ClosedBy->prevCode()->id === T_COMMA &&
            !($owner->prevCode()->id === T_LIST ||
                (($adjacent = $owner->adjacent(T_COMMA, T_CLOSE_BRACKET)) && $adjacent->id === T_EQUAL) ||
                (($root = $owner->withParentsWhile(T_OPEN_BRACKET)->last()) &&
                    $root->prevCode()->id === T_AS &&
                    $root->parent()->prevCode()->id === T_FOREACH))) {
            $items[] = $owner->ClosedBy;
            $items->addWhitespaceBefore(WhitespaceType::LINE, true);
        }
    }
}
