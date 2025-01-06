<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\DeclarationType;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;
use Salient\PHPDoc\PHPDoc;
use Throwable;

/**
 * Apply the Drupal code style
 *
 * @api
 */
final class Drupal implements Preset, TokenRule, DeclarationRule
{
    use TokenRuleTrait;
    use DeclarationRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getFormatter(int $flags = 0): Formatter
    {
        return Formatter::build()
                   ->insertSpaces()
                   ->tabSize(2)
                   ->enable([self::class])
                   ->flags($flags)
                   ->heredocIndent(HeredocIndent::NONE)
                   ->oneTrueBraceStyle()
                   ->build();
    }

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 420,
            self::PROCESS_DECLARATIONS => 420,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_DOC_COMMENT => true,
            \T_ELSEIF => true,
            \T_ELSE => true,
            \T_CATCH => true,
            \T_FINALLY => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getDeclarationTypes(array $all): array
    {
        return [
            DeclarationType::_CLASS => true,
            DeclarationType::_ENUM => true,
            DeclarationType::_INTERFACE => true,
            DeclarationType::_TRAIT => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedDeclarations(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Blank lines are added after DocBlocks with a `@file` tag.
     *
     * Newlines are added after close braces with a subsequent `elseif`, `else`,
     * `catch` or `finally`.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_DOC_COMMENT) {
                try {
                    $phpDoc = new PHPDoc($token->text);
                } catch (Throwable $ex) {
                    continue;
                }

                if ($phpDoc->hasTag('file')) {
                    $token->applyWhitespace(Space::BLANK_AFTER);
                }

                continue;
            }

            /** @var Token */
            $prev = $token->PrevCode;
            if ($prev->id === \T_CLOSE_BRACE) {
                $token->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }

    /**
     * Apply the rule to the given declarations
     *
     * Blank lines are added inside non-empty `class`, `enum`, `interface` and
     * `trait` braces.
     */
    public function processDeclarations(array $declarations): void
    {
        foreach ($declarations as $token) {
            $open = $token->nextSiblingOf(\T_OPEN_BRACE);

            /** @var Token */
            $next = $open->Next;
            if ($next->id === \T_CLOSE_BRACE) {
                continue;
            }

            /** @var Token */
            $close = $open->CloseBracket;

            $open->applyWhitespace(Space::BLANK_AFTER);
            $close->applyWhitespace(Space::BLANK_BEFORE);
        }
    }
}
