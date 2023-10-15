<?php
new class extends ParentClass {};
// -> ParentClass@anonymous
new class implements FirstInterface, SecondInterface {};
// -> FirstInterface@anonymous
new class {};
// -> class@anonymous
?>