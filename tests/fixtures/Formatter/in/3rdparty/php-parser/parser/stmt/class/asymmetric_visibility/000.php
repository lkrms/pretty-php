<?php

class Test {
    protected private(set) $a;
    private public(set) $b;
    protected(set) $c;

    public function __construct(
        protected private(set) $d,
        private public(set) $e,
        protected(set) $f,
    ) {}
}