<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * One-time migration: upload local assetsNew media to S3 and rewrite DB URLs.
 *
 * Usage (from browser or curl, while logged into admin OR with secret key):
 *   /index.php/Migrate_s3/run?key=YOUR_SECRET
 *   /index.php/Migrate_s3/run?key=YOUR_SECRET&dry_run=1
 *
 * Change MIGRATE_SECRET below before running.
 */
class Migrate_s3 extends CI_Controller
{
    // CHANGE THIS before running, then remove or disable this controller.
    const MIGRATE_SECRET = 'advpost-s3-migrate-2026';

    /** @var array */
    protected $stats = [
        'scanned' => 0,
        'uploaded' => 0,
        'updated' => 0,
        'skipped' => 0,
        'missing' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper('media');
        $this->load->library('aws_s3');
    }

    public function index()
    {
        echo "Use /Migrate_s3/run?key=SECRET (add &dry_run=1 to preview)";
    }

    public function run()
    {
        header('Content-Type: application/json');
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $key = (string) $this->input->get_post('key');
        if ($key !== self::MIGRATE_SECRET) {
            echo json_encode(['status' => 'failed', 'message' => 'Invalid key']);
            return;
        }

        if (!$this->aws_s3->is_ready()) {
            echo json_encode([
                'status' => 'failed',
                'message' => 'S3 not configured: ' . $this->aws_s3->get_last_error(),
            ]);
            return;
        }

        $dry_run = (string) $this->input->get_post('dry_run') === '1';

        // table => [column, ...]
        $targets = [
            'post_image' => ['new_post'],
            'post_video' => ['post_video', 'post_video_hls', 'post_video_thumbnail'],
            'reels_video' => ['reel_video', 'reel_video_thumbnail'],
            'story' => ['url', 'video_thumbnail', 'hls_url', 'preview_video'],
            'story_highlight' => ['cover_pic'],
            'users' => ['profile_pic', 'logo_url'],
            'chats' => ['url', 'video_thumbnail'],
            'products' => ['images'], // may be comma-separated
            'image_creation_jobs' => [
                'product_image_url',
                'logo_url',
                'output_image_url',
                'output_file_path',
            ],
            'avtar' => ['image'],
            'music' => ['image'],
            'admins' => ['profile_pic'],
        ];

        // Only include columns that exist
        foreach ($targets as $table => $cols) {
            $existing = [];
            foreach ($cols as $col) {
                if ($this->db->field_exists($col, $table)) {
                    $existing[] = $col;
                }
            }
            if (empty($existing) || !$this->db->table_exists($table)) {
                unset($targets[$table]);
            } else {
                $targets[$table] = $existing;
            }
        }

        foreach ($targets as $table => $cols) {
            $pk = $this->primary_key($table);
            if ($pk === null) {
                $this->stats['errors'][] = "No primary key for {$table}";
                continue;
            }

            $select = array_unique(array_merge([$pk], $cols));
            $rows = $this->db->select(implode(',', $select))->get($table)->result_array();

            foreach ($rows as $row) {
                $updates = [];
                foreach ($cols as $col) {
                    $value = isset($row[$col]) ? trim((string) $row[$col]) : '';
                    if ($value === '') {
                        continue;
                    }

                    // products.images can be comma-separated
                    if ($table === 'products' && $col === 'images' && strpos($value, ',') !== false) {
                        $parts = array_map('trim', explode(',', $value));
                        $newParts = [];
                        $changed = false;
                        foreach ($parts as $part) {
                            $res = $this->migrate_one_value($part, $dry_run);
                            $newParts[] = $res['url'];
                            if ($res['changed']) {
                                $changed = true;
                            }
                        }
                        if ($changed) {
                            $updates[$col] = implode(',', $newParts);
                        }
                        continue;
                    }

                    $res = $this->migrate_one_value($value, $dry_run);
                    if ($res['changed']) {
                        $updates[$col] = $res['url'];
                    }
                }

                if (!empty($updates) && !$dry_run) {
                    $this->db->where($pk, $row[$pk])->update($table, $updates);
                    $this->stats['updated']++;
                } elseif (!empty($updates) && $dry_run) {
                    $this->stats['updated']++;
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'dry_run' => $dry_run,
            'bucket' => $this->aws_s3->get_bucket(),
            'base_url' => $this->aws_s3->get_base_url(),
            'stats' => $this->stats,
        ], JSON_PRETTY_PRINT);
    }

    protected function migrate_one_value($value, $dry_run)
    {
        $this->stats['scanned']++;
        $value = trim((string) $value);

        // Already on S3 / absolute remote
        if (preg_match('#^https?://#i', $value)) {
            if (stripos($value, 'amazonaws.com') !== false || stripos($value, 's3.') !== false) {
                $this->stats['skipped']++;
                return ['url' => $value, 'changed' => false];
            }
            // Absolute URL pointing at local assetsNew — extract relative path
            if (preg_match('#/(assetsNew/.+)$#i', $value, $m)) {
                $value = $m[1];
            } else {
                $this->stats['skipped']++;
                return ['url' => $value, 'changed' => false];
            }
        }

        $rel = adv_media_rel_path($value);
        if ($rel === '' || strpos($rel, 'assetsNew/') !== 0) {
            // maybe already a bare filename for profile — try common folders
            $candidates = [
                'assetsNew/images/profile_pic/' . ltrim($value, '/'),
                'assetsNew/images/avtar/' . ltrim($value, '/'),
                'assetsNew/images/new_post/' . ltrim($value, '/'),
            ];
            $found = '';
            foreach ($candidates as $c) {
                if (is_file(FCPATH . $c)) {
                    $found = $c;
                    break;
                }
            }
            if ($found === '') {
                $this->stats['missing']++;
                return ['url' => $value, 'changed' => false];
            }
            $rel = $found;
        }

        $local = FCPATH . $rel;
        if (!is_file($local)) {
            $this->stats['missing']++;
            return ['url' => $value, 'changed' => false];
        }

        $s3Url = $this->aws_s3->public_url($rel);
        if ($dry_run) {
            $this->stats['uploaded']++;
            return ['url' => $s3Url, 'changed' => true];
        }

        $uploaded = $this->aws_s3->upload_file($local, $rel);
        if ($uploaded === false) {
            $this->stats['failed']++;
            $this->stats['errors'][] = $rel . ' => ' . $this->aws_s3->get_last_error();
            return ['url' => $value, 'changed' => false];
        }

        $this->stats['uploaded']++;
        return ['url' => $uploaded, 'changed' => true];
    }

    protected function primary_key($table)
    {
        $map = [
            'post_image' => 'id',
            'post_video' => 'id',
            'reels_video' => 'id',
            'story' => 'story_id',
            'story_highlight' => 'highlight_id',
            'users' => 'id',
            'chats' => 'id',
            'products' => 'id',
            'image_creation_jobs' => 'id',
            'avtar' => 'id',
            'music' => 'id',
            'admins' => 'id',
        ];
        return $map[$table] ?? null;
    }
}
