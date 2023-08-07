<?php

new
    #[AttrA]
    class extends B implements C, D, E {};

new
    #[AttrB(true)]
    #[AttrC(101, Types::ANON_CLASS)]
    #[AttrD()]
    class extends B implements
        C,
        D,
        E {};

new
    #[AttrE]
    class extends B implements C, D, E
    {
        #[AttrF(true)]
        #[AttrG(102, Types::FUNC)]
        function f(string $h, int $i, $j) {}

        #[AttrH]
        function g($k) {}
    };

new
    #[AttrI(true)]
    #[AttrJ(101, Types::ANON_CLASS)]
    #[AttrK()]
    class extends B implements
        C,
        D,
        E
    {
        #[AttrL(true)]
        #[AttrM(102, Types::FUNC)]
        function f(#[AttrN(true), Attr(103, Types::PARAM)] #[AttrO()] string $h, #[AttrP] int $i, $j)
        {
            $this->l($h, $i);
            $j();
        }

        function g(#[AttrQ(null)] $k)
        {
            echo $k;
        }

        function z(#[AttrY] $x) {}
    };
