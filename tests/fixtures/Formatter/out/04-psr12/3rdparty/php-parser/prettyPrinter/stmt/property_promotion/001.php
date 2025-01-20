<?php

class Test
{
    public $z;

    public function __construct(
        public int $x,

        /**
         * @SomeAnnotation()
         */
        public string $y = '123',
        string $z = 'abc'
    ) {}
}
