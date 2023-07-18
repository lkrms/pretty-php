<?php

#[Foo]
#[Bar('baz')]
class Demo
{
    #[Beep]
    private Foo $foo;

    public function __construct(
        #[Load(context: 'foo', bar: true)]
        private readonly FooService $fooService,

        #[LoadProxy(context: 'bar')]
        private readonly BarService $barService,
    ) {}

    /**
     * Sets the foo.
     */
    #[Poink('narf'), Narf('poink')]
    public function setFoo(
        #[Beep]
        Foo $new
    ): void {
        // ...
    }

    #[Complex(
        prop: 'val',
        other: 5,
    )]
    #[Other, Stuff]
    #[Here]
    public function complicated(
        string $a,

        #[Decl]
        string $b,

        #[Complex(
            prop: 'val',
            other: 5,
        )]
        string $c,

        int $d,
    ): string {
        // ...
    }
}
