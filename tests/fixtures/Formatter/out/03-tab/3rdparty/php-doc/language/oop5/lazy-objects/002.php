<?php
class BlogPost
{
	public function __construct(
		public int $id,
		public string $title,
		public string $content,
	) {}
}

$reflector = new ReflectionClass(BlogPost::class);

$post = $reflector->newLazyGhost(function ($post) {
	$data = fetch_from_store($post->id);
	$post->__construct($data['id'], $data['title'], $data['content']);
});

// Without this line, the following call to ReflectionProperty::setValue() would
// trigger initialization.
$reflector->getProperty('id')->skipLazyInitialization($post);
$reflector->getProperty('id')->setValue($post, 123);

// Alternatively, one can use this directly:
$reflector->getProperty('id')->setRawValueWithoutLazyInitialization($post, 123);

// The id property can be accessed without triggering initialization
var_dump($post->id);
?>