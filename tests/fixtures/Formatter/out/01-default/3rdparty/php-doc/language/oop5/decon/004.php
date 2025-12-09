<?php
$some_json_string = '{ "id": 1004, "name": "Elephpant" }';
$some_xml_string = '<animal><id>1005</id><name>Elephpant</name></animal>';

class Product
{
    private ?int $id;
    private ?string $name;

    private function __construct(?int $id = null, ?string $name = null)
    {
        $this->id = $id;
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
        $data = simplexml_load_string($xml);
        $new = new static();
        $new->id = (int) $data->id;
        $new->name = $data->name;
        return $new;
    }
}

$p1 = Product::fromBasicData(5, 'Widget');
$p2 = Product::fromJson($some_json_string);
$p3 = Product::fromXml($some_xml_string);

var_dump($p1, $p2, $p3);
