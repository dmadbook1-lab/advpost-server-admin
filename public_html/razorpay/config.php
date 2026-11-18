<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| Load FULL Razorpay SDK (IMPORTANT)
|--------------------------------------------------------------------------
| Razorpay.php internally loads Api, Order, Payment, etc.
*/
require_once dirname(dirname(__DIR__)) . '/vendor/razorpay/razorpay/Razorpay.php';

use Razorpay\Api\Api;

// LIVE KEYS
$keyId     = 'rzp_test_SHtjbZaBfQbQDw';
$keySecret = '5AVQU020MOsAlUra5fmna7mn';

$api = new Api($keyId, $keySecret);
