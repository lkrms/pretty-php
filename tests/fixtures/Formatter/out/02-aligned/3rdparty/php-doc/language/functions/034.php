<?php
// A basic shopping cart which contains a list of added products
// and the quantity of each product. Includes a method which
// calculates the total price of the items in the cart using a
// closure as a callback.
class Cart
{
    const PRICE_BUTTER = 1.0;
    const PRICE_MILK   = 3.0;
    const PRICE_EGGS   = 6.95;

    protected $products = array();

    public function add($product, $quantity)
    {
        $this->products[$product] = $quantity;
    }

    public function getQuantity($product)
    {
        return isset($this->products[$product])
                   ? $this->products[$product]
                   : FALSE;
    }

    public function getTotal($tax)
    {
        $total = 0.0;

        $callback =
            function ($quantity, $product) use ($tax, &$total) {
                $pricePerItem = constant(__CLASS__ . '::PRICE_'
                                             . strtoupper($product));
                $total       += ($pricePerItem * $quantity) * ($tax + 1.0);
            };

        array_walk($this->products, $callback);
        return round($total, 2);
    }
}

$my_cart = new Cart;

// Add some items to the cart
$my_cart->add('butter', 1);
$my_cart->add('milk', 3);
$my_cart->add('eggs', 6);

// Print the total with a 5% sales tax.
print $my_cart->getTotal(0.05) . "\n";
// The result is 54.29
?>