<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\GenericToken;
use Closure;

/**
 * Move comments if necessary for correct placement of adjacent delimiters and
 * operators
 *
 * @api
 */
final class MoveComments implements Filter
{
    use ExtensionTrait;

    /**
     * @var array<int,bool>
     */
    private array $BeforeCommentIndex;

    /**
     * @var array<int,bool>
     */
    private array $AfterCommentIndex;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        /** @todo Add support for ternary operators, `T_DOUBLE_ARROW` */
        $idx = $this->TypeIndex->withPreserveNewline();

        $this->BeforeCommentIndex = TokenType::intersectIndexes(
            TokenType::mergeIndexes(
                $this->TypeIndex->Movable,
                TokenType::getIndex(
                    \T_COMMA,
                    \T_SEMICOLON,
                    \T_EQUAL,
                ),
            ),
            $idx->PreserveNewlineAfter,
        );

        $this->AfterCommentIndex = TokenType::intersectIndexes(
            TokenType::mergeIndexes(
                $this->TypeIndex->Movable,
                TokenType::getIndex(
                    \T_LOGICAL_NOT,
                ),
            ),
            $idx->PreserveNewlineBefore,
        );
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
        //     1*(T_COMMA / T_SEMICOLON / T_EQUAL)
        //
        // Into this:
        //
        //     1*(T_COMMA / T_SEMICOLON / T_EQUAL)
        //     1*(T_COMMENT / T_DOC_COMMENT)
        //
        $tokens = $this->swapTokens(
            $tokens,
            $this->TypeIndex->Comment,
            $this->BeforeCommentIndex,
            // Moving a DocBlock to the other side of a delimiter risks side
            // effects like documenting previously undocumented structural
            // elements, but DocBlocks before delimiters are invalid anyway, so
            // convert them to standard C-style comments
            $callback = static function (array $comments): bool {
                /** @var T[] $comments */
                foreach ($comments as $token) {
                    if ($token->id === \T_DOC_COMMENT) {
                        $token->id = \T_COMMENT;
                        $token->text[2] = ' ';
                    }
                }
                return true;
            },
            null,
            // For consistency, also demote DocBlocks found before close
            // brackets etc., without moving the close brackets
            $this->TypeIndex->Undocumentable,
        );

        return $this->swapTokens(
            $tokens,
            $this->AfterCommentIndex,
            $this->TypeIndex->Comment,
            null,
            $callback,
        );
    }

    /**
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     * @param array<int,bool> $firstIdx
     * @param array<int,bool> $lastIdx
     * @param (Closure(array<int,T>): bool)|null $firstCallback
     * @param (Closure(array<int,T>): bool)|null $lastCallback
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

            // Index of first token in `$firstIdx`
            $first = $i;
            // Index of last token in `$lastIdx`
            $last = null;
            // Tokens in `$firstIdx`
            $firstTokens = [];
            // Tokens in `$lastIdx`
            $lastTokens = [];

            $i--;
            while (++$i < $count) {
                $token = $tokens[$i];

                if ($firstIdx[$token->id]) {
                    if (
                        $token->id === \T_DOC_COMMENT
                        && !$this->checkDocComment($i, $tokens, $count)
                    ) {
                        break;
                    }
                    $firstTokens[$i] = $token;
                    continue;
                }

                if ($lastIdx[$token->id]) {
                    if (
                        $token->id === \T_DOC_COMMENT
                        && !$this->checkDocComment($i, $tokens, $count)
                    ) {
                        break;
                    }
                    $last = $i;
                    $lastTokens[$i] = $token;
                    continue;
                }

                if ($keepLastIdx && $keepLastIdx[$token->id]) {
                    $last = $i;
                    $firstTokens[$i] = $token;
                }

                break;
            }

            if ($last === null) {
                continue;
            }

            $length = $last - $first + 1;

            // Discard any tokens in `$firstIdx` collected after the last token
            // in `$lastIdx`
            $firstTokens = array_slice($firstTokens, 0, $length - count($lastTokens), true);

            if (
                ($firstCallback && $firstCallback($firstTokens) === false)
                || ($lastCallback && $lastCallback($lastTokens) === false)
            ) {
                continue;
            }

            if ($keepLastIdx) {
                $lineTokens = $lastTokens;
                $prev = $tokens[$first - 1];
            } else {
                $lineTokens = $firstTokens;
                $prev = $tokens[$last];
            }
            $line = $prev->line + substr_count($prev->text, "\n");

            foreach ($lineTokens as $token) {
                $token->line = $line;
            }

            $replacement = $lastTokens + $firstTokens;
            array_splice($tokens, $first, $length, $replacement);
        }

        return $tokens;
    }

    /**
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     */
    private function checkDocComment(int $i, array $tokens, int $count): bool
    {
        // Find the next code token after the DocBlock, if any
        do {
            if (++$i >= $count) {
                return false;
            }

            if (!$this->TypeIndex->NotCode[$tokens[$i]->id]) {
                break;
            }
        } while (true);

        // Return `true` if the DocBlock can be safely demoted
        return $this->TypeIndex->Undocumentable[$tokens[$i]->id];
    }
}
