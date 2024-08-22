<?php declare(strict_types=1);

(function () {
    $maybeDefined = [
        // PHP 8.0
        'T_ATTRIBUTE' => true,
        'T_MATCH' => true,
        'T_NAME_FULLY_QUALIFIED' => true,
        'T_NAME_QUALIFIED' => true,
        'T_NAME_RELATIVE' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        // PHP 8.1
        'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' => true,
        'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => true,
        'T_ENUM' => true,
        'T_READONLY' => true,
        // PHP 8.4
        'T_PROPERTY_C' => false,
        // Custom
        'T_ATTRIBUTE_COMMENT' => false,
        'T_END_ALT_SYNTAX' => false,
        'T_NULL' => false,
    ];

    $defined = [];
    $toDefine = [];
    foreach ($maybeDefined as $token => $defineIfMissing) {
        if (!defined($token)) {
            if ($defineIfMissing) {
                $toDefine[] = $token;
            }
            continue;
        }

        /** @var mixed */
        $id = constant($token);

        // Fail if tokens defined by other libraries are invalid or do not have
        // unique IDs
        if (!is_int($id) || !($id < 0 || $id > 255)) {
            throw new Error(sprintf('%s is invalid', $token));
        }

        if (isset($defined[$id])) {
            throw new Error(sprintf(
                '%s and %s have the same ID',
                $token,
                $defined[$id],
            ));
        }

        $defined[$id] = $token;
    }

    $getNextId = function () use ($defined, &$id): int {
        $id++;
        while (isset($defined[$id])) {
            $id++;
        }
        return $id;
    };

    // Define missing tokens, skipping IDs already in use
    $id = 10000;
    foreach ($toDefine as $token) {
        define($token, $getNextId());
    }

    // Define PHP 8.4 tokens
    defined('T_PROPERTY_C') || define('T_PROPERTY_C', $getNextId());

    // Define custom tokens
    $id = 20000;
    defined('T_ATTRIBUTE_COMMENT') || define('T_ATTRIBUTE_COMMENT', $getNextId());
    defined('T_END_ALT_SYNTAX') || define('T_END_ALT_SYNTAX', $getNextId());
    defined('T_NULL') || define('T_NULL', $getNextId());

    // Define single-character tokens because the text of a token cannot be
    // relied upon to determine its type, e.g. the following tests may be true
    // when `$token->id === T_ENCAPSED_AND_WHITESPACE`, which means they are not
    // equivalent to `$token->id === ord('}')`:
    //
    // ```
    // $token->is('}');
    // $token->text === '}';
    // ```
    $isValid = function (string $token, string $char): bool {
        if (!defined($token)) {
            return false;
        }
        if (constant($token) === ord($char)) {
            return true;
        }
        throw new Error(sprintf('%s is invalid', $token));
    };

    $isValid('T_LOGICAL_NOT', '!') || define('T_LOGICAL_NOT', ord('!'));
    $isValid('T_DOUBLE_QUOTE', '"') || define('T_DOUBLE_QUOTE', ord('"'));
    $isValid('T_DOLLAR', '$') || define('T_DOLLAR', ord('$'));
    $isValid('T_MOD', '%') || define('T_MOD', ord('%'));
    $isValid('T_AND', '&') || define('T_AND', ord('&'));
    $isValid('T_OPEN_PARENTHESIS', '(') || define('T_OPEN_PARENTHESIS', ord('('));
    $isValid('T_CLOSE_PARENTHESIS', ')') || define('T_CLOSE_PARENTHESIS', ord(')'));
    $isValid('T_MUL', '*') || define('T_MUL', ord('*'));
    $isValid('T_PLUS', '+') || define('T_PLUS', ord('+'));
    $isValid('T_COMMA', ',') || define('T_COMMA', ord(','));
    $isValid('T_MINUS', '-') || define('T_MINUS', ord('-'));
    $isValid('T_CONCAT', '.') || define('T_CONCAT', ord('.'));
    $isValid('T_DIV', '/') || define('T_DIV', ord('/'));
    $isValid('T_COLON', ':') || define('T_COLON', ord(':'));
    $isValid('T_SEMICOLON', ';') || define('T_SEMICOLON', ord(';'));
    $isValid('T_SMALLER', '<') || define('T_SMALLER', ord('<'));
    $isValid('T_EQUAL', '=') || define('T_EQUAL', ord('='));
    $isValid('T_GREATER', '>') || define('T_GREATER', ord('>'));
    $isValid('T_QUESTION', '?') || define('T_QUESTION', ord('?'));
    $isValid('T_AT', '@') || define('T_AT', ord('@'));
    $isValid('T_OPEN_BRACKET', '[') || define('T_OPEN_BRACKET', ord('['));
    $isValid('T_CLOSE_BRACKET', ']') || define('T_CLOSE_BRACKET', ord(']'));
    $isValid('T_XOR', '^') || define('T_XOR', ord('^'));
    $isValid('T_BACKTICK', '`') || define('T_BACKTICK', ord('`'));
    $isValid('T_OPEN_BRACE', '{') || define('T_OPEN_BRACE', ord('{'));
    $isValid('T_OR', '|') || define('T_OR', ord('|'));
    $isValid('T_CLOSE_BRACE', '}') || define('T_CLOSE_BRACE', ord('}'));
    $isValid('T_NOT', '~') || define('T_NOT', ord('~'));
})();
