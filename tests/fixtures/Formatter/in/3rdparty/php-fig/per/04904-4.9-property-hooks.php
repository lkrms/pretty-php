<?php

class Example
{
    public function __construct(
        public string $name {
            set {
                if (strlen($value) < 3) {
                    throw new \Exception('Too short');
                }
                $this->newName = ucfirst($value);
            }
        }
    ) {}
}
