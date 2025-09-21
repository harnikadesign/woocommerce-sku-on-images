=== WooCommerce SKU On Images ===
Contributors: qtag
Tags: woocommerce, sku, images, watermark, overlay
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically overlay product SKU text onto WooCommerce product images when uploaded or assigned, with configurable position, style, and bulk regeneration.

== Description ==

This plugin adds the product SKU as a small, readable text overlay onto WooCommerce product images. It hooks into image uploads and when images are assigned to products, then writes the SKU onto the image files (full size and all generated sizes).

Features:
* Automatic overlay on upload and assignment
* Configurable position (4 corners), font size, colors, and margins
* Semi-transparent background panel for readability
* Uses GD or Imagick (whichever is available)
* Admin settings and bulk regeneration tool

== Installation ==

1. Upload the plugin to `wp-content/plugins/woocommerce-sku-on-images` (or install via ZIP).
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to WooCommerce → SKU Image Overlay to configure settings.

== Requirements ==

* WooCommerce
* PHP GD and/or Imagick extension

== Usage ==

* Upload new product images or reassign images — the SKU overlay is applied automatically.
* Use the Bulk Regenerate tool under the settings page to process existing product images.
* If you want custom typography, place a valid TTF file at `assets/fonts/arial.ttf` (or set a custom path in settings). If no valid TTF is available, the plugin falls back to built‑in fonts.

== Changelog ==

= 1.0.0 =
* Initial release

