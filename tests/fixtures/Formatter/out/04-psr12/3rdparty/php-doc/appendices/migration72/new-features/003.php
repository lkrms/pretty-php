<?php

interface A
{
    public function Test(array $input);
}

class B implements A
{
    public function Test($input) {}  // type omitted for $input
}
