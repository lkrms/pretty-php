<?php
class Test
{
    public $prop {
        get {
            return 42;
        }
        set {
            echo $value;
        }
    }

    private $prop2 {
        get => 42;
        set => $value;
    }

    abstract $prop3 { &get; set; }

    public $prop4 {
        final get {
            return 42;
        }
        set (string $value) {}
    }
}
