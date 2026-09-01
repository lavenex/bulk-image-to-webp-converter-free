=== Bulk Image to WebP Converter ===
Contributors: lavenex
Tags: webp, image optimization, media library, bulk converter, performance
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.9.0-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert PNG and JPG/JPEG images to WebP locally in your browser and save the results as new WordPress Media Library attachments.

== Description ==

Bulk Image to WebP Converter is a fully functional Free plugin for WordPress administrators. It converts new uploads or selected existing Media Library PNG/JPG/JPEG images to WebP without sending image contents to an external conversion service.

Free functionality is not time-limited, quota-limited, or license-gated. Administrators may run unlimited manual conversions in repeatable safety batches of up to 25 images. Each input and generated output may be up to 10 MB, additionally limited by the WordPress host upload ceiling. There is no intentional Free quality reduction and no fixed pixel-dimension ceiling; very large images remain subject to browser memory.

Features:

* Upload or select multiple PNG/JPG/JPEG images from the Media Library.
* Convert locally using the browser WebP encoder and a 40–100 quality control.
* Pause, resume, retry, cancel, and clear the refresh-safe sequential queue.
* Save each successful WebP as a new customer-owned Media Library attachment.
* Display original/output sizes, percentage change, progress, and an estimated image-transfer improvement.
* Review newest generated files and privacy-safe environment diagnostics.
* Preserve every original image; the plugin never overwrites or deletes originals.

The plugin works through standard WordPress APIs and does not require WooCommerce, Elementor, WPBakery, Gutenberg, or a particular theme. Generated WebP files are new Media Library items; existing page content is not automatically rewritten.

A separately distributed GPL-compatible add-on may provide additional automation. No premium implementation, license gate, or locked functionality is included in this WordPress.org Free package.

Development source and build information: https://github.com/lavenex/bulk-image-to-webp-converter-free

== Installation ==

1. Upload the plugin ZIP through Plugins > Add Plugin > Upload Plugin, or install it from WordPress.org when available.
2. Activate Bulk Image to WebP Converter.
3. Open Image to WebP in the WordPress administrator menu.
4. Select PNG/JPG/JPEG files, choose quality, and start conversion.

== Frequently Asked Questions ==

= Are Free conversions limited by day, trial, or license? =

No. Manual Free conversions are unlimited. The 25-image active-batch boundary is a repeatable safety measure for browser memory and shared hosting, not a daily or lifetime quota.

= Are originals deleted or overwritten? =

No. Every successful WebP is stored as a new Media Library attachment. Original images are never modified or deleted.

= Are customer images sent to Lavenex or another conversion service? =

No. Encoding happens locally in the administrator's browser. The generated WebP is posted only to the same authenticated WordPress site for validation and Media Library saving.

= What happens when a conversion fails? =

The job is marked failed and can be retried. A failed validation creates no Media Library output.

= Does uninstall delete media? =

No. Uninstall removes only short-lived plugin queue lock/replay options. Original and generated Media Library files remain owned by the site.

== Privacy ==

The plugin does not transmit customer images to an external conversion, analytics, advertising, or licensing service. Browser queue records stay in that browser. Generated files are sent only to the same authenticated WordPress site. The support report excludes image contents, filenames, and customer personal data.

== Changelog ==

= 0.9.0-alpha =

* Removed all bundled license, entitlement, and locked Pro feature implementation from the WordPress.org Free package.
* Kept unlimited manual Free conversion in repeatable 25-image safety batches with a 10 MB per-image ceiling.
* Preserved browser-local encoding, Media Library selection/saving, queue recovery, progress, history, diagnostics, and original-file protection.
* Verified the package with the automated PHP 7.4/8.3 release gate and official Plugin Check before submission.

== Upgrade Notice ==

= 0.9.0-alpha =

WordPress.org compliance candidate with all Free functionality fully available and no bundled license-gated implementation.
