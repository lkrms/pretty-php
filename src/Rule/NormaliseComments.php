<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\CommentType;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\Utility\Pcre;

/**
 * Normalise comments
 *
 * @api
 */
final class NormaliseComments implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 70;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return TokenType::COMMENT;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Extract DocBlock content, preserving indentation if any lines
            // start with characters other than "*", and normalise as per PSR-5
            if ($token->id === T_DOC_COMMENT || $token->IsInformalDocComment) {
                $preserveAsterisk = false;
                $deindent = null;
                $asterisksAreMissing = Pcre::match('/\n\h*+(?!\*)\S/', $token->text);
                if ($asterisksAreMissing) {
                    $firstLineHasContent = Pcre::match(
                        '/^(\/\*++\h*+)(?!\*)\S/',
                        $token->ExpandedText ?? $token->text,
                        $matches
                    );
                    if ($firstLineHasContent) {
                        $deindent = $token->column - 1 + strlen($matches[1]);
                    }

                    // Get the indent, in spaces, of the character closest to
                    // column 1 that isn't an asterisk
                    $otherLinesHaveIndent = Pcre::matchAll(
                        '/^(?!\A)(\h*+)(?!\*)\S/m',
                        $token->ExpandedText ?? $token->text,
                        $matches,
                        PREG_SET_ORDER
                    );
                    if ($otherLinesHaveIndent) {
                        foreach ($matches as $match) {
                            $length = strlen($match[1]);
                            $deindent = min($deindent ?? $length, $length);
                        }
                    }

                    // Expand tabs to spaces if tabs don't appear first in every
                    // line where they are combined with spaces for indentation
                    $text = Pcre::match('/\n(?=\h)(?!\t* *(?!\h))/', $token->text)
                        ? $token->expandedText()
                        : $token->text;

                    if ($deindent) {
                        $tabs = (int) ($deindent / $this->Formatter->TabSize);
                        $spaces = $deindent % $this->Formatter->TabSize;
                        $regex = ($tabs
                            ? '(?:\t|' . str_repeat(' ', $this->Formatter->TabSize) . "){{$tabs}}"
                            : '') . str_repeat(' ', $spaces);

                        // If every non-blank inner line will be deindented,
                        // assume any leading asterisks are part of the content
                        if (!Pcre::match("/\\n(?!\h*(?:$regex(?!\h)|(\*+\/)?\$))/m", $text)) {
                            $preserveAsterisk = true;
                        }

                        $text = Pcre::replace("/\\n(\h*)$regex(?!\h)/", "\n\$1", $text);
                    }
                } else {
                    // In comments where every line starts with "*" and ends
                    // with "\h*", remove the trailing asterisks
                    $hasTrailingAsterisks = !Pcre::match(
                        '/(?<!\h\*)\h*(?!\z)$/m',
                        $token->text
                    );
                    if ($hasTrailingAsterisks) {
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

                // Remove comment delimiters and trailing whitespace
                $replace = ['#^/\*+#', '#\*+/$#', '#\h++$#m'];
                $with = ['', '', ''];

                // Remove "*" from the start of each line
                if (!$preserveAsterisk) {
                    $replace[] = '#\n\h*+\* ?#';
                    $with[] = "\n";
                }

                $text = trim(Pcre::replace($replace, $with, $text));

                // Collapse DocBlocks with one line of content to a single line
                // unless they describe a file or are pinned to a declaration
                if (
                    ($token->id === T_DOC_COMMENT ||
                        strpos($token->OriginalText ?? $token->text, "\n") === false) &&
                    strpos($text, "\n") === false &&
                    !($token->_nextCode && (
                        $token->_nextCode->id === T_DECLARE ||
                        $token->_nextCode->id === T_NAMESPACE || (
                            $token->_nextCode->id === T_USE &&
                            $token->_nextCode->getUseType() === TokenSubType::USE_IMPORT
                        )
                    )) && (
                        !($token->_next &&
                            $token->_next->Statement === $token->_next &&
                            $token->_next->isDeclaration() &&
                            $token->_next->id !== T_DECLARE) ||
                        ($token->_next->id === T_USE &&
                            $token->_next->getUseType() === TokenSubType::USE_TRAIT)
                    )
                ) {
                    $text = $text === '' ? ' */' : " $text */";
                } else {
                    $text = preg_replace(
                        ["/\n(?!\n)/", "/\n(?=\n)/"],
                        ["\n * ", "\n *"],
                        $text
                    );
                    $text = $text === '' ? "\n *\n */" : "\n * " . $text . "\n */";
                }
                $text = ($token->id === T_DOC_COMMENT ? '/**' : '/*') . $text;
                $token->setText($text);
                continue;
            }

            switch ($token->CommentType) {
                case CommentType::C:
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

                case CommentType::SHELL:
                    $token->setText('//' . substr($token->text, 1));
                    // No break
                case CommentType::CPP:
                    $token->setText(Pcre::replace('#^//(?=\S)#', '// ', $token->text));

                    break;
            }
        }
    }
}
