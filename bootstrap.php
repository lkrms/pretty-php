<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10001);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10002);
defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 10003);
defined('T_BAD_CHARACTER') || define('T_BAD_CHARACTER', 10004);  // Silence Intelephense
defined('T_ENUM') || define('T_ENUM', 10005);
defined('T_MATCH') || define('T_MATCH', 10006);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 10007);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10008);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 10009);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 10010);
defined('T_READONLY') || define('T_READONLY', 10011);

/**
 * Returned when there aren't any real tokens to return
 *
 */
const T_NULL = 20001;

/**
 * Inserted before `endif`, `endfor`, etc. in lieu of a closing brace
 *
 */
const T_END_ALT_SYNTAX = 20002;

/**
 * An array that maps single-char tokens to the ASCII codepoint that serves as
 * their token id
 *
 * Converting tokens to numeric ids avoids, say, `$token->is('}')` returning
 * `true` when the token is actually a `T_ENCAPSED_AND_WHITESPACE` that happens
 * to contain a single "}".
 *
 */
const T_ID_MAP = [
    '-' => 45,
    ',' => 44,
    ';' => 59,
    ':' => 58,
    '!' => 33,
    '?' => 63,
    '.' => 46,
    '"' => 34,
    '(' => 40,
    ')' => 41,
    '[' => 91,
    ']' => 93,
    '{' => 123,
    '}' => 125,
    '@' => 64,
    '*' => 42,
    '/' => 47,
    '&' => 38,
    '%' => 37,
    '`' => 96,
    '^' => 94,
    '+' => 43,
    '<' => 60,
    '=' => 61,
    '>' => 62,
    '|' => 124,
    '~' => 126,
    '$' => 36,
];
