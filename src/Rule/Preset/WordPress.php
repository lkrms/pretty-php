<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Preset\Internal\WordPressTokenTypeIndex;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply the WordPress code style
 *
 * Specifically:
 *
 * - Add a space before alternative syntax ':' operators
 * - Add a space after '!' unless it appears before another '!'
 * - Add a space inside non-empty parentheses
 * - Add a space inside non-empty square brackets unless their first inner token
 *   is a T_CONSTANT_ENCAPSED_STRING
 */
final class WordPress implements Preset, TokenRule
{
    use TokenRuleTrait;

    private bool $DocCommentUnpinned = false;

    public static function getFormatter(int $flags = 0): Formatter
    {
        return (new FormatterBuilder())
                   ->insertSpaces(false)
                   ->tabSize(4)
                   ->disable([DeclarationSpacing::class])
                   ->enable([
                       AlignData::class,
                       self::class,
                   ])
                   ->flags($flags)
                   ->tokenTypeIndex(new WordPressTokenTypeIndex())
                   ->oneTrueBraceStyle()
                   ->spacesBesideCode(1)
                   ->with('RelaxAlignmentCriteria', true);
    }

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 100,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_COMMENT => true,
            \T_DOC_COMMENT => true,
            \T_COLON => true,
            \T_LOGICAL_NOT => true,
            \T_OPEN_BRACE => true,
            \T_CLOSE_BRACE => true,
            \T_OPEN_BRACKET => true,
            \T_OPEN_PARENTHESIS => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_COMMENT && !($token->Flags & TokenFlag::INFORMAL_DOC_COMMENT)) {
                continue;
            }

            if ($token->id === \T_DOC_COMMENT && !$this->DocCommentUnpinned) {
                $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
                if ($token->Next) {
                    $token->Next->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                }
                $this->DocCommentUnpinned = true;
            }

            if ($token->id === \T_DOC_COMMENT || $token->id === \T_COMMENT) {
                /** @var Token */
                $prev = $token->Prev;
                if (
                    $token->hasBlankLineBefore()
                    && $token->line - $prev->line - substr_count($prev->text, "\n") < 2
                ) {
                    $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                }
                continue;
            }

            if ($token->id === \T_COLON) {
                if (!$token->isColonAltSyntaxDelimiter()) {
                    continue;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                /** @var Token */
                $prev = $token->Prev;
                $prev->WhitespaceMaskNext |= WhitespaceType::SPACE;
                continue;
            }

            if ($token->id === \T_LOGICAL_NOT) {
                /** @var Token */
                $next = $token->Next;
                if ($next->id === \T_LOGICAL_NOT) {
                    continue;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                continue;
            }

            if ($token->id === \T_OPEN_BRACE) {
                /** @var Token */
                $next = $token->Next;
                $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
                $next->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                continue;
            }

            if ($token->id === \T_CLOSE_BRACE) {
                /** @var Token */
                $prev = $token->Prev;
                $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                $prev->WhitespaceMaskNext |= WhitespaceType::BLANK;
                continue;
            }

            // All that remains is T_OPEN_BRACKET and T_OPEN_PARENTHESIS
            if (
                !$token->Next
                || !$token->ClosedBy
                || !$token->ClosedBy->Prev
                || $token->ClosedBy === $token->Next
                || ($token->id === \T_OPEN_BRACKET && (
                    $token->String || (
                        $token->Next->Next === $token->ClosedBy
                        && $token->Next->id !== \T_VARIABLE
                    )
                ))
            ) {
                continue;
            }

            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
            $token->Next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            $token->ClosedBy->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            $token->ClosedBy->Prev->WhitespaceMaskNext |= WhitespaceType::SPACE;
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->DocCommentUnpinned = false;
    }
}
