<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Format control structures with unenclosed bodies
 *
 * @api
 */
final class ControlStructureSpacing implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 122,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->HasOptionalBraces;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * If the body of a control structure has no enclosing braces:
     *
     * - a newline is added after the body (if empty), or before and after the
     *   body (if non-empty)
     * - blank lines before the body are suppressed
     * - blank lines after the body are suppressed if the control structure
     *   continues
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (!($token->Flags & Flag::UNENCLOSED_PARENT)) {
                continue;
            }

            $open = $token->nextSiblingOf(\T_OPEN_UNENCLOSED);
            $continues = $open->Data[Data::UNENCLOSED_CONTINUES];
            $inner = $open->inner();
            /** @var Token */
            $close = $open->CloseBracket;
            $body = $inner->getFirstNotFrom($this->Idx->Comment);

            // `$body` can only be `null` if the body is a close tag
            if (!$body) {
                $open->applyWhitespace(Space::LINE_AFTER | Space::SPACE_AFTER);
            } elseif ($body->id !== \T_SEMICOLON) {
                $body->applyWhitespace(Space::LINE_BEFORE | Space::SPACE_BEFORE);
            }
            $open->Whitespace |= Space::NO_BLANK_AFTER;

            // If the structure continues, `$close` is bound to `elseif`, `else`
            // or `while`, otherwise it's bound to the end of the structure
            if ($continues) {
                $close->applyWhitespace(Space::LINE_BEFORE | Space::SPACE_BEFORE);
                $close->Whitespace |= Space::NO_BLANK_BEFORE;
            } else {
                $close->applyWhitespace(Space::LINE_AFTER | Space::SPACE_AFTER);
            }

            if ($this->Formatter->DetectProblems) {
                $end = $inner->last();
                $this->Formatter->registerProblem(
                    '%s body has no enclosing braces',
                    $body ?? $open,
                    $end,
                    $token->getTokenName(),
                );
            }
        }
    }
}
