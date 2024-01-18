<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\CommentType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Rule\Concern\RuleTrait;
use Lkrms\Utility\Pcre;

/**
 * Add whitespace after tokens that would otherwise fail to parse
 *
 * This rule adds sufficient whitespace to ensure formatter output can be parsed
 * if an edge case not covered by other rules should arise.
 *
 * @api
 */
final class EssentialWhitespace implements Rule
{
    use RuleTrait;

    public static function getPriority(string $method): ?int
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
                    $token->String ||
                    $next->String ||
                    $token->hasNewlineAfter()) {
                continue;
            }

            /* Add newlines after one-line comments with no subsequent `?>` */
            if ($token->CommentType &&
                    ($token->CommentType === CommentType::CPP ||
                        $token->CommentType === CommentType::SHELL) &&
                    $next->id !== \T_CLOSE_TAG) {
                $token->WhitespaceAfter |= WhitespaceType::LINE;
                $token->WhitespaceMaskNext |= WhitespaceType::LINE;
                $next->WhitespaceMaskPrev |= WhitespaceType::LINE;
                continue;
            }

            if ($token->effectiveWhitespaceAfter() ||
                    $this->TypeIndex->SuppressSpaceAfter[$token->id] ||
                    $this->TypeIndex->SuppressSpaceBefore[$next->id]) {
                continue;
            }

            if ($token->id === \T_OPEN_TAG ||
                    Pcre::match(
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
