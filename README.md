# Bulk Image to WebP Converter — Free

This directory is the complete WordPress.org Free plugin. It converts manually selected PNG/JPG/JPEG images to WebP locally in the administrator's browser and saves successful results as new Media Library attachments.

The Free package is fully functional: unlimited manual conversions, repeatable 25-image safety batches, up to 10 MB per image subject to host limits, full quality control, Media Library selection and saving, refresh-safe queue controls, progress, history, diagnostics, and original-file preservation.

No license system, entitlement check, premium implementation, daily quota, trial, or locked feature code is included. Any separately distributed commercial add-on is a different GPL-compatible plugin hosted outside WordPress.org.

## Structure

- `admin/` — converter and read-only history/diagnostics screens
- `assets/` — local administrator CSS and JavaScript
- `includes/` — unlimited Free usage, output validation, and retry/idempotency helpers
- `uninstall.php` — removes only temporary plugin-owned queue state; media is preserved

## Development

The distributed JavaScript and CSS are human-readable and are not minified or obfuscated. The maintained public source is available at:

https://github.com/lavenex/bulk-image-to-webp-converter-free

Plugin version: `0.9.0-alpha`.
