<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\RuleTrait;
use Lkrms\Pretty\Php\Contract\Rule;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddEssentialWhitespace implements Rule
{
    use RuleTrait;

    public function getPriority(string $method): ?int
    {
        return $method === self::BEFORE_RENDER
            ? 999
            : null;
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->hasNewlineAfter() || $token->StringOpenedBy || $token->HeredocOpenedBy) {
                continue;
            }

            if ($token->isOneLineComment() && !$token->next()->is(T_CLOSE_TAG)) {
                $token->WhitespaceAfter            |= WhitespaceType::LINE;
                $token->WhitespaceMaskNext         |= WhitespaceType::LINE;
                $token->next()->WhitespaceMaskPrev |= WhitespaceType::LINE;

                continue;
            }

            if ($token->hasWhitespaceAfter() ||
                    $token->is(TokenType::SUPPRESS_SPACE_AFTER) ||
                    $token->next()->is(TokenType::SUPPRESS_SPACE_BEFORE)) {
                continue;
            }

            if ($token->is(T_OPEN_TAG) ||
                    preg_match('/^[a-zA-Z0-9\\\\_\x80-\xff]{2}$/', substr($token->text, -1) . substr($token->next()->text, 0, 1))) {
                $token->WhitespaceAfter            |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext         |= WhitespaceType::SPACE;
                $token->next()->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            }
        }
    }
}
