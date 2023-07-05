<?php
const ONE = 1;

class foo
{
    const TWO = ONE * 2;
    const THREE = ONE + self::TWO;
    const SENTENCE = 'The value of THREE is ' . self::THREE;
}
?>