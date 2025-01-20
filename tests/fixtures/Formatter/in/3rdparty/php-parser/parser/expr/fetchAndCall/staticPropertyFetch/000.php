<?php

// property name variations
A::$b;
A::$$b;
A::${'b'};

// array access
A::$b['c'];

// class name variations can be found in staticCall.test