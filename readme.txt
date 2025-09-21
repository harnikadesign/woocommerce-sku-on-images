=== WooCommerce SKU On Images ===
Contributors: qtag
Tags: woocommerce, sku, images, watermark, overlay
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically overlay product SKU text onto WooCommerce product images when uploaded or assigned, with configurable position, style, bulk tools, and optional original conversion to AVIF/WebP.

== Description ==

This plugin adds the product SKU as a small, readable text overlay onto WooCommerce product images. It hooks into image uploads and when images are assigned to products, then writes the SKU onto the image file (original only by default). Optionally, it can convert the original to AVIF/WebP (best available) after overlay to reduce size without visible quality loss.

Features:
* Overlay on upload/assignment (original only, sizes untouched)
* Configurable position (4 corners), per-side margins, line-height
* Text styling: font size, colors, per-side inner padding, alignment
* Google Font auto-download (Roboto/Open Sans/etc.)
* Conversion (optional): convert original to AVIF (preferred) or WebP (fallback), Imagick/GD
* Back up originals before overlay/convert; restore per-item, visible page, filtered set, or bulk
* Admin settings page with live preview (upload or choose from Media Library)
* Logging with AJAX pagination, filters (ok/skip/fail), chips, and CSV/JSON export
* Bulk tools: regenerate overlays (force/skip), convert originals, dry-run and CSV for restores

== Installation ==

1. Upload the plugin to `wp-content/plugins/woocommerce-sku-on-images` (or install via ZIP).
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to WooCommerce → SKU Image Overlay to configure settings.

== Requirements ==

* WooCommerce
* PHP GD and/or Imagick extension

== Usage ==

* Upload new product images or reassign images — the SKU overlay is applied automatically.
* Optional: enable “Format Conversion” to convert the original to AVIF/WebP after overlay.
* Use “Bulk Regenerate” for overlays and the restore tools for backups; logs are visible with filters and exports.
* If you want custom typography, place a valid TTF file at `assets/fonts/arial.ttf` (or set a custom path in settings). If no valid TTF is available, the plugin falls back to built‑in fonts.

== Changelog ==

= 1.1.0 =
* Add AVIF/WebP conversion of originals (Imagick-first with GD WebP fallback)
* Add extensive restore tools: per-item, visible page, filtered set, dry-run and CSV export
* Add per-side margins and inner padding; text alignment; line height
* Add live preview (upload or Media Library); Google Fonts auto-download
* Improve logs: AJAX pagination, filters, chips, CSV/JSON export
* Allow plugin activation without WooCommerce; show notice only on Plugins screen

= 1.0.0 =
* Initial release
