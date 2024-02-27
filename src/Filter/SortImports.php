<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Filter\Concern\FilterTrait;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Pcre;

/**
 * Sort consecutive alias/import statements
 *
 * @api
 */
final class SortImports implements Filter
{
    use FilterTrait;

    /**
     * @var string[]
     */
    private array $Search;

    /**
     * @var string[]
     */
    private array $Replace;

    /**
     * @var array<int,array{int,string}>
     */
    private array $SortableImports;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        $this->Tokens = array_values($tokens);
        $this->SortableImports = [];
        $count = count($this->Tokens);

        // Identify relevant `T_USE` tokens and exit early if possible
        $tokens = [];
        /** @var array<int,Token> */
        $stack = [];
        foreach ($this->Tokens as $i => $token) {
            if ($this->TypeIndex->OpenBrace[$token->id]) {
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
            // Exclude `use` in:
            // - anonymous function declarations
            // - scopes other than the global scope and inside namespace
            //   declarations
            $prevCode = $this->prevCode($i);
            if ($prevCode && $prevCode->id === \T_CLOSE_PARENTHESIS) {
                continue;
            }
            $parent = array_key_last($stack);
            if ($parent === null || $this->isDeclarationOf($parent, \T_NAMESPACE)) {
                $tokens[] = $i;
            }
        }

        if (!$tokens) {
            return $this->Tokens;
        }

        while ($tokens) {
            $i = array_shift($tokens);

            /** @var array<non-empty-array<Token>> */
            $sort = [];
            /** @var Token[] */
            $current = [];
            /** @var Token|null */
            $terminator = null;
            while ($i < $count) {
                $token = $this->Tokens[$i];
                // If `$current` is non-empty, `$token` may be part of a `use`
                // statement that's being collected
                if ($current) {
                    // If `$terminator` is set, a `use` statement has been
                    // terminated, and token collection should only continue if
                    // `$token` is a subsequent comment on the same line, or a
                    // continuation thereof
                    if ($terminator) {
                        if ($this->TypeIndex->Comment[$token->id] &&
                            ($token->line === $terminator->line ||
                                ($this->isOneLineComment($i) &&
                                    $this->isOneLineComment($i - 1) &&
                                    $token->text[0] === $this->Tokens[$i - 1]->text[0] &&
                                    $token->line - $this->Tokens[$i - 1]->line === 1))) {
                            $current[$i++] = $token;
                            continue;
                        } else {
                            // Otherwise, finalise the `use` statement and add
                            // it to the sorting queue
                            $sort[] = $current;
                            $current = [];
                            $terminator = null;
                        }
                    } elseif ($token->id === \T_CLOSE_TAG) {
                        /* Exclude statements like `use A\B\C ?>` */
                        $current = [];
                        break;
                    } else {
                        if ($token->id === \T_SEMICOLON) {
                            $terminator = $token;
                        }
                        $current[$i++] = $token;
                        continue;
                    }
                }
                // This point is only reached when `$token` is:
                // - the first `T_USE` in a possible series of `use` statements
                // - the first token after a `use` statement is finalised
                if ($token->id !== \T_USE) {
                    break;
                }
                $current[$i++] = $token;
            }
            if ($current) {
                $sort[] = $current;
            }
            if (isset($sort[1])) {
                $this->Tokens = $this->sortImports($sort, $this->Tokens);
            }
            while ($tokens && $i > reset($tokens)) {
                array_shift($tokens);
            }
        }

        return $this->Tokens;
    }

    /**
     * @param non-empty-array<non-empty-array<Token>> $sort
     * @param Token[] $tokens
     * @return Token[]
     */
    private function sortImports(array $sort, array $tokens): array
    {
        $import = reset($sort);
        $nextLine = reset($import)->line;
        $nextKey = $firstKey = key($import);
        $unsorted = $sort;

        // Sort the alias/import statements
        uasort(
            $sort,
            function (array $a, array $b): int {
                $aIdx = array_key_first($a);
                $bIdx = array_key_first($b);
                $a = $this->SortableImports[$aIdx] ??= $this->sortableImport($a);
                $b = $this->SortableImports[$bIdx] ??= $this->sortableImport($b);

                return $a[0] <=> $b[0]           // 1 = `use function`, 2 = `use const`, otherwise 0
                    ?: strcasecmp($a[1], $b[1])  // e.g. "use 0A \ 2B" or "use A \ B"
                    ?: $aIdx <=> $bIdx;
            }
        );

        if ($sort === $unsorted) {
            return $tokens;
        }

        // Flatten, reindex, and update line numbers
        $sorted = [];
        foreach ($sort as $import) {
            $delta = $nextLine - reset($import)->line;
            foreach ($import as $t) {
                $sorted[$nextKey++] = $t;
                $t->line += $delta;
            }
            $nextLine += substr_count($t->text, "\n") + 1;
        }

        return Arr::spliceByKey($tokens, $firstKey, count($sorted), $sorted);
    }

    /**
     * @param Token[] $tokens
     * @return array{int,string}
     */
    private function sortableImport(array $tokens): array
    {
        $import = [];
        foreach ($tokens as $token) {
            if (
                $this->TypeIndex->Comment[$token->id] ||
                $token->id === \T_SEMICOLON
            ) {
                continue;
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
            $text[] = $token->text;
        }
        $import = implode(' ', $text);

        return [
            $order,
            Pcre::replace($this->Search, $this->Replace, $import),
        ];
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->SortableImports = [];

        if (isset($this->Replace)) {
            return;
        }

        // If sorting depth-first, normalise to:
        //
        // ```
        // use 2A
        // use 0A \ 0B \ 1{ D , E }
        // use 0A \ 0B \ 2C
        // use 0A \ 2B
        // ```
        //
        // Otherwise, normalise to:
        //
        // ```
        // use A
        // use A \ B \ D , E
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
