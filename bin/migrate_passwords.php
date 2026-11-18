#!/usr/bin/env php
<?php
/**
 * One-off: replace plain-text users.password values with bcrypt hashes.
 * Safe to re-run — rows that are already hashed are skipped.
 * (Logins also upgrade rows lazily, so this just covers accounts that never log in.)
 *
 *   php bin/migrate_passwords.php --dry-run    # count only
 *   php bin/migrate_passwords.php              # hash everything
 * The target database follows the same rules as the app (CI_ENV in .env).
 *
 * BACK UP THE DATABASE FIRST. This is one-way: original passwords cannot be recovered.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(1); }

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once ROOTPATH . 'private' . DIRECTORY_SEPARATOR . 'load_env.php';
advpost_load_env(ROOTPATH);
define('ENVIRONMENT', advpost_resolve_environment());

$dry = in_array('--dry-run', $argv, true);

// Same database selection as the app: CI_ENV=production -> DB_*, otherwise DB_LOCAL_*.
$host = adv_db_env('HOST', '127.0.0.1');
$user = adv_db_env('USERNAME', 'root');
$pass = adv_db_env('PASSWORD', '');
$name = adv_db_env('DATABASE', '');
if ($host === 'localhost') { $host = '127.0.0.1'; }

echo strtoupper(ENVIRONMENT) . " database: $name @ $host" . ($dry ? "  [dry run]" : "") . "\n";

mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli($host, $user, $pass, $name);
if ($db->connect_errno) { fwrite(STDERR, "DB connect failed: {$db->connect_error}\n"); exit(1); }

$res = $db->query("SELECT id, password FROM users WHERE password IS NOT NULL AND password <> ''");
$todo = 0; $done = 0; $skipped = 0;
while ($row = $res->fetch_assoc()) {
    if (!empty(password_get_info($row['password'])['algo'])) { $skipped++; continue; }
    $todo++;
    if ($dry) { continue; }
    $hash = password_hash($row['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $row['id']);
    if ($stmt->execute()) { $done++; }
}
echo "already hashed: $skipped, plain-text found: $todo" . ($dry ? "" : ", hashed now: $done") . "\n";
