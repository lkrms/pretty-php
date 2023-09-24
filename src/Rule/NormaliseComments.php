<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\CommentType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\Utility\Pcre;

/**
 * Normalise one-line comments
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
                return 780;

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
            switch ($token->CommentType) {
                case CommentType::C:
                case CommentType::DOC_COMMENT:
                    if (strpos($token->text, "\n") !== false) {
                        continue 2;
                    }

                    $token->setText(Pcre::replace([
                        '#^/\*\*\h++(?=\H)(.*)(?<=\H)\h*(?<!\*)\*+/$#',
                        '#^/\*\*\h++\*+/$#',
                        '#^/(?:\*|\*{3,}+|\*\*(?!\s))(?!\*)\h*+(?=\H)(.*)(?<=\H)\h*(?<!\*)\**/$#',
                        '#^/(?:\*|\*{3,}+)\h++\*+/$#',
                    ], [
                        '/** $1 */',
                        '/** */',
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
