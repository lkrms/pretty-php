<?php

const T_1 = 1 << 1;
const T_2 = 1 / 2;
const T_3 = 1.5 + 1.5;
const T_4 = 'foo' . 'bar';
const T_5 = (1.5 + 1.5) * 2;
const T_6 = 'foo' . 2 . 3 . 4.0;
const T_7 = __LINE__;

const T_8 = <<<ENDOFSTRING
    This is a test string
    ENDOFSTRING;

const T_9 = ~-1;
const T_10 = (-1 ?: 1) + (0 ? 2 : 3);
const T_11 = 1 && 0;
const T_12 = 1 and 1;
const T_13 = 0 || 0;
const T_14 = 1 or 0;
const T_15 = 1 xor 1;
const T_16 = 1 xor 0;
const T_17 = 1 < 0;
const T_18 = 0 <= 0;
const T_19 = 1 > 0;
const T_20 = 1 >= 0;
const T_21 = 1 === 1;
const T_22 = 1 !== 1;
const T_23 = 0 != '0';
const T_24 = 1 == '1';
const T_25 = 1 + 2 * 3;
const T_26 = '1' + 2 + '3';
const T_27 = 2 ** 3;
const T_28 = [1, 2, 3][1];
const T_29 = 12 - 13;
const T_30 = 12 ^ 13;
const T_31 = 12 & 13;
const T_32 = 12 | 13;
const T_33 = 12 % 3;
const T_34 = 100 >> 4;
const T_35 = !false;
