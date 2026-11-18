<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Lightweight AWS S3 uploader (Signature Version 4).
 * Credentials are loaded from the aws_credential table (preferred)
 * or from application/config/aws.php as fallback.
 */
class Aws_s3
{
    /** @var CI_Controller */
    protected $CI;

    protected $access_key = '';
    protected $secret_key = '';
    protected $bucket = '';
    protected $region = 'ap-south-1';
    protected $base_url = '';
    protected $loaded = false;
    protected $last_error = '';

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->load_credentials();
    }

    public function is_ready()
    {
        return $this->loaded
            && $this->access_key !== ''
            && $this->secret_key !== ''
            && $this->bucket !== '';
    }

    public function get_last_error()
    {
        return $this->last_error;
    }

    public function get_bucket()
    {
        return $this->bucket;
    }

    public function get_base_url()
    {
        if ($this->base_url !== '') {
            return rtrim($this->base_url, '/');
        }
        return 'https://' . $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
    }

    public function public_url($key)
    {
        $key = ltrim(str_replace('\\', '/', (string) $key), '/');
        return $this->get_base_url() . '/' . $key;
    }

    /**
     * Upload a local file path to S3.
     * @return string|false Public URL on success
     */
    public function upload_file($local_path, $key, $content_type = null)
    {
        if (!$this->is_ready()) {
            $this->last_error = 'S3 credentials not configured';
            return false;
        }
        if (!is_file($local_path)) {
            $this->last_error = 'Local file not found: ' . $local_path;
            return false;
        }

        $body = file_get_contents($local_path);
        if ($body === false) {
            $this->last_error = 'Unable to read file: ' . $local_path;
            return false;
        }

        if ($content_type === null || $content_type === '') {
            $content_type = $this->detect_mime($local_path);
        }

        return $this->upload_bytes($body, $key, $content_type);
    }

    /**
     * Upload raw bytes to S3.
     * @return string|false Public URL on success
     */
    public function upload_bytes($body, $key, $content_type = 'application/octet-stream')
    {
        if (!$this->is_ready()) {
            $this->last_error = 'S3 credentials not configured';
            return false;
        }

        $key = ltrim(str_replace('\\', '/', (string) $key), '/');
        if ($key === '') {
            $this->last_error = 'Empty S3 object key';
            return false;
        }

        $payload_hash = hash('sha256', $body);
        $amz_date = gmdate('Ymd\THis\Z');
        $date_stamp = gmdate('Ymd');
        $host = $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
        $canonical_uri = '/' . str_replace('%2F', '/', rawurlencode($key));

        // No object ACL — use a bucket policy for public read (BucketOwnerEnforced safe).
        $canonical_headers =
            "content-type:{$content_type}\n" .
            "host:{$host}\n" .
            "x-amz-content-sha256:{$payload_hash}\n" .
            "x-amz-date:{$amz_date}\n";

        $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonical_request = implode("\n", [
            'PUT',
            $canonical_uri,
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ]);

        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date_stamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode("\n", [
            $algorithm,
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        $signing_key = $this->get_signing_key($date_stamp);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        $authorization =
            "{$algorithm} Credential={$this->access_key}/{$credential_scope}, " .
            "SignedHeaders={$signed_headers}, Signature={$signature}";

        $url = 'https://' . $host . $canonical_uri;

        $headers = [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $content_type,
            'Content-Length: ' . strlen($body),
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $this->last_error = 'cURL error: ' . $error;
            return false;
        }

        if ($status < 200 || $status >= 300) {
            $this->last_error = 'S3 upload failed HTTP ' . $status . ': ' . substr((string) $response, 0, 500);
            return false;
        }

        $this->last_error = '';
        return $this->public_url($key);
    }

    /**
     * Upload a PHP $_FILES entry and return public URL.
     */
    public function upload_uploaded_file(array $file, $key, $content_type = null)
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            // Allow non-HTTP uploads (migration / internal moves)
            if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
                $this->last_error = 'Invalid upload file';
                return false;
            }
        }

        if ($content_type === null || $content_type === '') {
            $content_type = !empty($file['type']) ? $file['type'] : $this->detect_mime($file['tmp_name']);
        }

        return $this->upload_file($file['tmp_name'], $key, $content_type);
    }

    protected function load_credentials()
    {
        // 1) DB table aws_credential
        try {
            if (isset($this->CI->db)) {
                $row = $this->CI->db
                    ->order_by('id', 'DESC')
                    ->limit(1)
                    ->get('aws_credential')
                    ->row();

                if ($row) {
                    $this->access_key = trim((string) $row->aws_access_key_id);
                    $this->secret_key = trim((string) $row->aws_secret_access_key);
                    $this->bucket = trim((string) $row->aws_bucket);
                    $this->base_url = trim((string) $row->aws_url);
                    $this->region = 'ap-south-1';
                    if ($this->access_key && $this->secret_key && $this->bucket) {
                        $this->loaded = true;
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            // fall through to config
        }

        // 2) Config file fallback
        // load('aws', TRUE) namespaces keys under section "aws"
        $this->CI->config->load('aws', true);
        $cfg = $this->CI->config->item('aws', 'aws');
        if (!is_array($cfg)) {
            // file may define flat keys under the aws section
            $cfg = [
                'access_key_id'     => $this->CI->config->item('access_key_id', 'aws'),
                'secret_access_key' => $this->CI->config->item('secret_access_key', 'aws'),
                'bucket'            => $this->CI->config->item('bucket', 'aws'),
                'region'            => $this->CI->config->item('region', 'aws'),
                'url'               => $this->CI->config->item('url', 'aws'),
            ];
        }
        // Also support un-namespaced $config['aws'] when loaded without TRUE
        if (!is_array($cfg) || empty($cfg['access_key_id'])) {
            $this->CI->config->load('aws', false);
            $cfg = $this->CI->config->item('aws');
        }
        if (is_array($cfg)) {
            $this->access_key = trim((string) ($cfg['access_key_id'] ?? ''));
            $this->secret_key = trim((string) ($cfg['secret_access_key'] ?? ''));
            $this->bucket = trim((string) ($cfg['bucket'] ?? ''));
            $this->region = trim((string) ($cfg['region'] ?? 'ap-south-1'));
            $this->base_url = trim((string) ($cfg['url'] ?? ''));
            if ($this->access_key && $this->secret_key && $this->bucket) {
                $this->loaded = true;
            }
        }
    }

    protected function get_signing_key($date_stamp)
    {
        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $this->secret_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        return hash_hmac('sha256', 'aws4_request', $k_service, true);
    }

    protected function detect_mime($path)
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
