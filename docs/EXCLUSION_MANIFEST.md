# Exclusion & sanitization manifest

This public edition is a **selective copy** of the original operational system.
The original workspace and private backup were left untouched; Git was never
initialized there.

## Excluded (never copied / never committed)

| Category | Examples |
|---|---|
| SSL material | `ssl/keys/*.key`, `ssl/certs/*.crt`, `ssl.db*` |
| Hosting logs | `logs/`, `public_html/error_log` (~71 MB), `*.php.error.log` |
| Hosting artifacts | `lscache/`, `tmp/`, `public_ftp/`, `hide/`, `structure/`, `storage/` (12.7 GB) |
| Archives | `admin/admin.zip`, `vendor-panel/vendor-panel.zip`, `delivery-panel/delivery-panel.zip`, `public_html/phool-delivery.zip` |
| Production DB dumps | `database/phooldel_phooldelivery.sql`, `database/phooldel_research (2).sql`, `survey/*.sql` |
| Uploads / media | all `uploads/`, `*_images/`, `media_files/`, product/carousel/ad/notice images, `.mp4` videos |
| Research app | `survey/` (extracted to its own repository) |
| Env files | every `.env` (only `.env.example` is committed) |
| Debug/test scripts | root `tools/`, `public/debug-post.php`, `public_html/debug_*.php`, `admin/public/**/test*.php`, `check-db*.php` |

## Sanitized in place (code changes)

| Item | Change |
|---|---|
| Hardcoded DB credentials (5 files) | Replaced with `getenv('DB_*')` + safe local defaults (`root`, `phool_delivery_demo`) |
| Firebase config (2 files) | Real keys removed; all values read from env |
| Google OAuth (`app/config/social.php`) | client id/secret moved to env |
| Production domains (`phooldelivery.com`) | Replaced with reserved placeholder `phooldelivery.example` / `APP_URL` |
| Production DB name fallbacks | Changed to `phool_delivery_demo` |
| Local base path | `phool-delivery` → `phool-delivery-platform` |
| Duplicate/artifact folders | Removed `vendor-pannnel/`, `vendor-panel/vendor-panel/`, `admin/admin/`, `admin/vendor-panel/`; renamed `delivery-pannel/` → `delivery-panel/` |
| Dead / invalid code | Removed `api/order.php` (invalid fragment) and empty `api/products.php` |
| Root routing | Added root `index.php` landing page + `.htaccess` (`Options -Indexes`) |

## Source of truth

- Private original backup: user-held (untouched)
- Working raw copy: `C:\xampp\htdocs\phool-delivery` (reference only)
- Public source of truth: `C:\dev\phool-delivery-platform` (this repository)
