<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\CommentType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\RuleTrait;
use Lkrms\Pretty\Php\Contract\Rule;

/**
 * Add whitespace after tokens that would otherwise fail to parse
 *
 * This rule adds sufficient whitespace to ensure formatter output can be parsed
 * if an edge case not covered by other rules should arise.
 *
 * @api
 */
final class AddEssentialWhitespace implements Rule
{
    use RuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::BEFORE_RENDER:
                return 999;

            default:
                return null;
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($tokens as $token) {
            $next = $token->_next;
            if (!$next ||
                    $token->StringOpenedBy ||
                    $token->HeredocOpenedBy ||
                    $next->StringOpenedBy ||
                    $next->HeredocOpenedBy ||
                    $token->hasNewlineAfter()) {
                continue;
            }

            /* Add newlines after one-line comments with no subsequent `?>` */
            if ($token->CommentType &&
                    ($token->CommentType === CommentType::CPP ||
                        $token->CommentType === CommentType::SHELL) &&
                    $next->id !== T_CLOSE_TAG) {
                $token->WhitespaceAfter |= WhitespaceType::LINE;
                $token->WhitespaceMaskNext |= WhitespaceType::LINE;
                $next->WhitespaceMaskPrev |= WhitespaceType::LINE;
                continue;
            }

            if ($token->effectiveWhitespaceAfter() ||
                    $this->Formatter->TokenTypeIndex->SuppressSpaceAfter[$token->id] ||
                    $this->Formatter->TokenTypeIndex->SuppressSpaceBefore[$next->id]) {
                continue;
            }

            if ($token->id === T_OPEN_TAG ||
                    preg_match(
                        '/^[a-zA-Z0-9\\\\_\x80-\xff]{2}$/',
                        ($token->text[-1] ?? '') . ($next->text[0] ?? '')
                    )) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            }
        }
    }
}
