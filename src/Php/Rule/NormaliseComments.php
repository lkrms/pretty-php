<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\CommentType;
use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Normalise one-line comments
 *
 */
final class NormaliseComments implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 780;
    }

    public function getTokenTypes(): array
    {
        return TokenType::COMMENT;
    }

    public function processToken(Token $token): void
    {
        if (strpos($token->text, "\n") !== false) {
            return;
        }
        if (in_array(
            $token->CommentType,
            [CommentType::C, CommentType::DOC_COMMENT]
        )) {
            // Normalise comments like these:
            // - `/*  comment  */`  => `/* comment */`
            // - `/**comment*/`     => `/** comment */`
            // - `/**comment **/`   => `/** comment **/`
            //
            // Without modifying comments like these:
            // - `/*  comment  **/` (mismatched asterisks)
            // - `/***comment*/` (more than two leading asterisks)
            $token->setText(preg_replace(
                '#^(/\*(\*?))(?!\*)\h*+(?=\H)(.*)(?<=\H)\h*(?<!\*)(\2?\*/)$#',
                '$1 $3 $4',
                $token->text
            ));
        }
    }
}
