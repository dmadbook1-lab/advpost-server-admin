<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

$amount  = (int)($_GET['amount'] ?? 0);
$post_id = $_GET['post_id'] ?? 0;

if ($amount <= 0) {
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

try {
    $order = $api->order->create([
        'receipt'  => 'post_'.$post_id.'_'.time(),
        'amount'   => $amount * 100,
        'currency' => 'INR'
    ]);

    echo json_encode([
        'order_id' => $order['id'],
        'amount'   => $order['amount']
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
