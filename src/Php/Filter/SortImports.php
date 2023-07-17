<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Catalog\ImportSortOrder;
use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken as Token;

/**
 * Sort consecutive alias/import statements
 *
 */
final class SortImports implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $this->Tokens = array_values($tokens);
        $count = count($this->Tokens);

        // Identify relevant T_USE tokens and exit early if possible
        $tokens = array_keys(array_filter(
            $this->Tokens,
            fn(Token $t, int $i) => $t->id === T_USE &&
                $this->prevCode($i)->id !== T_CLOSE_PARENTHESIS,
            ARRAY_FILTER_USE_BOTH
        ));
        if (!$tokens) {
            return $this->Tokens;
        }

        while ($tokens) {
            $i = array_shift($tokens);

            /** @var array<Token[]> */
            $sort = [];
            /** @var Token[] */
            $current = [];
            /** @var Token|null */
            $terminator = null;
            $inTraitAdaptation = false;
            while ($i < $count) {
                $token = $this->Tokens[$i];
                // If `$current` is non-empty, `$token` may be part of a `use`
                // statement that's being collected
                if ($current) {
                    // If `$terminator` is set, a `use` statement has been
                    // terminated, and token collection will only continue if
                    // `$token` is a subsequent comment on the same line, or a
                    // continuation thereof
                    if ($terminator) {
                        if ($token->is(TokenType::COMMENT) &&
                            ($token->line === $terminator->line ||
                                ($this->isOneLineComment($i) &&
                                    $this->isOneLineComment($i - 1) &&
                                    $token->line - $this->Tokens[$i - 1]->line === 1))) {
                            $current[$i++] = $token;
                            continue;
                        } else {
                            // Otherwise, the `use` statement is finalised and
                            // added to the sorting queue
                            $sort[] = $current;
                            $current = [];
                            $terminator = null;
                        }
                    } elseif ($token->id === T_CLOSE_TAG) {
                        /* Statements like `use A\B\C ?>` are discarded */
                        $current = [];
                        break;
                    } else {
                        if ($token->id === T_OPEN_BRACE) {
                            if ($this->prevCode($i)->id !== T_NS_SEPARATOR) {
                                $inTraitAdaptation = true;
                            }
                        } elseif ($token->id === T_CLOSE_BRACE) {
                            if ($inTraitAdaptation) {
                                $terminator = $token;
                                $inTraitAdaptation = false;
                            }
                        } elseif ($token->id === T_SEMICOLON && !$inTraitAdaptation) {
                            $terminator = $token;
                        }
                        $current[$i++] = $token;
                        continue;
                    }
                }
                // This point is only reached with the first token in a possible
                // series of `use` statements, and with the first token after a
                // `use` statement is finalised
                if ($token->id !== T_USE) {
                    break;
                }
                $current[$i++] = $token;
            }
            if ($current) {
                $sort[] = $current;
            }
            if ($sort[1] ?? null) {
                /** @var non-empty-array<non-empty-array<Token>> $sort */
                $this->sortImports($sort);
            }
            while ($tokens && $i > reset($tokens)) {
                array_shift($tokens);
            }
        }

        return $this->Tokens;
    }

    /**
     * @param non-empty-array<non-empty-array<Token>> $sort
     */
    private function sortImports(array $sort): void
    {
        $import = reset($sort);
        $nextLine = reset($import)->line;
        $nextKey = $firstKey = key($import);
        $unsorted = $sort;

        // Sort the alias/import statements
        uasort(
            $sort,
            function (array $a, array $b): int {
                $a = $this->sortableImport(array_values($a));
                $b = $this->sortableImport(array_values($b));

                return $a[0] <=> $b[0]
                    ?: strcasecmp($a[1], $b[1])
                    ?: $a[2] <=> $b[2];
            }
        );

        if ($sort === $unsorted) {
            return;
        }

        // Flatten, reindex, and update line numbers
        $sorted = [];
        foreach ($sort as $i => $import) {
            $delta = $nextLine - reset($import)->line;
            foreach ($import as $t) {
                $sorted[$nextKey++] = $t;
                $t->line += $delta;
            }
            $nextLine += substr_count($t->text, "\n") + 1;
        }

        Convert::arraySpliceAtKey($this->Tokens, $firstKey, count($sorted), $sorted);
    }

    /**
     * @param Token[] $tokens
     * @return array{int,string,int}
     */
    private function sortableImport(array $tokens): array
    {
        switch ($tokens[1]->id ?? T_STRING) {
            case T_FUNCTION:
                $order = 1;
                break;
            case T_CONST:
                $order = 2;
                break;
            default:
                $order = 0;
                break;
        }

        if ($this->Formatter->ImportSortOrder === ImportSortOrder::NONE) {
            return [$order, '', $tokens[0]->Index];
        }

        // Strip comments and semicolons and normalise to:
        //
        //     use 2A
        //     use 0A \ 0B \ 1{ D , E }
        //     use 0A \ 0B \ 2C
        //     use 0A \ 2B
        //
        // For output like:
        //
        //     use A\B\{D, E};
        //     use A\B\C;
        //     use A\B;
        //     use A;
        //
        $depth = $this->Formatter->ImportSortOrder === ImportSortOrder::DEPTH;
        $import = preg_replace(
            [
                '/\\\\/',
                '/\h++/',
                '/(?:^use(?: function| const)?|\\\\) (?=[^ \\\\{]+(?: [^\\\\]|$))/i',
                '/\\\\ (?=\{)/i',
                '/(?:^use(?: function| const)?|\\\\) (?=[^ \\\\]+ \\\\)/i',
            ],
            [
                ' \ ',
                ' ',
                $depth ? '${0}2' : '${0}0',
                $depth ? '${0}1' : '${0}1',
                $depth ? '${0}0' : '${0}0',
            ],
            array_reduce(
                array_filter(
                    $tokens,
                    fn(Token $t) => !$t->is(TokenType::COMMENT) &&
                        $t->id !== T_SEMICOLON
                ),
                fn($carry, Token $t) => ($carry ? $carry . ' ' : '') . $t->text,
            )
        );

        return [$order, $import, $tokens[0]->Index];
    }
}
