<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask as Mask;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Regex;

/**
 * Normalise comments
 *
 * @api
 */
final class NormaliseComments implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 40,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->Comment;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * In one-line C-style comments (`/*`), unnecessary asterisks are removed
     * from both delimiters, the remaining content is trimmed, and a space is
     * added between delimiters and adjacent content.
     *
     * Shell-style comments (`#`) are converted to C++-style comments (`//`).
     *
     * In C++-style comments (`//`), a space is added between the delimiter and
     * adjacent content if horizontal whitespace is not already present.
     *
     * DocBlocks are normalised for PSR-5 compliance as follows:
     *
     * - An asterisk is added to the start of each line that doesn't have one.
     *   The indentation of undelimited lines relative to each other is
     *   maintained if possible.
     * - If every line starts and ends with an asterisk, trailing asterisks are
     *   removed.
     * - Trailing whitespace is removed from each line.
     * - The content of each DocBlock is applied to its token as
     *   `COMMENT_CONTENT` data.
     * - DocBlocks with one line of content are collapsed to a single line
     *   unless they appear to describe a file or have a subsequent named
     *   declaration. In the latter case, the `COLLAPSIBLE_COMMENT` flag is
     *   applied.
     *
     * Multi-line C-style comments where every line starts with an asterisk, or
     * at least one delimiter appears on its own line, receive the same
     * treatment as DocBlocks.
     *
     * > Multi-line C-style comments that do not meet this criteria are trimmed
     * > and may be reindented by the renderer.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $type = $token->Flags & Mask::COMMENT_TYPE;
            $text = $token->text;

            if (
                $type === Flag::C_COMMENT
                && !($token->Flags & Flag::C_DOC_COMMENT)
            ) {
                if ($token->hasNewline()) {
                    continue;
                }
                // Remove unnecessary asterisks and collapse empty comments
                $token->setText(Regex::replace([
                    '#^/\*++(?!\*)\h*+(?=\H)(.*)(?<=\H)\h*(?<!\*)\**/$#D',
                    '#^/\*++\h++\*+/$#D',
                ], [
                    '/* $1 */',
                    '/* */',
                ], $text));
                continue;
            }

            if ($token->Flags & Flag::ONELINE_COMMENT) {
                if ($type === Flag::SHELL_COMMENT) {
                    $text = '//' . substr($text, 1);
                }
                $token->setText(Regex::replace('#^//(?=\S)#', '// ', $text));
                continue;
            }

            // If any lines start with characters other than "*", detect and
            // preserve undelimited line indentation if possible
            if (Regex::match('/\n\h*+(?!\*)\S/', $text)) {
                $expanded = $token->ExpandedText ?? $text;

                // Get the column of the non-whitespace character closest to
                // column 1 that isn't an asterisk
                if (
                    Regex::match('/^(?!\A)(?!\*)\S/m', $expanded)
                    || !Regex::matchAll(
                        '/^(?!\A)(\h++)(?!\*)\S/m',
                        $expanded,
                        $matches,
                        \PREG_SET_ORDER,
                    )
                ) {
                    $deindent = 0;
                } else {
                    $deindent = null;

                    foreach ($matches as $match) {
                        $length = strlen($match[1]);
                        $deindent = min($deindent ?? $length, $length);
                    }

                    // Take any content on the first line into account
                    if (Regex::match('/^(\/\*++(?!\*)\h*+)\S/', $expanded, $matches)) {
                        $deindent = min($deindent, $token->column + strlen($matches[1]) - 1);
                    }
                }

                // If lines without leading asterisks all start inside the
                // margin of the DocBlock, maintain their position unless the
                // first line in the comment will be deindented, e.g.
                //
                //     /*
                //         if ($foo) {
                //             bar();
                //         }
                //     */
                $deindentRegex = $deindent
                    ? $this->getIndentRegex($deindent)
                    : '';
                if (
                    $deindent > $token->column + 2
                    && !Regex::match(
                        "/^\/\*++\h*+\\n(?:\h*+(?:\*\h*+)?\\n)*$deindentRegex/",
                        $expanded,
                    )
                ) {
                    $deindentRegex = $this->getIndentRegex($token->column + 2);
                }

                // Preserve asterisks aligned with "*" (preferred) or "/" in the
                // opening "/*", whichever appears first
                $indentRegex = $this->getIndentRegex($token->column);
                $altRegex = $this->getIndentRegex($token->column - 1);
                if (Regex::match(
                    "/\\n(?:$indentRegex|(?<alt>$altRegex))\*/",
                    $expanded,
                    $matches,
                    \PREG_UNMATCHED_AS_NULL,
                ) && $matches['alt'] !== null) {
                    $indentRegex = $altRegex;
                }

                // Expand leading tabs if tabs don't appear first in every line
                // where they are combined with spaces for indentation
                if (Regex::match('/\n(?=\h)(?!\t* *(?!\h))/', $text)) {
                    $text = $token->expandText();
                }

                // Add missing asterisks
                $text = Regex::replace(
                    "/\\n(?!$indentRegex\*)(\h*)$deindentRegex(?!\h)/",
                    "\n* \$1",
                    $text,
                );
            }

            // In comments where every line starts with an asterisk and ends
            // with `" *"` or `"\t*"`, remove the latter
            if (!Regex::match('/(?<!\h\*)\h*(?!\z)$/m', $text)) {
                $text = Regex::replace(
                    '/\h+\*\h*(?!\z)$/m',
                    '',
                    $text,
                );
            }

            // Remove comment delimiters, trailing whitespace, and "*" from the
            // start of each line
            $text = trim(Regex::replace(
                ['#^/\*+#', '#\*+/$#D', '#\h++$#m', '#\n\h*+\* ?#'],
                ['', '', '', "\n"],
                $text,
            ));
            $token->Data[Data::COMMENT_CONTENT] = $text;

            $isDocComment = $token->id === \T_DOC_COMMENT;
            $collapse = false;
            if ($isDocComment && strpos($text, "\n") === false) {
                $next = $this->getNextStatement($token);
                $declType = $next && $next->Flags & Flag::DECLARATION
                    ? $next->Data[Data::DECLARATION_TYPE]
                    : 0;
                if (
                    (!$declType && $token->NextSibling)
                    || $declType === Type::USE_TRAIT
                ) {
                    $collapse = true;
                } elseif ($declType && !($declType & (
                    Type::_DECLARE
                    | Type::_NAMESPACE
                    | Type::_USE
                ))) {
                    $token->Flags |= Flag::COLLAPSIBLE_COMMENT;
                }
            }

            if ($collapse) {
                $text = $text === '' ? ' */' : " $text */";
            } else {
                $text = Regex::replace(
                    ["/\n(?!\n)/", "/\n(?=\n)/"],
                    ["\n * ", "\n *"],
                    $text,
                );
                $text = $text === '' ? "\n *\n */" : "\n * " . $text . "\n */";
            }
            $text = ($isDocComment ? '/**' : '/*') . $text;
            $token->setText($text);
        }
    }

    private function getIndentRegex(int $indent): string
    {
        $tabs = (int) ($indent / $this->Formatter->TabSize);
        $spaces = $indent % $this->Formatter->TabSize;
        return ($tabs
            ? "(?:\\t|{$this->Formatter->SoftTab}){{$tabs}}"
            : '') . str_repeat(' ', $spaces);
    }

    /**
     * Get the next statement a given DocBlock could describe, or null if no
     * such statement is found
     */
    private function getNextStatement(Token $token): ?Token
    {
        $next = $token->NextCode;
        if (!$next || $next !== $next->Statement) {
            return null;
        }
        if ($next === $token->Next) {
            return $next;
        }
        /** @var Token */
        $from = $token->Next;
        /** @var Token */
        $to = $next->Prev;
        return $from->collect($to)->hasOneOf(\T_DOC_COMMENT)
            ? null
            : $next;
    }
}
