<?php

class Example
{
    public string $myName { get => __CLASS__; }

    public string $newName { set => ucfirst($value); }
}
