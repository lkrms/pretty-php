<?php

// Declare the interface 'Template'
interface Template
{
	public function setVariable($name, $var);
	public function getHtml($template);
}

// Implement the interface
// This will work
class WorkingTemplate implements Template
{
	private $vars = [];

	public function setVariable($name, $var)
	{
		$this->vars[$name] = $var;
	}

	public function getHtml($template)
	{
		foreach ($this->vars as $name => $value) {
			$template = str_replace('{' . $name . '}', $value, $template);
		}

		return $template;
	}
}

// This will not work
// Fatal error: Class BadTemplate contains 1 abstract methods
// and must therefore be declared abstract (Template::getHtml)
class BadTemplate implements Template
{
	private $vars = [];

	public function setVariable($name, $var)
	{
		$this->vars[$name] = $var;
	}
}
?>