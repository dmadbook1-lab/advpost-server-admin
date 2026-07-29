<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['razorpay_key_id']     = function_exists('adv_env') ? adv_env('RAZORPAY_KEY_ID', '') : '';
$config['razorpay_key_secret'] = function_exists('adv_env') ? adv_env('RAZORPAY_KEY_SECRET', '') : '';
