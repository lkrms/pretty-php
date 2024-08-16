<?php

$foo = bar()
           ->baz()
           ->qux();

$foo = bar()
           ->baz();

$foo = b()->baz()
          ->qux();

$foo = b()->baz();

$foo = b()->baz()
          ->qux();

$foo = bar()
           ->${'baz'}()
           ->${'qux'}();

$foo = bar()
           ->${'baz'}();

$_foo = bar()->${baz()}()
             ->qux()
             ->quux();

$_foo = bar()->${baz()}()
             ->qux();

$foo = BAR::${baz()}()
           ->qux()
           ->quux();

$foo = BAR::${baz()}()
           ->qux();

$foo = 'BAR'::${baz()}()
           ->qux()
           ->quux();

$foo = 'BAR'::${baz()}()
           ->qux();

$foo = 'BAR'->${baz()}()
            ->qux()
            ->quux();

$foo = 'BAR'->${baz()}()
            ->qux();

$foo = (
    bar()
)->baz()
 ->qux();

$foo = (
    bar()
)->baz();

$foo = bar('foo',
           'bar')
               ->baz()
               ->qux();

$foo = bar('foo',
           'bar')
               ->baz();

$foo = ['bar']
           ->baz()
           ->qux();

$foo = ['bar']
           ->baz();

$foo = array('bar')
           ->baz()
           ->qux();

$foo = array('bar')
           ->baz();

$foo = static::bar()
           ->baz()
           ->qux();

$foo = static::bar()
           ->baz();

$foo = readonly()
           ->baz()
           ->qux();

$foo = readonly()
           ->baz();
