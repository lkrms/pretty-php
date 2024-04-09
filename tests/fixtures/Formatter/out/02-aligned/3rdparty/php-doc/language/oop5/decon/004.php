<?php
class Product
{
    private ?int $id;
    private ?string $name;

    private function __construct(?int $id = null, ?string $name = null)
    {
        $this->id   = $id;
        $this->name = $name;
    }

    public static function fromBasicData(int $id, string $name): static
    {
        $new = new static($id, $name);
        return $new;
    }

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);
        return new static($data['id'], $data['name']);
    }

    public static function fromXml(string $xml): static
    {
        // Custom logic here.
        $data      = convert_xml_to_array($xml);
        $new       = new static();
        $new->id   = $data['id'];
        $new->name = $data['name'];
        return $new;
    }
}

$p1 = Product::fromBasicData(5, 'Widget');
$p2 = Product::fromJson($some_json_string);
$p3 = Product::fromXml($some_xml_string);
