<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Sort consecutive alias/import statements
 *
 */
final class SortImports implements Filter
{
    use FilterTrait;

    /**
     * @param Token[] $tokens
     * @return array{0:int,1:string}
     */
    private function sortableImport(array $tokens): array
    {
        switch ($tokens[1]->id ?? T_STRING) {
            case T_CONST:
                $order = 1;
                break;
            case T_FUNCTION:
                $order = 2;
                break;
            default:
                $order = 0;
                break;
        }

        return [
            $order,
            implode(
                ' ',
                array_map(
                    fn(Token $t) => $t->text,
                    array_filter(
                        $tokens,
                        fn(Token $t) => !$t->is(TokenType::COMMENT)
                    )
                )
            )
        ];
    }

    public function __invoke(array $tokens): array
    {
        $this->Tokens = array_values($tokens);
        $count        = count($this->Tokens);

        // Minimise unnecessary iterations by identifying relevant T_USE tokens
        // in advance and exiting early if possible
        $tokens = array_keys(array_filter(
            $this->Tokens,
            fn(Token $t, int $i) => $t->id === T_USE &&
                !(($prev = $this->prevCode($i, $prev_i))->id === T[')'] ||
                    ($prev->id === T['{'] &&
                        !$this->prevDeclarationOf($prev_i, T_CLASS, T_TRAIT)->IsNull)),
            ARRAY_FILTER_USE_BOTH
        ));
        if (!$tokens) {
            return $this->Tokens;
        }

        while ($tokens) {
            $i = array_shift($tokens);

            /** @var array<array<Token>> */
            $sort       = [];
            /** @var array<Token> */
            $current    = [];
            /** @var Token|null */
            $terminator = null;
            while ($i < $count) {
                $token = $this->Tokens[$i];
                if ($current) {
                    if ($terminator) {
                        // Collect comments that appear beside code, allowing
                        // other comments to break the sequence of statements
                        if ($token->is(TokenType::COMMENT) &&
                            ($token->line === $terminator->line ||
                                ($token->isOneLineComment() &&
                                    ($last = $this->Tokens[$i - 1])->isOneLineComment() &&
                                    $token->line - $last->line === 1))) {
                            $current[$i++] = $token;
                            continue;
                        } else {
                            $sort[]     = $current;
                            $current    = [];
                            $terminator = null;
                        }
                    } elseif ($token->id === T_CLOSE_TAG) {
                        /**
                         * Don't add improbable but technically legal statements
                         * like `use A\B\C ?>` to $sort
                         */
                        $current = [];
                    } else {
                        $current[$i++] = $token;
                        if ($token->id === T[';']) {
                            $terminator = $token;
                        }
                        continue;
                    }
                }
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
        $import   = reset($sort);
        $nextLine = reset($import)->line;
        $nextKey  = $firstKey = key($import);
        $unsorted = $sort;

        // Sort the alias/import statements
        uasort(
            $sort,
            function (array $a, array $b): int {
                $a = $this->sortableImport(array_values($a));
                $b = $this->sortableImport(array_values($b));

                return $a[0] <=> $b[0]
                           ?: strcasecmp($a[1], $b[1]);
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
                $t->line           += $delta;
            }
            $nextLine += substr_count($t->text, "\n") + 1;
        }

        Convert::arraySpliceAtKey($this->Tokens, $firstKey, count($sorted), $sorted);
    }
}
