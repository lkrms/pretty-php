<?php

class Test {
    function array() {}
    function public() {}

    static function list() {}
    static function protected() {}

    public $class;
    public $private;

    const TRAIT = 3, FINAL = 4;

    const __CLASS__ = 1, __TRAIT__ = 2, __FUNCTION__ = 3, __METHOD__ = 4, __LINE__ = 5,
          __FILE__ = 6, __DIR__ = 7, __NAMESPACE__ = 8;
    // __halt_compiler does not work
}

$t = new Test;
$t->array();
$t->public();

Test::list();
Test::protected();

$t->class;
$t->private;

Test::TRAIT;
Test::FINAL;

class Foo {
    use TraitA, TraitB {
        TraitA::catch insteadof namespace\TraitB;
        TraitA::list as foreach;
        TraitB::throw as protected public;
        TraitB::self as protected;
        exit as die;
        \TraitC::exit as bye;
        namespace\TraitC::exit as byebye;
        TraitA::
            //
            /** doc comment */
            #
        catch /* comment */
            // comment
            # comment
        insteadof TraitB;
    }
}