<?php

// The dynamic SQL part is validated against expected values
$sortingOrder = $_GET['sortingOrder'] === 'DESC' ? 'DESC' : 'ASC';
$productId = $_GET['productId'];
// The SQL is prepared with a placeholder
$stmt = $pdo->prepare("SELECT * FROM products WHERE id LIKE ? ORDER BY price {$sortingOrder}");
// The value is provided with LIKE wildcards
$stmt->execute(["%{$productId}%"]);

?>