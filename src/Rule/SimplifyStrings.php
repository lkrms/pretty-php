<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Exception\RuleException;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\TokenUtility;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * Normalise escape sequences in strings, and replace single- and double-quoted
 * strings with the most readable and economical syntax
 *
 * Single-quoted strings are preferred unless one or more characters require
 * escaping, or the double-quoted equivalent is shorter.
 *
 * @api
 */
final class SimplifyStrings implements TokenRule
{
    use TokenRuleTrait;

    private const INVISIBLE = '/^' . Regex::INVISIBLE_CHAR . '$/u';

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 60;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_CONSTANT_ENCAPSED_STRING => true,
            \T_ENCAPSED_AND_WHITESPACE => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        $string = '';
        foreach ($tokens as $token) {
            if ($token->id === \T_ENCAPSED_AND_WHITESPACE) {
                /** @var Token */
                $openedBy = $token->String;
                // Ignore nowdocs
                if (
                    $openedBy->id === \T_START_HEREDOC
                    && Str::startsWith($openedBy->text, "<<<'")
                ) {
                    continue;
                }
            } else {
                $openedBy = $token;
            }

            /** @var Token */
            $next = $token->Next;

            // Characters to backslash-escape
            $escape = "\r";

            // Matches characters that suppress single-quoted syntax
            $match = '\r';

            // Matches characters that are special after backslash
            $reserved = '';

            // Don't escape newlines unless they are already escaped
            if (!$token->hasNewline() || (
                $next->id === \T_END_HEREDOC
                && strpos(substr($token->text, 0, -1), "\n") === false
            )) {
                $escape .= "\n";
                $match .= '\n';
            }

            $doubleQuote = '';
            $suffix = '';
            switch ($openedBy->id) {
                case \T_CONSTANT_ENCAPSED_STRING:
                    eval("\$string = {$token->text};");
                    $doubleQuote = '"';
                    $escape .= '"';
                    $reserved .= '"';
                    break;

                case \T_DOUBLE_QUOTE:
                    eval("\$string = \"{$token->text}\";");
                    $escape .= '"';
                    $reserved .= '"';
                    break;

                case \T_BACKTICK:
                    $text = TokenUtility::unescapeBackticks($token->text);
                    eval("\$string = \"{$text}\";");
                    $escape .= '`';
                    $reserved .= '`';
                    break;

                case \T_START_HEREDOC:
                    /** @var Token */
                    $closedBy = $openedBy->StringClosedBy;

                    $start = trim($openedBy->text);
                    $text = $token->text;
                    $end = trim($closedBy->text);
                    if ($next->id === \T_END_HEREDOC) {
                        $text = substr($text, 0, -1);
                        $suffix = "\n";
                    }
                    eval("\$string = {$start}\n{$text}\n{$end};");
                    break;

                default:
                    // @codeCoverageIgnoreStart
                    throw new RuleException(sprintf(
                        'Not a string delimiter: %s',
                        $openedBy->getTokenName(),
                    ));
                    // @codeCoverageIgnoreEnd
            }

            // If $string contains valid UTF-8 sequences, don't escape leading
            // bytes (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
            $utf8 = false;
            if (mb_check_encoding($string, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
                $utf8 = true;
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
            }

            // \0..\t\v\f\x0e..\x1f is equivalent to \0..\x1f without \n and \r
            $double = addcslashes($string, "\0..\t\v\f\x0e..\x1f\$\\{$escape}");

            // Convert ignorable code points to "\u{xxxx}" unless they belong to
            // an extended grapheme cluster, i.e. a recognised Unicode sequence
            $utf8Escapes = 0;
            if ($utf8) {
                $double = Regex::replaceCallback(
                    '/(?![\x00-\x7f])\X/u',
                    function (array $matches) use (&$utf8Escapes): string {
                        if (!Regex::match(self::INVISIBLE, $matches[0])) {
                            return $matches[0];
                        }
                        $utf8Escapes++;
                        return sprintf('\u{%04X}', mb_ord($matches[0]));
                    },
                    $double,
                );
            }

            // Convert octal notation to hexadecimal (e.g. "\177" to "\x7f") and
            // correct for differences between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
            $double = Regex::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<NUL>000(?![0-7]))|(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches): string =>
                    $matches[1]
                        . ($matches['NUL'] !== null
                            ? '\0'
                            : ($matches['octal'] !== null
                                ? (($dec = octdec($matches['octal'])) === 27
                                    ? '\e'
                                    : sprintf('\x%02x', $dec))
                                : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']]))),
                $double,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL
            );

            // Remove unnecessary backslashes
            $reserved = "[nrtvef\\\\\${$reserved}]|[0-7]|x[0-9a-fA-F]|u\{[0-9a-fA-F]+\}";

            if ($token->id === \T_CONSTANT_ENCAPSED_STRING
                    || $next !== $openedBy->StringClosedBy
                    || $openedBy->id !== \T_START_HEREDOC) {
                $reserved .= '|$';
            }

            $double = Regex::replace(
                "/(?<!\\\\)\\\\\\\\(?!{$reserved})/",
                '\\',
                $double
            );

            // "\\\{$a}" becomes "\\\{", which escapes to "\\\\{", but we need
            // the brace to remain escaped lest it become a T_CURLY_OPEN
            if (
                $token->id !== \T_CONSTANT_ENCAPSED_STRING
                && $next !== $openedBy->StringClosedBy
            ) {
                $double = Regex::replace(
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
                $double = Regex::replaceCallback(
                    '/^(?:\\\\t)+(?=\S|$)/m',
                    fn(array $matches) =>
                        str_replace('\t', "\t", $matches[0]),
                    $double,
                );
                if ($token->id !== \T_CONSTANT_ENCAPSED_STRING
                        || $utf8Escapes
                        || Regex::match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)
                        || Regex::match('/(?<!\\\\)(?:\\\\\\\\)*\\\\t/', $double)) {
                    $token->setText($double);
                    continue;
                }
            } elseif ($token->id !== \T_CONSTANT_ENCAPSED_STRING
                    || $utf8Escapes
                    || Regex::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $string)) {
                $token->setText($double);
                continue;
            }

            $single = "'" . $this->maybeEscapeEscapes(Regex::replace(
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
        if (
            $string !== ''
            && $string[-1] === '\\'
            && Regex::matchAll('/(?<!\\\\)\\\\\\\\(?!\\\\)/', $string) === 1
            && !Regex::match("/(?<!\\\\)\\\\(?={$reserved})(?!\\\\\$)/", $string)
            && strpos($string, '\\\\\\') === false
        ) {
            return Regex::replace("/(?<!\\\\)\\\\(?!{$reserved})/", '\\\\$0', $string);
        }
        return $string;
    }
}
