<?php

class A
{
    private $b;

    protected function c()
    {
        return $this->b
            ?? ($this->b = implode(':', [
                'a',
                'b',
                'c',
                'd',
                'e'
            ]));
    }
}
