<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\GenericToken;
use Closure;

/**
 * Move comments with one or more subsequent delimiters to the position after
 * the last delimiter
 *
 * @api
 */
final class MoveComments implements Filter
{
    use ExtensionTrait;

    /**
     * @var array<int,bool>
     */
    private array $DelimiterIndex;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        /** @todo Add support for `T_COLON`, `T_DOUBLE_ARROW` and others */
        $this->DelimiterIndex = TokenType::getIndex(\T_COMMA, \T_SEMICOLON);
    }

    /**
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     * @return list<T>
     */
    public function filterTokens(array $tokens): array
    {
        // Rearrange one or more of these:
        //
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //     1*(T_COMMA / T_SEMICOLON)
        //
        // Into one of these:
        //
        //     1*(T_COMMA / T_SEMICOLON)
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //
        return $this->swapTokens(
            $tokens,
            $this->TypeIndex->Comment,
            $this->DelimiterIndex,
            // Moving a DocBlock to the other side of a delimiter risks side
            // effects like documenting previously undocumented structural
            // elements, but DocBlocks before delimiters are invalid anyway, so
            // convert them to standard C-style comments
            static function (array $tokens): void {
                /** @var T[] $tokens */
                foreach ($tokens as $token) {
                    if ($token->id === \T_DOC_COMMENT) {
                        $token->id = \T_COMMENT;
                        $token->text[2] = ' ';
                    }
                }
            },
            null,
            // For consistency, also demote DocBlocks found before close
            // brackets, without moving the close brackets
            $this->TypeIndex->CloseBracket,
        );
    }

    /**
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     * @param array<int,bool> $firstIdx
     * @param array<int,bool> $lastIdx
     * @param (Closure(T[]): mixed)|null $firstCallback
     * @param (Closure(T[]): mixed)|null $lastCallback
     * @param array<int,bool>|null $keepLastIdx
     * @return list<T>
     */
    private function swapTokens(
        array $tokens,
        array $firstIdx,
        array $lastIdx,
        ?Closure $firstCallback,
        ?Closure $lastCallback,
        ?array $keepLastIdx = null
    ): array {
        $count = count($tokens);
        for ($i = 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!$firstIdx[$token->id]) {
                continue;
            }

            $prev = $tokens[$i - 1];
            $line = $prev->line + substr_count($prev->text, "\n");

            // Index of first token in `$firstIdx`
            $first = $i;
            // Index of last token in `$lastIdx`
            $last = null;
            // Tokens in `$firstIdx`
            $firstTokens = [$token];
            // Tokens in `$lastIdx`
            $lastTokens = [];

            while (++$i < $count) {
                $token = $tokens[$i];

                if ($firstIdx[$token->id]) {
                    $firstTokens[] = $token;
                    continue;
                }

                if ($lastIdx[$token->id]) {
                    $last = $i;
                    $token->line = $line;
                    $lastTokens[] = $token;
                    continue;
                }

                if ($keepLastIdx && $keepLastIdx[$token->id]) {
                    $last = $i;
                    $firstTokens[] = $token;
                }

                break;
            }

            if ($last === null) {
                continue;
            }

            $length = $last - $first + 1;

            // Discard any tokens in `$firstIdx` collected after the last token
            // in `$lastIdx`
            $firstTokens = array_slice($firstTokens, 0, $length - count($lastTokens));

            if ($firstCallback) {
                $firstCallback($firstTokens);
            }

            if ($lastCallback) {
                $lastCallback($lastTokens);
            }

            $replacement = array_merge($lastTokens, $firstTokens);
            array_splice($tokens, $first, $length, $replacement);
        }

        return $tokens;
    }
}
