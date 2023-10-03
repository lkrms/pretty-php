<?php
if ($foo) {
    baz();
} elseif ($qux) {
    quux();
}
if ($foo ||
        $bar) {
    baz();
} elseif ($foo &&
        $qux) {
    quux();
}
switch ($foo) {
    default:
        baz();
        break;
}
switch ($foo ||
        $bar) {
    default:
        baz();
        break;
}
while ($foo) {
    baz();
}
while ($foo ||
        $bar) {
    baz();
}
do {
    baz();
} while ($foo);
do {
    baz();
} while ($foo ||
    $bar);
for (;;) {
    baz($i);
}
for (;;) {
    baz($i);
}
for ($a;;) {
    baz($i);
}
for ($a;;) {
    baz($i);
}
for (; $b;) {
    baz($i);
}
for (;
    $b;) {
    baz($i);
}
for (;; $c) {
    baz($i);
}
for (;;
    $c) {
    baz($i);
}
for ($i = 0; $i < 10; $i++) {
    baz($i);
}
for ($i = 0;
     $i < 10;
     $i++) {
    baz($i);
}
for ($i = 0, $j = 0;
     $i < 10;
     $i++) {
    baz($i);
}
for ($i = 0,
     $j = 0,
     $k = 0;

     $i < 10;

     $i++,
     $j++) {
    baz($i);
}
for ((isset($i) ||
         $i = 0); $i < 10; $i++) {
    baz($i);
}
for ((isset($i) ||
         $i = 0);
     $i < 10;
     $i++) {
    baz($i);
}
for ($i = 0, $j = 0, (isset($k) ||
         $k = 0);
     $i < 10;
     $i++) {
    baz($i);
}
for ($i = 0,
     $j = 0,
     (isset($k) ||
         $k = 0);

     $i < 10;

     $i++,
     $j++) {
    baz($i);
}
foreach ($foo as $bar) {
    baz($bar);
}
foreach ($foo
        + $bar as $baz) {
    qux($baz);
}
