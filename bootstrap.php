<?php declare(strict_types=1);

// As of PHP 7.4 (but still missing from widely-used stubs)
defined('T_BAD_CHARACTER') || define('T_BAD_CHARACTER', 10001);

// As of PHP 8.0
defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 10002);
defined('T_MATCH') || define('T_MATCH', 10003);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 10004);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10005);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 10006);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 10007);

// As of PHP 8.1
defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10008);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10009);
defined('T_ENUM') || define('T_ENUM', 10010);
defined('T_READONLY') || define('T_READONLY', 10011);

/**
 * Returned when there aren't any real tokens to return
 */
define('T_NULL', 20001);

/**
 * Inserted before 'endif', 'endfor', etc. in lieu of a closing brace
 */
define('T_END_ALT_SYNTAX', 20002);

/**
 * Used when a T_COMMENT starts with '#['
 */
define('T_ATTRIBUTE_COMMENT', 20003);

// The following constants are used to avoid, say, `$token->is('}')` returning
// `true` when `$token` is actually a `T_ENCAPSED_AND_WHITESPACE` that happens
// to contain a single "}"

/**
 * '!'
 */
define('T_LOGICAL_NOT', 33);

/**
 * '"'
 */
define('T_DOUBLE_QUOTE', 34);

/**
 * '$'
 */
define('T_DOLLAR', 36);

/**
 * '%'
 */
define('T_MOD', 37);

/**
 * '&'
 */
define('T_AND', 38);

/**
 * '('
 */
define('T_OPEN_PARENTHESIS', 40);

/**
 * ')'
 */
define('T_CLOSE_PARENTHESIS', 41);

/**
 * '*'
 */
define('T_MUL', 42);

/**
 * '+'
 */
define('T_PLUS', 43);

/**
 * ','
 */
define('T_COMMA', 44);

/**
 * '-'
 */
define('T_MINUS', 45);

/**
 * '.'
 */
define('T_CONCAT', 46);

/**
 * '/'
 */
define('T_DIV', 47);

/**
 * ':'
 */
define('T_COLON', 58);

/**
 * ';'
 */
define('T_SEMICOLON', 59);

/**
 * '<'
 */
define('T_SMALLER', 60);

/**
 * '='
 */
define('T_EQUAL', 61);

/**
 * '>'
 */
define('T_GREATER', 62);

/**
 * '?'
 */
define('T_QUESTION', 63);

/**
 * '@'
 */
define('T_AMPERSAND', 64);

/**
 * '['
 */
define('T_OPEN_BRACKET', 91);

/**
 * ']'
 */
define('T_CLOSE_BRACKET', 93);

/**
 * '^'
 */
define('T_XOR', 94);

/**
 * '`'
 */
define('T_BACKTICK', 96);

/**
 * '{'
 */
define('T_OPEN_BRACE', 123);

/**
 * '|'
 */
define('T_OR', 124);

/**
 * '}'
 */
define('T_CLOSE_BRACE', 125);

/**
 * '~'
 */
define('T_NOT', 126);
