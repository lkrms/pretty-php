<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Token;

/**
 * @api
 *
 * @phpstan-require-implements Rule
 */
trait RuleTrait
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}

    /**
     * Suppress vertical whitespace between the given tokens if they were on the
     * same line
     *
     * @param bool $force If `true`, suppress vertical whitespace even if the
     * tokens were on different lines.
     * @param bool $sameStatement If `true`, only suppress vertical whitespace
     * if the tokens belong to the same statement.
     * @return bool `true` if vertical whitespace is suppressed, otherwise
     * `false`.
     */
    private function preserveOneLine(
        Token $start,
        Token $end,
        bool $force = false,
        bool $sameStatement = false
    ): bool {
        if (!$force && $start->line !== $end->line) {
            return false;
        }

        if ($sameStatement) {
            $from = $start->Flags & Flag::CODE ? $start : $start->NextCode;
            $to = $end->Flags & Flag::CODE ? $end : $end->PrevCode;
            if (
                $from
                && $to
                && $from->index <= $to->index
                && $from->Statement !== $to->Statement
            ) {
                return false;
            }
        }

        $start->collect($end)
              ->setInnerWhitespace(Space::CRITICAL_NO_BLANK | Space::CRITICAL_NO_LINE);

        return true;
    }
}
