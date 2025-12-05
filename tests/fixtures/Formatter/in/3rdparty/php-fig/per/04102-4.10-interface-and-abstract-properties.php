<?php

abstract class Example {
    abstract public string $name {
        get => ucfirst($this->name);
        set;
    }
}
