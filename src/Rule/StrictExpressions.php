<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Add newlines before and after control structure expressions that break over
 * multiple lines
 *
 * @api
 */
final class StrictExpressions implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 222,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->HasExpression;
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
     * Newlines are added before and after control structure expressions that
     * break over multiple lines. In `for` expressions that break over multiple
     * lines, newlines are also added after semicolons between expressions.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            /** @var Token */
            $open = $token->NextCode;
            if ($open->hasNewlineAfter()) {
                continue;
            }
            /** @var Token */
            $close = $open->CloseBracket;
            /** @var Token */
            $last = $close->Prev;
            if ($open->collect($last)->hasNewline()) {
                $open->applyWhitespace(Space::LINE_AFTER);
                $close->applyWhitespace(Space::LINE_BEFORE);
                if ($token->id === \T_FOR) {
                    /** @var TokenCollection */
                    $semicolons = $token->Data[Data::FOR_PARTS][3];
                    $semicolons->setTokenWhitespace(Space::LINE_AFTER);
                }
            }
        }
    }
}
