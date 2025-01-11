<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\RuleTrait;
use Lkrms\PrettyPHP\Contract\Rule;
use Salient\Utility\Regex;

/**
 * Add newlines and spaces after tokens that would otherwise fail to parse
 *
 * @api
 */
final class EssentialSpacing implements Rule
{
    use RuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::BEFORE_RENDER => 999,
        ][$method] ?? null;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Newlines and spaces are added after tokens that would otherwise fail to
     * parse. This is to ensure that if an edge case not covered by other rules
     * arises, formatter output can still be parsed.
     */
    public function beforeRender(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $this->Idx->Virtual[$token->id]
                || !($next = $token->Next && $this->Idx->Virtual[$token->Next->id]
                    ? $token->Next->Data[Data::NEXT_REAL]
                    : $token->Next)
                || $next->String
                || $token->String
                || ($after = $token->getWhitespaceAfter()) & (Space::BLANK | Space::LINE)
            ) {
                continue;
            }

            // Add newlines after one-line comments with no subsequent close tag
            if (
                $token->Flags & Flag::ONELINE_COMMENT
                && $next->id !== \T_CLOSE_TAG
            ) {
                $token->applyWhitespace(Space::LINE_AFTER);
                continue;
            }

            if (
                $after
                || $this->Idx->SuppressSpaceAfter[$token->id]
                || $this->Idx->SuppressSpaceBefore[$next->id]
            ) {
                continue;
            }

            if (
                $token->id === \T_OPEN_TAG
                || Regex::match(
                    '/^[a-zA-Z0-9\\\\_\x80-\xff]{2}$/D',
                    ($token->text[-1] ?? '') . ($next->text[0] ?? '')
                )
            ) {
                $token->applyWhitespace(Space::SPACE_AFTER);
            }
        }
    }
}
