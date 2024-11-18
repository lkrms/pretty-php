<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Concern\FilterTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\GenericToken;
use Salient\Utility\Regex;

/**
 * Sort consecutive alias/import statements
 *
 * @api
 */
final class SortImports implements Filter
{
    use FilterTrait;

    /** @var string[] */
    private array $Search;
    /** @var string[] */
    private array $Replace;
    /** @var array<int,array{int,string}> */
    private array $SortableImports;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        $class = get_class($tokens[0]);

        $this->Tokens = $tokens;
        $count = count($tokens);

        $useTokens = [];
        $stack = [];
        $isNamespace = [];
        foreach ($tokens as $i => $token) {
            if ($this->Idx->OpenBrace[$token->id]) {
                $stack[$i] = $token;
                continue;
            }
            if ($token->id === \T_CLOSE_BRACE) {
                array_pop($stack);
                continue;
            }
            if ($token->id !== \T_USE) {
                continue;
            }
            // Exclude `function () use (<variable>) {}`
            $prevCode = $this->getPrevCode($i);
            if ($prevCode && $prevCode->id === \T_CLOSE_PARENTHESIS) {
                continue;
            }
            // Exclude `class <name> { use <trait>; }`
            $parent = array_key_last($stack);
            if ($parent === null || (
                $isNamespace[$parent] ??=
                    $this->isDeclarationOf($parent, \T_NAMESPACE)
            )) {
                $useTokens[] = $i;
            }
        }

        if (!$useTokens) {
            return $tokens;
        }

        $seen = -1;
        foreach ($useTokens as $i) {
            if ($i <= $seen) {
                continue;
            }

            $sort = [];
            $current = [$i => $tokens[$i]];
            $removeLastSemicolon = false;
            while (++$i < $count) {
                $token = $tokens[$i];
                if ($token->id === \T_SEMICOLON) {
                    $current[$i] = $token;
                    // Collect subsequent comments on the same line and
                    // continuations thereof
                    while (
                        ++$i < $count
                        && $this->Idx->Comment[$tokens[$i]->id]
                        && ($tokens[$i]->line === $token->line || (
                            $this->isOneLineComment($i)
                            && $this->isOneLineComment($i - 1)
                            && $tokens[$i]->text[0] === $tokens[$i - 1]->text[0]
                            && $tokens[$i]->line - $tokens[$i - 1]->line === 1
                        ))
                    ) {
                        $current[$i] = $tokens[$i];
                    }
                    $sort[] = $current;
                    if ($i < $count && $tokens[$i]->id === \T_USE) {
                        $current = [$i => $tokens[$i]];
                        $seen = $i;
                        continue;
                    }
                    break;
                } elseif ($token->id === \T_CLOSE_TAG) {
                    /* Handle imports like `use A\B\C ?>` */
                    $current[$i] = new $class(\T_SEMICOLON, ';', $token->line, $token->pos);
                    $sort[] = $current;
                    $removeLastSemicolon = true;
                    break;
                }
                $current[$i] = $token;
            }
            if (count($sort) > 1) {
                $tokens = $this->sortImports($tokens, $sort, $removeLastSemicolon);
            }
        }

        return $tokens;
    }

    /**
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     * @param non-empty-array<non-empty-array<int,T>> $sort
     * @return list<T>
     */
    private function sortImports(array $tokens, array $sort, bool $removeLastSemicolon): array
    {
        $import = reset($sort);
        $nextLine = reset($import)->line;
        $nextKey = $firstKey = key($import);
        $unsorted = $sort;

        usort(
            $sort,
            function (array $a, array $b): int {
                $ai = array_key_first($a);
                $bi = array_key_first($b);
                $a = $this->SortableImports[$ai] ??= $this->sortableImport($a);
                $b = $this->SortableImports[$bi] ??= $this->sortableImport($b);

                return $a[0] <=> $b[0]           // 1 = `use function`, 2 = `use const`, otherwise 0
                    ?: strcasecmp($a[1], $b[1])  // e.g. "use 0A \ 2B" or "use A \ B"
                    ?: $ai <=> $bi;
            }
        );

        if ($sort === $unsorted) {
            return $tokens;
        }

        // Flatten, reindex, and update line numbers
        if ($removeLastSemicolon) {
            $last = array_key_last($sort);
        }
        $sorted = [];
        foreach ($sort as $i => $import) {
            $delta = $nextLine - reset($import)->line;
            foreach ($import as $t) {
                if ($removeLastSemicolon && $i === $last && $t->id === \T_SEMICOLON) {
                    continue;
                }
                $sorted[$nextKey++] = $t;
                $t->line += $delta;
            }
            $nextLine += substr_count($t->text, "\n") + 1;
        }

        /** @var list<T> */
        return array_slice($tokens, 0, $firstKey)
            + $sorted
            + $tokens;
    }

    /**
     * @param GenericToken[] $tokens
     * @return array{int,string}
     */
    private function sortableImport(array $tokens): array
    {
        $import = [];
        foreach ($tokens as $token) {
            if (
                $this->Idx->Comment[$token->id]
                || $token->id === \T_SEMICOLON
            ) {
                continue;
            }
            if ($token->id === \T_COMMA) {
                break;
            }
            $import[] = $token;
        }

        $order = [
            \T_FUNCTION => 1,
            \T_CONST => 2,
        ][$import[1]->id] ?? 0;

        if ($this->Formatter->ImportSortOrder === ImportSortOrder::NONE) {
            return [$order, ''];
        }

        $text = [];
        foreach ($import as $token) {
            if ($token->id === \T_AS) {
                $text[] = '[';
                continue;
            }
            $text[] = $token->text;
        }
        $import = implode(' ', $text);

        return [
            $order,
            Regex::replace($this->Search, $this->Replace, $import),
        ];
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->SortableImports = [];
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        // If sorting depth-first, normalise to:
        //
        // ```
        // use 2A
        // use 0A \ 0B \ 1{ D
        // use 0A \ 0B \ 2C
        // use 0A \ 2B
        // ```
        //
        // Otherwise, normalise to:
        //
        // ```
        // use A
        // use A \ B \ D
        // use A \ B \ C
        // use A \ B
        // ```
        $search = [
            '/\\\\/',
            '/\h++/',
        ];

        $replace = [
            ' \ ',
            ' ',
        ];

        switch ($this->Formatter->ImportSortOrder) {
            case ImportSortOrder::DEPTH:
                array_push(
                    $search,
                    '/(?:^use(?: function| const)?|\\\\) (?=[^ \\\\{]+(?: [^\\\\]|$))/i',
                    '/\\\\ (?=\{)/',
                    '/(?:^use(?: function| const)?|\\\\) (?=[^ \\\\]+ \\\\)/i',
                );
                array_push(
                    $replace,
                    '${0}2',
                    '${0}1',
                    '${0}0',
                );
                break;

            case ImportSortOrder::NAME:
            default:
                $search[] = '/(?<=\\\\ )\{ /';
                $replace[] = '';
                break;
        }

        $this->Search = $search;
        $this->Replace = $replace;
    }
}
