<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Salient\Core\Utility\Pcre;

/**
 * Normalise comments
 *
 * @api
 */
final class NormaliseComments implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 70;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return TokenType::COMMENT;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Extract DocBlock content and normalise as per PSR-5
            if (
                $token->id === \T_DOC_COMMENT
                || ($token->Flags & TokenFlag::INFORMAL_DOC_COMMENT)
            ) {
                // If any lines start with characters other than "*", detect and
                // preserve the indentation of undelimited lines if possible
                if (Pcre::match('/\n\h*+(?!\*)\S/', $token->text)) {
                    $expanded = $token->ExpandedText ?? $token->text;

                    // Get the indent, in spaces, of the character closest to
                    // column 1 that isn't an asterisk
                    if (
                        Pcre::match('/^(?!\A)(?!\*)\S/m', $expanded)
                        || !Pcre::matchAll(
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
                        if (Pcre::match('/^(\/\*++(?!\*)\h*+)\S/', $expanded, $matches)) {
                            $deindent = min($deindent, $token->column + strlen($matches[1]) - 1);
                        }
                    }

                    // If lines without leading asterisks all start inside the
                    // margin of the DocBlock, maintain their position unless
                    // the first line in the comment will be deindented, e.g.
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
                        && !Pcre::match(
                            "/^\/\*++\h*+\\n(?:\h*+(?:\*\h*+)?\\n)*$deindentRegex/",
                            $expanded,
                        )
                    ) {
                        $deindentRegex = $this->getIndentRegex($token->column + 2);
                    }

                    // Preserve asterisks aligned with "*" (preferred) or "/" in
                    // the opening "/*", whichever appears first
                    $indentRegex = $this->getIndentRegex($token->column);
                    $altRegex = $this->getIndentRegex($token->column - 1);
                    if (Pcre::match(
                        "/\\n(?:$indentRegex|(?<alt>$altRegex))\*/",
                        $expanded,
                        $matches,
                    ) && isset($matches['alt'])) {
                        $indentRegex = $altRegex;
                    }

                    // Expand tabs to spaces if tabs don't appear first in every
                    // line where they are combined with spaces for indentation
                    $text = Pcre::match('/\n(?=\h)(?!\t* *(?!\h))/', $token->text)
                        ? $token->expandedText()
                        : $token->text;

                    // Add missing asterisks
                    $text = Pcre::replace(
                        "/\\n(?!$indentRegex\*)(\h*)$deindentRegex(?!\h)/",
                        "\n* \$1",
                        $text,
                    );
                } else {
                    // In comments where every line starts with "*" and ends
                    // with "\h*", remove the trailing asterisks
                    if (!Pcre::match('/(?<!\h\*)\h*(?!\z)$/m', $token->text)) {
                        $text = Pcre::replace(
                            '/\h+\*\h*(?!\z)$/m',
                            '',
                            $token->text,
                            -1,
                            $count
                        );
                        if ($count < 2) {
                            $text = $token->text;
                        }
                    } else {
                        $text = $token->text;
                    }
                }

                // Remove comment delimiters, trailing whitespace, and "*" from
                // the start of each line
                $text = trim(Pcre::replace(
                    ['#^/\*+#', '#\*+/$#', '#\h++$#m', '#\n\h*+\* ?#'],
                    ['', '', '', "\n"],
                    $text,
                ));
                $token->Data[TokenData::COMMENT_CONTENT] = $text;

                // Collapse DocBlocks with one line of content to a single line
                // unless they describe a file or are pinned to a declaration
                // other than `use <trait>`, `global <variable>`, or `static
                // <variable>` where <variable> is not a property
                $collapse = false;
                if ((
                    ($token->id === \T_DOC_COMMENT && strpos($text, "\n") === false)
                    || strpos($token->OriginalText ?? $token->text, "\n") === false
                ) && !(
                    $token->NextCode && (
                        $token->NextCode->id === \T_DECLARE
                        || $token->NextCode->id === \T_NAMESPACE
                        || (
                            $token->NextCode->id === \T_USE
                            && $token->NextCode->getSubType() === TokenSubType::USE_IMPORT
                        )
                    )
                )) {
                    if (!(
                        $token->Next
                        && $token->Next->Statement === $token->Next
                        && $token->Next->isDeclaration()
                    ) || (
                        $token->Next->id === \T_USE
                        && $token->Next->getSubType() === TokenSubType::USE_TRAIT
                    ) || (
                        $token->Next->id === \T_GLOBAL
                        || ($token->Next->id === \T_STATIC
                            && $token->Parent
                            && $token->Parent->isFunctionBrace())
                    )) {
                        $collapse = true;
                    } else {
                        // @phpstan-ignore-next-line
                        $token->Flags |= TokenFlag::COLLAPSIBLE_COMMENT;
                    }
                }

                if ($collapse) {
                    $text = $text === '' ? ' */' : " $text */";
                } else {
                    $text = preg_replace(
                        ["/\n(?!\n)/", "/\n(?=\n)/"],
                        ["\n * ", "\n *"],
                        $text,
                    );
                    $text = $text === '' ? "\n *\n */" : "\n * " . $text . "\n */";
                }
                $text = ($token->id === \T_DOC_COMMENT ? '/**' : '/*') . $text;
                $token->setText($text);
                continue;
            }

            switch ($token->Flags & TokenFlagMask::COMMENT_TYPE) {
                case TokenFlag::C_COMMENT:
                    if (strpos($token->text, "\n") !== false) {
                        continue 2;
                    }

                    $token->setText(Pcre::replace([
                        '#^/\*+(?!\*)\h*+(?=\H)(.*)(?<=\H)\h*(?<!\*)\**/$#',
                        '#^/\*+\h++\*+/$#',
                    ], [
                        '/* $1 */',
                        '/* */',
                    ], $token->text));

                    break;

                case TokenFlag::SHELL_COMMENT:
                    $token->setText('//' . substr($token->text, 1));
                    // No break
                case TokenFlag::CPP_COMMENT:
                    $token->setText(Pcre::replace('#^//(?=\S)#', '// ', $token->text));

                    break;
            }
        }
    }

    private function getIndentRegex(int $indent): string
    {
        $tabs = (int) ($indent / $this->Formatter->TabSize);
        $spaces = $indent % $this->Formatter->TabSize;
        return ($tabs
            ? '(?:\t|' . str_repeat(' ', $this->Formatter->TabSize) . "){{$tabs}}"
            : '') . str_repeat(' ', $spaces);
    }
}
