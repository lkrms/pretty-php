<?php
class Test
{
    public int $prop {
        get {
            return $this->prop + 1;
        }
        set {
            $this->prop = $value - 1;
        }
    }

    public $prop = 1 {
        #[Attr]
        &get => $this->prop;

        final set($value) => $value - 1;
    }

    abstract public $prop { get; set; }

    // TODO: Force multiline for hooks?
    public function __construct(
        public $foo {
            get => 42;
            set => 123;
        },
        public $bar
    ) {}
}
