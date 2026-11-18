<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| AWS S3 settings (fallback if aws_credential table is empty).
| Prefer storing credentials in the aws_credential DB table.
| All values are read from the project-root .env file.
*/
$config['access_key_id']     = function_exists('adv_env') ? adv_env('AWS_ACCESS_KEY_ID', '') : (getenv('AWS_ACCESS_KEY_ID') ?: '');
$config['secret_access_key'] = function_exists('adv_env') ? adv_env('AWS_SECRET_ACCESS_KEY', '') : (getenv('AWS_SECRET_ACCESS_KEY') ?: '');
$config['region']            = function_exists('adv_env') ? adv_env('AWS_REGION', 'ap-south-1') : (getenv('AWS_REGION') ?: 'ap-south-1');
$config['bucket']            = function_exists('adv_env') ? adv_env('AWS_BUCKET_NAME', 'advpost') : (getenv('AWS_BUCKET_NAME') ?: 'advpost');
$config['url']               = function_exists('adv_env') ? adv_env('AWS_URL', 'https://advpost.s3.ap-south-1.amazonaws.com') : (getenv('AWS_URL') ?: 'https://advpost.s3.ap-south-1.amazonaws.com');
