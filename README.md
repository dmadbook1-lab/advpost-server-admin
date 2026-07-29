# AdvPost Server (advpost-server-admin)

Production layout for the AdvPost CodeIgniter 3 backend + admin panel.
Admin UI views/templates are unchanged; only the server folder structure and security paths were hardened.

## Layout

```
advpost-server-admin/
├── public_html/          # WEB ROOT ONLY (document root on the host)
│   ├── index.php         # Front controller
│   ├── router.php        # Local PHP built-in server router
│   ├── .htaccess         # Rewrite + hardening
│   ├── assetsNew/        # Public media + admin static assets
│   ├── uploads/          # Public uploads (PHP execution disabled)
│   └── razorpay/         # Legacy payment helper scripts
├── application/          # App code (controllers, models, views, config) — NOT web-accessible
├── system/               # CodeIgniter core — NOT web-accessible
├── vendor/               # Composer dependencies — NOT web-accessible
├── private/
│   ├── credentials/      # Firebase / service-account JSON keys
│   ├── sql/              # Migration SQL + S3 setup docs
│   └── environment.php.example
├── storage/
│   ├── cache/
│   ├── logs/
│   └── sessions/
├── bin/
│   ├── migrate_s3.php            # CLI media → S3 migration
│   └── cleanup_local_media.php   # Delete local copies already on S3+DB
├── docs/                 # Non-runtime documentation
├── logs/                 # Host-level access/error logs
└── document_errors/      # Host custom error pages
```

## Why this layout

| Before | After |
|--------|--------|
| `application/`, `system/`, `vendor/` inside `public_html` | Moved above the web root |
| Firebase JSON in the web root | `private/credentials/` |
| SQL + CLI scripts publicly reachable | `private/sql/`, `bin/` |
| Sessions under `application/cache` | `storage/sessions/` |
| Notification logs in web root | `storage/logs/` |

## Local development

```bash
cd public_html
php -S localhost:8080 router.php
```

Optional: copy `.env.example` → `.env` and set production (`DB_*`) and local (`DB_LOCAL_*`) credentials.
Optional: copy `private/environment.php.example` → `private/environment.php` and set `development` or `production` (or use `CI_ENV` in `.env`).

## Deploy checklist

1. Point the vhost / hosting **document root** at `public_html/` (not the project root).
2. Ensure `storage/` is writable by PHP (`cache`, `logs`, `sessions`).
3. Place Firebase key at `private/credentials/advpost-firebase.json`.
4. Copy `.env.example` → `.env` and set DB, AWS, Razorpay, and `BASE_URL` (never commit `.env`).
5. Set `CI_ENV=production` in `.env` (or use `private/environment.php`).
6. Keep Composer deps at project root: `composer install --no-dev` from the project root.

## Media & S3

New uploads from `adpost-app` go to bucket `advpost` under:

```
users/{user_id}/profile|brand|posts|reels|stories|products|chat|generated/...
```

See `private/sql/S3_SETUP.md` for the full layout, IAM policy, and migrate CLI.
Local `public_html/assetsNew/` remains for admin UI assets and legacy files.

After migration, remove only local **media** copies that are already in the DB as S3 URLs and verified on the bucket (admin CSS/JS under `plugins/`, `dist/`, etc. are never touched):

```bash
php bin/cleanup_local_media.php --dry-run
php bin/cleanup_local_media.php
```

Remove local media files whose basename is not referenced in any media DB column (orphans):

```bash
php bin/cleanup_orphan_media.php --dry-run
php bin/cleanup_orphan_media.php
```# advpost-server-admin
