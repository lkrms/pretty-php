<?php
function makeyogurt($container = 'bowl', $flavour = 'raspberry', $style = 'Greek')
{
	return "Making a $container of $flavour $style yogurt.\n";
}

echo makeyogurt(style: 'natural');
?>