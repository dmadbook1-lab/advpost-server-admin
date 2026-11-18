# S3 Media Storage Setup (AdvPost)

## Bucket
- Name: `advpost`
- Region: `ap-south-1`
- Public URL base: `https://advpost.s3.ap-south-1.amazonaws.com`

## Canonical folder structure (per user)

Every user gets their own prefix. New uploads from `adpost-app` go here:

```
users/
  {user_id}/
    profile/                         # profile pictures
      profile_{uniq}.jpg
    brand/                           # business logos
      logo_{uniq}.png
    posts/
      {post_id}/
        images/                      # photo posts (post_image[])
        videos/                      # rare video-on-post
        thumbnails/
    reels/
      {post_id}/                     # app sends post_type=reel via add_post
        videos/                      # post_video[]
        thumbnails/                  # post_video_thumbnail[]
    stories/
      {story_id}/
        media/                       # story file (multipart field: url)
        thumbnails/                  # video_thumbnail
    products/
      product_{uniq}.jpg
    chat/
      images/
      videos/
    generated/
      images/                        # AI image jobs
      videos/                        # AI video jobs
```

Legacy (migrated) keys may still look like:

```
assetsNew/images/profile_pic/...
assetsNew/images/new_post/...
assetsNew/videos/post_video/...
```

API responses always return **absolute https URLs** (required by Flutter `SafeImageHelper`).

## App ↔ API field map (`adpost-app`)

| App action | Endpoint | Multipart fields | S3 destination |
|------------|----------|------------------|----------------|
| Photo post | `add_post` (`post_type=photo` → stored as `image`) | `post_image[]` | `users/{uid}/posts/{id}/images/` |
| Reel | `add_post` (`post_type=reel`) | `post_video[]`, `post_video_thumbnail[]` | `users/{uid}/reels/{id}/videos\|thumbnails/` |
| Story | `add_story` | `url`, `video_thumbnail` | `users/{uid}/stories/{id}/media\|thumbnails/` |
| Profile pic | `user_profile` | `profile_pic` | `users/{uid}/profile/` |

Base URL in app: `InstaVibeApiService.baseUrlInstaVibe` → must point at this server’s `…/index.php/api/PostController/`.

## 1) Bucket policy (public media reads)

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::advpost/*"
    }
  ]
}
```

Turn **Block public access** off for GetObject (or allow public policies).  
Object Ownership: **Bucket owner enforced** (uploader does not set ACLs).

## 2) IAM user permissions

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject", "s3:ListBucket"],
      "Resource": [
        "arn:aws:s3:::advpost",
        "arn:aws:s3:::advpost/*"
      ]
    }
  ]
}
```

## 3) DB schema + credentials

```bash
mysql -u root -p YOUR_DB < private/sql/s3_migration_schema.sql
```

Credentials: table `aws_credential` (preferred) or `application/config/aws.php` / env vars.

## 4) Migrate existing local `assetsNew` files → S3

```bash
php bin/migrate_s3.php --dry-run
php bin/migrate_s3.php
```

Migration keeps legacy key paths (`assetsNew/...`) so old DB rows keep working; new uploads use `users/{id}/...`.

## Security

Rotate AWS keys after sharing. Update `aws_credential`, `application/config/aws.php`, and `image-genration/.env`.
