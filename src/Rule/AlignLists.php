<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Rule\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align arguments, array elements and other list items with their
 * owners
 */
final class AlignLists implements ListRule
{
    use ListRuleTrait;

    /**
     * @var array<int,true>
     */
    private $ListOwnersByIndex = [];

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_LIST:
                return 400;

            default:
                return null;
        }
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        $first = $items->first();
        $lastToken = $owner->ClosedBy
            ?: $items->last()->pragmaticEndOfExpression();

        // Do nothing if:
        // - an interface list has a leading line break, or
        // - the list does not break over multiple lines
        if ((!$owner->ClosedBy &&
                    $owner->hasNewlineBeforeNextCode()) ||
                !$first->collect($lastToken)->hasNewline()) {
            return;
        }

        $items->forEach(
            fn(Token $item) =>
                $item->AlignedWith = $owner
        );

        $this->Formatter->registerCallback(
            $this,
            $first,
            fn() => $this->alignList($owner, $items, $first, $lastToken),
            710
        );

        $this->ListOwnersByIndex[$owner->Index] = true;
    }

    private function alignList(Token $owner, TokenCollection $items, Token $first, Token $lastToken): void
    {
        $callback =
            function (Token $item, Token $to, int $delta) use ($owner) {
                if (!$delta) {
                    return;
                }
                while (($adjacent = $to->lastSiblingBeforeNewline()) !== $to &&
                    ($adjacent->id !== \T_OPEN_BRACE ||
                        !$adjacent->isStructuralBrace() ||
                        count($adjacent->BracketStack) > count($owner->BracketStack))) {
                    $to = $adjacent;
                }
                while (($adjacent = $to->adjacentBeforeNewline(false)) &&
                    ($adjacent->id !== \T_OPEN_BRACE ||
                        !$adjacent->isStructuralBrace() ||
                        count($adjacent->BracketStack) > count($owner->BracketStack))) {
                    $to = $adjacent->pragmaticEndOfExpression();
                }
                $item->collect($to)->forEach(
                    fn(Token $t) => $t->LinePadding += $delta
                );
            };

        if (!$owner->hasNewlineBeforeNextCode()) {
            $delta = $owner->alignmentOffset() + ($owner->ClosedBy ? 0 : 1);
            $callback($first, $lastToken, $delta);
        }

        $items->forEach(
            fn(Token $item, ?Token $next) =>
                $callback(
                    $item,
                    $next
                        ? $next->prevCode(2)
                        : ($owner->ClosedBy
                            ? $owner->ClosedBy->prevCode()
                            : $item->pragmaticEndOfExpression()),
                    $item->alignmentOffset(false, $this->ListOwnersByIndex[$item->Index] ?? false)
                )
        );
    }

    public function reset(): void
    {
        $this->ListOwnersByIndex = [];
    }
}
