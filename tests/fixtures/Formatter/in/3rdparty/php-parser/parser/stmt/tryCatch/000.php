<?php

try {
    doTry();
} catch (A $b) {
    doCatchA();
} catch (B $c) {
    doCatchB();
} finally {
    doFinally();
}

// no finally
try { }
catch (A $b) { }

// no catch
try { }
finally { }
