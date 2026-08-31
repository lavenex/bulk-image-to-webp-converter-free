# Bulk Image to WebP Converter — WordPress plugin source

This folder is reserved for the installable WordPress plugin.

- `admin/` — admin screens and workflows
- `includes/` — WebP validation, usage, queue, and licensing classes
- `assets/css/` — admin styles
- `assets/js/` — upload, queue, progress, and preview scripts
- `languages/` — translation template and language files
- `tests/` — automated plugin tests

The V1 alpha converts new PNG/JPG/JPEG files or existing Media Library attachments locally in the administrator's browser, validates the generated WebP on the same WordPress site, and saves it as a new Media Library attachment without altering the source image. Free manual conversions are unlimited, support up to 10 MB per image, and use a 25-image safety boundary per active batch. Pro supports 25 MB per image, larger safe batches, smart Media Library suggestions, and one-click eligible-media conversion. There is no fixed pixel-dimension ceiling. The read-only History & Diagnostics screen shows recent generated-file savings, source/output links, host limits, browser/queue readiness, and a privacy-safe support report. The plugin has no required e-commerce, page-builder, or theme dependency. It includes a lavender License screen showing plan, status, bound site, and expiry, plus activate, refresh, and deactivate controls. These controls fail closed and remain disabled until the separately distributed private Pro connector is installed and configured. The converter core never stores or displays a raw license key. Generated WebP attachments can be used with properly coded themes and builders such as Elementor, WPBakery, and Gutenberg but do not automatically rewrite existing page content.
