<?php

class Point {
    public function __construct(
        public float $x = 0.0,
        protected array $y = [],
        private string $z = 'hello',
        public readonly int $a = 0,
        public $h { set => $value; },
        public $g = 1 { get => 2; },
        final $i,
    ) {}
}