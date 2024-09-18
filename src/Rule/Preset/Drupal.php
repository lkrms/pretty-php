<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Salient\PHPDoc\PHPDoc;
use Throwable;

/**
 * Apply the Drupal code style
 *
 * - Add blank lines before and after non-empty `class`, `enum`, `interface` and
 *   `trait` bodies
 * - Add a blank line after PHP DocBlocks with a `@file` tag
 * - Add a newline after close braces with a subsequent `catch`, `else`,
 *   `elseif` or `finally`
 */
final class Drupal implements Preset, TokenRule
{
    use TokenRuleTrait;

    public static function getFormatter(int $flags = 0): Formatter
    {
        return (new FormatterBuilder())
                   ->insertSpaces()
                   ->tabSize(2)
                   ->enable([self::class])
                   ->flags($flags)
                   ->heredocIndent(HeredocIndent::NONE)
                   ->oneTrueBraceStyle()
                   ->build();
    }

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 100;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            // --
            \T_CLASS,
            \T_ENUM,
            \T_INTERFACE,
            \T_TRAIT,
            // --
            \T_DOC_COMMENT,
            // --
            \T_CATCH,
            \T_ELSE,
            \T_ELSEIF,
            \T_FINALLY,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Add blank lines before and after non-empty `class`, `enum`,
            // `interface` and `trait` bodies
            if ($this->Idx->DeclarationClass[$token->id]) {
                if (!$token->inNamedDeclaration()) {
                    continue;
                }

                $open = $token->nextSiblingOf(\T_OPEN_BRACE);
                if ($open->Next->id === \T_CLOSE_BRACE) {
                    continue;
                }

                $open->WhitespaceAfter |= WhitespaceType::BLANK;
                $open->WhitespaceMaskNext |= WhitespaceType::BLANK;
                $open->ClosedBy->WhitespaceBefore |= WhitespaceType::BLANK;
                $open->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::BLANK;

                continue;
            }

            // Add a blank line after PHP DocBlocks with a `@file` tag
            if ($token->id === \T_DOC_COMMENT) {
                try {
                    $phpDoc = new PHPDoc($token->text);
                } catch (Throwable $ex) {
                    continue;
                }

                if (array_key_exists('file', $phpDoc->TagsByName)) {
                    $token->WhitespaceAfter |= WhitespaceType::BLANK;
                    $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
                }

                continue;
            }

            // Add a newline after close braces with a subsequent `catch`, `else`,
            // `elseif` or `finally`
            if ($token->PrevCode->id === \T_CLOSE_BRACE) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
                $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
            }
        }
    }
}
