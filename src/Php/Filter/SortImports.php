<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Facade\Convert;
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\NullToken;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

use const Lkrms\Pretty\Php\T_ID_MAP;

/**
 * Sort consecutive alias/import statements
 *
 */
final class SortImports implements TokenFilter
{
    /**
     * @var Token[]
     */
    private $Tokens;

    private function prevCode(int $i, ?int &$prev_i = null): Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            $prev_i = $i;

            return $token;
        }

        return NullToken::create();
    }

    /**
     * @param int|string ...$types
     */
    private function prevDeclarationOf(int $i, ...$types): Token
    {
        while ($i--) {
            $token = $this->Tokens[$i];
            if ($token->is(TokenType::NOT_CODE)) {
                continue;
            }
            if (!$token->is(TokenType::DECLARATION_PART)) {
                break;
            }
            if ($token->is($types)) {
                return $token;
            }
        }

        return NullToken::create();
    }

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
        $terminated = false;
        $terminator = null;
        $next       = null;
        foreach ($this->Tokens as $i => $token) {
            if ($current) {
                if ($terminated) {
                    if ($token->is(TokenType::COMMENT)) {
                        $current[$i] = $token;
                        continue;
                    } else {
                        $sort[]     = $current;
                        $next       = $token;
                        $current    = [];
                        $terminated = false;
                    }
                } else {
                    $current[$i] = $token;
                    if ($token->is(T_ID_MAP[';'])) {
                        $terminated = true;
                        $terminator = $token;
                    }
                    continue;
                }
            }
            if (!$token->is(T_USE)) {
                if (!$sort) {
                    continue;
                }

                if (count($sort) > 1) {
                    /** @var Token $terminator */
                    $this->sortImports($this->Tokens, $sort, $terminator, $next);
                }

                $sort = [];
                continue;
            }
            if (!$sort) {
                // Ignore:
                // - `class <class> { use <trait> ...`
                // - `function() use (<variable> ...`
                $prev = $this->prevCode($i, $prev_i);
                if ($prev->is(T_ID_MAP[')']) ||
                    ($prev->is(T_ID_MAP['{']) &&
                        !$this->prevDeclarationOf($prev_i, T_CLASS, T_TRAIT)->isNull())) {
                    continue;
                }
            }
            $current[$i] = $token;
        }

        return $this->Tokens;
    }

    /**
     * @param Token[] $tokens
     * @param non-empty-array<Token[]> $sort
     */
    private function sortImports(array &$tokens, array $sort, Token $terminator, Token $next): void
    {
        // Remove tokens after the last ';' unless they were on the same line
        $last = array_pop($sort);
        while (end($last)->line > $terminator->line) {
            $next = array_pop($last);
        }
        $sort[] = $last;

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
                $firstKey = key($import);
            }
            $prev   = $import;
            $prev_i = $i;
        }
        $lineDeltas[$prev_i] = $next->line - reset($prev)->line;

        // Sort the alias/import statements
        uasort(
            $sort,
            function (array $a, array $b): int {
                $a = $this->sortableImport(array_values($a));
                $b = $this->sortableImport(array_values($b));

                return $a[0] <=> $b[0]
                    ?: $a[1] <=> $b[1];
            }
        );

        // Regenerate line numbers
        foreach ($sort as $i => $import) {
            $delta = $nextLine - reset($import)->line;
            array_walk($import, fn(Token $t) => $t->line += $delta);
            $nextLine += $lineDeltas[$i];
        }

        // Flatten $sort into an array of token objects
        $sort =
            array_reduce(
                $sort,
                fn(array $tokens, array $import) =>
                    $tokens + $import,
                []
            );

        Convert::arraySpliceAtKey($tokens, $firstKey, count($sort), $sort);
    }
}
