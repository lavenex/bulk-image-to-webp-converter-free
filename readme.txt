=== Bulk Image to WebP Converter ===
Contributors: project01
Tags: webp, image converter, image optimization, bulk conversion
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.8.0-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert PNG and JPG/JPEG images into modern WebP files in bulk without sending source images to an external conversion service.

== Description ==

The Free edition provides unlimited manual conversions with up to 10 MB per input/output image and safe batches of up to 25 images. Administrators can start another batch after the current queue finishes. Pro supports up to 25 MB per input/output image, larger safe batches, smart Media Library suggestions, and one-click conversion of eligible media. Neither plan imposes a fixed pixel-dimension ceiling; actual processing remains subject to browser memory and hosting limits. Free and Pro use the same output-quality pipeline, and original images are never deleted.

V1 decodes and encodes images inside the administrator's browser. Administrators can choose new files or existing PNG/JPEG attachments. The generated WebP is sent to the same WordPress site for authenticated type, signature, size, and dimension validation, then saved as a new WordPress Media Library attachment. Source images are never overwritten. No customer image is sent to or retained by an external conversion service.

Users choose a WebP quality level from 40% to 100%; 85% is recommended. Free and Pro use the same output-quality pipeline. The results screen displays original and converted sizes, percentage saved, and a live estimated image-transfer improvement. The estimate is not presented as a guaranteed whole-site speed score.

The refresh-safe browser queue stores pending, failed, and completed job records locally in IndexedDB. Administrators can pause, resume, retry failed work, cancel pending work, and clear finished browser records. An interrupted job returns to Pending after refresh. Local-first processing resumes only while the converter page is open. Server-side idempotency keys prevent a successfully completed retry from creating another Media Library attachment.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Bulk Image to WebP Converter.
3. Open Image to WebP in the WordPress administrator menu.

Updating, deactivating, or reactivating the plugin does not delete Media Library files. Uninstall removes temporary queue replay/lock records only; original images and generated WebP attachments remain in the customer-owned WordPress database and Media Library.

== Frequently Asked Questions ==

= Are source images sent to an external API? =

No. V1 decoding and WebP encoding happen inside the administrator's browser. Only the generated WebP is sent temporarily to the same WordPress installation for validation; no external conversion API is used.

= Which inputs are supported? =

PNG and JPG/JPEG. WebP is the output format, not an input format.

= Is there a daily conversion quota? =

No. Free manual conversions are unlimited. A per-batch safety boundary protects browser memory and shared hosting; another batch can be started after the current queue finishes.

= Where are converted images saved? =

Only in the customer's own WordPress Media Library. The plugin does not keep customer images in external or plugin-operated storage.

= What happens during deactivation or uninstall? =

Deactivation and reactivation preserve all Media Library files. Core uninstall removes only temporary server-side queue lock/replay records. It never deletes original images or generated WebP attachments. The separately distributed Pro connector removes its encrypted activation token and cached entitlement when that connector is uninstalled.

== Changelog ==

= 0.8.0-alpha =

* Prepared the Free plugin for WordPress.org review by removing the commercial daily conversion quota.
* Added unlimited manual Free conversions with a 25-image safety boundary per active batch.
* Kept the 10 MB Free image limit, identical Free/Pro output quality, original-file preservation, and authenticated same-site validation.
* Repositioned Pro around automation, smart Media Library suggestions, one-click eligible-media conversion, larger safe batches, 25 MB inputs, updates, and support.

= 0.7.0-alpha =

* Added a read-only History & Diagnostics screen for recent generated WebP files, savings, source/output links, host limits, and browser readiness.
* Added a privacy-safe downloadable support report that excludes image contents, filenames, license keys, and customer personal data.
* Added narrow uninstall cleanup: temporary queue lock/replay records and private connector credentials are removed, while customer media and Free daily usage evidence remain preserved.

= 0.6.0-alpha =

* Added a Pro Media Library Assistant with smart PNG/JPEG suggestions and one-click conversion of all eligible media through the safe sequential queue.
* Already-converted source attachments are excluded, larger candidates appear first, and originals remain untouched.

= 0.5.2-alpha =

* Added a lavender License submenu showing plan, effective status, bound site, and expiry.
* Added activate, refresh, and deactivate forms protected by administrator capability checks and WordPress nonces.
* Kept every license action disabled until the separately distributed private Pro connector reports that it is available.
* Added fail-closed connector result hooks; the converter core performs no license-server request and stores no raw license key.
* Preserved Free conversion behavior when the Pro connector is absent or a license action fails.
* Passed 50 JavaScript/static workflow checks, five generated input-header checks, and 64 PHP assertions on PHP 7.4 and 8.3.

= 0.5.1-alpha =

* Clarified that this is a standalone WordPress plugin, not a WooCommerce-only plugin.
* Added an admin compatibility note for standard WordPress themes, Elementor, WPBakery, and Gutenberg.
* Confirmed there is no required WooCommerce, page-builder, or theme dependency.
* Clarified that generated WebP files are new Media Library items and existing page content is not automatically rewritten.

= 0.5.0-alpha =

* Added a standalone WordPress Pro entitlement adapter with no required e-commerce plugin dependency.
* Added one-site binding using the WordPress site host, port, and subdirectory path.
* Added active, bounded seven-day grace, expired, invalid-site, and Free effective states.
* Kept the Free plugin closed by default: there is no local activation switch or direct checkout dependency.
* Preserved expired-Pro fallback to Free limits without changing or deleting existing generated files.
* Passed 46 JavaScript workflow checks, five generated input-header checks, and 61 PHP assertions on PHP 7.4 and 8.3.

= 0.4.5-alpha =

* Verified that deactivation/reactivation and reinstall-style plugin file replacement do not reset the current site-day allowance.
* Verified that in-place plugin upgrades preserve the current site-day usage slots.
* Verified that expired Pro access falls back to the preserved Free allowance without deleting generated files.
* Confirmed prior-day usage evidence remains stored after exact-midnight rollover.
* Passed 43 JavaScript workflow checks, five generated input-header checks, and 51 PHP assertions on PHP 7.4 and 8.3.

= 0.4.4-alpha =

* Added exact-midnight boundary coverage for the Free six-successes-per-site-day allowance.
* Confirmed an exhausted allowance remains blocked through 23:59:59 in the WordPress site timezone.
* Confirmed a fresh six-image allowance begins at 00:00:00 without changing or deleting existing files.
* Passed 43 JavaScript workflow checks, five generated input-header checks, and 46 PHP assertions on PHP 7.4 and 8.3.

= 0.4.3-alpha =

* Added a persistent, accessible warning when IndexedDB queue storage is unavailable or blocked.
* Kept conversion operational in the current tab under private/storage-restricted browsing conditions.
* Prevented normal processing messages from hiding the refresh-recovery warning.
* Added RTL-aware warning styling and a workflow regression check for the storage fallback.
* Passed 43 JavaScript workflow checks and 43 PHP assertions on PHP 7.4 and 8.3.

= 0.4.2-alpha =

* Added accessible group and result-list semantics, described file requirements, atomic status announcements, and spoken progress values.
* Added dynamic spoken WebP-quality values and a stronger keyboard focus ring for the quality slider.
* Darkened the lavender Pro banner so white text reaches at least 6.48:1 contrast across the gradient.
* Darkened completed-status green for accessible contrast and disabled decorative transitions when reduced motion is requested.
* Replaced directional spacing/alignment with logical CSS properties so the interface adapts to RTL WordPress sites.
* Confirmed the revised UI live on WordPress 6.4/PHP 7.4 and passed 42 JavaScript workflow checks plus 43 PHP assertions on PHP 7.4 and 8.3.

= 0.4.1-alpha =

* Added automatic short retry when the same persistent job is already finishing in another administrator tab.
* Added stale-lock recovery and expired idempotency-result cleanup tests.
* Confirmed in a live two-tab test that both tabs converge on the same Media Library attachment without creating a duplicate.
* Refreshed the converter with a richer lavender interface, improved card depth, and visible keyboard focus styling.
* Added a dedicated processed/total image-compression progress bar and newest-completed-first result ordering.
* Strictly capped Free local and Media Library bulk selection to the remaining six-per-day allowance; Pro retains safe bulk queues.
* Added a prominent lavender Pro offer with ₹199/month and ₹1,500/year one-site pricing; checkout remains intentionally unlinked until the sales site is ready.
* Improved the Pro upgrade CTA with high-contrast white text across its normal, hover, and keyboard-focus states.
* Made the monthly Pro price the main highlighted amount and added configurable country/currency localization using browser timezone/locale, with checkout retained as the final pricing authority.

= 0.4.0-alpha =

* Added an IndexedDB-backed refresh-safe browser queue with pause, resume, retry failed, cancel pending, and clear finished controls.
* Added server-side job locks and cached successful responses for retry/idempotency protection.
* Added a live estimated image speed-impact panel showing cumulative bytes and estimated uncached 4G transfer time saved.
* Clearly states that local-first work runs only while the converter page is open and that speed impact is an estimate, not a guaranteed site score.

= 0.3.0-alpha =

* Added selection and conversion of existing WordPress Media Library PNG/JPEG attachments.
* Automatically saves successful WebP output as a new Media Library attachment without replacing the original.
* Set the Free per-image input/output limit to 10 MB and the Pro entitlement limit to 25 MB.
* Removed the fixed 6000 × 6000 pixel ceiling; invalid dimensions are still rejected and large images may fail safely when browser memory is insufficient.
* Added Media Library links alongside local WebP downloads.

= 0.2.1-alpha =

* Added client-side PNG/JPEG signature and structure inspection before image decoding.
* Added pre-decode dimension enforcement to reject oversized PNG/JPEG inputs earlier.
* Added regression fixtures for EXIF-oriented, CMYK, progressive, oversized, and spoofed images.

= 0.2.0-alpha =

* Pivoted the complete product from raster-to-SVG tracing to PNG/JPEG-to-WebP conversion.
* Added browser-local WebP encoding with a 40–100 quality control and an 85% recommended default.
* Added strict WebP signature, MIME, size, and dimension validation on the WordPress site.
* Added before/after file-size reporting and WebP downloads.
* Preserved the strict Free allowance of six successful conversions per site day; failures remain uncounted.
