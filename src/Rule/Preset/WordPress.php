<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Support\WordPressTokenTypeIndex;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;

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
                   ->tokenTypeIndex(WordPressTokenTypeIndex::create())
                   ->oneTrueBraceStyle()
                   ->spacesBesideCode(1)
                   ->with('IncreaseIndentBetweenUnenclosedTags', false)
                   ->with('RelaxAlignmentCriteria', true);
    }

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 100;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_COMMENT,
            \T_DOC_COMMENT,
            \T_COLON,
            \T_LOGICAL_NOT,
            \T_OPEN_BRACE,
            \T_CLOSE_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->id === \T_COMMENT && !$token->IsInformalDocComment) {
            return;
        }

        if ($token->id === \T_DOC_COMMENT && !$this->DocCommentUnpinned) {
            $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
            $this->DocCommentUnpinned = true;
        }

        if ($token->id === \T_DOC_COMMENT || $token->id === \T_COMMENT) {
            if ($token->hasBlankLineBefore() &&
                    $token->line - $token->Prev->line - substr_count($token->Prev->text, "\n") < 2) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            }
            return;
        }

        if ($token->id === \T_COLON) {
            if (!$token->startsAlternativeSyntax()) {
                return;
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            return;
        }

        if ($token->id === \T_LOGICAL_NOT) {
            if ($token->Next->id === \T_LOGICAL_NOT) {
                return;
            }
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
            return;
        }

        if ($token->id === \T_OPEN_BRACE) {
            $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
            return;
        }

        if ($token->id === \T_CLOSE_BRACE) {
            $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
            return;
        }

        // All that remains is T_OPEN_BRACKET and T_OPEN_PARENTHESIS
        if ($token->ClosedBy === $token->Next ||
            ($token->id === \T_OPEN_BRACKET &&
                ($token->String ||
                    ($token->Next->Next === $token->ClosedBy &&
                        $token->Next->id !== \T_VARIABLE)))) {
            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;
        $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
        $token->Next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
        $token->ClosedBy->WhitespaceBefore |= WhitespaceType::SPACE;
        $token->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::SPACE;
        $token->ClosedBy->Prev->WhitespaceMaskNext |= WhitespaceType::SPACE;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->DocCommentUnpinned = false;
    }
}
