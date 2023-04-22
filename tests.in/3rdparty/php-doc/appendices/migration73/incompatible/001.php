<?php
while ($foo) {
    switch ($bar) {
      case "baz":
         continue;
         // Warning: "continue" targeting switch is equivalent to
         //          "break". Did you mean to use "continue 2"?
   }
}
?>