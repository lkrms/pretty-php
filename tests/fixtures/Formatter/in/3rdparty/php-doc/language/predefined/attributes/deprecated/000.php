<?php

#[\Deprecated(message: "use safe_replacement() instead", since: "1.5")]
function unsafe_function()
{
   echo "This is unsafe", PHP_EOL;
}

unsafe_function();

?>