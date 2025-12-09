<?php

class Point
{
    public function __construct(
        public float $x         = 0.0,
        protected array $y      = [],
        private string $z       = 'hello',
        public readonly int $a  = 0,
        protected final bool $b = true,
    ) {}
}
