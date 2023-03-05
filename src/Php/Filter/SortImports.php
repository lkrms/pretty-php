<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Sort consecutive alias/import statements
 *
 */
final class SortImports implements TokenFilter
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

        /** @var array<Token[]> */
        $sort       = [];
        /** @var Token[] */
        $current    = [];
        /** @var Token|null */
        $terminator = null;

        $maybeEndCurrent = function () use (&$sort, &$current, &$terminator) {
            if (!$current) {
                return;
            }
            $sort[]     = $current;
            $current    = [];
            $terminator = null;
        };

        $maybeSort = function () use (&$sort) {
            if (!$sort) {
                return;
            }
            if (count($sort) > 1) {
                $this->sortImports($sort);
            }
            $sort = [];
        };

        foreach ($this->Tokens as $i => $token) {
            if ($current) {
                if ($terminator) {
                    // Collect comments that appear beside code, allowing other
                    // comments to break the sequence of statements
                    if ($token->is(TokenType::COMMENT) &&
                        ($token->line === $terminator->line ||
                            ($token->isOneLineComment() &&
                                ($last = end($current))->isOneLineComment() &&
                                $token->line - $last->line === 1))) {
                        $current[$i] = $token;
                        continue;
                    } else {
                        $maybeEndCurrent();
                    }
                } elseif ($token->is(T_CLOSE_TAG)) {
                    /**
                     * Don't add improbable but technically legal statements
                     * like `use A\B\C ?>` to $sort
                     */
                    $current = [];
                } else {
                    $current[$i] = $token;
                    if ($token->is(T[';'])) {
                        $terminator = $token;
                    }
                    continue;
                }
            }
            if (!$token->is(T_USE)) {
                $maybeSort();
                continue;
            }
            if (!$sort) {
                // Ignore:
                // - `class <class> { use <trait> ...`
                // - `function() use (<variable> ...`
                $prev = $this->prevCode($i, $prev_i);
                if ($prev->is(T[')']) ||
                    ($prev->is(T['{']) &&
                        !$this->prevDeclarationOf($prev_i, T_CLASS, T_TRAIT)->isNull())) {
                    continue;
                }
            }
            $current[$i] = $token;
        }
        $maybeEndCurrent();
        $maybeSort();

        return $this->Tokens;
    }

    /**
     * @param array<Token[]> $sort
     */
    private function sortImports(array $sort): void
    {
        // Store changes in line number from each import statement to the next
        $lineDeltas = [];
        /** @var Token[]|null */
        $prev       = null;
        $prev_i     = null;
        foreach ($sort as $i => $import) {
            if ($prev) {
                $lineDeltas[$prev_i] = reset($import)->line - reset($prev)->line;
            } else {
                $nextLine = reset($import)->line;
                $nextKey  = $firstKey = key($import);
            }
            $prev   = $import;
            $prev_i = $i;
        }
        $lineDeltas[$prev_i] = end($prev)->line - reset($prev)->line + 1;

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

        // Flatten, reindex, and update line numbers
        $sorted = [];
        foreach ($sort as $i => $import) {
            $delta = $nextLine - reset($import)->line;
            foreach ($import as $t) {
                $sorted[$nextKey++] = $t;
                $t->line           += $delta;
            }
            $nextLine += $lineDeltas[$i];
        }

        Convert::arraySpliceAtKey($this->Tokens, $firstKey, count($sorted), $sorted);
    }
}
