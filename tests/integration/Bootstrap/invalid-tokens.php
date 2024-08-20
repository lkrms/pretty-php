<?php declare(strict_types=1);

defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 0);
defined('T_MATCH') || define('T_MATCH', 1);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 2);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 3);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 4);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 5);
defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 6);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 7);
defined('T_ENUM') || define('T_ENUM', 8);
defined('T_READONLY') || define('T_READONLY', 9);
defined('T_PROPERTY_C') || define('T_PROPERTY_C', 10);
defined('T_ATTRIBUTE_COMMENT') || define('T_ATTRIBUTE_COMMENT', 11);
defined('T_END_ALT_SYNTAX') || define('T_END_ALT_SYNTAX', 12);
defined('T_NULL') || define('T_NULL', 13);

require dirname(__DIR__, 3) . '/vendor/autoload.php';
