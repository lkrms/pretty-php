<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Filter\Concern\FilterTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Move comments with one or more subsequent delimiters to the position after
 * the last delimiter
 */
final class MoveComments implements Filter
{
    use FilterTrait;

    /**
     * @var array<int,bool>
     */
    private array $CommentIndex;

    /**
     * @var array<int,bool>
     */
    private array $DelimiterIndex;

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        if (isset($this->DelimiterIndex)) {
            return;
        }

        $this->CommentIndex = TokenType::getIndex(\T_COMMENT, \T_DOC_COMMENT);
        /** @todo Add support for `T_COLON`, `T_DOUBLE_ARROW` and others */
        $this->DelimiterIndex = TokenType::getIndex(\T_COMMA, \T_SEMICOLON);
    }

    public function filterTokens(array $tokens): array
    {
        $this->Tokens = array_values($tokens);
        $count = count($this->Tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $this->Tokens[$i];

            // Find one or more subsequent occurrences of:
            //
            //     1*(T_COMMENT / T_DOC_COMMENT)
            //     1*(T_COMMA / T_SEMICOLON)
            //
            if (!$this->CommentIndex[$token->id]) {
                continue;
            }

            $first = $i;
            $last = null;
            $line = $token->line;
            $tokens = [$token];
            $delimiters = [];
            while (++$i < $count) {
                $token = $this->Tokens[$i];
                if ($this->CommentIndex[$token->id]) {
                    $tokens[] = $token;
                    continue;
                }
                if ($this->DelimiterIndex[$token->id]) {
                    $last = $i;
                    $token->line = $line;
                    $delimiters[] = $token;
                    continue;
                }
                // For consistency, demote DocBlocks found before close brackets
                // without moving them
                if ($this->TypeIndex->CloseBracket[$token->id]) {
                    $last = $i;
                    $tokens[] = $token;
                }
                break;
            }

            if ($last === null) {
                continue;
            }

            $length = $last - $first + 1;

            // Discard any comments collected after the last delimiter
            $tokens = array_slice($tokens, 0, $length - count($delimiters));

            // Moving a DocBlock to the other side of a delimiter risks side
            // effects like documenting previously undocumented structural
            // elements, but DocBlocks before delimiters are invalid anyway, so
            // convert them to standard C-style comments
            foreach ($tokens as $token) {
                if ($token->id === \T_DOC_COMMENT) {
                    $token->id = \T_COMMENT;
                    $token->text[2] = ' ';
                }
            }

            $replacement = array_merge($delimiters, $tokens);
            array_splice($this->Tokens, $first, $length, $replacement);
        }

        return $this->Tokens;
    }
}
