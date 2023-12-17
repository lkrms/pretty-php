<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Exception\RuleException;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\Utility\Pcre;

/**
 * Normalise escape sequences in strings, and replace single- and double-quoted
 * strings with the most readable and economical syntax
 *
 * Single-quoted strings are preferred unless one or more characters require
 * escaping, or the double-quoted equivalent is shorter.
 *
 * @api
 */
final class NormaliseStrings implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 60;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            \T_CONSTANT_ENCAPSED_STRING,
            \T_ENCAPSED_AND_WHITESPACE,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore nowdocs
            if ($token->id !== \T_CONSTANT_ENCAPSED_STRING &&
                    $token->String->id === \T_START_HEREDOC &&
                    substr($token->String->text, 0, 4) === "<<<'") {
                continue;
            }

            // Characters in $escape are backslash-escaped
            $escape = '';

            // Characters in $match trigger suppression of single-quoted syntax
            // when found in a T_CONSTANT_ENCAPSED_STRING
            $match = '';

            // Characters in $reserved have a special meaning after backslash
            $reserved = '';

            // Don't escape line breaks unless they are already escaped
            if (!$token->hasNewline() ||
                ($token->_next->id === \T_END_HEREDOC &&
                    strpos(substr($token->text, 0, -1), "\n") === false)) {
                $escape .= "\n\r";
                $match .= '\n\r';
            }

            $string = '';
            $doubleQuote = '';
            $suffix = '';
            if ($token->id === \T_CONSTANT_ENCAPSED_STRING) {
                eval("\$string = {$token->text};");
                $doubleQuote = '"';
                $escape .= '"';
                $reserved .= '"';
            } elseif ($token->String->id === \T_DOUBLE_QUOTE) {
                eval("\$string = \"{$token->text}\";");
                $escape .= '"';
                $reserved .= '"';
            } elseif ($token->String->id === \T_BACKTICK) {
                // Convert backtick-enclosed substrings to double-quoted
                // equivalents by escaping '\"' and '"', and unescaping '\`'
                $text = Pcre::replaceCallback(
                    '/((?<!\\\\)(?:\\\\\\\\)*)(\\\\?"|\\\\`)/',
                    fn(array $matches) =>
                        $matches[1]
                            . ($matches[2] === '\"'
                                ? '\\\\\\"'
                                : ($matches[2] === '"'
                                    ? '\"'
                                    : '`')),
                    $token->text
                );
                eval("\$string = \"{$text}\";");
                $escape .= '`';
                $reserved .= '`';
            } elseif ($token->String->id === \T_START_HEREDOC) {
                $start = trim($token->String->text);
                $text = $token->text;
                $end = trim($token->String->StringClosedBy->text);
                if ($token->_next->id === \T_END_HEREDOC) {
                    $text = substr($text, 0, -1);
                    $suffix = "\n";
                }
                eval("\$string = {$start}\n{$text}\n{$end};");
            } else {
                throw new RuleException(
                    sprintf('Not a string delimiter: %s', CustomToken::toName($token->String->id))
                );
            }

            // If $string contains valid UTF-8 sequences, don't escape leading
            // bytes (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
            if (mb_check_encoding($string, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
            }

            // \0..\t\v\f\x0e..\x1f is equivalent to \0..\x1f without \n and \r
            $double = addcslashes($string, "\0..\t\v\f\x0e..\x1f\$\\{$escape}");

            // Convert octal notation to hexadecimal (e.g. "\177" to "\x7f") and
            // correct for differences between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
            $double = Pcre::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches) =>
                    $matches[1]
                        . ($matches['octal'] !== null
                            ? (($dec = octdec($matches['octal']))
                                ? ($dec === 27 ? '\e' : sprintf('\x%02x', $dec))
                                : '\0')
                            : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']])),
                $double,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL
            );

            // Remove unnecessary backslashes
            $reserved = "[nrtvef\\\\\${$reserved}]|[0-7]|x[0-9a-fA-F]|u\{[0-9a-fA-F]+\}";

            if ($token->id === \T_CONSTANT_ENCAPSED_STRING ||
                    $token->_next !== $token->String->StringClosedBy ||
                    $token->String->id !== \T_START_HEREDOC) {
                $reserved .= '|$';
            }

            $double = Pcre::replace(
                "/(?<!\\\\)\\\\\\\\(?!{$reserved})/",
                '\\',
                $double
            );

            // "\\\{$a}" becomes "\\\{", which escapes to "\\\\{", but we need
            // the brace to remain escaped lest it become a T_CURLY_OPEN
            if ($token->id !== \T_CONSTANT_ENCAPSED_STRING &&
                    ($token->_next !== $token->String->StringClosedBy)) {
                $double = Pcre::replace(
                    '/(?<!\\\\)(\\\\(?:\\\\\\\\)*)\\\\(\{)$/',
                    '$1$2',
                    $double
                );
            }

            $double = $doubleQuote
                . $this->maybeEscapeEscapes($double, $reserved)
                . $suffix
                . $doubleQuote;

            // Use the double-quoted variant if escape sequences remain after
            // unescaping tabs used for indentation
            if ($this->Formatter->Tab === "\t") {
                $double = Pcre::replaceCallback(
                    '/^(?:\\\\t)+(?=\S|$)/m',
                    fn(array $matches) =>
                        str_replace('\t', "\t", $matches[0]),
                    $double,
                );
                if ($token->id !== \T_CONSTANT_ENCAPSED_STRING ||
                        Pcre::match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string) ||
                        Pcre::match('/(?<!\\\\)(?:\\\\\\\\)*\\\\t/', $double)) {
                    $token->setText($double);
                    continue;
                }
            } elseif ($token->id !== \T_CONSTANT_ENCAPSED_STRING ||
                    Pcre::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)) {
                $token->setText($double);
                continue;
            }

            $single = "'" . $this->maybeEscapeEscapes(Pcre::replace(
                "/(?:\\\\(?=\\\\)|(?<=\\\\)\\\\)|\\\\(?='|\$)|'/",
                '\\\\$0',
                $string
            )) . $suffix . "'";

            if (mb_strlen($single) <= mb_strlen($double)) {
                $token->setText($single);
                continue;
            }

            $token->setText($double);
        }
    }

    private function maybeEscapeEscapes(string $string, string $reserved = "['\\\\]"): string
    {
        // '\Name\\' is valid but confusing, so replace '\' with '\\' in strings
        // where every backslash other than the trailing '\\' is singular
        if (($string[-1] ?? null) === '\\' &&
                Pcre::matchAll('/(?<!\\\\)\\\\\\\\(?!\\\\)/', $string) === 1 &&
                !Pcre::match("/(?<!\\\\)\\\\(?={$reserved})(?!\\\\\$)/", $string) &&
                strpos($string, '\\\\\\') === false) {
            return Pcre::replace("/(?<!\\\\)\\\\(?!{$reserved})/", '\\\\$0', $string);
        }
        return $string;
    }
}
