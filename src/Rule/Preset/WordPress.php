<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Preset\Internal\WordPressTokenIndex;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;

/**
 * Apply the WordPress code style
 *
 * @api
 */
final class WordPress implements Preset, TokenRule
{
    use TokenRuleTrait;

    private bool $DocCommentUnpinned;

    /**
     * @inheritDoc
     */
    public static function getFormatter(int $flags = 0): Formatter
    {
        return Formatter::build()
                   ->insertSpaces(false)
                   ->tabSize(4)
                   ->disable([DeclarationSpacing::class])
                   ->enable([
                       AlignData::class,
                       self::class,
                   ])
                   ->flags($flags)
                   ->tokenIndex(new WordPressTokenIndex())
                   ->oneTrueBraceStyle()
                   ->spacesBesideCode(1)
                   ->with('MaxAssignmentPadding', 40)
                   ->with('MaxDoubleArrowColumn', 60);
    }

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 480,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_COMMENT => true,
            \T_DOC_COMMENT => true,
            \T_COLON => true,
            \T_LOGICAL_NOT => true,
            \T_OPEN_BRACE => true,
            \T_OPEN_BRACKET => true,
            \T_OPEN_PARENTHESIS => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->DocCommentUnpinned = false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Suppression of blank lines after DocBlocks is removed for the first
     * DocBlock in each document.
     *
     * Blank lines added before DocBlocks by other rules are removed.
     *
     * Leading spaces are added to `:` in alternative syntax constructs.
     *
     * Trailing spaces are added to `!` operators.
     *
     * Suppression of blank lines inside braces is removed.
     *
     * Spaces are added inside non-empty:
     *
     * - parentheses
     * - square brackets (except in strings or when they enclose one inner token
     *   that is not a variable)
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->id === \T_COMMENT
                && !($token->Flags & TokenFlag::C_DOC_COMMENT)
            ) {
                continue;
            }

            if ($token->id === \T_DOC_COMMENT && !$this->DocCommentUnpinned) {
                $token->removeWhitespace(Space::NO_BLANK_AFTER);
                $this->DocCommentUnpinned = true;
            }

            if ($this->Idx->Comment[$token->id]) {
                /** @var Token */
                $prev = $token->Prev;
                if (
                    $token->hasBlankBefore()
                    && $token->line - $prev->line - substr_count($prev->text, "\n") < 2
                ) {
                    $token->Whitespace |= Space::NO_BLANK_BEFORE;
                }
                continue;
            }

            if ($token->id === \T_COLON) {
                if (!$token->CloseBracket) {
                    continue;
                }
                $token->applyWhitespace(Space::SPACE_BEFORE);
                continue;
            }

            if ($token->id === \T_LOGICAL_NOT) {
                /** @var Token */
                $next = $token->Next;
                if ($next->id === \T_LOGICAL_NOT) {
                    continue;
                }
                $token->applyWhitespace(Space::SPACE_AFTER);
                continue;
            }

            /** @var Token */
            $close = $token->CloseBracket;

            if ($token->id === \T_OPEN_BRACE) {
                $token->removeWhitespace(Space::NO_BLANK_AFTER);
                $close->removeWhitespace(Space::NO_BLANK_BEFORE);
                continue;
            }

            /** @var Token */
            $next = $token->Next;

            if ($close === $next || (
                $token->id === \T_OPEN_BRACKET && (
                    $token->String || (
                        $next->Next === $close
                        && $next->id !== \T_VARIABLE
                    )
                )
            )) {
                continue;
            }

            $token->applyWhitespace(Space::SPACE_AFTER);
            $close->applyWhitespace(Space::SPACE_BEFORE);
        }
    }
}
